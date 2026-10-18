<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassProcurementLineWish;
use App\Entity\ActivityGrossanlassProcurementOrder;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassChargeMovement;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassEinsatz;
use App\Entity\DepartmentGrossanlassPackLine;
use App\Entity\ActivityGrossanlassProcurementQuote;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Mengenbewegungen an der Charge. Physische Wahrheit für den Wareneingang:
 * erhaltene Menge, Fehlmenge und Eingangsstand werden daraus berechnet.
 *
 * Übergangsregel: `item_details.inbound_status` der Charge sowie
 * `procurement_line_wish.received_quantity` und der Status der Bedarfsposition
 * werden aus den Bewegungen synchronisiert, nicht mehr unabhängig gepflegt.
 */
final class GrossanlassChargeMovementService
{
    public const STATE_NONE = 'none';
    public const STATE_PARTIAL = 'partial';
    public const STATE_COMPLETE = 'complete';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassStorageLagerplatzService $storageLagerplatz,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(Department $department, User $user, string $commitmentId): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canSeeMaterialUebersicht($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für die Materialübersicht');
        }
        $commitment = $this->findCommitment($department, $commitmentId);

        return array_map(
            fn (DepartmentGrossanlassChargeMovement $row) => $this->serialize($row),
            $this->movementsOf($commitment),
        );
    }

    /**
     * API: Bewegung an einer Charge buchen. Heute nur `received`.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function record(Department $department, User $user, string $commitmentId, array $data): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canOperateAusgabe($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für den Wareneingang');
        }
        $commitment = $this->findCommitment($department, $commitmentId);
        $kind = (string) ($data['kind'] ?? DepartmentGrossanlassChargeMovement::KIND_RECEIVED);
        if (!in_array($kind, DepartmentGrossanlassChargeMovement::KINDS, true)) {
            throw new \InvalidArgumentException('Ungültige Bewegungsart');
        }
        if (!in_array($kind, DepartmentGrossanlassChargeMovement::BOOKABLE_KINDS, true)) {
            throw new \InvalidArgumentException('Diese Bewegungsart ist noch nicht verfügbar');
        }

        $placeId = trim((string) ($data['place_id'] ?? ''));
        if ($kind === DepartmentGrossanlassChargeMovement::KIND_RECEIVED && $placeId === '') {
            throw new \InvalidArgumentException('Annahmeort (Lagerstandort) ist erforderlich');
        }
        $place = null;
        if ($placeId !== '') {
            $place = $this->resolveStoragePlace($department, $placeId);
        }
        $occurredAt = null;
        $rawAt = trim((string) ($data['occurred_at'] ?? ''));
        if ($rawAt !== '') {
            try {
                $occurredAt = new \DateTime($rawAt);
            } catch (\Exception) {
                throw new \InvalidArgumentException('Ungültiger Zeitpunkt');
            }
        }
        $note = trim((string) ($data['note'] ?? ''));
        // Offensichtliche Fehler vor der Transaktion melden (eine Ausnahme in der Transaktion schliesst den EntityManager).
        if ((int) ($data['quantity'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('Menge muss grösser als 0 sein');
        }
        if ($kind === DepartmentGrossanlassChargeMovement::KIND_RECEIPT_REVERSED && $note === '') {
            throw new \InvalidArgumentException('Für eine Gegenbuchung ist ein Grund erforderlich');
        }

        $movement = $this->entityManager->wrapInTransaction(function () use ($commitment, $data, $user, $place, $note, $occurredAt, $kind) {
            $this->lockCommitment($commitment);
            if ($kind === DepartmentGrossanlassChargeMovement::KIND_RECEIPT_REVERSED) {
                $movement = $this->reverseReceived(
                    $commitment,
                    (int) ($data['quantity'] ?? 0),
                    $user,
                    $note,
                    trim((string) ($data['reverses_id'] ?? '')) ?: null,
                    $occurredAt,
                );
            } else {
                $movement = $this->recordReceived(
                    $commitment,
                    (int) ($data['quantity'] ?? 0),
                    $user,
                    $place,
                    $note !== '' ? $note : null,
                    $occurredAt,
                );
            }
            $this->syncProcurementLine($commitment);
            $this->entityManager->flush();

            return $movement;
        });

        return [
            'movement' => $this->serialize($movement),
            'inbound' => $this->inboundSummaryFor($commitment),
        ];
    }

    /**
     * Mehrere Wareneingänge in einer Transaktion (z. B. Tour-Annahme).
     *
     * @param list<array{commitment_id: string, quantity: int, note?: string|null, skip?: bool, place_id?: string|null}> $lines
     *
     * @return list<array{movement: array<string, mixed>, inbound: array<string, mixed>}>
     */
    public function recordBatch(Department $department, User $user, string $defaultPlaceId, array $lines): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canOperateAusgabe($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für den Wareneingang');
        }
        $defaultPlaceId = trim($defaultPlaceId);

        return $this->entityManager->wrapInTransaction(function () use ($department, $user, $defaultPlaceId, $lines): array {
            $out = [];
            foreach ($lines as $line) {
                if (!empty($line['skip'])) {
                    continue;
                }
                $commitmentId = trim((string) ($line['commitment_id'] ?? ''));
                if ($commitmentId === '') {
                    throw new \InvalidArgumentException('commitment_id fehlt');
                }
                $quantity = (int) ($line['quantity'] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }
                $linePlaceId = trim((string) ($line['place_id'] ?? ''));
                $effectivePlaceId = $linePlaceId !== '' ? $linePlaceId : $defaultPlaceId;
                if ($effectivePlaceId === '') {
                    throw new \InvalidArgumentException('Annahmeort (Lagerstandort) ist erforderlich');
                }
                $place = $this->resolveStoragePlace($department, $effectivePlaceId);
                $commitment = $this->findCommitment($department, $commitmentId);
                $this->lockCommitment($commitment);
                $note = trim((string) ($line['note'] ?? ''));
                $movement = $this->recordReceived(
                    $commitment,
                    $quantity,
                    $user,
                    $place,
                    $note !== '' ? $note : null,
                );
                $this->syncProcurementLine($commitment);
                $out[] = [
                    'movement' => $this->serialize($movement),
                    'inbound' => $this->inboundSummaryFor($commitment),
                ];
            }
            if ($out === []) {
                throw new \InvalidArgumentException('Keine Menge zum Buchen');
            }
            $this->entityManager->flush();

            return $out;
        });
    }

    /**
     * Sperrt die Charge bis Transaktionsende, damit parallele Eingänge nicht überliefern.
     * Nur innerhalb einer Transaktion aufrufen.
     */
    public function lockCommitment(DepartmentGrossanlassCommitment $commitment): void
    {
        $this->entityManager->lock($commitment, LockMode::PESSIMISTIC_WRITE);
    }

    /**
     * Wareneingang buchen. Lehnt Überlieferung ab. Aufrufer sperrt die Charge ({@see lockCommitment}).
     */
    public function recordReceived(
        DepartmentGrossanlassCommitment $commitment,
        int $quantity,
        ?User $user,
        ?DepartmentGrossanlassPlace $place = null,
        ?string $note = null,
        ?\DateTime $occurredAt = null,
    ): DepartmentGrossanlassChargeMovement {
        self::assertReceivable($commitment->getQuantity(), $this->receivedQuantity($commitment), $quantity);

        $row = new DepartmentGrossanlassChargeMovement();
        $row->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::CHARGE_MOVEMENT,
            DepartmentGrossanlassChargeMovement::class,
        ));
        $row->setCommitment($commitment);
        $row->setKind(DepartmentGrossanlassChargeMovement::KIND_RECEIVED);
        $row->setQuantity($quantity);
        $row->setCreatedByUser($user);
        $row->setPlace($place);
        $row->setNote($note);
        if ($occurredAt !== null) {
            $row->setOccurredAt($occurredAt);
        }
        $this->entityManager->persist($row);
        $this->entityManager->flush();

        $this->syncInboundStatus($commitment);

        return $row;
    }

    /**
     * Gegenbuchung zu einem Wareneingang. Die ursprüngliche Buchung bleibt unverändert in der Historie;
     * die Gegenbuchung zieht die Menge ab und nennt den Grund. Aufrufer sperrt die Charge ({@see lockCommitment}).
     * Nicht möglich, soweit die Ware bereits zugeteilt oder gepackt ist.
     */
    public function reverseReceived(
        DepartmentGrossanlassCommitment $commitment,
        int $quantity,
        ?User $user,
        string $reason,
        ?string $reversesId = null,
        ?\DateTime $occurredAt = null,
    ): DepartmentGrossanlassChargeMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Menge muss grösser als 0 sein');
        }
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Für eine Gegenbuchung ist ein Grund erforderlich');
        }
        $received = $this->receivedQuantity($commitment);
        self::assertReversible($received, $quantity, $this->usedQuantity($commitment));

        $original = null;
        if ($reversesId !== null) {
            $original = $this->entityManager->getRepository(DepartmentGrossanlassChargeMovement::class)->find($reversesId);
            if (!$original instanceof DepartmentGrossanlassChargeMovement
                || $original->getCommitmentId() !== $commitment->getId()
                || $original->getKind() !== DepartmentGrossanlassChargeMovement::KIND_RECEIVED
            ) {
                throw new \InvalidArgumentException('Zu korrigierender Wareneingang nicht gefunden');
            }
            $already = 0;
            foreach ($this->movementsOf($commitment) as $m) {
                if ($m->getKind() === DepartmentGrossanlassChargeMovement::KIND_RECEIPT_REVERSED && $m->getReversesId() === $reversesId) {
                    $already += $m->getQuantity();
                }
            }
            if ($quantity > $original->getQuantity() - $already) {
                throw new \InvalidArgumentException(sprintf('Dieser Wareneingang (%d) ist schon mit %d korrigiert', $original->getQuantity(), $already));
            }
        }

        $row = new DepartmentGrossanlassChargeMovement();
        $row->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::CHARGE_MOVEMENT,
            DepartmentGrossanlassChargeMovement::class,
        ));
        $row->setCommitment($commitment);
        $row->setKind(DepartmentGrossanlassChargeMovement::KIND_RECEIPT_REVERSED);
        $row->setQuantity($quantity);
        $row->setCreatedByUser($user);
        $row->setNote(trim($reason));
        $row->setReversesId($original?->getId());
        if ($occurredAt !== null) {
            $row->setOccurredAt($occurredAt);
        }
        $this->entityManager->persist($row);
        $this->entityManager->flush();

        $this->syncInboundStatus($commitment);

        return $row;
    }

    /**
     * Menge, die von der Charge bereits fest eingeplant ist: Zuteilungen (Einsätze) oder Packs, je nachdem was grösser ist.
     */
    public function usedQuantity(DepartmentGrossanlassCommitment $commitment): int
    {
        $allocated = 0;
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassEinsatz::class)->findBy([
            'commitmentId' => $commitment->getId(),
            'kind' => DepartmentGrossanlassEinsatz::KIND_EINSATZ,
        ]) as $einsatz) {
            if ($einsatz instanceof DepartmentGrossanlassEinsatz && in_array($einsatz->getStatus(), [
                DepartmentGrossanlassEinsatz::STATUS_PLANNED,
                DepartmentGrossanlassEinsatz::STATUS_PENDING,
                DepartmentGrossanlassEinsatz::STATUS_ISSUED,
            ], true)) {
                $allocated += max(0, $einsatz->getQty());
            }
        }
        $packed = 0;
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassPackLine::class)->findBy(['commitmentId' => $commitment->getId()]) as $line) {
            if ($line instanceof DepartmentGrossanlassPackLine) {
                $packed += max(0, $line->getQtyPacked());
            }
        }

        return max($allocated, $packed);
    }

    /**
     * @throws \InvalidArgumentException wenn mehr korrigiert würde als eingegangen oder bereits eingeplant ist
     */
    public static function assertReversible(int $received, int $quantity, int $used): void
    {
        if ($quantity > $received) {
            throw new \InvalidArgumentException(sprintf('Nur %d eingegangen — %d lässt sich nicht zurückbuchen', $received, $quantity));
        }
        if ($received - $quantity < $used) {
            throw new \InvalidArgumentException(sprintf(
                'Von %d eingegangenen sind %d bereits zugeteilt oder gepackt. Zuteilung/Pack zuerst korrigieren.',
                $received,
                $used,
            ));
        }
    }

    /**
     * @throws \InvalidArgumentException bei Menge ≤ 0 oder Überlieferung
     */
    public static function assertReceivable(int $expected, int $alreadyReceived, int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Menge muss grösser als 0 sein');
        }
        $open = max(0, $expected - $alreadyReceived);
        if ($quantity > $open) {
            throw new \InvalidArgumentException(sprintf(
                'Überlieferung: erwartet %d, bereits erhalten %d, offen %d — %d ist zu viel',
                $expected,
                $alreadyReceived,
                $open,
                $quantity,
            ));
        }
    }

    /**
     * @return array{expected: int, received: int, missing: int, state: string}
     */
    public static function inboundSummary(int $expected, int $received): array
    {
        $expected = max(0, $expected);
        $received = max(0, $received);
        $state = self::STATE_NONE;
        if ($received > 0) {
            $state = $received >= $expected ? self::STATE_COMPLETE : self::STATE_PARTIAL;
        }

        return [
            'expected' => $expected,
            'received' => $received,
            'missing' => max(0, $expected - $received),
            'state' => $state,
        ];
    }

    /**
     * @return array{expected: int, received: int, missing: int, state: string}
     */
    public function inboundSummaryFor(DepartmentGrossanlassCommitment $commitment, ?int $received = null): array
    {
        return self::inboundSummary($commitment->getQuantity(), $received ?? $this->receivedQuantity($commitment));
    }

    public function receivedQuantity(DepartmentGrossanlassCommitment $commitment): int
    {
        return $this->receivedByCommitment([$commitment->getId()])[$commitment->getId()] ?? 0;
    }

    /**
     * @param list<string> $commitmentIds
     * @return array<string, int>
     */
    public function receivedByCommitment(array $commitmentIds): array
    {
        if ($commitmentIds === []) {
            return [];
        }
        $rows = $this->entityManager->createQueryBuilder()
            ->select('m.commitmentId AS id', 'SUM(CASE WHEN m.kind = :reversed THEN -m.quantity ELSE m.quantity END) AS qty')
            ->from(DepartmentGrossanlassChargeMovement::class, 'm')
            ->where('m.commitmentId IN (:ids)')
            ->andWhere('m.kind IN (:kinds)')
            ->setParameter('ids', $commitmentIds)
            ->setParameter('reversed', DepartmentGrossanlassChargeMovement::KIND_RECEIPT_REVERSED)
            ->setParameter('kinds', [DepartmentGrossanlassChargeMovement::KIND_RECEIVED, DepartmentGrossanlassChargeMovement::KIND_RECEIPT_REVERSED])
            ->groupBy('m.commitmentId')
            ->getQuery()
            ->getArrayResult();
        $out = [];
        foreach ($rows as $row) {
            $out[trim((string) $row['id'])] = max(0, (int) $row['qty']);
        }

        return $out;
    }

    /**
     * Kauf-Charge einer Bestellung (eine Charge je Bestellung).
     */
    public function buyChargeForOrder(ActivityGrossanlassProcurementOrder $order): ?DepartmentGrossanlassCommitment
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)->findOneBy(['orderId' => $order->getId()]);
        if ($row instanceof DepartmentGrossanlassCommitment) {
            return $row;
        }
        // Altbestand ohne Relation: Charge der Position, die die Bestellung in item_details nennt.
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)->findBy([
            'procurementLineId' => $order->getProcurementLineId(),
            'origin' => DepartmentGrossanlassCommitment::ORIGIN_BUY,
        ]) as $candidate) {
            if ($candidate instanceof DepartmentGrossanlassCommitment
                && $candidate->getOrderId() === null
                && ($candidate->getItemDetails()['order_id'] ?? null) === $order->getId()
            ) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Aktive Bestellungen einer Position (ohne stornierte), älteste zuerst.
     *
     * @return list<ActivityGrossanlassProcurementOrder>
     */
    public function activeOrders(ActivityGrossanlassProcurementLine $line): array
    {
        $rows = $this->entityManager->getRepository(ActivityGrossanlassProcurementOrder::class)
            ->findBy(['procurementLineId' => $line->getId()], ['createdAt' => 'ASC']);

        return array_values(array_filter(
            $rows,
            static fn ($o) => $o instanceof ActivityGrossanlassProcurementOrder && !$o->isCancelled(),
        ));
    }

    /**
     * Status der Bedarfsposition aus ihren aktiven Bestellungen und deren Wareneingängen nachführen:
     * bestellt / teilweise erhalten / erhalten (alles Bestellte ist da). Ohne aktive Bestellung fällt sie auf den Stand
     * vor der Bestellung zurück. Optionale `$allocations` (wish-id → Menge) halten eine bewusste Zuteilung des Eingangs
     * an Wünsche fest; es gibt keine automatische Verteilung nach Wunschreihenfolge.
     *
     * @param array<string, int>|null $allocations
     */
    public function syncLineReceived(ActivityGrossanlassProcurementLine $line, ?array $allocations = null): void
    {
        $ordered = 0;
        $received = 0;
        $orders = $this->activeOrders($line);
        foreach ($orders as $order) {
            $charge = $this->buyChargeForOrder($order);
            $ordered += $charge instanceof DepartmentGrossanlassCommitment ? $charge->getQuantity() : ($order->getQuantity() ?? $line->getQuantity());
            $received += $charge instanceof DepartmentGrossanlassCommitment ? $this->receivedQuantity($charge) : 0;
        }

        $links = $this->entityManager->getRepository(ActivityGrossanlassProcurementLineWish::class)
            ->findBy(['procurementLineId' => $line->getId()], ['createdAt' => 'ASC']);
        $byWish = [];
        foreach ($links as $link) {
            if ($link instanceof ActivityGrossanlassProcurementLineWish) {
                $byWish[$link->getWishLineId()] = $link;
            }
        }
        if ($allocations !== null) {
            foreach ($allocations as $wishId => $qty) {
                if (isset($byWish[$wishId])) {
                    $byWish[$wishId]->setReceivedQuantity(max(0, min((int) $qty, $byWish[$wishId]->getWishLine()->getQuantity())));
                }
            }
        }
        // Zuteilung nie über den tatsächlich eingegangenen Bestand hinaus; bei Rückbuchung wird sie bewusst neu gesetzt.
        if (array_sum(array_map(static fn (ActivityGrossanlassProcurementLineWish $l) => $l->getReceivedQuantity(), $byWish)) > $received) {
            foreach ($byWish as $link) {
                $link->setReceivedQuantity(0);
            }
        }

        $status = $line->getStatus();
        if ($orders === []) {
            if (in_array($status, [
                ActivityGrossanlassProcurementLine::STATUS_BESTELLT,
                ActivityGrossanlassProcurementLine::STATUS_TEILWEISE,
                ActivityGrossanlassProcurementLine::STATUS_ERHALTEN,
            ], true)) {
                $quotes = $this->entityManager->getRepository(ActivityGrossanlassProcurementQuote::class)->findBy(['procurementLineId' => $line->getId()]);
                $selected = array_filter($quotes, static fn ($q) => $q instanceof ActivityGrossanlassProcurementQuote && $q->isSelected());
                $status = $selected !== []
                    ? ActivityGrossanlassProcurementLine::STATUS_BUDGETIERT
                    : ($quotes !== [] ? ActivityGrossanlassProcurementLine::STATUS_OFFERTE : ActivityGrossanlassProcurementLine::STATUS_BEDARF);
            }
        } elseif ($received > 0 && $received >= $ordered) {
            $status = ActivityGrossanlassProcurementLine::STATUS_ERHALTEN;
        } elseif ($received > 0) {
            $status = ActivityGrossanlassProcurementLine::STATUS_TEILWEISE;
        } else {
            $status = ActivityGrossanlassProcurementLine::STATUS_BESTELLT;
        }
        if ($status !== $line->getStatus()) {
            $line->setStatus($status);
            $line->touchUpdatedAt();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(DepartmentGrossanlassChargeMovement $row): array
    {
        return [
            'id' => $row->getId(),
            'commitment_id' => $row->getCommitmentId(),
            'kind' => $row->getKind(),
            'quantity' => $row->getQuantity(),
            'occurred_at' => $row->getOccurredAt()->format(\DateTimeInterface::ATOM),
            'place_id' => $row->getPlaceId(),
            'place_name' => $row->getPlace()?->getName(),
            'einsatz_id' => $row->getEinsatzId(),
            'reverses_id' => $row->getReversesId(),
            'note' => $row->getNote(),
            'created_by_user_id' => $row->getCreatedByUserId(),
            'created_by_name' => $row->getCreatedByUser()?->getProfile()?->getDisplayName(),
            'created_at' => $row->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function resolveStoragePlace(Department $department, string $placeId): DepartmentGrossanlassPlace
    {
        $place = $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)->find($placeId);
        if (!$place instanceof DepartmentGrossanlassPlace
            || $place->getDepartmentId() !== $department->getId()
            || !$this->storageLagerplatz->isLagerplatzActiveForInbound($place)
        ) {
            throw new \InvalidArgumentException('Ungültiger Lagerstandort');
        }

        return $place;
    }

    private function syncProcurementLine(DepartmentGrossanlassCommitment $commitment): void
    {
        $line = $commitment->getProcurementLine();
        if ($line instanceof ActivityGrossanlassProcurementLine
            && $commitment->getOrigin() === DepartmentGrossanlassCommitment::ORIGIN_BUY
        ) {
            $this->syncLineReceived($line);
        }
    }

    /** Kompatibilität für Leser von `item_details.inbound_status`. */
    private function syncInboundStatus(DepartmentGrossanlassCommitment $commitment): void
    {
        $summary = $this->inboundSummaryFor($commitment);
        $details = $commitment->getItemDetails();
        $next = $summary['state'] === self::STATE_COMPLETE ? 'here' : 'expected';
        if (($details['inbound_status'] ?? null) !== $next) {
            $details['inbound_status'] = $next;
            $commitment->setItemDetails($details);
        }
    }

    /**
     * @return list<DepartmentGrossanlassChargeMovement>
     */
    private function movementsOf(DepartmentGrossanlassCommitment $commitment): array
    {
        return $this->entityManager->getRepository(DepartmentGrossanlassChargeMovement::class)
            ->findBy(['commitmentId' => $commitment->getId()], ['occurredAt' => 'ASC', 'createdAt' => 'ASC']);
    }

    private function findCommitment(Department $department, string $id): DepartmentGrossanlassCommitment
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)->find($id);
        if (!$row instanceof DepartmentGrossanlassCommitment || $row->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Zusage nicht gefunden');
        }

        return $row;
    }
}
