<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassProcurementOrder;
use App\Entity\ActivityGrossanlassProcurementQuote;
use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassCost;
use App\Entity\DepartmentGrossanlassEinsatz;
use App\Entity\DepartmentGrossanlassInquiry;
use App\Entity\DepartmentGrossanlassPackLine;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

final class GrossanlassCommitmentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassCostService $costService,
        private GrossanlassChargeMovementService $movements,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(Department $department, User $user): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canSeeMaterialUebersicht($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Zusagen');
        }
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)
            ->findBy(['departmentId' => $department->getId()], ['createdAt' => 'DESC']);
        $received = $this->movements->receivedByCommitment(
            array_map(static fn (DepartmentGrossanlassCommitment $row) => $row->getId(), $rows),
        );

        return array_map(
            fn (DepartmentGrossanlassCommitment $row) => $this->serialize($row, $received[$row->getId()] ?? 0),
            $rows,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function get(Department $department, User $user, string $id): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canSeeMaterialUebersicht($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Zusagen');
        }

        return $this->serialize($this->find($department, $id));
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
        $this->syncCost($row, $data);
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
        $this->syncCost($row, $data);
        $this->entityManager->flush();

        return $this->serialize($row);
    }

    /**
     * Eigenbestand hat keinen Kostenfall; bestehende Kostenzeilen werden weiter nachgeführt.
     *
     * @param array<string, mixed> $data
     */
    private function syncCost(DepartmentGrossanlassCommitment $row, array $data): void
    {
        if ($row->getOrigin() === DepartmentGrossanlassCommitment::ORIGIN_OWN
            && $this->entityManager->getRepository(DepartmentGrossanlassCost::class)->findOneBy(['commitmentId' => $row->getId()]) === null
        ) {
            return;
        }
        $this->costService->syncFromCommitment($row, $data);
    }

    public function delete(Department $department, User $user, string $id): void
    {
        $this->assertManage($department, $user);
        $row = $this->find($department, $id);
        $this->detachRelated($row);
        $this->entityManager->remove($row);
        $this->entityManager->flush();
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
            'item_details' => [
                'absprache' => true,
                'inbound_status' => 'expected',
                'inbound_mode' => 'pickup',
            ],
        ]);
    }

    /**
     * Eigen-Charge am Bedarf-Stamm, wenn eine Bestellung gespeichert wird.
     * Keine Absprache und keine zweite Kostenzeile — die Offerte bleibt die Akte.
     */
    public function ensureBuyChargeFromOrder(
        Department $department,
        User $user,
        ActivityGrossanlassProcurementLine $line,
        ActivityGrossanlassProcurementOrder $order,
        ?ActivityGrossanlassProcurementQuote $quote,
    ): void {
        $this->assertManage($department, $user);

        $related = $this->commitmentsForLine($department, $line->getId());
        $existing = null;
        foreach ($related as $row) {
            if ($row->getOrigin() === DepartmentGrossanlassCommitment::ORIGIN_BUY) {
                $existing = $row;
                break;
            }
        }

        $qty = max(0, $line->getQuantity());
        $supplier = trim((string) ($quote?->getSupplier() ?? ''));
        $source = $supplier !== '' ? $supplier : $line->getLabel();
        $details = [
            'from_line_id' => $line->getId(),
            'inbound_status' => 'expected',
            'inbound_mode' => 'delivery',
        ];
        if ($quote !== null) {
            $details['quote_id'] = $quote->getId();
        }
        $details['order_id'] = $order->getId();
        if ($order->getOrderRef()) {
            $details['order_ref'] = $order->getOrderRef();
        }

        $family = $line->getWishKind() === 'fahrzeug'
            ? DepartmentGrossanlassCommitment::FAMILY_VEHICLE
            : DepartmentGrossanlassCommitment::FAMILY_MATERIAL;

        $returnRequired = $quote?->isReturnNeeded() ?? false;
        $ownership = [
            'procurement_line_id' => $line->getId(),
            'return_required' => $returnRequired,
            'owner_kind' => $returnRequired
                ? DepartmentGrossanlassCommitment::OWNER_EXTERNAL
                : DepartmentGrossanlassCommitment::OWNER_GROSSANLASS,
        ];

        if ($existing instanceof DepartmentGrossanlassCommitment) {
            $merged = array_merge($existing->getItemDetails(), $details);
            $stillExpected = $this->movements->receivedQuantity($existing) === 0
                && !$existing->isPacked()
                && !$existing->isReturnedToFirm();
            $payload = [
                'name' => $line->getLabel(),
                'source' => $source,
                'family' => $family,
                'origin' => DepartmentGrossanlassCommitment::ORIGIN_BUY,
                'item_details' => $merged,
                'category_id' => $line->getCategoryId(),
                'released' => true,
            ] + $ownership;
            if ($stillExpected && $qty > 0) {
                $payload['quantity'] = $qty;
            }
            if ($stillExpected && $order->getDeliveryAt() instanceof \DateTime) {
                $payload['present_from'] = $order->getDeliveryAt();
            }
            $this->apply($existing, $department, $payload, false);
            $this->entityManager->flush();

            return;
        }

        if ($qty <= 0) {
            return;
        }

        $row = new DepartmentGrossanlassCommitment();
        $row->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::COMMITMENT,
            DepartmentGrossanlassCommitment::class,
        ));
        $row->setDepartment($department);
        $this->apply($row, $department, [
            'name' => $line->getLabel(),
            'source' => $source,
            'family' => $family,
            'origin' => DepartmentGrossanlassCommitment::ORIGIN_BUY,
            'quantity' => $qty,
            'item_details' => $details,
            'category_id' => $line->getCategoryId(),
            'released' => true,
            'present_from' => $order->getDeliveryAt(),
        ] + $ownership, true);
        $this->entityManager->persist($row);
        $this->entityManager->flush();
    }

    /**
     * @return list<DepartmentGrossanlassCommitment>
     */
    private function commitmentsForLine(Department $department, string $lineId): array
    {
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)
            ->findBy(['departmentId' => $department->getId(), 'procurementLineId' => $lineId]);

        return array_values(array_filter($rows, static fn ($row) => $row instanceof DepartmentGrossanlassCommitment));
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
        $previousQty = $row->getQuantity();
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
        $originChanged = false;
        if (array_key_exists('origin', $data) || $creating) {
            $origin = (string) ($data['origin'] ?? DepartmentGrossanlassCommitment::ORIGIN_LOAN);
            if (!in_array($origin, DepartmentGrossanlassCommitment::ORIGINS, true)) {
                throw new \InvalidArgumentException('Ungültige Herkunftsart');
            }
            $originChanged = $creating || $origin !== $row->getOrigin();
            $row->setOrigin($origin);
        }
        $this->applyOwnership($row, $department, $data, $originChanged);
        if (array_key_exists('quantity', $data) || $creating) {
            $nextQty = max(0, (int) ($data['quantity'] ?? ($creating ? 1 : $previousQty)));
            if (!$creating && $nextQty !== $previousQty) {
                $received = $this->movements->receivedQuantity($row);
                if ($nextQty < $received) {
                    throw new \InvalidArgumentException(sprintf(
                        'Menge %d ist kleiner als die bereits erhaltene Menge %d',
                        $nextQty,
                        $received,
                    ));
                }
            }
            $row->setQuantity($nextQty);
        }
        if (array_key_exists('item_details', $data) || $creating) {
            $details = $data['item_details'] ?? [];
            $row->setItemDetails(is_array($details) ? $this->sanitizeItemDetails($details) : []);
        }
        $this->applyProcurementLine($row, $department, $data);
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
        // Eingangsstand kommt aus den Charge-Bewegungen, nicht aus dem Request.
        $details = $row->getItemDetails();
        $inboundStatus = $this->movements->inboundSummaryFor($row)['state'] === GrossanlassChargeMovementService::STATE_COMPLETE
            ? 'here'
            : 'expected';
        if (($details['inbound_status'] ?? null) !== $inboundStatus) {
            $details['inbound_status'] = $inboundStatus;
            $row->setItemDetails($details);
        }
        if ($row->getQuantity() === 0) {
            $row->setReleased(false);
            if ($previousQty > 0) {
                $this->releaseBookingsForZeroQuantity($row);
            }
        }
    }

    /**
     * Rückgabepflicht und Eigentümer. Ohne explizite Angabe folgen sie der Herkunft,
     * aber nur beim Anlegen oder wenn sich die Herkunft ändert.
     *
     * @param array<string, mixed> $data
     */
    private function applyOwnership(
        DepartmentGrossanlassCommitment $row,
        Department $department,
        array $data,
        bool $originChanged,
    ): void {
        if (array_key_exists('return_required', $data)) {
            $row->setReturnRequired(filter_var($data['return_required'], FILTER_VALIDATE_BOOLEAN));
        } elseif ($originChanged) {
            $row->setReturnRequired(DepartmentGrossanlassCommitment::defaultReturnRequired($row->getOrigin()));
        }

        $ownerDepartmentId = array_key_exists('owner_department_id', $data)
            ? trim((string) ($data['owner_department_id'] ?? ''))
            : null;
        if (array_key_exists('owner_kind', $data)) {
            $kind = (string) $data['owner_kind'];
        } elseif ($ownerDepartmentId !== null && $ownerDepartmentId !== '') {
            $kind = DepartmentGrossanlassCommitment::OWNER_DEPARTMENT;
        } elseif ($originChanged) {
            $kind = DepartmentGrossanlassCommitment::defaultOwnerKind($row->getOrigin());
        } else {
            $kind = $row->getOwnerKind();
        }
        if (!in_array($kind, DepartmentGrossanlassCommitment::OWNER_KINDS, true)) {
            throw new \InvalidArgumentException('Ungültiger Eigentümer');
        }
        $row->setOwnerKind($kind);

        if ($kind !== DepartmentGrossanlassCommitment::OWNER_DEPARTMENT) {
            $row->setOwnerDepartment(null);

            return;
        }
        if ($ownerDepartmentId === null) {
            if ($row->getOwnerDepartmentId() === null) {
                throw new \InvalidArgumentException('Eigentümer-Abteilung fehlt');
            }

            return;
        }
        $owner = $ownerDepartmentId !== ''
            ? $this->entityManager->getRepository(Department::class)->find($ownerDepartmentId)
            : null;
        if (!$owner instanceof Department) {
            throw new \InvalidArgumentException('Eigentümer-Abteilung nicht gefunden');
        }
        if ($owner->getId() === $department->getId()) {
            throw new \InvalidArgumentException('Eigenbestand bitte als Eigentümer Grossanlass erfassen');
        }
        $row->setOwnerDepartment($owner);
    }

    /**
     * Bedarfsposition als echte Relation. `item_details.from_line_id` bleibt für bestehende Leser erhalten;
     * zeigt es auf einen Wunsch statt auf eine Position, bleibt die Relation leer.
     *
     * @param array<string, mixed> $data
     */
    private function applyProcurementLine(DepartmentGrossanlassCommitment $row, Department $department, array $data): void
    {
        if (array_key_exists('procurement_line_id', $data)) {
            $lineId = trim((string) ($data['procurement_line_id'] ?? ''));
        } else {
            $lineId = trim((string) ($row->getItemDetails()['from_line_id'] ?? ''));
            if ($lineId === '' || $lineId === $row->getProcurementLineId()) {
                return;
            }
        }
        if ($lineId === '') {
            $row->setProcurementLine(null);

            return;
        }
        $line = $this->entityManager->getRepository(ActivityGrossanlassProcurementLine::class)->find($lineId);
        if ($line instanceof ActivityGrossanlassProcurementLine && $line->getDepartmentId() === $department->getId()) {
            $row->setProcurementLine($line);
        } elseif (array_key_exists('procurement_line_id', $data)) {
            throw new \InvalidArgumentException('Bedarfsposition nicht gefunden');
        }
    }

    private function releaseBookingsForZeroQuantity(DepartmentGrossanlassCommitment $row): void
    {
        $einsaetze = $this->entityManager->getRepository(DepartmentGrossanlassEinsatz::class)
            ->findBy(['commitmentId' => $row->getId()]);
        foreach ($einsaetze as $einsatz) {
            if (!$einsatz instanceof DepartmentGrossanlassEinsatz) {
                continue;
            }
            if ($einsatz->getKind() !== DepartmentGrossanlassEinsatz::KIND_EINSATZ) {
                continue;
            }
            if ($einsatz->getStatus() === DepartmentGrossanlassEinsatz::STATUS_RETURNED) {
                continue;
            }
            if (
                $einsatz->getStatus() === DepartmentGrossanlassEinsatz::STATUS_ISSUED
                || $einsatz->isPacked()
            ) {
                throw new \InvalidArgumentException(
                    'Artikel ist noch ausgegeben oder gepackt — zuerst zurücknehmen, dann Menge 0.',
                );
            }
            $this->entityManager->remove($einsatz);
        }
    }

    /**
     * @param array<mixed> $raw
     *
     * @return array<string, mixed>
     */
    private function sanitizeItemDetails(array $raw): array
    {
        $out = [];
        $weight = trim((string) ($raw['weight'] ?? ''));
        $packUnit = trim((string) ($raw['pack_unit'] ?? ''));
        $packSize = trim((string) ($raw['pack_size'] ?? ''));
        $notes = trim((string) ($raw['notes'] ?? ''));
        if ($weight !== '') {
            $out['weight'] = mb_substr($weight, 0, 80);
        }
        if ($packUnit !== '') {
            $out['pack_unit'] = mb_substr($packUnit, 0, 40);
        }
        if ($packSize !== '') {
            $out['pack_size'] = mb_substr($packSize, 0, 40);
        }
        if ($notes !== '') {
            $out['notes'] = mb_substr($notes, 0, 500);
        }
        $fromLineId = trim((string) ($raw['from_line_id'] ?? ''));
        if ($fromLineId !== '') {
            $out['from_line_id'] = mb_substr($fromLineId, 0, 12);
        }
        $inboundStatus = trim((string) ($raw['inbound_status'] ?? ''));
        if (in_array($inboundStatus, ['expected', 'here'], true)) {
            $out['inbound_status'] = $inboundStatus;
        }
        $inboundMode = trim((string) ($raw['inbound_mode'] ?? ''));
        if (in_array($inboundMode, ['pickup', 'delivery'], true)) {
            $out['inbound_mode'] = $inboundMode;
        }
        $quoteId = trim((string) ($raw['quote_id'] ?? ''));
        if ($quoteId !== '') {
            $out['quote_id'] = mb_substr($quoteId, 0, 12);
        }
        $orderId = trim((string) ($raw['order_id'] ?? ''));
        if ($orderId !== '') {
            $out['order_id'] = mb_substr($orderId, 0, 12);
        }
        $orderRef = trim((string) ($raw['order_ref'] ?? ''));
        if ($orderRef !== '') {
            $out['order_ref'] = mb_substr($orderRef, 0, 80);
        }
        if (array_key_exists('qty_checked', $raw)) {
            $out['qty_checked'] = (bool) $raw['qty_checked'];
        }
        $pickupEinsatzId = trim((string) ($raw['pickup_einsatz_id'] ?? ''));
        if ($pickupEinsatzId !== '') {
            $out['pickup_einsatz_id'] = mb_substr($pickupEinsatzId, 0, 12);
        }
        $deliveryEinsatzId = trim((string) ($raw['delivery_einsatz_id'] ?? ''));
        if ($deliveryEinsatzId !== '') {
            $out['delivery_einsatz_id'] = mb_substr($deliveryEinsatzId, 0, 12);
        }

        $parts = [];
        $rawParts = $raw['parts'] ?? [];
        if (is_array($rawParts)) {
            foreach ($rawParts as $part) {
                if (!is_array($part)) {
                    continue;
                }
                $name = trim((string) ($part['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $parts[] = [
                    'name' => mb_substr($name, 0, 120),
                    'qty' => max(1, (int) ($part['qty'] ?? $part['quantity'] ?? 1)),
                ];
            }
        }
        if ($parts !== []) {
            $out['parts'] = $parts;
        }

        return $out;
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
            return GrossanlassQuarterHour::snap(new \DateTime($raw));
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

    private function detachRelated(DepartmentGrossanlassCommitment $row): void
    {
        $costs = $this->entityManager->getRepository(DepartmentGrossanlassCost::class)
            ->findBy(['commitmentId' => $row->getId()]);
        foreach ($costs as $cost) {
            if (!$cost instanceof DepartmentGrossanlassCost) {
                continue;
            }
            if ($cost->getProcurementLineId() === null) {
                $this->entityManager->remove($cost);
            } else {
                $cost->setCommitment(null);
            }
        }

        $packLines = $this->entityManager->getRepository(DepartmentGrossanlassPackLine::class)
            ->findBy(['commitmentId' => $row->getId()]);
        foreach ($packLines as $packLine) {
            if ($packLine instanceof DepartmentGrossanlassPackLine) {
                $packLine->setCommitmentId(null);
            }
        }

        $wishes = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)
            ->findBy(['enoughOnHandRefId' => $row->getId()]);
        foreach ($wishes as $wish) {
            if (!$wish instanceof ActivityGrossanlassWishLine) {
                continue;
            }
            if ($wish->getEnoughOnHandSource() === 'commitment') {
                $wish->setEnoughOnHand(false);
                $wish->setEnoughOnHandSource(null);
                $wish->setEnoughOnHandDetail(null);
            }
            $wish->setEnoughOnHandRefId(null);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(DepartmentGrossanlassCommitment $row, ?int $received = null): array
    {
        $wishFrom = $row->getWishFrom();
        $wishTo = $row->getWishTo();
        $inbound = $this->movements->inboundSummaryFor($row, $received);

        return [
            'id' => $row->getId(),
            'inquiry_id' => $row->getInquiryId(),
            'name' => $row->getName(),
            'family' => $row->getFamily(),
            'origin' => $row->getOrigin(),
            'quantity' => $row->getQuantity(),
            'packed' => $row->isPacked(),
            'pack_phase' => $row->getPackPhase(),
            'returned_to_firm' => $row->isReturnedToFirm(),
            'return_required' => $row->isReturnRequired(),
            'owner_kind' => $row->getOwnerKind(),
            'owner_department_id' => $row->getOwnerDepartmentId(),
            'owner_department_name' => $row->getOwnerDepartment()?->getName(),
            'procurement_line_id' => $row->getProcurementLineId(),
            'received_quantity' => $inbound['received'],
            'missing_quantity' => $inbound['missing'],
            'inbound_state' => $inbound['state'],
            'item_details' => $row->getItemDetails(),
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
        if (!$this->access->canTakeInquiry($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Zusagen');
        }
    }
}
