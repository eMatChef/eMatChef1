<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassProcurementLineWish;
use App\Entity\ActivityGrossanlassProcurementOrder;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassChargeMovement;
use App\Entity\DepartmentGrossanlassCommitment;
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

        $place = null;
        $placeId = trim((string) ($data['place_id'] ?? ''));
        if ($placeId !== '') {
            $place = $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)->find($placeId);
            if (!$place instanceof DepartmentGrossanlassPlace || $place->getDepartmentId() !== $department->getId()) {
                throw new \InvalidArgumentException('Ort nicht gefunden');
            }
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

        $movement = $this->entityManager->wrapInTransaction(function () use ($commitment, $data, $user, $place, $note, $occurredAt) {
            $this->lockCommitment($commitment);
            $movement = $this->recordReceived(
                $commitment,
                (int) ($data['quantity'] ?? 0),
                $user,
                $place,
                $note !== '' ? $note : null,
                $occurredAt,
            );
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
            ->select('m.commitmentId AS id', 'SUM(m.quantity) AS qty')
            ->from(DepartmentGrossanlassChargeMovement::class, 'm')
            ->where('m.commitmentId IN (:ids)')
            ->andWhere('m.kind = :kind')
            ->setParameter('ids', $commitmentIds)
            ->setParameter('kind', DepartmentGrossanlassChargeMovement::KIND_RECEIVED)
            ->groupBy('m.commitmentId')
            ->getQuery()
            ->getArrayResult();
        $out = [];
        foreach ($rows as $row) {
            $out[trim((string) $row['id'])] = (int) $row['qty'];
        }

        return $out;
    }

    /**
     * Kauf-Charge, die aus der Bestellung dieser Bedarfsposition entstanden ist.
     */
    public function buyChargeForLine(ActivityGrossanlassProcurementLine $line): ?DepartmentGrossanlassCommitment
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)->findOneBy([
            'procurementLineId' => $line->getId(),
            'origin' => DepartmentGrossanlassCommitment::ORIGIN_BUY,
        ]);

        return $row instanceof DepartmentGrossanlassCommitment ? $row : null;
    }

    /**
     * Bedarfsposition aus den Bewegungen der Kauf-Charge nachführen:
     * erhaltene Mengen je Wunsch und Status (bestellt / teilweise / erhalten).
     *
     * @param array<string, int>|null $allocations wish-id → Menge; null = bestehende Verteilung fortschreiben
     */
    public function syncLineReceived(ActivityGrossanlassProcurementLine $line, ?array $allocations = null): void
    {
        $charge = $this->buyChargeForLine($line);
        $total = $charge instanceof DepartmentGrossanlassCommitment ? $this->receivedQuantity($charge) : 0;

        $links = $this->entityManager->getRepository(ActivityGrossanlassProcurementLineWish::class)
            ->findBy(['procurementLineId' => $line->getId()], ['createdAt' => 'ASC']);
        $current = [];
        $caps = [];
        $byWish = [];
        foreach ($links as $link) {
            if (!$link instanceof ActivityGrossanlassProcurementLineWish) {
                continue;
            }
            $wishId = $link->getWishLineId();
            $byWish[$wishId] = $link;
            $current[$wishId] = $allocations[$wishId] ?? $link->getReceivedQuantity();
            $caps[$wishId] = $link->getWishLine()->getQuantity();
        }
        foreach (self::distributeReceived($current, $caps, $total) as $wishId => $qty) {
            $byWish[$wishId]->setReceivedQuantity($qty);
        }

        $order = $this->entityManager->getRepository(ActivityGrossanlassProcurementOrder::class)
            ->findOneBy(['procurementLineId' => $line->getId()]);
        if (!$order instanceof ActivityGrossanlassProcurementOrder) {
            return;
        }
        $status = $line->getStatus();
        if ($total > 0 && $total >= $line->getQuantity()) {
            $status = ActivityGrossanlassProcurementLine::STATUS_ERHALTEN;
        } elseif ($total > 0) {
            $status = ActivityGrossanlassProcurementLine::STATUS_TEILWEISE;
        } elseif (in_array($status, [
            ActivityGrossanlassProcurementLine::STATUS_TEILWEISE,
            ActivityGrossanlassProcurementLine::STATUS_ERHALTEN,
        ], true)) {
            $status = ActivityGrossanlassProcurementLine::STATUS_BESTELLT;
        }
        if ($status !== $line->getStatus()) {
            $line->setStatus($status);
            $line->touchUpdatedAt();
        }
    }

    /**
     * Gesamtmenge auf Wünsche verteilen. Bestehende Verteilung bleibt, solange sie
     * nicht grösser als die Gesamtmenge ist; der Rest füllt Wünsche der Reihe nach bis zur Wunschmenge.
     *
     * @param array<string, int> $current wish-id → bisher verteilt
     * @param array<string, int> $caps wish-id → Wunschmenge
     * @return array<string, int>
     */
    public static function distributeReceived(array $current, array $caps, int $total): array
    {
        $out = [];
        foreach ($current as $id => $qty) {
            $out[$id] = max(0, min((int) $qty, (int) ($caps[$id] ?? 0)));
        }
        if (array_sum($out) > $total) {
            $out = array_fill_keys(array_keys($current), 0);
        }
        $rest = $total - array_sum($out);
        foreach ($out as $id => $qty) {
            if ($rest <= 0) {
                break;
            }
            $room = max(0, (int) ($caps[$id] ?? 0) - $qty);
            $add = min($room, $rest);
            $out[$id] = $qty + $add;
            $rest -= $add;
        }

        return $out;
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
            'note' => $row->getNote(),
            'created_by_user_id' => $row->getCreatedByUserId(),
            'created_by_name' => $row->getCreatedByUser()?->getProfile()?->getDisplayName(),
            'created_at' => $row->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
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
