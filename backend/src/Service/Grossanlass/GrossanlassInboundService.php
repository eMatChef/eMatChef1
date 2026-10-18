<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassProcurementOrder;
use App\Entity\ActivityGrossanlassProcurementQuote;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassEinsatz;
use App\Entity\DepartmentGrossanlassInquiry;
use App\Entity\DepartmentGrossanlassTour;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Wareneingangs-Arbeitsraum: Lieferungen und Transporte gruppieren, Annahme bündeln.
 * Buchungen laufen über {@see GrossanlassChargeMovementService}; hier keine parallele Bestandslogik.
 */
final class GrossanlassInboundService
{
    public const GROUP_TOUR = 'tour';
    public const GROUP_TRIP = 'trip';
    public const GROUP_DIRECT = 'direct';
    public const GROUP_UNASSIGNED = 'unassigned';

    public const PHASE_EXPECTED = 'expected';
    public const PHASE_ACCEPTANCE = 'acceptance';
    public const PHASE_RECEIVED = 'received';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassCommitmentService $commitments,
        private GrossanlassChargeMovementService $movements,
        private GrossanlassDispatchService $dispatch,
        private GrossanlassTourService $tours,
        private GrossanlassPlaceService $places,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function workspace(Department $department, User $user): array
    {
        $this->assertCanSee($department, $user);

        $positions = $this->inboundPositions($department);
        ['needs' => $needs, 'tours' => $serializedTours] = $this->dispatchContext($department);
        $assigned = [];
        $shipments = [];

        foreach ($serializedTours as $tour) {
            $tourPositions = [];
            foreach ($tour['need_ids'] as $needId) {
                $need = $needs[$needId] ?? null;
                if ($need === null || !$this->dispatchNeedIsInboundTrip($need)) {
                    continue;
                }
                $pos = $this->positionForNeed($positions, (string) $needId, $need);
                if ($pos === null) {
                    continue;
                }
                $tourPositions[] = $pos;
                $assigned[$pos['commitment_id']] = true;
            }
            if ($tourPositions === []) {
                continue;
            }
            $shipments[] = $this->shipmentFromPositions(
                self::GROUP_TOUR,
                (string) $tour['id'],
                (string) $tour['name'],
                $tourPositions,
                [
                    'tour_id' => $tour['id'],
                    'tour_status' => $tour['status'],
                    'chauffeur_user_id' => $tour['chauffeur_user_id'],
                    'vehicle_id' => $tour['vehicle_id'],
                    'target_place_id' => $this->tourTargetPlaceId($tour, $needs),
                    'transport_status' => (string) $tour['status'],
                    'stops' => $tour['stops'],
                ],
            );
        }

        foreach ($needs as $needId => $need) {
            if (!$this->dispatchNeedIsInboundTrip($need)) {
                continue;
            }
            if (($need['tour_id'] ?? null) !== null) {
                continue;
            }
            $pos = $this->positionForNeed($positions, (string) $needId, $need);
            if ($pos === null || isset($assigned[$pos['commitment_id']])) {
                continue;
            }
            $assigned[$pos['commitment_id']] = true;
            $shipments[] = $this->shipmentFromPositions(
                self::GROUP_TRIP,
                'trip:' . $needId,
                (string) ($need['title'] ?? 'Fahrauftrag'),
                [$pos],
                [
                    'need_id' => $needId,
                    'target_place_id' => $need['to_place_id'] ?? null,
                    'transport_status' => (string) ($need['status'] ?? 'open'),
                    'chauffeur_user_id' => $need['chauffeur_user_id'] ?? null,
                    'vehicle_id' => $need['vehicle_id'] ?? null,
                    'from' => $need['from'] ?? null,
                    'to' => $need['to'] ?? null,
                ],
            );
        }

        foreach ($positions as $pos) {
            if (isset($assigned[$pos['commitment_id']])) {
                continue;
            }
            $mode = (string) ($pos['inbound_mode'] ?? 'delivery');
            $group = $mode === 'delivery' ? self::GROUP_DIRECT : self::GROUP_UNASSIGNED;
            $key = $group . ':' . $pos['commitment_id'];
            $label = trim((string) ($pos['supplier'] ?? ''));
            if ($label === '') {
                $label = (string) $pos['name'];
            }
            $shipments[] = $this->shipmentFromPositions(
                $group,
                $key,
                $label,
                [$pos],
                [
                    'target_place_id' => null,
                    'transport_status' => null,
                ],
            );
            $assigned[$pos['commitment_id']] = true;
        }

        usort($shipments, static fn (array $a, array $b) => strcmp((string) ($a['arrives_at'] ?? ''), (string) ($b['arrives_at'] ?? '')));

        $byPhase = [
            self::PHASE_EXPECTED => [],
            self::PHASE_ACCEPTANCE => [],
            self::PHASE_RECEIVED => [],
        ];
        foreach ($shipments as $shipment) {
            $byPhase[$shipment['phase']][] = $shipment;
        }

        $drivers = [];
        $vehicles = [];
        try {
            $dispatch = $this->dispatch->overview($department, $user);
            foreach ($dispatch['drivers'] ?? [] as $row) {
                if (is_array($row) && isset($row['id'], $row['name'])) {
                    $drivers[(string) $row['id']] = (string) $row['name'];
                }
            }
            foreach ($dispatch['vehicles'] ?? [] as $row) {
                if (is_array($row) && isset($row['id'], $row['name'])) {
                    $vehicles[(string) $row['id']] = (string) $row['name'];
                }
            }
        } catch (\Throwable) {
        }

        return [
            'storage_places' => $this->places->listLagerplaetze($department),
            'drivers' => $drivers,
            'vehicles' => $vehicles,
            'phases' => $byPhase,
            'shipments' => $shipments,
        ];
    }

