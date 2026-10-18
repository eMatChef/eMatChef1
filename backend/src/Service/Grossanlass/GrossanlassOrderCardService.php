<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassTask;
use App\Entity\Group;
use App\Entity\User;
use App\Service\Clock\BusinessClock;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Kennzahlen für die Auftragskarten der Planung (Aufträge und Bauaufträge).
 * Nur berechnet, nichts gespeichert: Material aus dem Materialfortschritt, Aufgaben aus den Aufgaben der
 * Gruppe. Besetzung = zugeteilte Helfer (aktive Zuteilungen des Helferpools) gegen den
 * erfassten Personalbedarf; ohne erfassten Bedarf steht null statt eines geschätzten Werts.
 */
final class GrossanlassOrderCardService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassMaterialProgressService $materialProgress,
        private BusinessClock $clock,
        private GrossanlassStaffingNeedService $staffingNeeds,
        private GrossanlassOrderAcceptanceService $acceptance,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function cards(Department $department, User $user, bool $includeArchived = false): array
    {
        $this->access->assertGrossanlassDepartment($department);

        $seeAll = $this->access->canManagePlanung($user, $department)
            || $this->access->canSeeAnlassOverview($user, $department);
        $groups = [];
        foreach ($this->entityManager->getRepository(Group::class)->findBy(['departmentId' => $department->getId()]) as $group) {
            if (!$group instanceof Group || !in_array($group->getGrossanlassKind(), [Group::GROSSANLASS_KIND_TEILBEREICH, Group::GROSSANLASS_KIND_AUFTRAG], true)) {
                continue;
            }
            if (!$includeArchived && $group->isArchived()) {
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
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassTask::class)->findBy(['departmentId' => $department->getId(), 'isDefault' => false]) as $task) {
            if ($task instanceof DepartmentGrossanlassTask) {
                $tasks[] = [
                    'group_id' => $task->getGroupId(),
                    'assignee' => null, // Aufgabenverantwortliche zählen nicht als Helferzuteilung
                    'starts_at' => $task->getStartsAt(),
                    'duration' => $task->getDurationMinutes(),
                    'status' => $task->getStatus(),
                ];
            }
        }

        $needs = $this->helperNeeds(array_keys($groups));
        $members = $this->assignedHelpers(array_keys($groups));

        return self::summarize(array_keys($groups), $items, $tasks, $this->clock->now($department->getId()), $needs, $members, $this->acceptance->statuses(array_keys($groups)));
    }

    /**
     * Spitzenbedarf an Helfern je Gruppe aus dem Personalbedarf aller Aufgaben (zeitlich getrennte Bedarfe addieren sich nicht);
     * Gruppen ohne Bedarf fehlen (dann steht `null` statt eines geschätzten Werts).
     *
     * @param list<string> $groupIds
     * @return array<string, int>
     */
    private function helperNeeds(array $groupIds): array
    {
        $out = [];
        foreach ($this->staffingNeeds->demandRows($groupIds) as $groupId => $rows) {
            $peak = GrossanlassBauprojektService::helperDemandOverTime($rows)['peak'];
            if ($peak > 0) {
                $out[$groupId] = $peak;
            }
        }

        return $out;
    }

    /**
     * Zugeteilte Helfer je Gruppe (je Person einmal) aus den aktiven Zuteilungen; keine Namen.
     *
     * @param list<string> $groupIds
     * @return array<string, list<string>>
     */
    private function assignedHelpers(array $groupIds): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT DISTINCT t.group_id, a.helper_id
             FROM department_grossanlass_staffing_assignment a
             JOIN department_grossanlass_staffing_need n ON n.id = a.need_id
             JOIN department_grossanlass_task t ON t.id = n.task_id
             WHERE t.group_id IN (?) AND a.status NOT IN ('cancelled', 'conflict')",
            [$groupIds],
            [ArrayParameterType::STRING],
        );
        $out = [];
        foreach ($rows as $row) {
            $out[trim((string) $row['group_id'])][] = trim((string) $row['helper_id']);
        }

        return $out;
    }

    /**
     * @param list<string> $groupIds
     * @param list<array<string, mixed>> $items Zeilen des Materialfortschritts
     * @param list<array{group_id: string, assignee: ?string, starts_at: ?\DateTimeInterface, duration: ?int, status?: string}> $tasks
     * @param array<string, int> $needs Gesamtbedarf an Helfern je Gruppe
     * @param array<string, list<string>> $members zugeteilte Helfer je Gruppe
     * @param array<string, string> $orderStatuses Fertigmeldung/Abnahme je Gruppe (`open`|`review`|`accepted`); unabhängig vom Aufgabenfortschritt
     * @return list<array<string, mixed>>
     */
    public static function summarize(array $groupIds, array $items, array $tasks, \DateTimeInterface $now, array $needs = [], array $members = [], array $orderStatuses = []): array
    {
        $out = [];
        foreach ($groupIds as $id) {
            $out[$id] = [
                'group_id' => $id,
                'order_status' => $orderStatuses[$id] ?? GrossanlassOrderAcceptanceService::OPEN,
                'material' => ['items' => 0, 'covered_items' => 0, 'at_place_items' => 0],
                'tasks' => ['total' => 0, 'scheduled' => 0, 'done' => 0, 'planned' => 0, 'in_window' => 0, 'overdue' => 0],
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
            $scheduled = $task['starts_at'] !== null && $task['duration'] !== null;
            if ($scheduled) {
                ++$out[$id]['tasks']['scheduled'];
            }
            if (($task['status'] ?? null) === DepartmentGrossanlassTask::STATUS_DONE) {
                ++$out[$id]['tasks']['done'];
                continue;
            }
            // Offene Aufgaben nach Fachzeit (BusinessClock): geplant, im Terminfenster oder überfällig (Ende überschritten).
            if ($task['starts_at'] === null) {
                continue;
            }
            $start = \DateTimeImmutable::createFromInterface($task['starts_at']);
            $end = $start->modify(sprintf('+%d minutes', max(0, (int) ($task['duration'] ?? 0))));
            if ($now < $start) {
                ++$out[$id]['tasks']['planned'];
            } elseif ($now <= $end) {
                ++$out[$id]['tasks']['in_window'];
            } else {
                ++$out[$id]['tasks']['overdue'];
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
