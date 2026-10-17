<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\User;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lesende Auswertung des Helferpools (V1): zeitabhängige Personaldeckung, Konflikte und Zeitplan.
 * Die Deckung wird immer aus den tatsächlichen Zuteilungen berechnet, nie gespeichert.
 *
 * - {@see overview()}: Zahlen je Bedarf und Gruppe, **ohne Helfernamen und ohne Berechtigungsprüfung** (der Controller
 *   entscheidet, wer die Übersicht sieht). Eine Abfrage für Bedarfe, eine für Zuteilungen; keine Helfertabelle.
 * - {@see needDetail()}, {@see conflicts()}, {@see schedule()}: mit Namen, nur GA-HV/GA-SUBHV.
 */
final class GrossanlassStaffingCoverageService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GrossanlassAccessService $access,
    ) {
    }

    /**
     * @param array{group_ids?: list<string>, from?: mixed, to?: mixed} $filter Bedarfe, die das Fenster berühren (undatierte immer)
     * @return array{needs: list<array<string, mixed>>, groups: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    public function overview(Department $department, array $filter = []): array
    {
        [$where, $params, $types] = $this->scope($department, $filter);
        $conn = $this->entityManager->getConnection();
        $needs = $conn->fetchAllAssociative(
            "SELECT n.id, n.task_id, n.label, n.required_count, n.starts_at, n.ends_at, t.group_id, t.title, t.is_default
             FROM department_grossanlass_staffing_need n
             JOIN department_grossanlass_task t ON t.id = n.task_id
             WHERE $where ORDER BY t.group_id, t.sort_order, n.sort_order, n.starts_at, n.label",
            $params,
            $types,
        );
        $byNeed = [];
        foreach ($conn->fetchAllAssociative(
            "SELECT a.need_id, a.starts_at, a.ends_at
             FROM department_grossanlass_staffing_assignment a
             JOIN department_grossanlass_staffing_need n ON n.id = a.need_id
             JOIN department_grossanlass_task t ON t.id = n.task_id
             WHERE $where AND a.status <> 'cancelled'",
            $params,
            $types,
        ) as $row) {
            $byNeed[trim((string) $row['need_id'])][] = [new \DateTime($row['starts_at']), new \DateTime($row['ends_at'])];
        }

        $outNeeds = [];
        $groups = [];
        foreach ($needs as $row) {
            $id = trim((string) $row['id']);
            $groupId = trim((string) $row['group_id']);
            $required = (int) $row['required_count'];
            $dated = $row['starts_at'] !== null && $row['ends_at'] !== null;
            $from = $dated ? new \DateTime($row['starts_at']) : null;
            $to = $dated ? new \DateTime($row['ends_at']) : null;
            $coverage = $dated ? GrossanlassStaffingTime::coverage($required, $from, $to, $byNeed[$id] ?? []) : null;

            $outNeeds[] = [
                'id' => $id,
                'task_id' => trim((string) $row['task_id']),
                'task_title' => $row['is_default'] ? null : $row['title'],
                'is_default_task' => (bool) $row['is_default'],
                'group_id' => $groupId,
                'label' => $row['label'],
                'required_count' => $required,
                'starts_at' => $from?->format('Y-m-d\TH:i:s'),
                'ends_at' => $to?->format('Y-m-d\TH:i:s'),
                'dated' => $dated,
                'assigned_count' => count($byNeed[$id] ?? []),
                'coverage' => $coverage,
            ];

            $g = &$groups[$groupId];
            $g ??= ['group_id' => $groupId, 'required_total' => 0, 'open_needs' => 0, 'undated_needs' => 0, 'overbooked_needs' => 0, '_covered_pm' => 0.0, '_required_pm' => 0.0, '_rows' => []];
            $g['required_total'] += $required;
            if ($coverage === null) {
                $g['undated_needs'] += $required > 0 ? 1 : 0;
                $g['_rows'][] = ['starts_at' => null, 'duration' => null, 'total' => $required];
            } else {
                $g['open_needs'] += $coverage['gaps'] !== [] ? 1 : 0;
                $g['overbooked_needs'] += $coverage['overbooked'] ? 1 : 0;
                $g['_required_pm'] += $required * $coverage['minutes'];
                $g['_covered_pm'] += ($coverage['fill_ratio'] ?? 0) * $required * $coverage['minutes'];
                $g['_rows'][] = ['starts_at' => $from, 'duration' => $coverage['minutes'], 'total' => $required];
            }
            unset($g);
        }

        $outGroups = [];
        $totalRequired = 0;
        $totalCovered = 0.0;
        $totalPm = 0.0;
        foreach ($groups as $g) {
            $demand = GrossanlassBauprojektService::helperDemandOverTime($g['_rows']);
            $outGroups[] = [
                'group_id' => $g['group_id'],
                'required_total' => $g['required_total'],
                'peak_required' => $demand['peak'],
                'fill_ratio' => $g['_required_pm'] > 0 ? round($g['_covered_pm'] / $g['_required_pm'], 4) : null,
                'open_needs' => $g['open_needs'],
                'undated_needs' => $g['undated_needs'],
                'overbooked_needs' => $g['overbooked_needs'],
            ];
            $totalRequired += $g['required_total'];
            $totalCovered += $g['_covered_pm'];
            $totalPm += $g['_required_pm'];
        }

        return [
            'needs' => $outNeeds,
            'groups' => $outGroups,
            'totals' => [
                'required_total' => $totalRequired,
                'fill_ratio' => $totalPm > 0 ? round($totalCovered / $totalPm, 4) : null,
                'open_needs' => (int) array_sum(array_column($outGroups, 'open_needs')),
                'undated_needs' => (int) array_sum(array_column($outGroups, 'undated_needs')),
            ],
        ];
    }

    /**
     * Ein Bedarf mit Deckung und den zugeteilten Helfern (Namen).
     *
     * @return array<string, mixed>
     */
    public function needDetail(Department $department, User $user, string $needId): array
    {
        $this->assertCanManage($department, $user);
        $conn = $this->entityManager->getConnection();
        $need = $conn->fetchAssociative(
            'SELECT n.id, n.task_id, n.label, n.required_count, n.starts_at, n.ends_at, t.group_id, t.title, t.is_default
             FROM department_grossanlass_staffing_need n JOIN department_grossanlass_task t ON t.id = n.task_id
             WHERE n.id = ? AND n.department_id = ?',
            [$needId, $department->getId()],
        );
        if ($need === false) {
            throw new \InvalidArgumentException('Personalbedarf nicht gefunden');
        }
        $assignments = $conn->fetchAllAssociative(
            "SELECT a.id, a.helper_id, a.starts_at, a.ends_at, a.status, a.override_reason, a.version, h.first_name, h.last_name
             FROM department_grossanlass_staffing_assignment a
             JOIN department_grossanlass_helper h ON h.id = a.helper_id
             WHERE a.need_id = ? AND a.status <> 'cancelled' ORDER BY a.starts_at, h.last_name, h.first_name",
            [$needId],
        );
        $dated = $need['starts_at'] !== null && $need['ends_at'] !== null;

        return [
            'id' => trim((string) $need['id']),
            'task_id' => trim((string) $need['task_id']),
            'task_title' => $need['is_default'] ? null : $need['title'],
            'group_id' => trim((string) $need['group_id']),
            'label' => $need['label'],
            'required_count' => (int) $need['required_count'],
            'starts_at' => $dated ? (new \DateTime($need['starts_at']))->format('Y-m-d\TH:i:s') : null,
            'ends_at' => $dated ? (new \DateTime($need['ends_at']))->format('Y-m-d\TH:i:s') : null,
            'dated' => $dated,
            'coverage' => $dated ? GrossanlassStaffingTime::coverage(
                (int) $need['required_count'],
                new \DateTime($need['starts_at']),
                new \DateTime($need['ends_at']),
                array_map(static fn (array $a) => [new \DateTime($a['starts_at']), new \DateTime($a['ends_at'])], $assignments),
            ) : null,
            'assignments' => array_map(static fn (array $a) => [
                'id' => trim((string) $a['id']),
                'helper_id' => trim((string) $a['helper_id']),
                'helper_name' => trim($a['first_name'] . ' ' . $a['last_name']),
                'starts_at' => (new \DateTime($a['starts_at']))->format('Y-m-d\TH:i:s'),
                'ends_at' => (new \DateTime($a['ends_at']))->format('Y-m-d\TH:i:s'),
                'status' => $a['status'],
                'override_reason' => $a['override_reason'],
                'version' => (int) $a['version'],
            ], $assignments),
        ];
    }

    /**
     * Konflikte im Helferpool: Doppelbuchung (mit Kennzeichen `excused` bei begründeter Ausnahme), Zuteilung ausserhalb der
     * Verfügbarkeit oder des Bedarfsfensters, Überbesetzung, undatierter Bedarf.
     *
     * @return list<array<string, mixed>>
     */
    public function conflicts(Department $department, User $user): array
    {
        $this->assertCanManage($department, $user);
        $conn = $this->entityManager->getConnection();
        $assignments = $conn->fetchAllAssociative(
            "SELECT a.id, a.need_id, a.helper_id, a.starts_at, a.ends_at, a.override_reason, n.starts_at AS n_from, n.ends_at AS n_to
             FROM department_grossanlass_staffing_assignment a JOIN department_grossanlass_staffing_need n ON n.id = a.need_id
             WHERE a.department_id = ? AND a.status <> 'cancelled' ORDER BY a.helper_id, a.starts_at",
            [$department->getId()],
        );
        $availability = [];
        foreach ($conn->fetchAllAssociative(
            'SELECT helper_id, starts_at, ends_at FROM department_grossanlass_helper_availability WHERE department_id = ?',
            [$department->getId()],
        ) as $row) {
            $availability[trim((string) $row['helper_id'])][] = [new \DateTime($row['starts_at']), new \DateTime($row['ends_at'])];
        }

        $out = [];
        $byHelper = [];
        foreach ($assignments as $a) {
            $byHelper[trim((string) $a['helper_id'])][] = $a;
        }
        $fmt = static fn (\DateTimeInterface $d): string => $d->format('Y-m-d\TH:i:s');
        foreach ($byHelper as $helperId => $rows) {
            foreach ($rows as $i => $a) {
                $aFrom = new \DateTime($a['starts_at']);
                $aTo = new \DateTime($a['ends_at']);
                if (!GrossanlassStaffingTime::isCovered($availability[$helperId] ?? [], $aFrom, $aTo)) {
                    $out[] = ['kind' => 'outside_availability', 'helper_id' => $helperId, 'assignment_ids' => [trim((string) $a['id'])], 'starts_at' => $fmt($aFrom), 'ends_at' => $fmt($aTo)];
                }
                if ($a['n_from'] === null || new \DateTime($a['n_from']) > $aFrom || new \DateTime($a['n_to']) < $aTo) {
                    $out[] = ['kind' => 'outside_need_window', 'helper_id' => $helperId, 'assignment_ids' => [trim((string) $a['id'])], 'need_id' => trim((string) $a['need_id']), 'starts_at' => $fmt($aFrom), 'ends_at' => $fmt($aTo)];
                }
                for ($j = $i + 1, $n = count($rows); $j < $n; ++$j) {
                    $b = $rows[$j];
                    $bFrom = new \DateTime($b['starts_at']);
                    if ($bFrom >= $aTo) {
                        break; // nach Start sortiert: weitere Zeilen beginnen noch später
                    }
                    $bTo = new \DateTime($b['ends_at']);
                    $out[] = [
                        'kind' => 'double_booking',
                        'helper_id' => $helperId,
                        'assignment_ids' => [trim((string) $a['id']), trim((string) $b['id'])],
                        'starts_at' => $fmt(max($aFrom, $bFrom)),
                        'ends_at' => $fmt(min($aTo, $bTo)),
                        'excused' => $a['override_reason'] !== null || $b['override_reason'] !== null,
                    ];
                }
            }
        }
        foreach ($this->overview($department)['needs'] as $need) {
            if (!$need['dated'] && $need['required_count'] > 0) {
                $out[] = ['kind' => 'need_undated', 'need_id' => $need['id']];
            } elseif ($need['coverage']['overbooked'] ?? false) {
                $out[] = ['kind' => 'overbooked', 'need_id' => $need['id'], 'required' => $need['required_count'], 'peak_assigned' => $need['coverage']['peak_assigned']];
            }
        }

        return $out;
    }

    /**
     * Zeitplan: Helfer mit ihren Verfügbarkeitsfenstern und Zuteilungen im Zeitraum (mit Namen).
     *
     * @return list<array<string, mixed>>
     */
    public function schedule(Department $department, User $user, mixed $from, mixed $to): array
    {
        $this->assertCanManage($department, $user);
        $start = GrossanlassStaffingTime::parse($from, 'from');
        $end = GrossanlassStaffingTime::parse($to, 'to');
        GrossanlassStaffingTime::assertWindow($start, $end);
        $conn = $this->entityManager->getConnection();
        $params = [$department->getId(), $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')];

        $helpers = [];
        $ensure = static function (array &$helpers, array $row): void {
            $id = trim((string) $row['helper_id']);
            $helpers[$id] ??= ['helper_id' => $id, 'name' => trim($row['first_name'] . ' ' . $row['last_name']), 'status' => $row['status'], 'availability' => [], 'assignments' => []];
        };
        foreach ($conn->fetchAllAssociative(
            'SELECT v.helper_id, v.starts_at, v.ends_at, h.first_name, h.last_name, h.status
             FROM department_grossanlass_helper_availability v JOIN department_grossanlass_helper h ON h.id = v.helper_id
             WHERE v.department_id = ? AND v.starts_at < ? AND v.ends_at > ? ORDER BY h.last_name, h.first_name, v.starts_at',
            $params,
        ) as $row) {
            $ensure($helpers, $row);
            $helpers[trim((string) $row['helper_id'])]['availability'][] = [
                'starts_at' => (new \DateTime($row['starts_at']))->format('Y-m-d\TH:i:s'),
                'ends_at' => (new \DateTime($row['ends_at']))->format('Y-m-d\TH:i:s'),
            ];
        }
        foreach ($conn->fetchAllAssociative(
            "SELECT a.id, a.helper_id, a.need_id, a.starts_at, a.ends_at, a.override_reason, n.label, t.title, t.is_default, t.group_id, h.first_name, h.last_name, h.status
             FROM department_grossanlass_staffing_assignment a
             JOIN department_grossanlass_helper h ON h.id = a.helper_id
             JOIN department_grossanlass_staffing_need n ON n.id = a.need_id
             JOIN department_grossanlass_task t ON t.id = n.task_id
             WHERE a.department_id = ? AND a.starts_at < ? AND a.ends_at > ? AND a.status <> 'cancelled'
             ORDER BY h.last_name, h.first_name, a.starts_at",
            $params,
        ) as $row) {
            $ensure($helpers, $row);
            $helpers[trim((string) $row['helper_id'])]['assignments'][] = [
                'id' => trim((string) $row['id']),
                'need_id' => trim((string) $row['need_id']),
                'group_id' => trim((string) $row['group_id']),
                'task_title' => $row['is_default'] ? null : $row['title'],
                'label' => $row['label'],
                'starts_at' => (new \DateTime($row['starts_at']))->format('Y-m-d\TH:i:s'),
                'ends_at' => (new \DateTime($row['ends_at']))->format('Y-m-d\TH:i:s'),
                'has_override' => $row['override_reason'] !== null,
            ];
        }

        return array_values($helpers);
    }

    /**
     * @param array{group_ids?: list<string>, from?: mixed, to?: mixed} $filter
     * @return array{0: string, 1: list<mixed>, 2: list<mixed>}
     */
    private function scope(Department $department, array $filter): array
    {
        $where = ['n.department_id = ?'];
        $params = [$department->getId()];
        $types = [\Doctrine\DBAL\ParameterType::STRING];
        if (($filter['group_ids'] ?? []) !== []) {
            $where[] = 't.group_id IN (?)';
            $params[] = array_values($filter['group_ids']);
            $types[] = ArrayParameterType::STRING;
        }
        if (isset($filter['from'], $filter['to'])) {
            $from = GrossanlassStaffingTime::parse($filter['from'], 'from');
            $to = GrossanlassStaffingTime::parse($filter['to'], 'to');
            GrossanlassStaffingTime::assertWindow($from, $to);
            $where[] = '(n.starts_at IS NULL OR (n.starts_at < ? AND n.ends_at > ?))';
            array_push($params, $to->format('Y-m-d H:i:s'), $from->format('Y-m-d H:i:s'));
            array_push($types, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING);
        }

        return [implode(' AND ', $where), $params, $types];
    }

    private function assertCanManage(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManageStaffing($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für den Helferpool');
        }
    }
}
