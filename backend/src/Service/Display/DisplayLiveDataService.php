<?php

declare(strict_types=1);

namespace App\Service\Display;

use App\Entity\Department;
use App\Entity\DepartmentDisplayScreen;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassDispatchEvent;
use App\Entity\DepartmentGrossanlassEinsatz;
use App\Entity\DepartmentGrossanlassPack;
use App\Entity\DepartmentGrossanlassPackLine;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\DepartmentGrossanlassVehicleNeed;
use App\Entity\DepartmentGrossanlassOrderEvent;
use App\Entity\DepartmentGrossanlassTask;
use App\Entity\DepartmentGrossanlassTaskEvent;
use App\Entity\DepartmentGrossanlassTour;
use App\Entity\DepartmentGrossanlassTourEvent;
use App\Entity\DepartmentGrossanlassTaskStep;
use App\Entity\Group;
use App\Entity\User;
use App\Service\Clock\BusinessClock;
use App\Service\Grossanlass\GrossanlassDispatchService;
use App\Service\Grossanlass\GrossanlassMaterialProgressService;
use App\Service\Grossanlass\GrossanlassStaffingCoverageService;
use App\Service\Grossanlass\GrossanlassTourService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lesende Live-Daten für die GA-Fachwidgets der Infoscreens. Es gibt keine eigene Geschäftslogik und keine Kopie der
 * Daten: Material kommt aus dem Materialfortschritt, Transporte und Touren aus der Disposition, Aufgaben und Ereignisse
 * direkt aus den GA-Tabellen. Das Department kommt ausschliesslich vom Screen (Gerät bzw. Verwalter-Vorschau), nie aus
 * dem Request. Es werden nur Anzeigefelder geliefert (keine Rechte-Flags, Codes oder E-Mail-Adressen).
 *
 * Fehlende Daten sind leere Listen, nie Platzhalter. Zeitbezüge (überfällig, dringend) nutzen die BusinessClock, damit
 * Demo-Grossanlässe mit ihrer simulierten Zeit konsistent bleiben.
 */
final class DisplayLiveDataService
{
    private const FEED_LIMIT = 30;
    private const URGENT_HOURS = 24;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassMaterialProgressService $materialProgress,
        private GrossanlassDispatchService $dispatch,
        private GrossanlassTourService $tours,
        private GrossanlassStaffingCoverageService $staffing,
        private BusinessClock $clock,
        private DisplayClockPayload $clockPayload,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forScreen(DepartmentDisplayScreen $screen): array
    {
        $department = $this->entityManager->getRepository(Department::class)->find($screen->getDepartmentId());
        if (!$department instanceof Department || !$department->isGrossanlass()) {
            return ['state' => 'unavailable'];
        }
        $now = $this->clock->now($department);

        $groups = $this->groups($department);
        $material = $this->material($department, $groups);
        $logistics = $this->logistics($department, $now, $groups);
        $tasks = $this->tasks($department, $groups, $now);
        $projects = $this->projects($groups, $tasks['rows'], $material['groups'], $this->helpers($department));

        return [
            'state' => 'ready',
            'generated_at' => $now->format('Y-m-d\TH:i:s'),
            'clock' => $this->clockPayload->for($department),
            'groups' => array_values(array_filter($groups, static fn (array $g) => !$g['archived'])),
            'material' => $material,
            'logistics' => $logistics,
            'tasks' => $tasks['rows'],
            'task_counts' => $tasks['counts'],
            'projects' => $projects,
            'feed' => $this->feed($department, $groups, $logistics['need_groups']),
            'warnings' => $this->warnings($tasks['rows'], $material['groups'], $logistics, $groups),
        ];
    }

