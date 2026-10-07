<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassProcurementLineWish;
use App\Entity\ActivityGrossanlassRound;
use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassChargeMovement;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassEinsatz;
use App\Entity\DepartmentGrossanlassPack;
use App\Entity\DepartmentGrossanlassPackLine;
use App\Entity\Group;
use App\Entity\User;
use App\Service\GroupHierarchyService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Mengenbasierter Materialfortschritt pro Materialposition (Wunsch oder Direkt-Bedarf).
 * Nur berechnet, nichts gespeichert. Quellen: Wunsch, Bedarfsposition, Charge,
 * Charge-Bewegungen, Einsätze, Packs.
 */
final class GrossanlassMaterialProgressService
{
    /** Mengenfelder in Ausgabereihenfolge. */
    public const QUANTITY_KEYS = [
        'required',
        'covered',
        'open',
        'received',
        'allocated',
        'packed',
        'ready_for_transport',
        'in_transit',
        'at_place',
        'return_open',
        'returned',
        'disposed',
    ];

    private const ACTIVE_EINSATZ = [
        DepartmentGrossanlassEinsatz::STATUS_PLANNED,
        DepartmentGrossanlassEinsatz::STATUS_PENDING,
        DepartmentGrossanlassEinsatz::STATUS_ISSUED,
    ];

    private const END_KINDS = [
        DepartmentGrossanlassChargeMovement::KIND_DISPOSED,
        DepartmentGrossanlassChargeMovement::KIND_CONSUMED,
        DepartmentGrossanlassChargeMovement::KIND_LOST,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GroupHierarchyService $hierarchy,
        private GrossanlassChargeMovementService $movements,
    ) {}

    /**
     * API: Fortschritt für den Anlass oder einen Bereich/ein Bauprojekt (inkl. Unterknoten).
     *
     * @return array<string, mixed>
     */
    public function overview(Department $department, User $user, ?string $groupId = null): array
    {
        $this->access->assertGrossanlassDepartment($department);
        $group = null;
        if ($groupId !== null && $groupId !== '') {
            $group = $this->entityManager->getRepository(Group::class)->find($groupId);
            if (!$group instanceof Group || $group->getDepartmentId() !== $department->getId()) {
                throw new \InvalidArgumentException('Bereich nicht gefunden');
            }
        }
        $allowed = $this->access->canSeeMaterialUebersicht($user, $department)
            || $this->access->canManagePlanung($user, $department)
            || ($group instanceof Group && $this->access->userIsMemberInRessortBranch($user, $department->getId(), $group));
        if (!$allowed) {
            throw new \RuntimeException('Keine Berechtigung für den Materialfortschritt');
        }

        return $this->forDepartment($department, $group);
    }

    /**
     * Ohne Berechtigungsprüfung — für Aufrufer, die schon geprüft haben (Bauprojekt-Briefing).
     *
     * @return array{items: list<array<string, mixed>>, groups: list<array<string, mixed>>}
     */
    public function forDepartment(Department $department, ?Group $group = null): array
    {
        $groupIds = $group instanceof Group
            ? array_fill_keys($this->hierarchy->expandWithDescendants($department->getId(), [$group->getId()]), true)
            : null;
        $groupNames = [];
        foreach ($this->entityManager->getRepository(Group::class)->findBy(['departmentId' => $department->getId()]) as $row) {
            if ($row instanceof Group) {
                $groupNames[$row->getId()] = $row->getName();
            }
        }

        $data = $this->load($department);
        $items = self::compute($data['items'], $data['line_items'], $data['charges'], $data['einsaetze'], $data['packs'], $data['end_movements']);

        $out = [];
        foreach ($items as $item) {
            if ($groupIds !== null && !isset($groupIds[$item['group_id']])) {
                continue;
            }
            $item['group_name'] = $groupNames[$item['group_id']] ?? '';
            $out[] = $item;
        }

        return ['items' => $out, 'groups' => self::groupTotals($out)];
    }

