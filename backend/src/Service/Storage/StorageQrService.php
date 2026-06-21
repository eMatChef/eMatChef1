<?php

namespace App\Service\Storage;

use App\Entity\Address;
use App\Entity\PrintTaskItem;
use App\Entity\StorageRack;
use App\Entity\StorageSlot;
use App\Entity\User;
use App\Service\Print\MaterialQrExportRow;
use App\Service\Public\PublicCodeService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

class StorageQrService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PublicCodeService $publicCodeService,
    ) {}

    /**
     * @return array{entity_type: string, entity_id: string, public_code: string, public_url: string, label: string}
     */
    public function ensureAddressQr(Address $address, ?string $userId): array
    {
        $code = $this->publicCodeService->ensureStorageAddressPublicCode($address, $userId);
        $label = $this->publicCodeService->buildStorageAddressLabel($address);

        return $this->publicCodeService->buildStorageQrPayload(
            PublicCodeService::ENTITY_STORAGE_ADDRESS,
            $code,
            $label,
        );
    }

    /**
     * @return array{entity_type: string, entity_id: string, public_code: string, public_url: string, label: string}
     */
    public function ensureRackQr(StorageRack $rack, ?string $userId): array
    {
        $code = $this->publicCodeService->ensureStorageRackPublicCode($rack, $userId);
        $locationName = $this->locationNameForRack($rack);
        $label = $this->publicCodeService->buildStorageRackLabel($locationName, $rack->getName());

        return $this->publicCodeService->buildStorageQrPayload(
            PublicCodeService::ENTITY_STORAGE_RACK,
            $code,
            $label,
        );
    }

    /**
     * @return array{entity_type: string, entity_id: string, public_code: string, public_url: string, label: string}
     */
    public function ensureSlotQr(StorageSlot $slot, ?string $userId): array
    {
        $code = $this->publicCodeService->ensureStorageSlotPublicCode($slot, $userId);
        $rack = $slot->getRack();
        $locationName = $this->locationNameForRack($rack);
        $label = $this->publicCodeService->buildStorageSlotLabel(
            $locationName,
            $rack->getName(),
            $slot->getName(),
        );

        return $this->publicCodeService->buildStorageQrPayload(
            PublicCodeService::ENTITY_STORAGE_SLOT,
            $code,
            $label,
        );
    }

    /**
     * @return array{created_count: int, skipped_count: int, items: list<array<string, mixed>>}
     */
    public function queuePrint(
        string $departmentId,
        string $scope,
        ?string $addressId,
        ?string $rackId,
        ?string $slotId,
        ?User $user,
    ): array {
        $payloads = match ($scope) {
            'all' => $this->collectAllPayloads($departmentId, $user?->getId()),
            'address' => $this->collectAddressPayloads($departmentId, (string) $addressId, $user?->getId()),
            'rack' => $this->collectRackPayloads($departmentId, (string) $rackId, $user?->getId()),
            'slot' => $this->collectSlotPayloads($departmentId, (string) $slotId, $user?->getId()),
            default => throw new \InvalidArgumentException('Unbekannter scope: ' . $scope),
        };

        return $this->persistPrintTasks($departmentId, $payloads, $user?->getId());
    }

    /**
     * PDF-Zeilen in Baum-Reihenfolge (Standort → Regale → Fächer), gefiltert nach Auswahl.
     *
     * @param list<array{entity_type: string, entity_id: string}> $selections
     *
     * @return MaterialQrExportRow[]
     */
    public function collectPdfRows(string $departmentId, string $addressId, array $selections, ?string $userId): array
    {
        if ($addressId === '') {
            throw new \InvalidArgumentException('address_id ist erforderlich');
        }

        /** @var Address|null $address */
        $address = $this->entityManager->getRepository(Address::class)->find($addressId);
        if (!$address || $address->getDepartmentId() !== $departmentId || $address->getType() !== 'storage') {
            throw new \InvalidArgumentException('Lagerstandort nicht gefunden');
        }
        if ($address->getDeletedAt() !== null) {
            throw new \InvalidArgumentException('Lagerstandort ist gelöscht');
        }

        $selected = [];
        foreach ($selections as $selection) {
            $entityType = trim((string) ($selection['entity_type'] ?? ''));
            $entityId = trim((string) ($selection['entity_id'] ?? ''));
            if ($entityType === '' || $entityId === '') {
                continue;
            }
            $selected[$entityType . '|' . $entityId] = true;
        }

        if ($selected === []) {
            throw new \InvalidArgumentException('Mindestens ein QR muss ausgewählt sein');
        }

        $rows = [];
        $addressKey = PublicCodeService::ENTITY_STORAGE_ADDRESS . '|' . $addressId;
        if (isset($selected[$addressKey])) {
            $rows[] = $this->toExportRow(
                $this->ensureAddressQr($address, $userId),
                'Lagerstandort',
            );
        }

        $racks = $this->entityManager->getRepository(StorageRack::class)->createQueryBuilder('r')
            ->where('r.departmentId = :departmentId')
            ->andWhere('r.storageAddressId = :addressId')
            ->andWhere('r.isActive = true')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('addressId', $addressId)
            ->orderBy('r.sortOrder', 'ASC')
            ->addOrderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($racks as $rack) {
            $rackKey = PublicCodeService::ENTITY_STORAGE_RACK . '|' . $rack->getId();
            if (isset($selected[$rackKey])) {
                $rows[] = $this->toExportRow(
                    $this->ensureRackQr($rack, $userId),
                    'Regal',
                );
            }

            $slots = $this->entityManager->getRepository(StorageSlot::class)->createQueryBuilder('s')
                ->where('s.rackId = :rackId')
                ->andWhere('s.isActive = true')
                ->setParameter('rackId', $rack->getId())
                ->orderBy('s.sortOrder', 'ASC')
                ->addOrderBy('s.name', 'ASC')
                ->getQuery()
                ->getResult();

            foreach ($slots as $slot) {
                $slotKey = PublicCodeService::ENTITY_STORAGE_SLOT . '|' . $slot->getId();
                if (!isset($selected[$slotKey])) {
                    continue;
                }
                $rows[] = $this->toExportRow(
                    $this->ensureSlotQr($slot, $userId),
                    'Fach',
                );
            }
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('Keine gültigen QR-Auswahlen für diesen Standort');
        }

        $this->entityManager->flush();

        return $rows;
    }

    /**
     * @param array{entity_type: string, entity_id: string, public_code: string, public_url: string, label: string} $payload
     */
    private function toExportRow(array $payload, string $typeLabel): MaterialQrExportRow
    {
        return new MaterialQrExportRow(
            materialName: (string) $payload['label'],
            lineLabel: $typeLabel,
            publicCode: (string) $payload['public_code'],
            publicUrl: (string) $payload['public_url'],
        );
    }

    /**
     * @return list<array{entity_type: string, entity_id: string, public_code: string, public_url: string, label: string}>
     */
    private function collectAllPayloads(string $departmentId, ?string $userId): array
    {
        $payloads = [];
        $addresses = $this->entityManager->getRepository(Address::class)->createQueryBuilder('a')
            ->where('a.departmentId = :departmentId')
            ->andWhere('a.type = :type')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('type', 'storage')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
        foreach ($addresses as $address) {
            $payloads = array_merge($payloads, $this->collectAddressPayloads($departmentId, $address->getId(), $userId));
        }

        return $this->uniquePayloads($payloads);
    }

    /**
     * @return list<array{entity_type: string, entity_id: string, public_code: string, public_url: string, label: string}>
     */
    private function collectAddressPayloads(string $departmentId, string $addressId, ?string $userId): array
    {
        if ($addressId === '') {
            throw new \InvalidArgumentException('address_id ist erforderlich für scope=address');
        }

        /** @var Address|null $address */
        $address = $this->entityManager->getRepository(Address::class)->find($addressId);
        if (!$address || $address->getDepartmentId() !== $departmentId || $address->getType() !== 'storage') {
            throw new \InvalidArgumentException('Lagerstandort nicht gefunden');
        }

        $payloads = [$this->ensureAddressQr($address, $userId)];

        $racks = $this->entityManager->getRepository(StorageRack::class)->createQueryBuilder('r')
            ->where('r.departmentId = :departmentId')
            ->andWhere('r.storageAddressId = :addressId')
            ->andWhere('r.isActive = true')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('addressId', $addressId)
            ->orderBy('r.sortOrder', 'ASC')
            ->addOrderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($racks as $rack) {
            $payloads = array_merge($payloads, $this->collectRackPayloads($departmentId, $rack->getId(), $userId));
        }

        return $this->uniquePayloads($payloads);
    }

    /**
     * @return list<array{entity_type: string, entity_id: string, public_code: string, public_url: string, label: string}>
     */
    private function collectRackPayloads(string $departmentId, string $rackId, ?string $userId): array
    {
        if ($rackId === '') {
            throw new \InvalidArgumentException('rack_id ist erforderlich für scope=rack');
        }

        /** @var StorageRack|null $rack */
        $rack = $this->entityManager->getRepository(StorageRack::class)->find($rackId);
        if (!$rack || $rack->getDepartmentId() !== $departmentId || !$rack->getIsActive()) {
            throw new \InvalidArgumentException('Regal nicht gefunden');
        }

        $payloads = [$this->ensureRackQr($rack, $userId)];

        $slots = $this->entityManager->getRepository(StorageSlot::class)->createQueryBuilder('s')
            ->where('s.rackId = :rackId')
            ->andWhere('s.isActive = true')
            ->setParameter('rackId', $rackId)
            ->orderBy('s.sortOrder', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($slots as $slot) {
            $payloads[] = $this->ensureSlotQr($slot, $userId);
        }

        return $this->uniquePayloads($payloads);
    }

    /**
     * @return list<array{entity_type: string, entity_id: string, public_code: string, public_url: string, label: string}>
     */
    private function collectSlotPayloads(string $departmentId, string $slotId, ?string $userId): array
    {
        if ($slotId === '') {
            throw new \InvalidArgumentException('slot_id ist erforderlich für scope=slot');
        }

        /** @var StorageSlot|null $slot */
        $slot = $this->entityManager->getRepository(StorageSlot::class)->find($slotId);
        if (!$slot || !$slot->getIsActive()) {
            throw new \InvalidArgumentException('Fach nicht gefunden');
        }
        if ($slot->getRack()->getDepartmentId() !== $departmentId) {
            throw new \InvalidArgumentException('Fach gehört nicht zu diesem Department');
        }

        return [$this->ensureSlotQr($slot, $userId)];
    }

    /**
     * @param list<array{entity_type: string, entity_id: string, public_code: string, public_url: string, label: string}> $payloads
     *
     * @return array{created_count: int, skipped_count: int, items: list<array<string, mixed>>}
     */
    private function persistPrintTasks(string $departmentId, array $payloads, ?string $userId): array
    {
        $created = 0;
        $skipped = 0;
        $items = [];

        foreach ($payloads as $payload) {
            /** @var PrintTaskItem|null $existing */
            $existing = $this->entityManager->getRepository(PrintTaskItem::class)->findOneBy([
                'departmentId' => $departmentId,
                'entityType' => $payload['entity_type'],
                'entityId' => $payload['entity_id'],
                'status' => 'pending',
            ]);
            if ($existing) {
                ++$skipped;
                $items[] = $this->serializePrintItem($existing);
                continue;
            }

            $item = new PrintTaskItem();
            $item->setId(IdGenerator::generate13Unique($this->entityManager, PrintTaskItem::class, 'pt'));
            $item->setDepartmentId($departmentId);
            $item->setCreatedByUserId($userId);
            $item->setEntityType($payload['entity_type']);
            $item->setEntityId($payload['entity_id']);
            $item->setLabel($payload['label']);
            $item->setPublicCode($payload['public_code']);
            $item->setPublicUrl($payload['public_url']);
            $item->setStatus('pending');
            $this->entityManager->persist($item);
            ++$created;
            $items[] = $this->serializePrintItem($item);
        }

        $this->entityManager->flush();

        return [
            'created_count' => $created,
            'skipped_count' => $skipped,
            'items' => $items,
        ];
    }

    /**
     * @param list<array{entity_type: string, entity_id: string, public_code: string, public_url: string, label: string}> $payloads
     *
     * @return list<array{entity_type: string, entity_id: string, public_code: string, public_url: string, label: string}>
     */
    private function uniquePayloads(array $payloads): array
    {
        $seen = [];
        $out = [];
        foreach ($payloads as $payload) {
            $key = $payload['entity_type'] . '|' . $payload['entity_id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $payload;
        }

        return $out;
    }

    private function locationNameForRack(StorageRack $rack): string
    {
        $address = $rack->getStorageAddress();
        if (!$address) {
            return '';
        }

        return trim((string) ($address->getName() ?: $address->getFullAddress()));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePrintItem(PrintTaskItem $item): array
    {
        return [
            'id' => $item->getId(),
            'department_id' => $item->getDepartmentId(),
            'entity_type' => $item->getEntityType(),
            'entity_id' => $item->getEntityId(),
            'label' => $item->getLabel(),
            'public_code' => $item->getPublicCode(),
            'public_url' => $item->getPublicUrl(),
            'status' => $item->getStatus(),
            'created_at' => $item->getCreatedAt()->format('c'),
        ];
    }
}
