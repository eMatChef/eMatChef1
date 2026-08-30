<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassEinsatz;
use App\Entity\DepartmentGrossanlassPack;
use App\Entity\Group;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

final class GrossanlassUebersichtService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassUserCardService $cards,
        private GrossanlassCommitmentService $commitments,
        private GrossanlassPackService $packs,
        private GrossanlassPlaceService $places,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(Department $department, User $user): array
    {
        $this->assertSee($department, $user);
        $commitments = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)
            ->findBy(['departmentId' => $department->getId()], ['name' => 'ASC']);
        $einsaetze = $this->entityManager->getRepository(DepartmentGrossanlassEinsatz::class)
            ->findBy(['departmentId' => $department->getId()], ['startsAt' => 'ASC']);

        $serialized = array_map(fn (DepartmentGrossanlassEinsatz $row) => $this->serializeEinsatz($row), $einsaetze);
        $conflicts = $this->detectConflicts($einsaetze, $commitments);
        $conflictById = [];
        foreach ($conflicts as $conflict) {
            foreach ($conflict['einsatz_ids'] as $eid) {
                $conflictById[$eid] = $conflict['id'];
            }
        }
        foreach ($serialized as &$row) {
            if (isset($conflictById[$row['id']])) {
                $row['conflict_id'] = $conflictById[$row['id']];
            }
        }
        unset($row);

        $issued = [];
        foreach ($einsaetze as $row) {
            if ($row->getStatus() !== DepartmentGrossanlassEinsatz::STATUS_ISSUED || !$row->getCommitmentId()) {
                continue;
            }
            $cid = $row->getCommitmentId();
            $issued[$cid] = ($issued[$cid] ?? 0) + $row->getQty();
        }

        return [
            'einsaetze' => array_values(array_filter($serialized, fn (array $row) => $row['kind'] === DepartmentGrossanlassEinsatz::KIND_EINSATZ)),
            'orders' => array_values(array_filter($serialized, fn (array $row) => $row['kind'] === DepartmentGrossanlassEinsatz::KIND_ORDER)),
            'conflicts' => $conflicts,
            'issues' => $this->issuesFrom($einsaetze),
            'pack' => $this->packFrom($commitments),
            'returns' => $this->returnsFrom($commitments),
            'cards' => $this->cards->listCards($department),
            'wishes' => $this->wishTemplates($department, $commitments),
            'issued_by_object' => $issued,
            'places' => $this->places->list($department, $user),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createEinsatz(Department $department, User $user, array $data): array
    {
        $this->access->assertGrossanlassDepartment($department);
        $kind = (string) ($data['kind'] ?? DepartmentGrossanlassEinsatz::KIND_EINSATZ);
        if (!in_array($kind, [DepartmentGrossanlassEinsatz::KIND_EINSATZ, DepartmentGrossanlassEinsatz::KIND_ORDER], true)) {
            throw new \InvalidArgumentException('Ungültige Buchungsart');
        }
        $from = $this->parseDate($data['from'] ?? $data['fromIso'] ?? null);
        $to = $this->parseDate($data['to'] ?? $data['toIso'] ?? null);
        if ($from === null || $to === null || $to <= $from) {
            throw new \InvalidArgumentException('Zeitraum ist erforderlich');
        }

        $row = new DepartmentGrossanlassEinsatz();
        $row->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::EINSATZ,
            DepartmentGrossanlassEinsatz::class,
        ));
        $row->setDepartment($department);
        $row->setKind($kind);
        $row->setQty(max(1, (int) ($data['qty'] ?? 1)));
        $row->setStartsAt($from);
        $row->setEndsAt($to);
        $row->setWho(trim((string) ($data['who'] ?? '')));
        $row->setDelivery($this->parseDelivery($data['delivery'] ?? null));
        $row->setChauffeurUserId(isset($data['chauffeur_user_id']) ? trim((string) $data['chauffeur_user_id']) : null);
        if (!$row->isTrip()) {
            $row->setChauffeurUserId(null);
        }
        $row->setWishLineId(isset($data['wish_line_id']) ? trim((string) $data['wish_line_id']) : null);
        if (isset($data['destination_place_id'])) {
            $dest = trim((string) $data['destination_place_id']);
            $row->setDestinationPlaceId($dest !== '' ? $dest : null);
        }
        $group = null;
        $groupId = trim((string) ($data['group_id'] ?? ''));
        if ($groupId !== '') {
            $found = $this->entityManager->getRepository(Group::class)->find($groupId);
            if ($found instanceof Group && $found->getDepartmentId() === $department->getId()) {
                $group = $found;
                $row->setGroup($group);
            }
        }
        if (!$this->access->canSubmitEinsatz($user, $department, $group)) {
            throw new \RuntimeException('Keine Berechtigung für Einsätze');
        }
        $pending = !empty($data['pending']) || !empty($data['has_conflict']);
        if (!$this->access->canApproveEinsatz($user, $department)) {
            $pending = true;
        }
        $row->setStatus($pending
            ? DepartmentGrossanlassEinsatz::STATUS_PENDING
            : DepartmentGrossanlassEinsatz::STATUS_PLANNED);
        $row->setPlace(DepartmentGrossanlassEinsatz::PLACE_ASSIGNED);
        $row->setPackPhase($this->phaseFor($from));

        $commitmentId = trim((string) ($data['commitment_id'] ?? $data['object_id'] ?? ''));
        if ($commitmentId !== '') {
            $commitment = $this->findCommitment($department, $commitmentId);
            $row->setCommitment($commitment);
            if ($row->isTrip()
                && $kind === DepartmentGrossanlassEinsatz::KIND_EINSATZ
                && $row->getChauffeurUserId() === null
                && !$pending
            ) {
                throw new \InvalidArgumentException('Fahrauftrag braucht einen Chauffeur');
            }
        } elseif ($kind !== DepartmentGrossanlassEinsatz::KIND_ORDER) {
            throw new \InvalidArgumentException('Objekt ist erforderlich');
        }

        $this->entityManager->persist($row);
        $this->syncPlaceFromPack($row);
        $this->packs->ensureDefaultPack($row);
        $this->entityManager->flush();

        if (!$this->access->canSeeAnlassOverview($user, $department)) {
            return ['einsatz' => $this->serializeEinsatz($row)];
        }

        return $this->overview($department, $user);
    }

    /**
     * Bereichsleitung: Objekte, Orte, eigene Einsätze — ohne volle Übersicht.
     *
     * @return array<string, mixed>
     */
    public function submitBoard(Department $department, User $user): array
    {
        $this->access->assertGrossanlassDepartment($department);
        $groups = $this->entityManager->getRepository(Group::class)
            ->findBy(['departmentId' => $department->getId()]);
        $mine = [];
        foreach ($groups as $group) {
            if ($group instanceof Group && $this->access->canSubmitEinsatz($user, $department, $group)) {
                $mine[] = ['id' => $group->getId(), 'name' => $group->getName()];
            }
        }
        if ($mine === []) {
            throw new \RuntimeException('Keine Berechtigung, Einsätze einzureichen');
        }
        $ids = array_column($mine, 'id');
        $einsaetze = [];
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassEinsatz::class)
            ->findBy(['departmentId' => $department->getId()], ['startsAt' => 'ASC']) as $row) {
            if ($row instanceof DepartmentGrossanlassEinsatz && in_array($row->getGroupId(), $ids, true)) {
                $einsaetze[] = $this->serializeEinsatz($row);
            }
        }
        $objects = [];
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)
            ->findBy(['departmentId' => $department->getId()], ['name' => 'ASC']) as $commitment) {
            if ($commitment instanceof DepartmentGrossanlassCommitment) {
                $objects[] = [
                    'id' => $commitment->getId(),
                    'name' => $commitment->getName(),
                    'qty' => $commitment->getQuantity(),
                    'family' => $commitment->getFamily(),
                ];
            }
        }

        return [
            'groups' => $mine,
            'objects' => $objects,
            'places' => $this->places->list($department, $user),
            'einsaetze' => $einsaetze,
            'cards' => $this->cards->listCards($department),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateEinsatz(Department $department, User $user, string $id, array $data): array
    {
        $this->assertSee($department, $user);
        $row = $this->findEinsatz($department, $id);
        if (array_key_exists('packed', $data) || array_key_exists('pack_phase', $data)) {
            $this->assertAusgabe($department, $user);
        }
        if (array_key_exists('trip_released', $data)) {
            $this->access->assertGrossanlassDepartment($department);
            if (!$this->access->canReleaseTrip($user, $department)) {
                throw new \RuntimeException('Keine Berechtigung für Fahrt-Frei');
            }
        }
        if (array_key_exists('status', $data)) {
            $newStatus = (string) $data['status'];
            if ($row->getStatus() === DepartmentGrossanlassEinsatz::STATUS_PENDING
                && $newStatus === DepartmentGrossanlassEinsatz::STATUS_PLANNED
            ) {
                if (!$this->access->canApproveEinsatz($user, $department)) {
                    throw new \RuntimeException('Keine Berechtigung zur Einsatz-Freigabe');
                }
            }
            if ($newStatus === DepartmentGrossanlassEinsatz::STATUS_ISSUED) {
                $this->assertAusgabe($department, $user);
            }
        }
        if (array_key_exists('packed', $data)) {
            $this->packs->applyBooleanPacked($row, (bool) $data['packed']);
        }
        if (array_key_exists('pack_phase', $data)) {
            $row->setPackPhase((string) $data['pack_phase']);
        }
        if (array_key_exists('delivery', $data)) {
            $row->setDelivery($this->parseDelivery($data['delivery']));
        }
        if (array_key_exists('chauffeur_user_id', $data)) {
            $row->setChauffeurUserId($data['chauffeur_user_id'] !== null ? trim((string) $data['chauffeur_user_id']) : null);
        }
        if (array_key_exists('destination_place_id', $data)) {
            $row->setDestinationPlaceId($data['destination_place_id'] !== null ? trim((string) $data['destination_place_id']) : null);
        }
        if (array_key_exists('trip_released', $data)) {
            if (!$row->isTrip()) {
                throw new \InvalidArgumentException('Fahrt-Frei nur bei Checkbox Fahrt');
            }
            if (!empty($data['trip_released'])) {
                if (!$row->isPacked()) {
                    throw new \InvalidArgumentException('Fahrt-Frei erst nach Pack (Teilpack reicht)');
                }
                if ($row->getStatus() === DepartmentGrossanlassEinsatz::STATUS_PENDING) {
                    throw new \InvalidArgumentException('Einsatz ist noch nicht frei');
                }
                $row->setTripReleasedAt($row->getTripReleasedAt() ?? new \DateTime());
                $pack = $this->packs->ensureDefaultPack($row);
                if (!$pack->isTripReleased()) {
                    $this->packs->releaseTrip($department, $user, $pack->getId());
                }
            } else {
                $row->setTripReleasedAt(null);
            }
        }
        if (array_key_exists('status', $data)) {
            $status = (string) $data['status'];
            $allowed = [
                DepartmentGrossanlassEinsatz::STATUS_PLANNED,
                DepartmentGrossanlassEinsatz::STATUS_PENDING,
                DepartmentGrossanlassEinsatz::STATUS_ISSUED,
                DepartmentGrossanlassEinsatz::STATUS_RETURNED,
            ];
            if (!in_array($status, $allowed, true)) {
                throw new \InvalidArgumentException('Ungültiger Status');
            }
            $row->setStatus($status);
        }
        $this->syncPlaceFromPack($row);
        $this->entityManager->flush();

        return $this->overview($department, $user);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function issueEinsatz(Department $department, User $user, string $id, array $data): array
    {
        $this->assertAusgabe($department, $user);
        $row = $this->findEinsatz($department, $id);
        if ($row->getStatus() === DepartmentGrossanlassEinsatz::STATUS_PENDING) {
            throw new \InvalidArgumentException('Einsatz ist noch nicht frei');
        }
        $toUser = trim((string) ($data['user_id'] ?? ''));
        $vehicle = $row->getCommitment()?->getFamily() === DepartmentGrossanlassCommitment::FAMILY_VEHICLE;
        if ($row->isTrip()) {
            if (!$row->isTripReleased()) {
                throw new \InvalidArgumentException('Fahrt ist noch nicht frei');
            }
            if ($row->getChauffeurUserId() === null) {
                throw new \InvalidArgumentException('Fahrauftrag braucht einen Chauffeur');
            }
            if ($row->getDestinationPlaceId() === null) {
                throw new \InvalidArgumentException('Ziel-Ort fehlt');
            }
            $this->cards->assertMayDrive($department, $row->getChauffeurUserId(), $vehicle);
        } elseif ($vehicle && $toUser !== '') {
            $this->cards->assertMayDrive($department, $toUser, true);
        }
        $row->setStatus(DepartmentGrossanlassEinsatz::STATUS_ISSUED);
        $row->setPlace(DepartmentGrossanlassEinsatz::PLACE_OUT);
        $row->setIssuedToUserId($toUser !== '' ? $toUser : null);
        if ($toUser !== '') {
            foreach ($this->cards->listCards($department) as $card) {
                if (($card['user_id'] ?? '') === $toUser) {
                    $row->setWho((string) ($card['name'] ?? $row->getWho()));
                    break;
                }
            }
        }
        $this->syncPlaceFromPack($row);
        if ($row->isTrip()) {
            $pack = $this->packs->ensureDefaultPack($row);
            if ($pack->getStatus() !== DepartmentGrossanlassPack::STATUS_AT_PLACE) {
                $pack->setStatus(DepartmentGrossanlassPack::STATUS_IN_TRANSIT);
            }
        }
        $this->entityManager->flush();

        return $this->overview($department, $user);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateCommitmentOps(Department $department, User $user, string $commitmentId, array $data): array
    {
        $this->assertAusgabe($department, $user);
        $row = $this->findCommitment($department, $commitmentId);
        if (array_key_exists('packed', $data)) {
            $row->setPacked((bool) $data['packed']);
        }
        if (array_key_exists('pack_phase', $data)) {
            $phase = (string) $data['pack_phase'];
            $row->setPackPhase(in_array($phase, ['aufbau', 'anlass'], true) ? $phase : $row->getPackPhase());
        }
        if (array_key_exists('returned_to_firm', $data)) {
            $row->setReturnedToFirm((bool) $data['returned_to_firm']);
        }
        $this->entityManager->flush();

        return $this->overview($department, $user);
    }

    /**
     * @param list<DepartmentGrossanlassEinsatz> $einsaetze
     * @param list<DepartmentGrossanlassCommitment> $commitments
     * @return list<array<string, mixed>>
     */
    private function detectConflicts(array $einsaetze, array $commitments): array
    {
        $byObject = [];
        foreach ($einsaetze as $row) {
            if ($row->getKind() !== DepartmentGrossanlassEinsatz::KIND_EINSATZ) {
                continue;
            }
            if ($row->getStatus() === DepartmentGrossanlassEinsatz::STATUS_RETURNED) {
                continue;
            }
            $cid = $row->getCommitmentId() ?? '';
            if ($cid === '') {
                continue;
            }
            $byObject[$cid][] = $row;
        }
        $stock = [];
        $names = [];
        $unique = [];
        foreach ($commitments as $commitment) {
            $stock[$commitment->getId()] = max(1, $commitment->getQuantity());
            $names[$commitment->getId()] = $commitment->getName();
            $unique[$commitment->getId()] = $commitment->getFamily() === DepartmentGrossanlassCommitment::FAMILY_VEHICLE
                || $commitment->getQuantity() <= 1;
        }

        $out = [];
        $n = 1;
        foreach ($byObject as $cid => $rows) {
            $count = count($rows);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $rows[$i];
                    $b = $rows[$j];
                    if ($a->getEndsAt() <= $b->getStartsAt() || $b->getEndsAt() <= $a->getStartsAt()) {
                        continue;
                    }
                    $used = $a->getQty() + $b->getQty();
                    $cap = $stock[$cid] ?? 1;
                    if (!empty($unique[$cid]) || $used > $cap) {
                        $kind = !empty($unique[$cid]) ? 'unique_overlap' : 'quantity_overbook';
                        $name = $names[$cid] ?? $cid;
                        $out[] = [
                            'id' => 'cf-' . $n,
                            'kind' => $kind,
                            'object_id' => $cid,
                            'object_name' => $name,
                            'einsatz_ids' => [$a->getId(), $b->getId()],
                            'title' => $kind === 'unique_overlap'
                                ? $name . ': überlappende Einsätze'
                                : $name . ': Menge überbucht',
                            'text' => $kind === 'unique_overlap'
                                ? 'Zwei Einsätze wollen dasselbe Objekt zur gleichen Zeit.'
                                : 'Überlappende Einsätze brauchen mehr als den Bestand (' . $cap . ').',
                        ];
                        $n++;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @param list<DepartmentGrossanlassEinsatz> $einsaetze
     * @return list<array<string, mixed>>
     */
    private function issuesFrom(array $einsaetze): array
    {
        $today = (new \DateTime('today'))->format('Y-m-d');
        $tomorrow = (new \DateTime('tomorrow'))->format('Y-m-d');
        $out = [];
        foreach ($einsaetze as $row) {
            if ($row->getKind() !== DepartmentGrossanlassEinsatz::KIND_EINSATZ) {
                continue;
            }
            if ($row->getStatus() === DepartmentGrossanlassEinsatz::STATUS_RETURNED) {
                continue;
            }
            if ($row->getStatus() === DepartmentGrossanlassEinsatz::STATUS_PENDING) {
                continue;
            }
            $day = $row->getStartsAt()->format('Y-m-d');
            $bucket = $day === $today ? 'today' : ($day === $tomorrow ? 'tomorrow' : 'express');
            $commitment = $row->getCommitment();
            $out[] = [
                'id' => $row->getId(),
                'name' => $commitment?->getName() ?? $row->getWho(),
                'qty' => $row->getQty(),
                'family' => $commitment?->getFamily() ?? 'material',
                'place' => $row->getPlace(),
                'recipient_kind' => 'ressort',
                'recipient' => $row->getGroup()?->getName() ?? $row->getWho(),
                'driver_ok' => $commitment?->getFamily() !== DepartmentGrossanlassCommitment::FAMILY_VEHICLE || $row->getChauffeurUserId() !== null,
                'bucket' => $bucket,
                'when_label' => $row->getStartsAt()->format('H:i'),
                'planned_for' => $row->getGroup()?->getName() ?? '',
                'person_id' => $row->getIssuedToUserId() ?? $row->getChauffeurUserId(),
                'status' => $row->getStatus(),
            ];
        }

        return $out;
    }

    /**
     * @param list<DepartmentGrossanlassCommitment> $commitments
     * @return list<array<string, mixed>>
     */
    private function packFrom(array $commitments): array
    {
        $out = [];
        foreach ($commitments as $row) {
            $from = $row->getPresentFrom() ?? $row->getHandoverFrom();
            $phase = $row->getPackPhase() === 'aufbau'
                ? 'aufbau'
                : ($from instanceof \DateTime ? $this->phaseFor($from) : 'anlass');
            $out[] = [
                'id' => $row->getId(),
                'phase' => $phase,
                'name' => $row->getName(),
                'qty' => max(1, $row->getQuantity()),
                'packed' => $row->isPacked(),
            ];
        }

        return $out;
    }

    /**
     * @param list<DepartmentGrossanlassCommitment> $commitments
     * @return list<array<string, mixed>>
     */
    private function returnsFrom(array $commitments): array
    {
        $out = [];
        foreach ($commitments as $row) {
            if ($row->getOrigin() !== DepartmentGrossanlassCommitment::ORIGIN_LOAN) {
                continue;
            }
            $due = $row->getReturnFrom() ?? $row->getPresentTo();
            $out[] = [
                'id' => $row->getId(),
                'name' => $row->getName(),
                'firm' => $row->getSource(),
                'due' => $due?->format('d.m.Y') ?? '',
                'returned' => $row->isReturnedToFirm(),
            ];
        }

        return $out;
    }

    /**
     * @param list<DepartmentGrossanlassCommitment> $commitments
     * @return list<array<string, mixed>>
     */
    private function wishTemplates(Department $department, array $commitments): array
    {
        $lines = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)
            ->createQueryBuilder('w')
            ->innerJoin('w.round', 'r')
            ->innerJoin('r.activity', 'a')
            ->innerJoin('w.group', 'g')
            ->addSelect('g')
            ->where('a.departmentId = :departmentId')
            ->andWhere('w.status != :discarded')
            ->setParameter('departmentId', $department->getId())
            ->setParameter('discarded', ActivityGrossanlassWishLine::STATUS_DISCARDED)
            ->orderBy('w.createdAt', 'DESC')
            ->setMaxResults(80)
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($lines as $line) {
            if (!$line instanceof ActivityGrossanlassWishLine) {
                continue;
            }
            $match = $this->matchCommitment($line->getLabel(), $commitments);
            $from = $line->getValidFrom();
            $to = $line->getValidTo();
            $out[] = [
                'id' => $line->getId(),
                'label' => $line->getLabel(),
                'object_id' => $match?->getId() ?? '',
                'object_name' => $match?->getName() ?? $line->getLabel(),
                'kind' => ($match && $match->getQuantity() > 1) ? 'quantity' : 'unique',
                'qty' => $line->getQuantity(),
                'stock' => $match?->getQuantity() ?? $line->getQuantity(),
                'from' => $from->format(\DateTimeInterface::ATOM),
                'to' => $to->format(\DateTimeInterface::ATOM),
                'ressort' => $line->getGroup()->getName(),
                'group_id' => $line->getGroupId(),
                'who' => '',
            ];
        }

        return $out;
    }

    /**
     * @param list<DepartmentGrossanlassCommitment> $commitments
     */
    private function matchCommitment(string $label, array $commitments): ?DepartmentGrossanlassCommitment
    {
        $needle = mb_strtolower(trim($label));
        if ($needle === '') {
            return null;
        }
        $best = null;
        foreach ($commitments as $row) {
            $name = mb_strtolower($row->getName());
            if ($name === $needle) {
                return $row;
            }
            if (str_contains($needle, $name) || str_contains($name, $needle)) {
                $best = $row;
            }
        }

        return $best;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEinsatz(DepartmentGrossanlassEinsatz $row): array
    {
        $commitment = $row->getCommitment();
        $from = $row->getStartsAt();
        $to = $row->getEndsAt();

        return [
            'id' => $row->getId(),
            'kind' => $row->getKind(),
            'object_id' => $row->getCommitmentId() ?? '',
            'object_name' => $commitment?->getName() ?? $row->getWho(),
            'einsatz_kind' => ($commitment && $commitment->getQuantity() > 1) ? 'quantity' : 'unique',
            'qty' => $row->getQty(),
            'stock' => $commitment?->getQuantity() ?? $row->getQty(),
            'from' => $from->format(\DateTimeInterface::ATOM),
            'to' => $to->format(\DateTimeInterface::ATOM),
            'ressort' => $row->getGroup()?->getName() ?? '',
            'group_id' => $row->getGroupId(),
            'status' => $row->getStatus(),
            'who' => $row->getWho(),
            'place' => $row->getPlace(),
            'packed' => $row->isPacked(),
            'wish_line_id' => $row->getWishLineId(),
            'chauffeur_user_id' => $row->getChauffeurUserId(),
            'issued_to_user_id' => $row->getIssuedToUserId(),
            'delivery' => $row->getDelivery(),
            'trip_released' => $row->isTripReleased(),
            'trip_released_at' => $row->getTripReleasedAt()?->format(\DateTimeInterface::ATOM),
            'destination_place_id' => $row->getDestinationPlaceId(),
            'packs' => $this->packs->serializePacks($row),
            'bar_role' => 'einsatz',
        ];
    }

    private function parseDelivery(mixed $value): string
    {
        $raw = strtolower(trim((string) ($value ?? DepartmentGrossanlassEinsatz::DELIVERY_PICKUP)));
        if ($raw === 'trip' || $raw === 'fahrt') {
            return DepartmentGrossanlassEinsatz::DELIVERY_TRIP;
        }

        return DepartmentGrossanlassEinsatz::DELIVERY_PICKUP;
    }

    /**
     * Teilpack + MW-Freigabe (Fahrt frei bzw. Abholung bereit): Materialplatz schon leer,
     * auch vor starts_at und bevor jemand fährt.
     */
    private function syncPlaceFromPack(DepartmentGrossanlassEinsatz $row): void
    {
        if ($row->getStatus() === DepartmentGrossanlassEinsatz::STATUS_RETURNED) {
            $row->setPlace(DepartmentGrossanlassEinsatz::PLACE_LAGER);

            return;
        }
        if ($row->getStatus() === DepartmentGrossanlassEinsatz::STATUS_ISSUED) {
            $row->setPlace(DepartmentGrossanlassEinsatz::PLACE_OUT);

            return;
        }
        if ($row->getStatus() === DepartmentGrossanlassEinsatz::STATUS_PENDING) {
            $row->setPlace(DepartmentGrossanlassEinsatz::PLACE_ASSIGNED);

            return;
        }
        $leavesPlatz = $row->isPacked() && (
            !$row->isTrip()
            || $row->isTripReleased()
        );
        $row->setPlace($leavesPlatz
            ? DepartmentGrossanlassEinsatz::PLACE_OUT
            : DepartmentGrossanlassEinsatz::PLACE_ASSIGNED);
    }

    private function phaseFor(\DateTime $from): string
    {
        $hour = (int) $from->format('G');
        $w = (int) $from->format('N');

        return ($w <= 5 && $hour < 16) ? 'aufbau' : 'anlass';
    }

    private function parseDate(mixed $value): ?\DateTime
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTime) {
            return $value;
        }
        try {
            return new \DateTime((string) $value);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Ungültiges Datum');
        }
    }

    private function findCommitment(Department $department, string $id): DepartmentGrossanlassCommitment
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)->find($id);
        if (!$row instanceof DepartmentGrossanlassCommitment || $row->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Zusage nicht gefunden');
        }

        return $row;
    }

    private function findEinsatz(Department $department, string $id): DepartmentGrossanlassEinsatz
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassEinsatz::class)->find($id);
        if (!$row instanceof DepartmentGrossanlassEinsatz || $row->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Einsatz nicht gefunden');
        }

        return $row;
    }

    private function assertSee(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canSeeAnlassOverview($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für die Materialübersicht');
        }
    }

    private function assertAusgabe(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canOperateAusgabe($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Ausgabe und Pack');
        }
    }
}
