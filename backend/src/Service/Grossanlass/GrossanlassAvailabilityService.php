<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassEinsatz;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Verbindliche Verfügbarkeit einer Charge (`DepartmentGrossanlassCommitment`), serverseitig und für alle Schreibwege gleich.
 *
 * - Reservierung: Einsätze (`kind=einsatz`) mit Status geplant, zur Freigabe oder ausgegeben belegen ihre Menge im
 *   Zeitfenster. `pending_approval` blockiert wie geplant; zurückgenommene Einsätze zählen nicht. Die gleichzeitige Menge
 *   darf die Chargenmenge nie übersteigen (Unikate und Fahrzeuge: nie zwei gleichzeitig).
 * - Ausgabe: höchstens die physisch vorhandene Menge. Eigenbestand (`origin=own`) ist vorhanden, alles andere erst nach
 *   Wareneingang (Charge-Bewegung `received`). Ausgegebene, nicht zurückgenommene Einsätze zählen einmal.
 * - Abhol-/Liefer-Einsätze (`item_details.pickup_einsatz_id` / `delivery_einsatz_id`) transportieren die Charge zum
 *   Grossanlass und verbrauchen keine Menge; sie sind von beiden Regeln ausgenommen.
 *
 * Schreibende Aufrufer führen die Prüfung in `transactional()` aus; {@see lock()} sperrt die Charge (wie der Wareneingang).
 */
final class GrossanlassAvailabilityService
{
    private const RESERVING = [
        DepartmentGrossanlassEinsatz::STATUS_PLANNED,
        DepartmentGrossanlassEinsatz::STATUS_PENDING,
        DepartmentGrossanlassEinsatz::STATUS_ISSUED,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GrossanlassChargeMovementService $movements,
    ) {
    }

    /**
     * Führt $fn in einer Transaktion aus. Anders als `wrapInTransaction` schliesst ein fachlicher Konflikt den EntityManager nicht.
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public function transactional(callable $fn): mixed
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $result = $fn();
            $connection->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Sperrt die Charge bis Transaktionsende. Ausserhalb einer Transaktion (interne Abgleiche der Beschaffung) gibt es
     * nichts zu sperren; alle Schreibwege der Einsätze laufen in {@see transactional()}.
     */
    public function lock(DepartmentGrossanlassCommitment $commitment): void
    {
        if (!$this->entityManager->getConnection()->isTransactionActive()) {
            return;
        }
        $this->movements->lockCommitment($commitment);
    }

    public static function isUnique(DepartmentGrossanlassCommitment $commitment): bool
    {
        return $commitment->getFamily() === DepartmentGrossanlassCommitment::FAMILY_VEHICLE || $commitment->getQuantity() <= 1;
    }

    /** Ein Einsatz, der die Charge reserviert (Menge belegt). */
    public function reserves(DepartmentGrossanlassEinsatz $row): bool
    {
        return $row->getKind() === DepartmentGrossanlassEinsatz::KIND_EINSATZ
            && $row->getCommitmentId() !== null
            && in_array($row->getStatus(), self::RESERVING, true)
            && !$this->isInbound($row);
    }