    /**
     * Atomare Annahme mehrerer Positionen (Tour, Fahrauftrag oder Einzellieferung).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function receiveBatch(Department $department, User $user, array $data): array
    {
        $placeId = trim((string) ($data['place_id'] ?? ''));
        $lines = array_values(array_filter((array) ($data['lines'] ?? []), static fn ($row) => is_array($row)));
        if ($lines === []) {
            throw new \InvalidArgumentException('Mindestens eine Position ist erforderlich');
        }

        $movementResults = $this->movements->recordBatch($department, $user, $placeId, $lines);
        $results = [];
        foreach ($movementResults as $row) {
            $commitmentId = (string) ($row['movement']['commitment_id'] ?? '');
            if ($commitmentId === '') {
                continue;
            }
            $results[] = [
                ...$row,
                'commitment' => $this->commitments->get($department, $user, $commitmentId),
            ];
        }

        return ['results' => $results, 'place_id' => $placeId];
    }

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * @return list<array<string, mixed>>
     */
    private function inboundPositions(Department $department): array
    {
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)->findBy(
            ['departmentId' => $department->getId()],
            ['name' => 'ASC'],
        );
        $out = [];
        foreach ($rows as $row) {
            if (!$row instanceof DepartmentGrossanlassCommitment) {
                continue;
            }
            if (!in_array($row->getFamily(), [DepartmentGrossanlassCommitment::FAMILY_MATERIAL, DepartmentGrossanlassCommitment::FAMILY_VEHICLE], true)) {
                continue;
            }
            $serialized = $this->commitments->serialize($row);
            if (!$this->isWareneingangRelevant($row, $serialized)) {
                continue;
            }
            $missing = (int) ($serialized['missing_quantity'] ?? 0);
            $received = (int) ($serialized['received_quantity'] ?? 0);
            $details = is_array($serialized['item_details'] ?? null) ? $serialized['item_details'] : [];
            $mode = (string) ($details['inbound_mode'] ?? '');
            if ($mode !== 'pickup' && $mode !== 'delivery') {
                $mode = $row->getOrigin() === DepartmentGrossanlassCommitment::ORIGIN_LOAN ? 'pickup' : 'delivery';
            }
            $arrivesAt = $this->iso($row->getPresentFrom());
            if ($arrivesAt === null && $row->getOrigin() === DepartmentGrossanlassCommitment::ORIGIN_LOAN) {
                $arrivesAt = $this->iso($row->getHandoverFrom());
            }
            $out[] = [
                'commitment_id' => $row->getId(),
                'name' => $row->getName(),
                'supplier' => $row->getSource(),
                'origin' => $row->getOrigin(),
                'family' => $row->getFamily(),
                'expected_qty' => $row->getQuantity(),
                'received_qty' => $received,
                'open_qty' => $missing,
                'inbound_state' => (string) ($serialized['inbound_state'] ?? 'none'),
                'inbound_mode' => $mode,
                'arrives_at' => $arrivesAt,
                'order_ref' => (string) ($details['order_ref'] ?? ''),
                'procurement_line_id' => $row->getProcurementLineId(),
                'barcode' => $row->getBarcode(),
                'item_details' => $details,
            ];
        }