    /**
     * Bereiche und Bauprojekte mit ihrer Zuordnung aus der bestehenden GA-Hierarchie (Ressort › Bereich › Teilbereich ›
     * Auftrag, `Group.grossanlassKind`). Dieselben Namen filtern die Widgets: `ressort` und `bereich` sind die Namen der
     * nächsten Vorfahren (oder der Gruppe selbst) dieser Art; fehlt die Art in der Kette, bleibt der Wert leer.
     *
     * @return array<string, array{id: string, name: string, kind: ?string, ressort: string, bereich: string, origin: list<string>, window_end: ?string, archived: bool}>
     */
    private function groups(Department $department): array
    {
        $rows = [];
        foreach ($this->entityManager->getRepository(Group::class)->findBy(['departmentId' => $department->getId()]) as $group) {
            if ($group instanceof Group) {
                $rows[$group->getId()] = $group;
            }
        }
        $out = [];
        foreach ($rows as $id => $group) {
            $ressort = '';
            $bereich = '';
            $origin = [];
            $cursor = $group;
            for ($depth = 0; $cursor !== null && $depth < 12; ++$depth) {
                array_unshift($origin, $cursor->getName());
                if ($ressort === '' && $cursor->getGrossanlassKind() === Group::GROSSANLASS_KIND_RESSORT) {
                    $ressort = $cursor->getName();
                }
                if ($bereich === '' && $cursor->getGrossanlassKind() === Group::GROSSANLASS_KIND_BEREICH) {
                    $bereich = $cursor->getName();
                }
                $parentId = $cursor->getParentId();
                $cursor = $parentId !== null ? ($rows[$parentId] ?? null) : null;
            }
            $out[$id] = [
                'id' => $id,
                'name' => $group->getName(),
                'kind' => $group->getGrossanlassKind(),
                'ressort' => $ressort,
                'bereich' => $bereich,
                'origin' => $origin,
                'window_end' => $group->getWindowEnd()?->format('Y-m-d\TH:i:s'),
                'archived' => $group->isArchived(),
            ];
        }

        return $out;
    }

    /**
     * Materialstand je Bereich/Bauprojekt (gleiche Rechnung wie die GA-Materialübersicht).
     *
     * @param array<string, array<string, mixed>> $groups
     *
     * @return array{groups: list<array<string, mixed>>, items: list<array<string, mixed>>, packs: list<array<string, mixed>>, missing: list<array<string, mixed>>, incoming: list<array<string, mixed>>}
     */
    private function material(Department $department, array $groups): array
    {
        $progress = $this->materialProgress->forDepartment($department);
        $rows = [];
        foreach ($progress['groups'] as $g) {
            $meta = $groups[$g['group_id']] ?? null;
            if ($meta === null || $meta['archived']) {
                continue;
            }
            $rows[] = ['group_id' => $g['group_id'], 'group_name' => $meta['name'], 'items' => $g['items'], 'open_items' => $g['open_items'], 'totals' => $g['totals']];
        }
        $missing = [];
        $incoming = [];
        $items = [];
        foreach ($progress['items'] as $item) {
            $meta = $groups[$item['group_id']] ?? null;
            if ($meta === null || $meta['archived']) {
                continue;
            }
            $items[] = [
                'id' => $item['id'],
                'group_id' => $item['group_id'],
                'label' => $item['label'],
                'unit' => $item['unit'],
                'required' => $item['required'],
                'covered' => $item['covered'],
                'open' => $item['open'],
                'received' => $item['received'],
                'allocated' => $item['allocated'],
                'packed' => $item['packed'],
                'ready_for_transport' => $item['ready_for_transport'],
                'in_transit' => $item['in_transit'],
                'at_place' => $item['at_place'],
            ];
            $line = [
                'label' => $item['label'],
                'unit' => $item['unit'],
                'group_id' => $item['group_id'],
                'group_name' => $meta['name'],
                'required' => $item['required'],
                'covered' => $item['covered'],
                'open' => $item['open'],
                'received' => $item['received'],
                'packed' => $item['packed'],
            ];
            if ($item['open'] > 0) {
                $missing[] = $line;
            }
            if ($item['covered'] > $item['received'] && $item['open'] === 0) {
                $line['expected'] = $item['covered'] - $item['received'];
                $incoming[] = $line;
            }
        }

        return ['groups' => $rows, 'items' => $items, 'packs' => $this->packs($department, $groups), 'missing' => $missing, 'incoming' => $incoming];
    }

    /**
     * Helferbedarf und Zuteilung je Bauprojekt aus der bestehenden Einsatzplanung (Deckung wie in der Helferplanung).
     *
     * @return array<string, array{need: int, assigned: int}>
     */
    private function helpers(Department $department): array
    {
        $out = [];
        foreach ($this->staffing->overview($department)['needs'] as $need) {
            $g = &$out[$need['group_id']];
            $g ??= ['need' => 0, 'assigned' => 0];
            $g['need'] += $need['required_count'];
            $g['assigned'] += min($need['assigned_count'], $need['required_count']);
            unset($g);
        }

        return $out;
    }

