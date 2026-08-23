<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassInquiry;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

final class GrossanlassCommitmentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(Department $department, User $user): array
    {
        $this->assertManage($department, $user);
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)
            ->findBy(['departmentId' => $department->getId()], ['createdAt' => 'DESC']);

        return array_map(fn (DepartmentGrossanlassCommitment $row) => $this->serialize($row), $rows);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(Department $department, User $user, array $data): array
    {
        $this->assertManage($department, $user);
        $row = new DepartmentGrossanlassCommitment();
        $row->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::COMMITMENT,
            DepartmentGrossanlassCommitment::class,
        ));
        $row->setDepartment($department);
        $this->apply($row, $department, $data, true);
        $this->entityManager->persist($row);
        $this->entityManager->flush();

        return $this->serialize($row);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(Department $department, User $user, string $id, array $data): array
    {
        $this->assertManage($department, $user);
        $row = $this->find($department, $id);
        $this->apply($row, $department, $data, false);
        $this->entityManager->flush();

        return $this->serialize($row);
    }

    /**
     * @return array<string, mixed>
     */
    public function ensureFromInquiry(Department $department, User $user, string $inquiryId): array
    {
        $this->assertManage($department, $user);
        $inquiry = $this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)->find($inquiryId);
        if (!$inquiry instanceof DepartmentGrossanlassInquiry || $inquiry->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Anfrage nicht gefunden');
        }
        $existing = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)
            ->findOneBy(['inquiryId' => $inquiry->getId()]);
        if ($existing instanceof DepartmentGrossanlassCommitment) {
            return $this->serialize($existing);
        }
        $inquiry->setStatus(DepartmentGrossanlassInquiry::STATUS_ZUSAGE);
        $inquiry->appendThread(['who' => 'ok', 'text' => 'Als Zusage erfasst.']);

        return $this->create($department, $user, [
            'name' => $inquiry->getName(),
            'source' => $inquiry->getName(),
            'family' => in_array('fahrzeuge', array_map('strtolower', $inquiry->getCategoryIds()), true)
                ? DepartmentGrossanlassCommitment::FAMILY_VEHICLE
                : DepartmentGrossanlassCommitment::FAMILY_MATERIAL,
            'origin' => DepartmentGrossanlassCommitment::ORIGIN_LOAN,
            'inquiry_id' => $inquiry->getId(),
            'category_id' => $inquiry->getCategoryIds()[0] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apply(
        DepartmentGrossanlassCommitment $row,
        Department $department,
        array $data,
        bool $creating,
    ): void {
        if ($creating || array_key_exists('name', $data)) {
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException('Name ist erforderlich');
            }
            $row->setName($name);
        }
        if ($creating || array_key_exists('source', $data)) {
            $source = trim((string) ($data['source'] ?? ''));
            if ($source === '') {
                throw new \InvalidArgumentException('Firma / Herkunft ist erforderlich');
            }
            $row->setSource($source);
        }
        if (array_key_exists('family', $data) || $creating) {
            $family = (string) ($data['family'] ?? DepartmentGrossanlassCommitment::FAMILY_MATERIAL);
            if (!in_array($family, DepartmentGrossanlassCommitment::FAMILIES, true)) {
                throw new \InvalidArgumentException('Ungültige Art');
            }
            $row->setFamily($family);
        }
        if (array_key_exists('origin', $data) || $creating) {
            $origin = (string) ($data['origin'] ?? DepartmentGrossanlassCommitment::ORIGIN_LOAN);
            if (!in_array($origin, DepartmentGrossanlassCommitment::ORIGINS, true)) {
                throw new \InvalidArgumentException('Ungültige Herkunftsart');
            }
            $row->setOrigin($origin);
        }
        if (array_key_exists('plate', $data) || $creating) {
            $row->setPlate(isset($data['plate']) ? trim((string) $data['plate']) : null);
        }
        if (array_key_exists('barcode', $data)) {
            $row->setBarcode(trim((string) $data['barcode']) ?: null);
        } elseif ($creating && $row->getBarcode() === null) {
            $row->setBarcode('ZS-' . strtoupper($row->getId()));
        }
        if (array_key_exists('category_id', $data) || $creating) {
            $row->setCategoryId(isset($data['category_id']) ? trim((string) $data['category_id']) ?: null : null);
        }
        if (array_key_exists('released', $data)) {
            $row->setReleased((bool) $data['released']);
        }
        if (array_key_exists('inquiry_id', $data)) {
            $inquiryId = trim((string) $data['inquiry_id']);
            if ($inquiryId === '') {
                $row->setInquiry(null);
            } else {
                $inquiry = $this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)->find($inquiryId);
                if (!$inquiry instanceof DepartmentGrossanlassInquiry || $inquiry->getDepartmentId() !== $department->getId()) {
                    throw new \InvalidArgumentException('Anfrage nicht gefunden');
                }
                $row->setInquiry($inquiry);
            }
        }
        foreach ([
            'present_from' => 'setPresentFrom',
            'present_to' => 'setPresentTo',
            'handover_from' => 'setHandoverFrom',
            'handover_to' => 'setHandoverTo',
            'return_from' => 'setReturnFrom',
            'return_to' => 'setReturnTo',
            'wish_from' => 'setWishFrom',
            'wish_to' => 'setWishTo',
        ] as $field => $setter) {
            if (array_key_exists($field, $data)) {
                $row->{$setter}($this->parseDate($data[$field]));
            }
        }
        if (array_key_exists('wish_label', $data)) {
            $label = trim((string) $data['wish_label']);
            $row->setWishLabel($label !== '' ? $label : null);
        }
        if (array_key_exists('services', $data) && is_array($data['services'])) {
            $services = [];
            foreach ($data['services'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $services[] = [
                    'id' => (string) ($item['id'] ?? GrossanlassIdGenerator::hex()),
                    'kind' => (string) ($item['kind'] ?? 'other'),
                    'fromIso' => (string) ($item['fromIso'] ?? $item['from'] ?? ''),
                    'toIso' => (string) ($item['toIso'] ?? $item['to'] ?? ''),
                    'who' => (string) ($item['who'] ?? ''),
                    'label' => isset($item['label']) ? (string) $item['label'] : null,
                ];
            }
            $row->setServices($services);
        }
    }

    private function parseDate(mixed $value): ?\DateTime
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTime) {
            return $value;
        }
        $raw = (string) $value;
        try {
            return new \DateTime($raw);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Ungültiges Datum: ' . $raw);
        }
    }

    private function find(Department $department, string $id): DepartmentGrossanlassCommitment
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)->find($id);
        if (!$row instanceof DepartmentGrossanlassCommitment || $row->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Zusage nicht gefunden');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(DepartmentGrossanlassCommitment $row): array
    {
        $wishFrom = $row->getWishFrom();
        $wishTo = $row->getWishTo();

        return [
            'id' => $row->getId(),
            'inquiry_id' => $row->getInquiryId(),
            'name' => $row->getName(),
            'family' => $row->getFamily(),
            'origin' => $row->getOrigin(),
            'source' => $row->getSource(),
            'plate' => $row->getPlate(),
            'barcode' => $row->getBarcode(),
            'category_id' => $row->getCategoryId(),
            'released' => $row->isReleased(),
            'present_from' => $this->iso($row->getPresentFrom()),
            'present_to' => $this->iso($row->getPresentTo()),
            'handover_from' => $this->iso($row->getHandoverFrom()),
            'handover_to' => $this->iso($row->getHandoverTo()),
            'return_from' => $this->iso($row->getReturnFrom()),
            'return_to' => $this->iso($row->getReturnTo()),
            'wish_label' => $row->getWishLabel(),
            'wish_from' => $this->iso($wishFrom),
            'wish_to' => $this->iso($wishTo),
            'services' => $row->getServices(),
            'created_at' => $row->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $row->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function iso(?\DateTime $value): ?string
    {
        return $value?->format(\DateTimeInterface::ATOM);
    }

    private function assertManage(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Zusagen');
        }
    }
}