    /**
     * Prüft, ob $qty im Fenster zusätzlich zu allen anderen Einsätzen der Charge noch Platz hat.
     * Mit $exclude wird ein bestehender Einsatz beim Ändern oder Freigeben nicht doppelt gezählt.
     */
    public function assertWithinCapacity(
        DepartmentGrossanlassCommitment $commitment,
        ?DepartmentGrossanlassEinsatz $exclude,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        int $qty,
    ): void {
        $unique = self::isUnique($commitment);
        $capacity = GrossanlassAvailability::capacity($commitment->getQuantity(), $unique);
        $slots = $this->slots($commitment, $exclude?->getId());
        $slots[] = ['id' => $exclude?->getId() ?? 'neu', 'from' => $from, 'to' => $to, 'qty' => max(1, $qty)];
        $result = GrossanlassAvailability::peak($slots, $from, $to);
        if ($result['peak'] <= $capacity) {
            return;
        }

        $others = array_values(array_filter($result['ids'], static fn (string $id): bool => $id !== ($exclude?->getId() ?? 'neu')));
        $name = $commitment->getName();
        $message = $unique && $capacity === 1 && count($others) > 0
            ? sprintf('%s: im gewählten Zeitraum schon vergeben (nur einmal verfügbar).', $name)
            : sprintf(
                '%s: im gewählten Zeitraum nicht genug Menge — Bestand %d, mit dieser Buchung %d gleichzeitig benötigt.',
                $name,
                $capacity,
                $result['peak'],
            );

        throw new GrossanlassAvailabilityConflict(
            $message,
            $unique ? GrossanlassAvailabilityConflict::KIND_UNIQUE : GrossanlassAvailabilityConflict::KIND_OVERBOOKED,
            [
                'commitment_id' => $commitment->getId(),
                'commitment_name' => $name,
                'capacity' => $capacity,
                'requested' => max(1, $qty),
                'peak' => $result['peak'],
                'einsatz_ids' => $others,
            ],
        );
    }

    /**
     * Ausgabe nur bei physisch vorhandener Menge.
     */
    public function assertIssuable(DepartmentGrossanlassEinsatz $row): void
    {
        $commitment = $row->getCommitment();
        if ($row->getKind() !== DepartmentGrossanlassEinsatz::KIND_EINSATZ
            || !$commitment instanceof DepartmentGrossanlassCommitment
            || $this->isInbound($row)
        ) {
            return;
        }

        $onHand = $this->physicalQuantity($commitment);
        $out = 0;
        foreach ($this->rowsOf($commitment, $row->getId()) as $other) {
            if ($other->getStatus() === DepartmentGrossanlassEinsatz::STATUS_ISSUED && !$this->isInbound($other)) {
                $out += $other->getQty();
            }
        }

        if ($out + $row->getQty() <= $onHand) {
            return;
        }

        $free = max(0, $onHand - $out);
        throw new GrossanlassAvailabilityConflict(
            sprintf(
                '%s: nur %d physisch verfügbar (vorhanden %d, bereits ausgegeben %d) — %d lassen sich nicht ausgeben.',
                $commitment->getName(),
                $free,
                $onHand,
                $out,
                $row->getQty(),
            ),
            GrossanlassAvailabilityConflict::KIND_NOT_ON_HAND,
            [
                'commitment_id' => $commitment->getId(),
                'commitment_name' => $commitment->getName(),
                'on_hand' => $onHand,
                'already_issued' => $out,
                'requested' => $row->getQty(),
            ],
        );
    }

    /**
     * Verringern der Chargenmenge darf bestehende Reservierungen nicht unterschreiten.
     */
    public function assertQuantityCoversBookings(DepartmentGrossanlassCommitment $commitment, int $newQuantity): void
    {
        $capacity = GrossanlassAvailability::capacity($newQuantity, $newQuantity <= 1 || self::isUnique($commitment));
        $result = GrossanlassAvailability::peak($this->slots($commitment, null));
        if ($result['peak'] <= $capacity) {
            return;
        }

        throw new GrossanlassAvailabilityConflict(
            sprintf(
                '%s: Menge %d ist zu klein — gleichzeitig sind bis zu %d reserviert.',
                $commitment->getName(),
                $newQuantity,
                $result['peak'],
            ),
            GrossanlassAvailabilityConflict::KIND_BELOW_BOOKED,
            [
                'commitment_id' => $commitment->getId(),
                'commitment_name' => $commitment->getName(),
                'capacity' => $capacity,
                'peak' => $result['peak'],
                'einsatz_ids' => $result['ids'],
            ],
        );
    }