    /**
     * Packs (Paletten) der Einsätze mit Code, Stand, Bauprojekt und Ziel; Zeilen nur als Beschriftung.
     *
     * @param array<string, array{id: string, name: string, kind: ?string, ressort: string, bereich: string, origin: list<string>, window_end: ?string, archived: bool}> $groups
     *
     * @return list<array<string, mixed>>
     */
    private function packs(Department $department, array $groups): array
    {
        $packs = $this->entityManager->getRepository(DepartmentGrossanlassPack::class)->findBy(['departmentId' => $department->getId()], ['sortOrder' => 'ASC'], 300);
        $labels = [];
        $ids = array_map(static fn (DepartmentGrossanlassPack $p) => $p->getId(), array_filter($packs, static fn ($p) => $p instanceof DepartmentGrossanlassPack));
        if ($ids !== []) {
            foreach ($this->entityManager->getRepository(DepartmentGrossanlassPackLine::class)->findBy(['packId' => $ids]) as $line) {
                if ($line instanceof DepartmentGrossanlassPackLine) {
                    $labels[$line->getPackId()][] = ['label' => $line->getLabel(), 'qty' => $line->getQtyPacked() > 0 ? $line->getQtyPacked() : $line->getQtyNeeded()];
                }
            }
        }
        $places = [];
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassPlace::class)->findBy(['departmentId' => $department->getId()]) as $place) {
            if ($place instanceof DepartmentGrossanlassPlace) {
                $places[$place->getId()] = $place->getName();
            }
        }
        $out = [];
        foreach ($packs as $pack) {
            if (!$pack instanceof DepartmentGrossanlassPack) {
                continue;
            }
            $einsatz = $pack->getEinsatz();
            $groupId = $einsatz->getGroupId();
            if ($groupId === null || !isset($groups[$groupId]) || $groups[$groupId]['archived']) {
                continue;
            }
            $lines = $labels[$pack->getId()] ?? [];
            $out[] = [
                'id' => $pack->getId(),
                'code' => $pack->getPublicCode(),
                'status' => $pack->getStatus(),
                'group_id' => $groupId,
                'title' => $lines !== [] ? $lines[0]['label'] : '',
                'lines' => $lines,
                'target' => $einsatz->getDestinationPlaceId() !== null ? ($places[$einsatz->getDestinationPlaceId()] ?? '') : '',
            ];
        }

        return $out;
    }

    /**
     * Transporte (Fahraufträge, Fahrzeugbedarf) und Touren der Disposition.
     *
     * @param array<string, array<string, mixed>> $groups
     *
     * @return array<string, mixed>
     */
    private function logistics(Department $department, \DateTime $now, array $groups): array
    {
        $needs = $this->dispatch->needMap($department);
        $needGroups = $this->needGroups($needs);
        $tourRows = [];
        $activeTours = 0;
        foreach ($this->tours->tours($department) as $tour) {
            $row = $this->tours->serialize($tour, $needs);
            if ($row['closed_at'] !== null) {
                continue;
            }
            ++$activeTours;
            $tourGroups = [];
            foreach ($row['need_ids'] as $needId) {
                if (isset($needGroups[$needId])) {
                    $tourGroups[$needGroups[$needId]] = true;
                }
            }
            $tourRows[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'status' => $row['status'],
                'confirmed' => $row['confirmed'],
                'vehicle' => $row['vehicle_id'] !== null ? $this->commitmentName($row['vehicle_id']) : '',
                'trailer' => (string) ($row['trailer'] ?? ''),
                'driver' => $this->userName($row['chauffeur_user_id']),
                'starts_at' => $row['starts_at'],
                'ends_at' => $row['ends_at'],
                'stops' => array_map(fn (array $s) => [
                    'label' => $s['label'],
                    'kind' => $s['kind'],
                    'planned_at' => $s['planned_at'],
                    'done' => $s['need_id'] !== null && ($needs[$s['need_id']]['status'] ?? '') === GrossanlassDispatchService::STATUS_DONE,
                ], $row['stops']),
                'need_count' => \count($row['need_ids']),
                'group_ids' => array_keys($tourGroups),
                'has_conflict' => $row['conflicts'] !== [],
            ];
        }
        $needRows = [];
        $counts = ['open' => 0, 'planned' => 0, 'underway' => 0, 'done' => 0, 'urgent' => 0];
        $horizon = (clone $now)->modify('+' . self::URGENT_HOURS . ' hours')->format('Y-m-d\TH:i:s');
        foreach ($needs as $need) {
            $status = (string) $need['status'];
            $counts[$status] = ($counts[$status] ?? 0) + 1;
            $urgent = $status === GrossanlassDispatchService::STATUS_OPEN && (string) $need['starts_at'] <= $horizon;
            if ($urgent) {
                ++$counts['urgent'];
            }
            if ($status === GrossanlassDispatchService::STATUS_DONE) {
                continue;
            }
            $needRows[] = [
                'id' => $need['id'],
                'kind' => $need['vehicle_need_id'] !== null ? 'vehicle' : 'transport',
                'title' => $need['title'],
                'from' => $need['from'] ?? '',
                'to' => $need['to'] ?? '',
                'target' => $need['target'] ?? '',
                'cargo' => $need['cargo'] ?? '',
                'starts_at' => $need['starts_at'],
                'ends_at' => $need['ends_at'],
                'status' => $status,
                'ready' => (bool) ($need['ready'] ?? false),
                'released' => (bool) ($need['released'] ?? false),
                'urgent' => $urgent,
                'tour_id' => $need['tour_id'] ?? null,
                'group_id' => $needGroups[$need['id']] ?? null,
            ];
        }
        usort($needRows, static fn (array $a, array $b) => strcmp($a['starts_at'], $b['starts_at']));

        return [
            'counts' => $counts,
            'needs' => $needRows,
            'tours' => $tourRows,
            'active_tours' => $activeTours,
            'vehicles' => $this->commitmentCount(DepartmentGrossanlassCommitment::FAMILY_VEHICLE, $department),
            'drivers' => $this->driverCount($department),
            'need_groups' => $needGroups,
        ];
    }

    /**
     * Bauprojekt/Bereich je Transportbedarf aus den bestehenden Einsätzen bzw. dem Fahrzeugbedarf (nicht ableitbar = fehlt).
     *
     * @param array<string, array<string, mixed>> $needs
     *
     * @return array<string, string>
     */
    private function needGroups(array $needs): array
    {
        $out = [];
        $byEinsatz = [];
        $byVehicle = [];
        $einsatzIds = [];
        $vehicleIds = [];
        foreach ($needs as $id => $need) {
            if (!empty($need['einsatz_id'])) {
                $einsatzIds[$id] = (string) $need['einsatz_id'];
            } elseif (!empty($need['vehicle_need_id'])) {
                $vehicleIds[$id] = (string) $need['vehicle_need_id'];
            }
        }
        if ($einsatzIds !== []) {
            foreach ($this->entityManager->getRepository(DepartmentGrossanlassEinsatz::class)->findBy(['id' => array_values($einsatzIds)]) as $row) {
                if ($row instanceof DepartmentGrossanlassEinsatz) {
                    $groupId = $row->getGroupId() ?? $row->getCommitment()?->getProcurementLine()?->getGroup()?->getId();
                    if ($groupId !== null) {
                        $byEinsatz[$row->getId()] = $groupId;
                    }
                }
            }
            foreach ($einsatzIds as $needId => $einsatzId) {
                if (isset($byEinsatz[$einsatzId])) {
                    $out[$needId] = $byEinsatz[$einsatzId];
                }
            }
        }
        if ($vehicleIds !== []) {
            foreach ($this->entityManager->getRepository(DepartmentGrossanlassVehicleNeed::class)->findBy(['id' => array_values($vehicleIds)]) as $row) {
                if ($row instanceof DepartmentGrossanlassVehicleNeed) {
                    $byVehicle[$row->getId()] = $row->getGroupId();
                }
            }
            foreach ($vehicleIds as $needId => $vehicleNeedId) {
                if (isset($byVehicle[$vehicleNeedId])) {
                    $out[$needId] = $byVehicle[$vehicleNeedId];
                }
            }
        }

        return $out;
    }

    private function driverCount(Department $department): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM department_grossanlass_user_card WHERE department_id = ? AND may_drive = TRUE', [$department->getId()]);
    }

    /**
     * @param array<string, array<string, mixed>> $groups
     *
     * @return array{rows: list<array<string, mixed>>, counts: array<string, int>}
     */
    private function tasks(Department $department, array $groups, \DateTime $now): array
    {
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassTask::class)
            ->findBy(['departmentId' => $department->getId(), 'isDefault' => false], ['startsAt' => 'ASC', 'sortOrder' => 'ASC']);
        $ids = array_map(static fn (DepartmentGrossanlassTask $t) => $t->getId(), array_filter($rows, static fn ($t) => $t instanceof DepartmentGrossanlassTask));
        $steps = [];
        if ($ids !== []) {
            foreach ($this->entityManager->getRepository(DepartmentGrossanlassTaskStep::class)->findBy(['taskId' => $ids]) as $step) {
                if ($step instanceof DepartmentGrossanlassTaskStep) {
                    $steps[$step->getTaskId()]['total'] = ($steps[$step->getTaskId()]['total'] ?? 0) + 1;
                    $steps[$step->getTaskId()]['done'] = ($steps[$step->getTaskId()]['done'] ?? 0) + ($step->isDone() ? 1 : 0);
                }
            }
        }
        $counts = ['open' => 0, 'progress' => 0, 'review' => 0, 'done' => 0, 'blocked' => 0, 'overdue' => 0];
        $out = [];
        foreach ($rows as $task) {
            if (!$task instanceof DepartmentGrossanlassTask) {
                continue;
            }
            $meta = $groups[$task->getGroupId()] ?? null;
            if ($meta === null || $meta['archived']) {
                continue;
            }
            $start = $task->getStartsAt();
            $end = $start !== null && $task->getDurationMinutes() !== null ? (clone $start)->modify('+' . $task->getDurationMinutes() . ' minutes') : $start;
            $status = $task->getStatus();
            $overdue = $status !== DepartmentGrossanlassTask::STATUS_DONE && $end !== null && $end < $now;
            ++$counts[$status];
            $counts['blocked'] += $task->isBlocked() ? 1 : 0;
            $counts['overdue'] += $overdue ? 1 : 0;
            $total = $steps[$task->getId()]['total'] ?? 0;
            $done = $steps[$task->getId()]['done'] ?? 0;
            $out[] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'group_id' => $task->getGroupId(),
                'group_name' => $meta['name'],
                'ressort' => $meta['ressort'],
                'bereich' => $meta['bereich'],
                'origin' => $meta['origin'],
                'status' => $status,
                'starts_at' => $start?->format('Y-m-d\TH:i:s'),
                'ends_at' => $end?->format('Y-m-d\TH:i:s'),
                'steps_total' => $total,
                'steps_done' => $done,
                'blocked' => $task->isBlocked(),
                'blocked_reason' => $task->getBlockedReason(),
                'overdue' => $overdue,
            ];
        }

        return ['rows' => $out, 'counts' => $counts];
    }

    /**
     * @param array<string, array<string, mixed>> $groups
     * @param list<array<string, mixed>> $tasks
     * @param list<array<string, mixed>> $materialGroups
     * @param array<string, array{need: int, assigned: int}> $helpers
     *
     * @return list<array<string, mixed>>
     */
    private function projects(array $groups, array $tasks, array $materialGroups, array $helpers): array
    {
        $byGroup = [];
        foreach ($tasks as $task) {
            $g = &$byGroup[$task['group_id']];
            $g ??= ['total' => 0, 'done' => 0, 'progress' => 0, 'open' => 0, 'review' => 0, 'blocked' => 0, 'overdue' => 0, 'next' => null, 'current' => null, 'end' => null];
            ++$g['total'];
            ++$g[$task['status']];
            $g['blocked'] += $task['blocked'] ? 1 : 0;
            $g['overdue'] += $task['overdue'] ? 1 : 0;
            if ($task['status'] === DepartmentGrossanlassTask::STATUS_PROGRESS && $g['current'] === null) {
                $g['current'] = $task['title'];
            }
            if ($task['status'] === DepartmentGrossanlassTask::STATUS_OPEN && $g['next'] === null) {
                $g['next'] = $task['title'];
            }
            if ($task['ends_at'] !== null && ($g['end'] === null || $task['ends_at'] > $g['end'])) {
                $g['end'] = $task['ends_at'];
            }
            unset($g);
        }
        $materialByGroup = [];
        foreach ($materialGroups as $m) {
            $materialByGroup[$m['group_id']] = $m;
        }
        $out = [];
        foreach ($byGroup as $groupId => $g) {
            $needed = 0;
            $packed = 0;
            foreach ($materialByGroup[$groupId]['totals'] ?? [] as $totals) {
                $needed += $totals['required'];
                $packed += $totals['packed'];
            }
            $out[] = [
                'group_id' => $groupId,
                'name' => $groups[$groupId]['name'] ?? '',
                'tasks' => ['total' => $g['total'], 'open' => $g['open'], 'progress' => $g['progress'], 'review' => $g['review'], 'done' => $g['done'], 'blocked' => $g['blocked'], 'overdue' => $g['overdue']],
                'progress' => $g['total'] > 0 ? (int) round($g['done'] / $g['total'] * 100) : 0,
                'material' => $needed > 0 ? ['required' => $needed, 'packed' => $packed, 'open_items' => $materialByGroup[$groupId]['open_items'] ?? 0] : null,
                'helpers' => $helpers[$groupId] ?? ['need' => 0, 'assigned' => 0],
                'current_task' => $g['current'],
                'next_task' => $g['next'],
                'deadline' => $g['end'],
            ];
        }
        usort($out, static fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * Ereignisse aus den tatsächlichen GA-Protokollen (Aufgaben, Bauauftrag-Abnahme, Disposition). Ohne Personennamen.
     *
     * @param array<string, array<string, mixed>> $groups
     * @param array<string, string> $needGroups
     *
     * @return list<array<string, mixed>>
     */
    private function feed(Department $department, array $groups, array $needGroups): array
    {
        $events = [];
        $taskEvents = $this->entityManager->createQueryBuilder()
            ->select('e', 't')
            ->from(DepartmentGrossanlassTaskEvent::class, 'e')
            ->innerJoin(DepartmentGrossanlassTask::class, 't', 'WITH', 't.id = e.taskId')
            ->where('t.departmentId = :d')
            ->setParameter('d', $department->getId())
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults(self::FEED_LIMIT)
            ->getQuery()
            ->getResult();
        for ($i = 0; $i + 1 < \count($taskEvents); $i += 2) {
            $e = $taskEvents[$i];
            $t = $taskEvents[$i + 1];
            if ($e instanceof DepartmentGrossanlassTaskEvent && $t instanceof DepartmentGrossanlassTask) {
                $events[] = ['id' => 'te-' . $e->getId(), 'at' => $e->getCreatedAt()->format('Y-m-d\TH:i:s'), 'area' => 'task', 'kind' => 'task_' . $e->getType(), 'group_id' => $t->getGroupId(), 'subject' => $t->getTitle(), 'detail' => $groups[$t->getGroupId()]['name'] ?? '', 'severity' => $e->getType() === DepartmentGrossanlassTaskEvent::RETURNED ? 'warning' : ($e->getType() === DepartmentGrossanlassTaskEvent::ACCEPTED ? 'success' : 'info')];
            }
        }
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassOrderEvent::class)->findBy(['departmentId' => $department->getId()], ['createdAt' => 'DESC'], self::FEED_LIMIT) as $e) {
            if ($e instanceof DepartmentGrossanlassOrderEvent) {
                $events[] = ['id' => 'oe-' . $e->getId(), 'at' => $e->getCreatedAt()->format('Y-m-d\TH:i:s'), 'area' => 'task', 'kind' => 'order_' . $e->getType(), 'group_id' => $e->getGroupId(), 'subject' => $groups[$e->getGroupId()]['name'] ?? '', 'detail' => '', 'severity' => $e->getType() === DepartmentGrossanlassOrderEvent::RETURNED ? 'warning' : ($e->getType() === DepartmentGrossanlassOrderEvent::ACCEPTED ? 'success' : 'info')];
            }
        }
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassDispatchEvent::class)->findBy(['departmentId' => $department->getId()], ['createdAt' => 'DESC'], self::FEED_LIMIT) as $e) {
            if ($e instanceof DepartmentGrossanlassDispatchEvent) {
                $events[] = ['id' => 'de-' . $e->getId(), 'at' => $e->getCreatedAt()->format('Y-m-d\TH:i:s'), 'area' => 'logistics', 'kind' => 'dispatch_' . $e->getAction(), 'group_id' => $needGroups[$e->getSubjectId()] ?? null, 'subject' => '', 'detail' => '', 'severity' => $e->getAction() === 'not_executed' ? 'warning' : 'info'];
            }
        }
        // Touren: Bestätigung, Rücknahme der Bestätigung und Abschluss aus dem Tourprotokoll (das Anlegen ist noch keine Meldung wert)
        $tourEvents = $this->entityManager->createQueryBuilder()
            ->select('e', 't')
            ->from(DepartmentGrossanlassTourEvent::class, 'e')
            ->innerJoin(DepartmentGrossanlassTour::class, 't', 'WITH', 't.id = e.tourId')
            ->where('t.departmentId = :d')
            ->andWhere('e.action IN (:actions)')
            ->setParameter('d', $department->getId())
            ->setParameter('actions', ['confirmed', 'unconfirmed', 'closed'])
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults(self::FEED_LIMIT * 2)
            ->getQuery()
            ->getResult();
        for ($i = 0; $i + 1 < \count($tourEvents); $i += 2) {
            $e = $tourEvents[$i];
            $t = $tourEvents[$i + 1];
            if ($e instanceof DepartmentGrossanlassTourEvent && $t instanceof DepartmentGrossanlassTour) {
                $events[] = ['id' => 'tr-' . $e->getId(), 'at' => $e->getCreatedAt()->format('Y-m-d\TH:i:s'), 'area' => 'logistics', 'kind' => 'tour_' . $e->getAction(), 'group_id' => null, 'subject' => $t->getName(), 'detail' => '', 'severity' => $e->getAction() === 'unconfirmed' ? 'warning' : 'info'];
            }
        }
        usort($events, static fn (array $a, array $b) => strcmp($b['at'], $a['at']));

        return \array_slice($events, 0, self::FEED_LIMIT);
    }

    /**
     * @param list<array<string, mixed>> $tasks
     * @param list<array<string, mixed>> $materialGroups
     * @param array<string, mixed> $logistics
     * @param array<string, array<string, mixed>> $groups
     *
     * @return list<array<string, mixed>>
     */
    private function warnings(array $tasks, array $materialGroups, array $logistics, array $groups): array
    {
        $out = [];
        foreach ($tasks as $task) {
            if ($task['blocked']) {
                $out[] = ['level' => 'critical', 'kind' => 'task_blocked', 'group_id' => $task['group_id'], 'title' => $task['title'], 'detail' => (string) ($task['blocked_reason'] ?? $task['group_name'])];
            } elseif ($task['overdue']) {
                $out[] = ['level' => 'warning', 'kind' => 'task_overdue', 'group_id' => $task['group_id'], 'title' => $task['title'], 'detail' => $task['group_name']];
            }
        }
        foreach ($materialGroups as $m) {
            if ($m['open_items'] > 0) {
                $out[] = ['level' => 'warning', 'kind' => 'material_missing', 'group_id' => $m['group_id'], 'title' => $groups[$m['group_id']]['name'] ?? '', 'detail' => (string) $m['open_items']];
            }
        }
        foreach ($logistics['needs'] as $need) {
            if ($need['urgent']) {
                $out[] = ['level' => 'warning', 'kind' => 'transport_urgent', 'group_id' => $need['group_id'], 'title' => $need['title'], 'detail' => $need['to']];
            }
        }
        foreach ($logistics['tours'] as $tour) {
            if ($tour['has_conflict']) {
                $out[] = ['level' => 'critical', 'kind' => 'tour_conflict', 'group_ids' => $tour['group_ids'], 'group_id' => $tour['group_ids'][0] ?? null, 'title' => $tour['name'], 'detail' => ''];
            }
        }
        usort($out, static fn (array $a, array $b) => ($a['level'] === 'critical' ? 0 : 1) <=> ($b['level'] === 'critical' ? 0 : 1));

        return $out;
    }

    private function commitmentName(string $id): string
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)->find($id);

        return $row instanceof DepartmentGrossanlassCommitment ? $row->getName() : '';
    }

    private function commitmentCount(string $family, Department $department): int
    {
        return $this->entityManager->getRepository(DepartmentGrossanlassCommitment::class)->count(['departmentId' => $department->getId(), 'family' => $family]);
    }

    private function userName(?string $userId): string
    {
        if ($userId === null) {
            return '';
        }
        $user = $this->entityManager->getRepository(User::class)->find($userId);

        return $user instanceof User ? ($user->getProfile()?->getDisplayName() ?? '') : '';
    }
}
