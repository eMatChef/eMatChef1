<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassGroupHelperNeed;
use App\Entity\DepartmentGrossanlassTaskHelperNeed;
use App\Entity\DepartmentGrossanlassTask;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\Membership;
use App\Entity\User;
use App\Service\Clock\BusinessClock;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Kennzahlen für die Auftragskarten der Planung (Aufträge und Bauaufträge).
 * Nur berechnet, nichts gespeichert: Material aus dem Materialfortschritt, Aufgaben aus den Aufgaben der
 * Gruppe. Besetzung = eingeteilte Helfer (Helferpool-Mitglieder der Gruppe und Aufgabenzuweisungen) gegen den
 * erfassten Helferbedarf; ohne erfassten Bedarf steht null statt eines geschätzten Werts.
 */
final class GrossanlassOrderCardService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassMaterialProgressService $materialProgress,
        private BusinessClock $clock,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function cards(Department $department, User $user): array
    {
        $this->access->assertGrossanlassDepartment($department);

        $seeAll = $this->access->canManagePlanung($user, $department)
            || $this->access->canSeeAnlassOverview($user, $department);
        $groups = [];
        foreach ($this->entityManager->getRepository(Group::class)->findBy(['departmentId' => $department->getId()]) as $group) {
            if (!$group instanceof Group || !in_array($group->getGrossanlassKind(), [Group::GROSSANLASS_KIND_TEILBEREICH, Group::GROSSANLASS_KIND_AUFTRAG], true)) {
                continue;
            }
            if ($seeAll || $this->access->userIsMemberInRessortBranch($user, $department->getId(), $group)) {
                $groups[$group->getId()] = $group;
            }
        }
        if ($groups === []) {
            return [];
        }

        $items = $this->materialProgress->forDepartment($department)['items'];
        $tasks = [];
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassTask::class)->findBy(['departmentId' => $department->getId()]) as $task) {
            if ($task instanceof DepartmentGrossanlassTask) {
                $tasks[] = [
                    'group_id' => $task->getGroupId(),
                    'assignee' => $task->getAssigneeUserId(),
                    'starts_at' => $task->getStartsAt(),
                    'duration' => $task->getDurationMinutes(),
                ];
            }
        }

        $needs = $this->helperNeeds(array_keys($groups));
        $members = [];
        foreach ($this->entityManager->getRepository(GroupMembership::class)->findBy(['groupId' => array_keys($groups), 'role' => 'member']) as $membership) {
            if ($membership instanceof GroupMembership) {
                $members[$membership->getGroupId()][] = $membership->getUserId();
            }
        }

        return self::summarize(array_keys($groups), $items, $tasks, $this->clock->now($department->getId()), $needs, $members);
    }

    /**
     * Gesamtbedarf je Gruppe (Zeile mit leerem Label); null, wenn nichts erfasst ist.
     *
     * @param list<string> $groupIds
     * @return array<string, int>
     */
    private function helperNeeds(array $groupIds): array
    {
        try {
            $rows = $this->entityManager->getRepository(DepartmentGrossanlassGroupHelperNeed::class)
                ->findBy(['groupId' => $groupIds, 'label' => DepartmentGrossanlassGroupHelperNeed::TOTAL_LABEL]);
        } catch (TableNotFoundException) {
            return []; // Migration noch nicht angewendet
        }
        $out = [];
        foreach ($rows as $row) {
            if ($row instanceof DepartmentGrossanlassGroupHelperNeed && $row->getRequiredCount() > 0) {
                $out[$row->getGroupId()] = $row->getRequiredCount();
            }
        }

        // Aufgabenbezogener Bedarf geht vor: Spitzenbedarf zeitlich getrennter Aufgaben, nicht ihre Summe.
        foreach ($this->taskPeakNeeds($groupIds) as $groupId => $peak) {
            $out[$groupId] = $peak;
        }

        return $out;
    }

    /**
     * Höchster gleichzeitiger Helferbedarf aus den Aufgaben je Gruppe (nur Gruppen mit Aufgabenbedarf).
     *
     * @param list<string> $groupIds
     * @return array<string, int>
     */
    private function taskPeakNeeds(array $groupIds): array
    {
        try {
            $needRows = $this->entityManager->getRepository(DepartmentGrossanlassTaskHelperNeed::class)
                ->findBy(['label' => DepartmentGrossanlassTaskHelperNeed::TOTAL_LABEL]);
        } catch (TableNotFoundException) {
            return []; // Migration noch nicht angewendet
        }
        $totals = [];
        foreach ($needRows as $row) {
            if ($row instanceof DepartmentGrossanlassTaskHelperNeed) {
                $totals[$row->getTaskId()] = $row->getRequiredCount();
            }
        }
        $byGroup = [];
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassTask::class)->findBy(['groupId' => $groupIds]) as $task) {
            if ($task instanceof DepartmentGrossanlassTask && ($totals[$task->getId()] ?? 0) > 0) {
                $byGroup[$task->getGroupId()][] = [
                    'starts_at' => $task->getStartsAt(),
                    'duration' => $task->getDurationMinutes(),
                    'total' => $totals[$task->getId()],
                ];
            }
        }
        $out = [];
        foreach ($byGroup as $groupId => $rows) {
            $out[$groupId] = GrossanlassBauprojektService::helperDemandOverTime($rows)['peak'];
        }

        return $out;
    }

    /**
     * @param list<string> $groupIds
     * @param list<array<string, mixed>> $items Zeilen des Materialfortschritts
     * @param list<array{group_id: string, assignee: ?string, starts_at: ?\DateTimeInterface, duration: ?int}> $tasks
     * @param array<string, int> $needs Gesamtbedarf an Helfern je Gruppe
     * @param array<string, list<string>> $members Helfer-Mitglieder (Nicht-Leitung) je Gruppe
     * @return list<array<string, mixed>>
     */
    public static function summarize(array $groupIds, array $items, array $tasks, \DateTimeInterface $now, array $needs = [], array $members = []): array
    {
        $out = [];
        foreach ($groupIds as $id) {
            $out[$id] = [
                'group_id' => $id,
                'material' => ['items' => 0, 'covered_items' => 0, 'at_place_items' => 0],
                'tasks' => ['total' => 0, 'scheduled' => 0, 'done' => 0],
                'staffing' => ['need' => $needs[$id] ?? null, 'assigned' => 0],
            ];
        }
        foreach ($items as $item) {
            $id = (string) ($item['group_id'] ?? '');
            if (!isset($out[$id]) || (int) $item['required'] <= 0) {
                continue;
            }
            ++$out[$id]['material']['items'];
            if ((int) $item['open'] === 0) {
                ++$out[$id]['material']['covered_items'];
            }
            if ((int) $item['at_place'] >= (int) $item['required']) {
                ++$out[$id]['material']['at_place_items'];
            }
        }
        // Eingeteilt = Helfer-Mitglieder der Gruppe und Personen mit zugewiesener Aufgabe (je Person einmal).
        $assignees = [];
        foreach ($members as $id => $userIds) {
            foreach ($userIds as $userId) {
                $assignees[$id][$userId] = true;
            }
        }
        foreach ($tasks as $task) {
            $id = $task['group_id'];
            if (!isset($out[$id])) {
                continue;
            }
            ++$out[$id]['tasks']['total'];
            if ($task['assignee'] !== null && $task['assignee'] !== '') {
                $assignees[$id][$task['assignee']] = true;
            }
            if ($task['starts_at'] !== null && $task['duration'] !== null) {
                ++$out[$id]['tasks']['scheduled'];
                $end = \DateTime::createFromInterface($task['starts_at'])->modify('+' . $task['duration'] . ' minutes');
                if ($end <= $now) {
                    ++$out[$id]['tasks']['done'];
                }
            }
        }
        foreach ($assignees as $id => $users) {
            if (isset($out[$id])) {
                $out[$id]['staffing']['assigned'] = count($users);
            }
        }

        return array_values($out);
    }
}