    /**
     * Reine Mengenrechnung.
     *
     * @param list<array{id: string, type: string, group_id: string, label: string, unit: string, required: int, line_id: ?string, covered_by_requester: bool}> $items
     * @param array<string, list<string>> $lineItems Bedarfsposition → Positions-IDs in Verknüpfungsreihenfolge
     * @param list<array{id: string, line_id: ?string, quantity: int, received: int}> $charges
     * @param list<array{id: string, item_id: ?string, commitment_id: ?string, qty: int, status: string, delivery: string, starts_at: string}> $einsaetze
     * @param list<array{einsatz_id: string, status: string, packed: int}> $packs
     * @param array<string, int> $endMovements Einsatz-ID → entsorgt/verbraucht/verloren
     * @return list<array<string, mixed>>
     */
    public static function compute(
        array $items,
        array $lineItems,
        array $charges,
        array $einsaetze,
        array $packs,
        array $endMovements = [],
    ): array {
        $byItem = [];
        foreach ($items as $item) {
            $byItem[$item['id']] = $item;
        }

        // 1. Ansprüche auf Chargen: zuerst Chargen der Bedarfsposition (verteilt auf ihre Wünsche),
        //    dann Einsätze auf andere Chargen. Ein Einsatz auf eine Charge der eigenen Position zählt nicht nochmals.
        $claims = [];
        $need = [];
        foreach ($items as $item) {
            $need[$item['id']] = $item['required'];
        }
        // Reihenfolge innerhalb einer Position: zuerst Chargen, auf die ein Einsatz der Position bucht,
        // dann physisch eingegangene, sonst wie geliefert. So zählt eine leere Zusage nicht vor vorhandener Ware.
        $booked = [];
        foreach ($einsaetze as $einsatz) {
            if ($einsatz['commitment_id'] !== null && $einsatz['item_id'] !== null) {
                $booked[$einsatz['commitment_id']] = true;
            }
        }
        $ordered = $charges;
        $position = array_flip(array_column($charges, 'id'));
        usort($ordered, static fn (array $a, array $b) => [
            isset($booked[$b['id']]),
            $b['received'],
            $position[$a['id']],
        ] <=> [
            isset($booked[$a['id']]),
            $a['received'],
            $position[$b['id']],
        ]);
        $chargeById = [];
        foreach ($ordered as $charge) {
            $chargeById[$charge['id']] = $charge;
            $claims[$charge['id']] = [];
            $lineId = $charge['line_id'];
            if ($lineId === null || !isset($lineItems[$lineId])) {
                continue;
            }
            $avail = max(0, $charge['quantity']);
            foreach ($lineItems[$lineId] as $itemId) {
                if ($avail <= 0) {
                    break;
                }
                $take = min($avail, max(0, $need[$itemId] ?? 0));
                if ($take > 0) {
                    $claims[$charge['id']][] = ['item' => $itemId, 'qty' => $take];
                    $need[$itemId] -= $take;
                    $avail -= $take;
                }
            }
        }
        $sortedEinsaetze = $einsaetze;
        usort($sortedEinsaetze, static fn (array $a, array $b) => strcmp($a['starts_at'], $b['starts_at']));
        foreach ($sortedEinsaetze as $einsatz) {
            $itemId = $einsatz['item_id'];
            $chargeId = $einsatz['commitment_id'];
            if ($itemId === null || !isset($byItem[$itemId]) || $chargeId === null || !isset($chargeById[$chargeId])) {
                continue;
            }
            $chargeLine = $chargeById[$chargeId]['line_id'];
            if ($chargeLine !== null && $chargeLine === $byItem[$itemId]['line_id']) {
                continue;
            }
            $claims[$chargeId][] = ['item' => $itemId, 'qty' => max(0, $einsatz['qty'])];
        }

        // 2. Gedeckt und eingegangen: eingegangene Menge einer Charge der Reihe nach auf ihre Ansprüche.
        $covered = [];
        $received = [];
        foreach ($claims as $chargeId => $rows) {
            $left = max(0, $chargeById[$chargeId]['received']);
            foreach ($rows as $claim) {
                $covered[$claim['item']] = ($covered[$claim['item']] ?? 0) + $claim['qty'];
                $got = min($left, $claim['qty']);
                $received[$claim['item']] = ($received[$claim['item']] ?? 0) + $got;
                $left -= $got;
            }
        }

        // 3. Einsätze und Packs.
        $packsByEinsatz = [];
        foreach ($packs as $pack) {
            $packsByEinsatz[$pack['einsatz_id']][] = $pack;
        }
        $flow = [];
        foreach ($einsaetze as $einsatz) {
            $itemId = $einsatz['item_id'];
            if ($itemId === null || !isset($byItem[$itemId])) {
                continue;
            }
            $f = $flow[$itemId] ?? array_fill_keys(
                ['allocated', 'packed', 'ready_for_transport', 'in_transit', 'at_place', 'return_open', 'returned', 'disposed'],
                0,
            );
            $f['disposed'] += $endMovements[$einsatz['id']] ?? 0;
            if ($einsatz['status'] === DepartmentGrossanlassEinsatz::STATUS_RETURNED) {
                $f['returned'] += max(0, $einsatz['qty']);
                $flow[$itemId] = $f;
                continue;
            }
            if (!in_array($einsatz['status'], self::ACTIVE_EINSATZ, true)) {
                $flow[$itemId] = $f;
                continue;
            }
            $f['allocated'] += max(0, $einsatz['qty']);
            $issued = $einsatz['status'] === DepartmentGrossanlassEinsatz::STATUS_ISSUED;
            $pickup = $einsatz['delivery'] === DepartmentGrossanlassEinsatz::DELIVERY_PICKUP;
            $out = 0;
            foreach ($packsByEinsatz[$einsatz['id']] ?? [] as $pack) {
                $qty = max(0, $pack['packed']);
                $f['packed'] += $qty;
                $state = self::packState($pack['status'], $einsatz['status'], $pickup);
                if ($state !== null) {
                    $f[$state] += $qty;
                }
                if ($state === 'in_transit' || $state === 'at_place') {
                    $out += $qty;
                }
            }
            if ($issued) {
                // Ausgegeben ohne gepackte Menge (alter Ablauf): ganze Einsatzmenge ist draussen.
                $f['return_open'] += $out > 0 ? $out : max(0, $einsatz['qty']);
            }
            $flow[$itemId] = $f;
        }

        $result = [];
        foreach ($items as $item) {
            $id = $item['id'];
            $required = max(0, $item['required']);
            $cov = $item['covered_by_requester'] ? $required : min($required, $covered[$id] ?? 0);
            $f = $flow[$id] ?? [];
            $result[] = [
                'id' => $id,
                'type' => $item['type'],
                'wish_line_id' => $item['type'] === 'wish' ? $id : null,
                'procurement_line_id' => $item['line_id'],
                'group_id' => $item['group_id'],
                'label' => $item['label'],
                'unit' => $item['unit'],
                'covered_by_requester' => $item['covered_by_requester'],
                'required' => $required,
                'covered' => $cov,
                'open' => max(0, $required - $cov),
                'received' => min($cov, $received[$id] ?? 0),
                'allocated' => $f['allocated'] ?? 0,
                'packed' => $f['packed'] ?? 0,
                'ready_for_transport' => $f['ready_for_transport'] ?? 0,
                'in_transit' => $f['in_transit'] ?? 0,
                'at_place' => $f['at_place'] ?? 0,
                'return_open' => $f['return_open'] ?? 0,
                'returned' => $f['returned'] ?? 0,
                'disposed' => $f['disposed'] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * Wo eine gepackte Menge steht. Selbstabholung braucht keine Fahrt-Frei: gepackt + frei = bereit,
     * ausgegeben = unterwegs, Ziel-Scan = vor Ort.
     */
    private static function packState(string $packStatus, string $einsatzStatus, bool $pickup): ?string
    {
        return match ($packStatus) {
            DepartmentGrossanlassPack::STATUS_AT_PLACE => 'at_place',
            DepartmentGrossanlassPack::STATUS_IN_TRANSIT => 'in_transit',
            DepartmentGrossanlassPack::STATUS_TRIP_RELEASED => 'ready_for_transport',
            default => !$pickup ? null : match ($einsatzStatus) {
                DepartmentGrossanlassEinsatz::STATUS_ISSUED => 'in_transit',
                DepartmentGrossanlassEinsatz::STATUS_PLANNED => 'ready_for_transport',
                default => null,
            },
        };
    }

    /**
     * Summen je Bereich und Einheit (Stk und m nicht vermischen).
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public static function groupTotals(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $gid = (string) $item['group_id'];
            $groups[$gid] ??= [
                'group_id' => $gid,
                'group_name' => $item['group_name'] ?? '',
                'items' => 0,
                'open_items' => 0,
                'totals' => [],
            ];
            $groups[$gid]['items']++;
            if ($item['open'] > 0) {
                $groups[$gid]['open_items']++;
            }
            $unit = (string) $item['unit'];
            $totals = $groups[$gid]['totals'][$unit] ?? array_fill_keys(self::QUANTITY_KEYS, 0);
            foreach (self::QUANTITY_KEYS as $key) {
                $totals[$key] += (int) $item[$key];
            }
            $groups[$gid]['totals'][$unit] = $totals;
        }

        return array_values($groups);
    }

    /**
     * @return array{
     *     items: list<array{id: string, type: string, group_id: string, label: string, unit: string, required: int, line_id: ?string, covered_by_requester: bool}>,
     *     line_items: array<string, list<string>>,
     *     charges: list<array{id: string, line_id: ?string, quantity: int, received: int}>,
     *     einsaetze: list<array{id: string, item_id: ?string, commitment_id: ?string, qty: int, status: string, delivery: string, starts_at: string}>,
     *     packs: list<array{einsatz_id: string, status: string, packed: int}>,
     *     end_movements: array<string, int>
     * }
     */
    private function load(Department $department): array
    {
        $departmentId = $department->getId();

        $wishes = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)
            ->createQueryBuilder('w')
            ->innerJoin('w.round', 'r')
            ->innerJoin('r.activity', 'a')
            ->where('a.departmentId = :departmentId')
            ->andWhere('w.status != :discarded')
            ->andWhere('r.formPurpose = :purpose')
            ->andWhere('w.wishKind IN (:kinds)')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('discarded', ActivityGrossanlassWishLine::STATUS_DISCARDED)
            ->setParameter('purpose', ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH)
            ->setParameter('kinds', [ActivityGrossanlassWishLine::KIND_MATERIAL, ActivityGrossanlassWishLine::KIND_BEIDES])
            ->orderBy('w.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $lines = $this->entityManager->getRepository(ActivityGrossanlassProcurementLine::class)
            ->findBy(['departmentId' => $departmentId], ['createdAt' => 'ASC']);
        $lineIds = [];
        foreach ($lines as $line) {
            if ($line instanceof ActivityGrossanlassProcurementLine) {
                $lineIds[] = $line->getId();
            }
        }
        $lineOfWish = [];
        $lineItems = [];
        if ($lineIds !== []) {
            $links = $this->entityManager->getRepository(ActivityGrossanlassProcurementLineWish::class)
                ->createQueryBuilder('lw')
                ->where('lw.procurementLineId IN (:ids)')
                ->setParameter('ids', $lineIds)
                ->orderBy('lw.createdAt', 'ASC')
                ->getQuery()
                ->getResult();
            foreach ($links as $link) {
                if ($link instanceof ActivityGrossanlassProcurementLineWish) {
                    $lineOfWish[$link->getWishLineId()] = $link->getProcurementLineId();
                    $lineItems[$link->getProcurementLineId()][] = $link->getWishLineId();
                }
            }
        }

        $items = [];
        foreach ($wishes as $wish) {
            if (!$wish instanceof ActivityGrossanlassWishLine) {
                continue;
            }
            $items[] = [
                'id' => $wish->getId(),
                'type' => 'wish',
                'group_id' => $wish->getGroupId(),
                'label' => $wish->getLabel(),
                'unit' => $wish->getQuantityUnit(),
                'required' => $wish->getQuantity(),
                'line_id' => $lineOfWish[$wish->getId()] ?? null,
                'covered_by_requester' => $wish->isSelfOrganized() || $wish->isEnoughOnHand(),
            ];
        }
        foreach ($lines as $line) {
            if (!$line instanceof ActivityGrossanlassProcurementLine
                || $line->getSource() !== ActivityGrossanlassProcurementLine::SOURCE_DIRECT
                || $line->getWishKind() === ActivityGrossanlassWishLine::KIND_FAHRZEUG
                || isset($lineItems[$line->getId()])
            ) {
                continue;
            }
            $items[] = [
                'id' => $line->getId(),
                'type' => 'line',
                'group_id' => $line->getGroupId(),
                'label' => $line->getLabel(),
                'unit' => $line->getQuantityUnit(),
                'required' => $line->getQuantity(),
                'line_id' => $line->getId(),
                'covered_by_requester' => false,
            ];
            $lineItems[$line->getId()] = [$line->getId()];
        }

        $commitments = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)
            ->findBy(['departmentId' => $departmentId, 'family' => DepartmentGrossanlassCommitment::FAMILY_MATERIAL], ['createdAt' => 'ASC']);
        $receivedBy = $this->movements->receivedByCommitment(array_map(
            static fn (DepartmentGrossanlassCommitment $row) => $row->getId(),
            $commitments,
        ));
        $charges = [];
        foreach ($commitments as $row) {
            $charges[] = [
                'id' => $row->getId(),
                'line_id' => $row->getProcurementLineId(),
                'quantity' => $row->getQuantity(),
                'received' => $receivedBy[$row->getId()] ?? 0,
            ];
        }

        $einsaetze = [];
        $einsatzIds = [];
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassEinsatz::class)
            ->findBy(['departmentId' => $departmentId, 'kind' => DepartmentGrossanlassEinsatz::KIND_EINSATZ]) as $row) {
            if (!$row instanceof DepartmentGrossanlassEinsatz) {
                continue;
            }
            $einsatzIds[] = $row->getId();
            $einsaetze[] = [
                'id' => $row->getId(),
                'item_id' => $row->getWishLineId(),
                'commitment_id' => $row->getCommitmentId(),
                'qty' => $row->getQty(),
                'status' => $row->getStatus(),
                'delivery' => $row->getDelivery(),
                'starts_at' => $row->getStartsAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        $packs = [];
        if ($einsatzIds !== []) {
            $packed = $this->entityManager->createQueryBuilder()
                ->select('p.id AS pack_id', 'p.einsatzId AS einsatz_id', 'p.status AS status', 'COALESCE(SUM(l.qtyPacked), 0) AS packed')
                ->from(DepartmentGrossanlassPack::class, 'p')
                ->leftJoin(DepartmentGrossanlassPackLine::class, 'l', 'WITH', 'l.packId = p.id')
                ->where('p.departmentId = :departmentId')
                ->setParameter('departmentId', $departmentId)
                ->groupBy('p.id', 'p.einsatzId', 'p.status')
                ->getQuery()
                ->getArrayResult();
            foreach ($packed as $row) {
                $packs[] = [
                    'einsatz_id' => trim((string) $row['einsatz_id']),
                    'status' => (string) $row['status'],
                    'packed' => (int) $row['packed'],
                ];
            }
        }

        $endMovements = [];
        if ($einsatzIds !== []) {
            $rows = $this->entityManager->createQueryBuilder()
                ->select('m.einsatzId AS einsatz_id', 'SUM(m.quantity) AS qty')
                ->from(DepartmentGrossanlassChargeMovement::class, 'm')
                ->where('m.departmentId = :departmentId')
                ->andWhere('m.einsatzId IS NOT NULL')
                ->andWhere('m.kind IN (:kinds)')
                ->setParameter('departmentId', $departmentId)
                ->setParameter('kinds', self::END_KINDS)
                ->groupBy('m.einsatzId')
                ->getQuery()
                ->getArrayResult();
            foreach ($rows as $row) {
                $endMovements[trim((string) $row['einsatz_id'])] = (int) $row['qty'];
            }
        }

        return [
            'items' => $items,
            'line_items' => $lineItems,
            'charges' => $charges,
            'einsaetze' => $einsaetze,
            'packs' => $packs,
            'end_movements' => $endMovements,
        ];
    }
}