    /**
     * Physisch vorhandene Menge: Eigenbestand ganz, sonst die erhaltene Menge aus den Charge-Bewegungen.
     */
    public function physicalQuantity(DepartmentGrossanlassCommitment $commitment): int
    {
        if ($commitment->getOrigin() === DepartmentGrossanlassCommitment::ORIGIN_OWN) {
            return max(0, $commitment->getQuantity());
        }

        return min(max(0, $commitment->getQuantity()), $this->movements->receivedQuantity($commitment));
    }

    /**
     * Überbuchte Gruppen für die Konfliktvorschau: dieselbe Rechnung wie die Buchungssperre.
     *
     * @param list<DepartmentGrossanlassEinsatz> $einsaetze
     * @param list<DepartmentGrossanlassCommitment> $commitments
     * @return list<array{commitment: DepartmentGrossanlassCommitment, unique: bool, capacity: int, peak: int, ids: list<string>}>
     */
    public function overbookedGroups(array $einsaetze, array $commitments): array
    {
        $inbound = [];
        $byCommitment = [];
        foreach ($commitments as $commitment) {
            foreach (self::inboundIds($commitment) as $id) {
                $inbound[$id] = true;
            }
        }
        foreach ($einsaetze as $row) {
            $cid = $row->getCommitmentId() ?? '';
            if ($cid === '' || isset($inbound[$row->getId()])) {
                continue;
            }
            if ($row->getKind() !== DepartmentGrossanlassEinsatz::KIND_EINSATZ || !in_array($row->getStatus(), self::RESERVING, true)) {
                continue;
            }
            $byCommitment[$cid][] = $this->slot($row);
        }

        $out = [];
        foreach ($commitments as $commitment) {
            $slots = $byCommitment[$commitment->getId()] ?? [];
            if (count($slots) === 0) {
                continue;
            }
            $unique = self::isUnique($commitment);
            $capacity = GrossanlassAvailability::capacity($commitment->getQuantity(), $unique);
            foreach (GrossanlassAvailability::overbooked($slots, $capacity) as $group) {
                $out[] = ['commitment' => $commitment, 'unique' => $unique, 'capacity' => $capacity] + $group;
            }
        }

        return $out;
    }

    /**
     * @return list<array{id: string, from: \DateTimeInterface, to: \DateTimeInterface, qty: int}>
     */
    private function slots(DepartmentGrossanlassCommitment $commitment, ?string $excludeId): array
    {
        $slots = [];
        foreach ($this->rowsOf($commitment, $excludeId) as $row) {
            if ($this->reserves($row)) {
                $slots[] = $this->slot($row);
            }
        }

        return $slots;
    }

    /**
     * @return array{id: string, from: \DateTimeInterface, to: \DateTimeInterface, qty: int}
     */
    private function slot(DepartmentGrossanlassEinsatz $row): array
    {
        return ['id' => $row->getId(), 'from' => $row->getStartsAt(), 'to' => $row->getEndsAt(), 'qty' => $row->getQty()];
    }

    /**
     * @return list<DepartmentGrossanlassEinsatz>
     */
    private function rowsOf(DepartmentGrossanlassCommitment $commitment, ?string $excludeId): array
    {
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassEinsatz::class)
            ->findBy(['commitmentId' => $commitment->getId()]);

        return array_values(array_filter(
            $rows,
            static fn (DepartmentGrossanlassEinsatz $row): bool => $row->getId() !== $excludeId,
        ));
    }

    private function isInbound(DepartmentGrossanlassEinsatz $row): bool
    {
        $commitment = $row->getCommitment();

        return $commitment instanceof DepartmentGrossanlassCommitment
            && in_array($row->getId(), self::inboundIds($commitment), true);
    }

    /**
     * @return list<string>
     */
    private static function inboundIds(DepartmentGrossanlassCommitment $commitment): array
    {
        $details = $commitment->getItemDetails();
        $ids = [];
        foreach (['pickup_einsatz_id', 'delivery_einsatz_id'] as $key) {
            $id = trim((string) ($details[$key] ?? ''));
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