        usort($out, static function (array $a, array $b): int {
            $at = (string) ($a['arrives_at'] ?? '');
            $bt = (string) ($b['arrives_at'] ?? '');
            if ($at === '' && $bt !== '') {
                return 1;
            }
            if ($bt === '' && $at !== '') {
                return -1;
            }
            $cmp = strcmp($at, $bt);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a['name'], (string) $b['name']);
        });

        return $out;
    }

    /**
     * Gleiche Logik wie die bisherige Wareneingangsliste (Zusagen-Katalog + inboundStatus).
     *
     * @param array<string, mixed> $serialized
     */
    private function isWareneingangRelevant(DepartmentGrossanlassCommitment $row, array $serialized): bool
    {
        if ($row->getQuantity() <= 0 || $row->isReturnedToFirm()) {
            return false;
        }
        if ($row->getOrigin() === DepartmentGrossanlassCommitment::ORIGIN_OWN) {
            return false;
        }
        if ($row->getFamily() === DepartmentGrossanlassCommitment::FAMILY_VEHICLE && !$this->vehicleExpectsGoodsReceipt($row)) {
            return false;
        }
        $received = (int) ($serialized['received_quantity'] ?? 0);
        $missing = (int) ($serialized['missing_quantity'] ?? 0);
        if (!$this->hasProcurementBinding($row) && $received === 0) {
            return false;
        }
        $state = (string) ($serialized['inbound_state'] ?? 'none');
        if ($row->isPacked() || $state === GrossanlassChargeMovementService::STATE_COMPLETE) {
            return $received > 0 || $missing > 0;
        }

        return $missing > 0 || $received > 0;
    }

    /**
     * Externer Wareneingang nur bei verbindlicher Beschaffung (Bestellung, Zusage, Leihe/Spende am Bedarf).
     * Eine Charge ohne Beleg genügt nicht; bereits gebuchte Mengen bleiben sichtbar.
     */
    /**
     * Fahrzeug-Chargen nur bei Anlieferung ans Gelände (z. B. Teleskoplader), nicht bei reiner
     * Logistik-Bereitstellung (Lieferwagen/Transporter zur Disposition).
     */
    private function vehicleExpectsGoodsReceipt(DepartmentGrossanlassCommitment $row): bool
    {
        $mode = (string) ($row->getItemDetails()['inbound_mode'] ?? '');
        if ($mode === 'delivery') {
            return true;
        }
        if ($mode === 'pickup') {
            return false;
        }

        return $row->getPresentFrom() !== null;
    }

    private function hasProcurementBinding(DepartmentGrossanlassCommitment $row): bool
    {
        return match ($row->getOrigin()) {
            DepartmentGrossanlassCommitment::ORIGIN_BUY => $this->hasBuyBinding($row),
            DepartmentGrossanlassCommitment::ORIGIN_LOAN => $this->hasLoanBinding($row),
            DepartmentGrossanlassCommitment::ORIGIN_DONATION => $this->hasDonationBinding($row),
            default => false,
        };
    }

    private function hasBuyBinding(DepartmentGrossanlassCommitment $row): bool
    {
        if ($row->getQuantity() <= 0) {
            return false;
        }
        $order = $row->getOrder();
        if (!$order instanceof ActivityGrossanlassProcurementOrder) {
            $orderId = trim((string) ($row->getItemDetails()['order_id'] ?? ''));
            if ($orderId !== '') {
                $order = $this->entityManager->find(ActivityGrossanlassProcurementOrder::class, $orderId);
            }
        }
        if (!$order instanceof ActivityGrossanlassProcurementOrder || $order->isCancelled()) {
            return false;
        }

        return $order->getStatus() === ActivityGrossanlassProcurementOrder::STATUS_CONFIRMED;
    }

    private function hasLoanBinding(DepartmentGrossanlassCommitment $row): bool
    {
        if ($row->getQuantity() <= 0) {
            return false;
        }
        if ($this->inquiryIsBinding($row->getInquiry())) {
            return true;
        }
        $line = $this->resolveProcurementLine($row);
        if (!$line instanceof ActivityGrossanlassProcurementLine) {
            return false;
        }
        $inquiry = $this->bindingInquiryForLine($row->getDepartmentId(), $line->getId());
        if ($this->inquiryIsBinding($inquiry)) {
            return true;
        }
        if ($this->hasSelectedQuoteOnLine($line) && $inquiry instanceof DepartmentGrossanlassInquiry) {
            return $inquiry->getStatus() === DepartmentGrossanlassInquiry::STATUS_ZUSAGE;
        }
        $fromLineId = trim((string) ($row->getItemDetails()['from_line_id'] ?? ''));
        if ($fromLineId !== '' && $fromLineId === $line->getId()) {
            return true;
        }

        return false;
    }

    private function hasDonationBinding(DepartmentGrossanlassCommitment $row): bool
    {
        if ($row->getQuantity() <= 0) {
            return false;
        }
        if ($this->inquiryIsBinding($row->getInquiry())) {
            return true;
        }
        $details = $row->getItemDetails();

        return filter_var($details['donation_pledge'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || (filter_var($details['absprache'] ?? false, FILTER_VALIDATE_BOOLEAN) && ($details['agreement_kind'] ?? '') === 'donation');
    }

    private function inquiryIsBinding(?DepartmentGrossanlassInquiry $inquiry): bool
    {
        return $inquiry instanceof DepartmentGrossanlassInquiry
            && $inquiry->getStatus() === DepartmentGrossanlassInquiry::STATUS_ZUSAGE;
    }

    private function resolveProcurementLine(DepartmentGrossanlassCommitment $row): ?ActivityGrossanlassProcurementLine
    {
        $line = $row->getProcurementLine();
        if ($line instanceof ActivityGrossanlassProcurementLine) {
            return $line;
        }
        $lineId = trim((string) ($row->getItemDetails()['from_line_id'] ?? ''));
        if ($lineId === '') {
            return null;
        }
        $line = $this->entityManager->find(ActivityGrossanlassProcurementLine::class, $lineId);

        return $line instanceof ActivityGrossanlassProcurementLine && $line->getDepartmentId() === $row->getDepartmentId()
            ? $line
            : null;
    }

    private function bindingInquiryForLine(string $departmentId, string $lineId): ?DepartmentGrossanlassInquiry
    {
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)->findBy(['departmentId' => $departmentId]) as $inquiry) {
            if (!$inquiry instanceof DepartmentGrossanlassInquiry) {
                continue;
            }
            if (in_array($lineId, $inquiry->getLineIds(), true)) {
                return $inquiry;
            }
        }

        return null;
    }

    private function hasSelectedQuoteOnLine(ActivityGrossanlassProcurementLine $line): bool
    {
        foreach ($this->entityManager->getRepository(ActivityGrossanlassProcurementQuote::class)->findBy(['procurementLineId' => $line->getId()]) as $quote) {
            if ($quote instanceof ActivityGrossanlassProcurementQuote && $quote->isSelected()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{needs: array<string, array<string, mixed>>, tours: array<string, array<string, mixed>>}
     */
    private function dispatchContext(Department $department): array
    {
        try {
            $needs = $this->dispatch->needMap($department);
            $serializedTours = [];
            foreach ($this->tours->tours($department) as $tour) {
                if ($tour instanceof DepartmentGrossanlassTour) {
                    $serializedTours[$tour->getId()] = $this->tours->serialize($tour, $needs);
                }
            }

            return ['needs' => $needs, 'tours' => $serializedTours];
        } catch (\Throwable) {
            return ['needs' => [], 'tours' => []];
        }
    }

    /**
     * @param list<array<string, mixed>> $positions
     * @param array<string, mixed>       $need
     */
    private function positionForNeed(array $positions, string $needId, array $need): ?array
    {
        $einsatzId = (string) ($need['einsatz_id'] ?? $needId);
        if ($einsatzId === '') {
            return null;
        }
        $einsatz = $this->entityManager->getRepository(DepartmentGrossanlassEinsatz::class)->find($einsatzId);
        if (!$einsatz instanceof DepartmentGrossanlassEinsatz) {
            return null;
        }
        $commitmentId = $einsatz->getCommitmentId();
        if ($commitmentId === null) {
            return null;
        }
        foreach ($positions as $pos) {
            if ($pos['commitment_id'] === $commitmentId) {
                $placeId = $need['to_place_id'] ?? null;

                return array_merge($pos, [
                    'need_id' => $needId,
                    'transport_status' => (string) ($need['status'] ?? 'open'),
                    'from' => $need['from'] ?? null,
                    'to' => $need['to'] ?? null,
                    'target_place_id' => $placeId !== null && $placeId !== '' ? (string) $placeId : null,
                ]);
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $positions
     * @param array<string, mixed>       $meta
     *
     * @return array<string, mixed>
     */
    private function shipmentFromPositions(string $group, string $id, string $title, array $positions, array $meta): array
    {
        $expected = 0;
        $received = 0;
        $open = 0;
        $arrivesAt = null;
        foreach ($positions as $pos) {
            $expected += (int) $pos['expected_qty'];
            $received += (int) $pos['received_qty'];
            $open += (int) $pos['open_qty'];
            $at = (string) ($pos['arrives_at'] ?? '');
            if ($at !== '' && ($arrivesAt === null || $at < $arrivesAt)) {
                $arrivesAt = $at;
            }
        }
        $phase = self::PHASE_RECEIVED;
        if ($open > 0 && $received > 0) {
            $phase = self::PHASE_ACCEPTANCE;
        } elseif ($open > 0) {
            $phase = self::PHASE_EXPECTED;
        }

        return [
            'id' => $id,
            'group' => $group,
            'title' => $title,
            'phase' => $phase,
            'position_count' => count($positions),
            'progress' => [
                'expected' => $expected,
                'received' => $received,
                'open' => $open,
            ],
            'arrives_at' => $arrivesAt,
            'positions' => $positions,
            ...$meta,
        ];
    }

    /**
     * @param array<string, mixed>                $tour
     * @param array<string, array<string, mixed>> $needs
     */
    private function tourTargetPlaceId(array $tour, array $needs): ?string
    {
        foreach ($tour['stops'] ?? [] as $stop) {
            if (!is_array($stop)) {
                continue;
            }
            if (($stop['kind'] ?? '') === 'unload' && !empty($stop['place_id'])) {
                return (string) $stop['place_id'];
            }
        }
        foreach ($tour['need_ids'] ?? [] as $needId) {
            $placeId = $needs[$needId]['to_place_id'] ?? null;
            if ($placeId) {
                return (string) $placeId;
            }
        }

        return null;
    }

    /**
     * Fahraufträge aus der Beschaffung; mit Pack-Auftrag zählt die Disposition sie als «packen», bleiben aber externe Lieferung.
     *
     * @param array<string, mixed> $need
     */
    private function dispatchNeedIsInboundTrip(array $need): bool
    {
        $source = (string) ($need['source'] ?? '');
        if ($source === GrossanlassDispatchService::SOURCE_BESCHAFFUNG) {
            return true;
        }
        if ($source === GrossanlassDispatchService::SOURCE_PACKEN && ($need['scope'] ?? '') === 'extern') {
            return true;
        }

        return false;
    }

    private function assertCanSee(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canSeeMaterialUebersicht($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für den Wareneingang');
        }
    }

    private function iso(?\DateTimeInterface $value): ?string
    {
        return $value?->format('Y-m-d\TH:i:s');
    }
}
