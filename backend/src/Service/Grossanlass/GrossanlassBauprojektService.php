<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassDependency;
use App\Entity\DepartmentGrossanlassEinsatz;
use App\Entity\DepartmentGrossanlassGroupHelperNeed;
use App\Entity\DepartmentGrossanlassMap;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\DepartmentGrossanlassTask;
use App\Entity\DepartmentGrossanlassVehicleNeed;
use App\Entity\Group;
use App\Entity\User;
use App\Service\InboxMessageService;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Bauprojekt-Briefing: Fenster, Aufgaben, Material=Wünsche, GA-Ort — kein zweites QR/Fahrt-Modul.
 */
final class GrossanlassBauprojektService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassPlaceService $places,
        private GrossanlassMapService $maps,
        private GrossanlassWishService $wishes,
        private GrossanlassPackService $packs,
        private GrossanlassProcurementService $procurement,
        private GrossanlassProcurementProgress $procurementProgress,
        private GrossanlassMaterialProgressService $materialProgress,
        private GrossanlassStaffingNeedService $staffingNeeds,
        private GrossanlassTaskWorkflowService $taskWorkflow,
        private InboxMessageService $inbox,
        private GrossanlassDependencyService $dependencies,
        private GrossanlassOrderAcceptanceService $acceptance,
        private GrossanlassOrderArchiveService $archive,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function briefingForGroup(Department $department, User $user, Group $group): array
    {
        $this->assertGroup($department, $group);
        $this->assertCanSee($department, $user, $group);
        $place = $group->getGrossanlassKind() === Group::GROSSANLASS_KIND_TEILBEREICH
            ? $this->places->ensureForBauprojekt($department, $group)
            : $this->places->findForGroup($department, $group->getId());

        return $this->briefing($department, $user, $group, $place);
    }

    /**
     * @return array<string, mixed>
     */
    public function briefingForPlace(Department $department, User $user, string $placeId): array
    {
        $this->places->assertCanSeePlaces($user, $department);
        $place = $this->places->getPlace($department, $placeId);
        $group = null;
        $placeGroupIds = $this->places->groupIdsForPlace($place);
        // Bei gemeinsam genutzten Standorten gibt es kein eindeutiges Briefing-Projekt.
        if (count($placeGroupIds) === 1) {
            $found = $this->entityManager->getRepository(Group::class)->find($placeGroupIds[0]);
            $group = $found instanceof Group && $found->getDepartmentId() === $department->getId()
                ? $found
                : null;
        }
        if ($group instanceof Group) {
            return $this->briefing($department, $user, $group, $place);
        }

        return [
            'group' => null,
            'window_start' => null,
            'window_end' => null,
            'build_status' => null,
            'description' => null,
            'place' => $this->places->serialize($place),
            'tasks' => [],
            'vehicles' => [],
            'material' => [],
            'direct_material' => [],
            'packs' => $this->packs->listAtPlace($department, $place->getId()),
            'einsaetze' => [],
            'map' => $this->mapSnippet($department, $place),
            'can_edit' => false,
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateWindow(Department $department, User $user, Group $group, array $data): array
    {
        $this->assertBauprojekt($department, $group);
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar oder ausführbar
        $this->assertCanEdit($department, $user, $group);
        $start = $this->parseOptionalDate($data['window_start'] ?? null, 'window_start');
        $end = $this->parseOptionalDate($data['window_end'] ?? null, 'window_end');
        if ($start !== null && $end !== null && $end < $start) {
            throw new \InvalidArgumentException('Zeitfenster Ende muss nach Start liegen');
        }
        $windowsBefore = $this->staffingNeeds->snapshotWindows($group);
        $group->setWindowStart($start);
        $group->setWindowEnd($end);
        $this->staffingNeeds->realign($group, $windowsBefore); // Bedarf folgt dem Fenster der Aufgaben ohne eigene Zeit
        if (array_key_exists('build_status', $data) && $this->access->canSetBuildStatus($user, $department, $group)) {
            $raw = $data['build_status'];
            if ($raw === null || $raw === '') {
                $group->setBuildStatus(null);
            } else {
                $status = strtolower(trim((string) $raw));
                if (!in_array($status, Group::BUILD_STATUSES, true)) {
                    throw new \InvalidArgumentException('Ungültiger Bauvorhaben-Status');
                }
                $group->setBuildStatus($status);
            }
        }
        if (array_key_exists('description', $data)) {
            $raw = $data['description'];
            $group->setDescription($raw === null ? null : (string) $raw);
        }
        $group->updateTimestamps();
        $this->entityManager->flush();

        return $this->briefingForGroup($department, $user, $group);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTasks(Department $department, User $user, Group $group): array
    {
        $this->assertGroup($department, $group);
        $this->assertCanSee($department, $user, $group);

        return $this->serializeTasks($department, $user, $group);
    }

    /**
     * Aufgaben aller für den Benutzer sichtbaren Gruppen (zentrale Aufgabenübersicht).
     *
     * @return list<array<string, mixed>>
     */
    public function listAllTasks(Department $department, User $user, bool $includeArchived = false): array
    {
        $this->access->assertGrossanlassDepartment($department);
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassTask::class)
            ->findBy(['departmentId' => $department->getId(), 'isDefault' => false], ['startsAt' => 'ASC', 'sortOrder' => 'ASC']);
        $helperTaskIds = $this->taskWorkflow->assignedHelperTaskIds($user, $department->getId());
        $visible = [];
        $shown = [];
        foreach ($rows as $row) {
            if (!$includeArchived && $row->getGroup()->isArchived()) {
                continue; // archivierte Bauaufträge: nur über den Filter «archivierte anzeigen»
            }
            $groupId = $row->getGroupId();
            if (!isset($visible[$groupId])) {
                try {
                    $this->assertCanSee($department, $user, $row->getGroup());
                    $visible[$groupId] = true;
                } catch (\Throwable) {
                    $visible[$groupId] = false;
                }
            }
            // Eigene Aufgaben (verantwortlich oder als Helfer eingeteilt) sind auch ohne Ressortzugriff sichtbar.
            if ($visible[$groupId] || $row->getAssigneeUserId() === $user->getId() || in_array($row->getId(), $helperTaskIds, true)) {
                $shown[] = $row;
            }
        }

        return $this->serializeTaskList($shown, $department, $user, $helperTaskIds);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createTask(Department $department, User $user, Group $group, array $data): array
    {
        $this->assertBauprojekt($department, $group);
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar oder ausführbar
        $this->assertCanEdit($department, $user, $group);
        $this->acceptance->assertTasksMutable($group->getId()); // keine neue Aufgabe in einem gemeldeten/abgenommenen Bauauftrag
        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        if ($title === '' && $description === '') {
            throw new \InvalidArgumentException('Titel oder Beschrieb ist erforderlich');
        }
        if (is_array($data['helper_need'] ?? null)) {
            self::normalizeHelperNeedInput($data['helper_need']); // vor dem Anlegen prüfen, damit keine halbe Aufgabe bleibt
        }
        $max = (int) $this->entityManager->getRepository(DepartmentGrossanlassTask::class)
            ->createQueryBuilder('t')
            ->select('MAX(t.sortOrder)')
            ->where('t.groupId = :groupId')
            ->setParameter('groupId', $group->getId())
            ->getQuery()
            ->getSingleScalarResult();

        $task = new DepartmentGrossanlassTask();
        $task->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::TASK,
            DepartmentGrossanlassTask::class,
        ));
        $task->setDepartment($department);
        $task->setGroup($group);
        $task->setTitle($title);
        $task->setDescription($description === '' ? null : $description);
        $task->setSortOrder($max + 1);
        $this->applyTaskSchedule($task, $data);
        if (trim((string) ($data['rework_event_id'] ?? '')) !== '') {
            // Nachbesserung: an eine Rückweisung dieses Bauauftrags geknüpft; Zuteilung/Ressort kommen nur aus den Eingabedaten (kein Auto-Übernehmen)
            $task->setReworkEventId($this->acceptance->assertReworkEvent($group, (string) $data['rework_event_id'])->getId());
        }
        if (array_key_exists('status', $data)) {
            $this->taskWorkflow->assertStatusChangeAllowed($task, (string) $data['status'], false);
            $task->setStatus((string) $data['status']);
        }
        $this->entityManager->persist($task);
        $this->entityManager->flush();
        if (is_array($data['helper_need'] ?? null)) {
            $this->saveTaskHelperNeed($department, $task, $data['helper_need']);
            $this->entityManager->flush();
        }

        return $this->serializeTaskFor($task, $department, $user, $group);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateTask(Department $department, User $user, Group $group, string $taskId, array $data): array
    {
        $this->assertBauprojekt($department, $group);
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar oder ausführbar
        $task = $this->findTask($department, $group, $taskId);
        $perms = $this->taskWorkflow->permissionsFor($user, $task, $this->canEdit($department, $user, $group));
        if (!$perms['edit']) {
            // Verantwortliche ohne Bearbeitungsrecht dürfen nur den Arbeitsfortschritt (Status) melden.
            if (!$perms['report'] || array_diff(array_keys($data), ['status']) !== []) {
                throw new \RuntimeException('Keine Berechtigung für dieses Bauprojekt');
            }
        }
        $windowsBefore = $this->staffingNeeds->snapshotWindows($group);
        if (array_key_exists('title', $data)) {
            $task->setTitle(trim((string) $data['title']));
        }
        if (array_key_exists('description', $data)) {
            $description = trim((string) $data['description']);
            $task->setDescription($description === '' ? null : $description);
        }
        if (trim($task->getTitle()) === '' && trim((string) $task->getDescription()) === '') {
            throw new \InvalidArgumentException('Titel oder Beschrieb ist erforderlich');
        }
        if (array_key_exists('sort_order', $data)) {
            $task->setSortOrder((int) $data['sort_order']);
        }
        $this->applyTaskSchedule($task, $data);
        if (array_key_exists('status', $data)) {
            $this->taskWorkflow->assertStatusChangeAllowed($task, (string) $data['status'], $perms['approve']);
            if ($task->getStatus() === DepartmentGrossanlassTask::STATUS_DONE && (string) $data['status'] !== DepartmentGrossanlassTask::STATUS_DONE) {
                $this->acceptance->assertTasksMutable($group->getId()); // abgenommene Aufgabe wieder öffnen würde die Gesamtabnahme ungültig machen
            }
            if ((string) $data['status'] === DepartmentGrossanlassTask::STATUS_PROGRESS && $task->getStatus() !== DepartmentGrossanlassTask::STATUS_PROGRESS) {
                $this->dependencies->assertCanProceed($task, $this->dependencyVisibility($department, $user)); // wartende Aufgabe kann nicht gestartet werden
            }
            $task->setStatus((string) $data['status']);
        }
        $this->staffingNeeds->realign($group, $windowsBefore); // Bedarf folgt der neuen Aufgabenzeit
        if (is_array($data['helper_need'] ?? null)) {
            $this->saveTaskHelperNeed($department, $task, $data['helper_need']);
        }
        $this->entityManager->flush();

        return $this->serializeTaskFor($task, $department, $user, $group);
    }

    /**
     * Checklistenpunkt anlegen (nur Bearbeitungsrecht).
     *
     * @return array<string, mixed>
     */
    public function addTaskStep(Department $department, User $user, Group $group, string $taskId, string $label): array
    {
        $task = $this->taskForWorkflow($department, $user, $group, $taskId, 'edit');
        $this->taskWorkflow->addStep($task, $label);
        $this->entityManager->flush();

        return $this->serializeTaskFor($task, $department, $user, $group);
    }

    /**
     * Punkt umbenennen (Bearbeitungsrecht) oder abhaken (zusätzlich Verantwortliche und zugeteilte Helfer).
     *
     * @param array<string, mixed> $data label, done
     *
     * @return array<string, mixed>
     */
    public function updateTaskStep(Department $department, User $user, Group $group, string $taskId, string $stepId, array $data): array
    {
        $task = $this->taskForWorkflow($department, $user, $group, $taskId, 'check');
        $perms = $this->taskWorkflow->permissionsFor($user, $task, $this->canEdit($department, $user, $group));
        $this->taskWorkflow->updateStep($task, $stepId, $data, $user, $perms);
        $this->entityManager->flush();

        return $this->serializeTaskFor($task, $department, $user, $group);
    }

    /** @return array<string, mixed> */
    public function deleteTaskStep(Department $department, User $user, Group $group, string $taskId, string $stepId): array
    {
        $task = $this->taskForWorkflow($department, $user, $group, $taskId, 'edit');
        $this->taskWorkflow->deleteStep($task, $stepId);
        $this->entityManager->flush();

        return $this->serializeTaskFor($task, $department, $user, $group);
    }

    /**
     * @param list<string> $orderedIds
     *
     * @return array<string, mixed>
     */
    public function reorderTaskSteps(Department $department, User $user, Group $group, string $taskId, array $orderedIds): array
    {
        $task = $this->taskForWorkflow($department, $user, $group, $taskId, 'edit');
        $this->taskWorkflow->reorder($task, $orderedIds);
        $this->entityManager->flush();

        return $this->serializeTaskFor($task, $department, $user, $group);
    }

    /**
     * Fertigmeldung (Verantwortliche oder Bearbeitungsrecht). Noch kein Abschluss; Kommentar optional.
     *
     * @return array<string, mixed>
     */
    public function reportTaskDone(Department $department, User $user, Group $group, string $taskId, ?string $note): array
    {
        $task = $this->taskForWorkflow($department, $user, $group, $taskId, 'report');
        $this->dependencies->assertCanProceed($task, $this->dependencyVisibility($department, $user));
        $this->taskWorkflow->reportDone($task, $user, $note);
        $this->entityManager->flush();
        $this->notifyTask($department, $user, $task, $this->taskWorkflow->approverCandidateIds($group), 'Aufgabe fertig gemeldet', 'wurde fertig gemeldet und wartet auf Abnahme', $note);

        return $this->serializeTaskFor($task, $department, $user, $group);
    }

    /**
     * Abnahme (Bearbeitungsrecht): erst damit gilt die Aufgabe als erledigt.
     *
     * @return array<string, mixed>
     */
    public function acceptTask(Department $department, User $user, Group $group, string $taskId): array
    {
        $task = $this->taskForWorkflow($department, $user, $group, $taskId, 'approve');
        $reporter = $this->taskWorkflow->lastReporterId($task);
        $this->taskWorkflow->accept($task, $user);
        $this->entityManager->flush();
        $this->notifyTask($department, $user, $task, array_filter([$reporter, $task->getAssigneeUserId()]), 'Aufgabe abgenommen', 'wurde abgenommen und ist erledigt', null);

        return $this->serializeTaskFor($task, $department, $user, $group);
    }

    /**
     * Rückgabe zur Nachbearbeitung mit Begründung (Bearbeitungsrecht).
     *
     * @return array<string, mixed>
     */
    public function returnTask(Department $department, User $user, Group $group, string $taskId, string $reason): array
    {
        $task = $this->taskForWorkflow($department, $user, $group, $taskId, 'approve');
        $reporter = $this->taskWorkflow->lastReporterId($task);
        $this->taskWorkflow->returnForRework($task, $user, $reason);
        $this->entityManager->flush();
        $this->notifyTask($department, $user, $task, array_filter([$reporter, $task->getAssigneeUserId()]), 'Aufgabe zur Nachbearbeitung zurückgegeben', 'wurde zur Nachbearbeitung zurückgegeben', $reason);

        return $this->serializeTaskFor($task, $department, $user, $group);
    }

    /**
     * Gesamtabnahme des Bauauftrags: Zustand, Verlauf und Rechte des Benutzers.
     * Melden/Zurückziehen: Bearbeitungsrecht am Bauauftrag (wie Aufgaben). Abnehmen, Zurückweisen und Widerrufen: bestehendes
     * Gruppen-Bearbeitungsrecht übergeordneter Personen (`canEditGroup`: Struktur, Bereichsleitung, Leitung der Gruppe oder
     * eines Elternknotens, Teilen-Planung). Keine eigene Rollenverwaltung.
     *
     * @return array<string, mixed>
     */
    public function orderAcceptance(Department $department, User $user, Group $group): array
    {
        $this->assertGroup($department, $group);
        $this->assertCanSee($department, $user, $group);

        return $this->orderAcceptancePayload($department, $user, $group);
    }

    /** @return array<string, mixed> */
    public function reportOrder(Department $department, User $user, Group $group): array
    {
        $this->assertGroup($department, $group);
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar oder ausführbar
        $this->assertCanEdit($department, $user, $group);
        $this->acceptance->report($department, $group, $user);

        return $this->orderAcceptancePayload($department, $user, $group);
    }

    /** @return array<string, mixed> */
    public function withdrawOrder(Department $department, User $user, Group $group): array
    {
        $this->assertGroup($department, $group);
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar oder ausführbar
        $this->assertCanEdit($department, $user, $group);
        $this->acceptance->withdraw($department, $group, $user);

        return $this->orderAcceptancePayload($department, $user, $group);
    }

    /** @return array<string, mixed> */
    public function acceptOrder(Department $department, User $user, Group $group): array
    {
        $this->assertGroup($department, $group);
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar oder ausführbar
        $this->assertCanApproveOrder($department, $user, $group);
        $this->acceptance->accept($department, $group, $user);

        return $this->orderAcceptancePayload($department, $user, $group);
    }

    /** @return array<string, mixed> */
    public function returnOrder(Department $department, User $user, Group $group, string $reason): array
    {
        $this->assertGroup($department, $group);
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar oder ausführbar
        $this->assertCanApproveOrder($department, $user, $group);
        $this->acceptance->returnOrder($department, $group, $user, $reason);

        return $this->orderAcceptancePayload($department, $user, $group);
    }

    /** @return array<string, mixed> */
    public function reopenOrder(Department $department, User $user, Group $group, string $reason): array
    {
        $this->assertGroup($department, $group);
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar oder ausführbar
        $this->assertCanApproveOrder($department, $user, $group);
        $this->acceptance->reopen($department, $group, $user, $reason);

        return $this->orderAcceptancePayload($department, $user, $group);
    }

    private function assertCanApproveOrder(Department $department, User $user, Group $group): void
    {
        if (!$this->access->canEditGroup($user, $department, $group)) {
            throw new \RuntimeException('Keine Berechtigung für die Gesamtabnahme dieses Bauauftrags');
        }
    }

    /** @return array<string, mixed> */
    private function orderAcceptancePayload(Department $department, User $user, Group $group): array
    {
        // Vorschläge für Nachbesserungen (nie automatisch zugewiesen): ursprüngliches Ressort und bisherige Verantwortliche
        $rootId = $this->access->findRootRessortId($group);
        $root = $this->entityManager->getRepository(Group::class)->find($rootId);
        $assignees = [];
        foreach ($this->acceptance->suggestedAssigneeIds($group->getId()) as $userId) {
            $assignees[] = ['user_id' => $userId, 'name' => $this->entityManager->getRepository(User::class)->find($userId)?->getProfile()?->getDisplayName()];
        }

        return $this->acceptance->summary($group) + [
            'rework_suggestion' => [
                'ressort' => $root instanceof Group ? ['id' => $root->getId(), 'name' => $root->getName()] : null,
                'assignees' => $assignees,
            ],
            'permissions' => [
                'report' => $this->canEdit($department, $user, $group),
                'approve' => $this->access->canEditGroup($user, $department, $group),
            ],
        ];
    }

    /**
     * Aufgabe wartet auf eine andere Aufgabe (auch aus einem anderen Ressort desselben Grossanlasses).
     * Berechtigung: Bearbeitungsrecht am Nachfolger, Sichtrecht am Vorgänger.
     *
     * @return array<string, mixed>
     */
    public function addTaskDependency(Department $department, User $user, Group $group, string $taskId, string $predecessorTaskId, string $mode = DepartmentGrossanlassDependency::MODE_SEQUENTIAL): array
    {
        $task = $this->taskForWorkflow($department, $user, $group, $taskId, 'edit');
        $predecessor = $this->entityManager->getRepository(DepartmentGrossanlassTask::class)->find(trim($predecessorTaskId));
        if (!$predecessor instanceof DepartmentGrossanlassTask || $predecessor->getDepartmentId() !== $department->getId() || $predecessor->isDefault()) {
            throw new \InvalidArgumentException('Aufgabe nicht gefunden');
        }
        $this->assertCanSee($department, $user, $predecessor->getGroup());
        $this->dependencies->add($department, $user, 'task', $task->getId(), $predecessor->getId(), $mode);
        $this->entityManager->flush();

        return $this->serializeTaskFor($task, $department, $user, $group);
    }

    /**
     * Verknüpfungen einer Aufgabe lesen (Sichtrecht auf den Bauauftrag genügt; Vorgängerdetails nur mit Sichtrecht).
     *
     * @return array<string, mixed>
     */
    public function taskDependencies(Department $department, User $user, Group $group, string $taskId): array
    {
        $this->assertBauprojekt($department, $group);
        $this->assertCanSee($department, $user, $group);

        return $this->dependencyPayload($this->findTask($department, $group, $taskId), $department, $user);
    }

    /**
     * Art einer Verknüpfung ändern (nacheinander ↔ gleichzeitig); Bearbeitungsrecht an der Aufgabe, protokolliert.
     *
     * @return array<string, mixed>
     */
    public function changeTaskDependencyMode(Department $department, User $user, Group $group, string $taskId, string $dependencyId, string $mode): array
    {
        $task = $this->taskForWorkflow($department, $user, $group, $taskId, 'edit');
        $this->dependencies->changeMode($this->dependencies->find($department, 'task', $task->getId(), $dependencyId), $user, $mode);
        $this->entityManager->flush();

        return $this->serializeTaskFor($task, $department, $user, $group);
    }

    /** @return array<string, mixed> */
    public function removeTaskDependency(Department $department, User $user, Group $group, string $taskId, string $dependencyId): array
    {
        $task = $this->taskForWorkflow($department, $user, $group, $taskId, 'edit');
        $this->dependencies->remove($this->dependencies->find($department, 'task', $task->getId(), $dependencyId), $user);
        $this->entityManager->flush();

        return $this->serializeTaskFor($task, $department, $user, $group);
    }

    /**
     * Abhängigkeit vorzeitig freigeben (Bearbeitungsrecht, Begründung Pflicht, protokolliert).
     *
     * @return array<string, mixed>
     */
    public function releaseTaskDependency(Department $department, User $user, Group $group, string $taskId, string $dependencyId, string $reason): array
    {
        $task = $this->taskForWorkflow($department, $user, $group, $taskId, 'edit');
        $this->dependencies->release($this->dependencies->find($department, 'task', $task->getId(), $dependencyId), $user, $reason);
        $this->entityManager->flush();

        return $this->serializeTaskFor($task, $department, $user, $group);
    }

    /**
     * Abhängigkeiten eines Bauauftrags (er wartet auf andere Bauaufträge).
     *
     * @return array{dependencies: list<array<string, mixed>>, waiting: bool}
     */
    public function groupDependencies(Department $department, User $user, Group $group): array
    {
        $this->assertGroup($department, $group);
        $this->assertCanSee($department, $user, $group);

        return $this->groupDependencyPayload($department, $user, $group);
    }

    /** @return array{dependencies: list<array<string, mixed>>, waiting: bool} */
    public function addGroupDependency(Department $department, User $user, Group $group, string $predecessorGroupId): array
    {
        $this->assertGroup($department, $group);
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar oder ausführbar
        $this->assertCanEdit($department, $user, $group);
        $predecessor = $this->entityManager->getRepository(Group::class)->find(trim($predecessorGroupId));
        if (!$predecessor instanceof Group || $predecessor->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Bauauftrag nicht gefunden');
        }
        $this->assertCanSee($department, $user, $predecessor);
        $this->dependencies->add($department, $user, 'group', $group->getId(), $predecessor->getId());
        $this->entityManager->flush();

        return $this->groupDependencyPayload($department, $user, $group);
    }

    /** @return array{dependencies: list<array<string, mixed>>, waiting: bool} */
    public function removeGroupDependency(Department $department, User $user, Group $group, string $dependencyId): array
    {
        $this->assertGroup($department, $group);
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar oder ausführbar
        $this->assertCanEdit($department, $user, $group);
        $this->dependencies->remove($this->dependencies->find($department, 'group', $group->getId(), $dependencyId), $user);
        $this->entityManager->flush();

        return $this->groupDependencyPayload($department, $user, $group);
    }

    /** @return array{dependencies: list<array<string, mixed>>, waiting: bool} */
    public function releaseGroupDependency(Department $department, User $user, Group $group, string $dependencyId, string $reason): array
    {
        $this->assertGroup($department, $group);
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar oder ausführbar
        $this->assertCanEdit($department, $user, $group);
        $this->dependencies->release($this->dependencies->find($department, 'group', $group->getId(), $dependencyId), $user, $reason);
        $this->entityManager->flush();

        return $this->groupDependencyPayload($department, $user, $group);
    }

    /** @return array{dependencies: list<array<string, mixed>>, waiting: bool} */
    private function groupDependencyPayload(Department $department, User $user, Group $group): array
    {
        $all = $this->dependencies->activeFor($department->getId(), 'group', $group->getId());
        $openIds = array_map(static fn ($d) => $d->getId(), $this->dependencies->openOf($all));

        return [
            'dependencies' => array_map(static fn (array $d) => $d + ['open' => in_array($d['id'], $openIds, true)], $this->dependencies->serialize($all, $this->dependencyVisibility($department, $user))),
            'waiting' => $openIds !== [],
        ];
    }

    /**
     * Problem melden: Aufgabe mit Begründung blockieren (Verantwortliche oder Bearbeitungsrecht).
     *
     * @return array<string, mixed>
     */
    public function blockTask(Department $department, User $user, Group $group, string $taskId, string $reason): array
    {
        $task = $this->taskForWorkflow($department, $user, $group, $taskId, 'report');
        $this->taskWorkflow->block($task, $user, $reason);
        $this->entityManager->flush();

        return $this->serializeTaskFor($task, $department, $user, $group);
    }

    /**
     * Blockierung aufheben – nur mit Bearbeitungsrecht, nie automatisch.
     *
     * @return array<string, mixed>
     */
    public function unblockTask(Department $department, User $user, Group $group, string $taskId): array
    {
        $task = $this->taskForWorkflow($department, $user, $group, $taskId, 'unblock');
        $this->taskWorkflow->unblock($task);
        $this->entityManager->flush();

        return $this->serializeTaskFor($task, $department, $user, $group);
    }

    public function deleteTask(Department $department, User $user, Group $group, string $taskId): void
    {
        $this->assertBauprojekt($department, $group);
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar oder ausführbar
        $this->assertCanEdit($department, $user, $group);
        $task = $this->findTask($department, $group, $taskId);
        $this->acceptance->assertTasksMutable($group->getId());
        if ($task->getReworkEventId() !== null) {
            throw new \InvalidArgumentException('Nachbesserungsaufgaben können nicht gelöscht werden; sie müssen abgenommen werden, bevor der Bauauftrag erneut gemeldet werden kann');
        }
        $this->dependencies->assertDeletable($department->getId(), [$task->getId()], []);
        $this->entityManager->remove($task);
        $this->entityManager->flush();
    }

    /**
     * Personalbedarf des Bauprojekts: alle Bedarfe seiner Aufgaben inklusive Standardaufgabe «Allgemein» (ohne Helfernamen).
     *
     * @return list<array<string, mixed>>
     */
    public function listStaffingNeeds(Department $department, User $user, Group $group): array
    {
        $this->assertGroup($department, $group);
        $this->assertCanSee($department, $user, $group);
        $out = [];
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassTask::class)->findBy(['groupId' => $group->getId()]) as $task) {
            foreach ($this->staffingNeeds->listForTask($task->getId()) as $need) {
                $out[] = $this->staffingNeeds->serialize($need) + ['group_id' => $group->getId(), 'is_default_task' => $task->isDefault()];
            }
        }

        return $out;
    }

    /**
     * Neuer Personalbedarf an einer Aufgabe; ohne `task_id` am Bauprojekt selbst (Standardaufgabe «Allgemein», lazy angelegt).
     *
     * @param array<string, mixed> $data task_id, label, required_count, starts_at, ends_at
     *
     * @return array<string, mixed>
     */
    public function createStaffingNeed(Department $department, User $user, Group $group, array $data): array
    {
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar
        $this->assertBauprojekt($department, $group);
        $this->assertCanEdit($department, $user, $group);
        $taskId = trim((string) ($data['task_id'] ?? ''));
        $task = $taskId === '' ? $this->staffingNeeds->ensureDefaultTask($department, $group) : $this->findTask($department, $group, $taskId);

        return $this->staffingNeeds->serialize($this->staffingNeeds->create($department, $task, $group, $data));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateStaffingNeed(Department $department, User $user, Group $group, string $needId, array $data): array
    {
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar
        $this->assertBauprojekt($department, $group);
        $this->assertCanEdit($department, $user, $group);

        return $this->staffingNeeds->serialize($this->staffingNeeds->update($this->findStaffingNeed($department, $group, $needId), $data));
    }

    public function deleteStaffingNeed(Department $department, User $user, Group $group, string $needId): void
    {
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar
        $this->assertBauprojekt($department, $group);
        $this->assertCanEdit($department, $user, $group);
        $this->staffingNeeds->delete($this->findStaffingNeed($department, $group, $needId));
    }

    private function findStaffingNeed(Department $department, Group $group, string $needId): \App\Entity\DepartmentGrossanlassStaffingNeed
    {
        $need = $this->staffingNeeds->find($department, $needId);
        $task = $this->entityManager->find(DepartmentGrossanlassTask::class, $need->getTaskId());
        if (!$task instanceof DepartmentGrossanlassTask || $task->getGroupId() !== $group->getId()) {
            throw new \InvalidArgumentException('Personalbedarf nicht gefunden');
        }

        return $need;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createVehicleNeed(Department $department, User $user, Group $group, array $data): array
    {
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar
        $this->assertBauprojekt($department, $group);
        $this->assertCanEdit($department, $user, $group);
        $vehicle = trim((string) ($data['vehicle_label'] ?? ''));
        $task = trim((string) ($data['task_label'] ?? ''));
        if ($vehicle === '' && $task === '') {
            throw new \InvalidArgumentException('Fahrzeug oder Aufgabe ist erforderlich');
        }
        $max = (int) $this->entityManager->getRepository(DepartmentGrossanlassVehicleNeed::class)
            ->createQueryBuilder('v')
            ->select('MAX(v.sortOrder)')
            ->where('v.groupId = :groupId')
            ->setParameter('groupId', $group->getId())
            ->getQuery()
            ->getSingleScalarResult();

        $row = new DepartmentGrossanlassVehicleNeed();
        $row->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::VEHICLE_NEED,
            DepartmentGrossanlassVehicleNeed::class,
        ));
        $row->setDepartment($department);
        $row->setGroup($group);
        $row->setVehicleLabel($vehicle);
        $row->setTaskLabel($task);
        $row->setCategoryLabel($this->categoryLabel($data));
        $row->setSortOrder($max + 1);
        $this->applyVehicleSchedule($row, $data);
        $this->entityManager->persist($row);
        $this->entityManager->flush();

        return $this->serializeVehicleNeed($row);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateVehicleNeed(Department $department, User $user, Group $group, string $needId, array $data): array
    {
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar
        $this->assertBauprojekt($department, $group);
        $this->assertCanEdit($department, $user, $group);
        $row = $this->findVehicleNeed($department, $group, $needId);
        if (array_key_exists('vehicle_label', $data)) {
            $row->setVehicleLabel(trim((string) $data['vehicle_label']));
        }
        if (array_key_exists('task_label', $data)) {
            $row->setTaskLabel(trim((string) $data['task_label']));
        }
        if (array_key_exists('category_label', $data)) {
            $row->setCategoryLabel($this->categoryLabel($data));
        }
        if (trim($row->getVehicleLabel()) === '' && trim($row->getTaskLabel()) === '') {
            throw new \InvalidArgumentException('Fahrzeug oder Aufgabe ist erforderlich');
        }
        $this->applyVehicleSchedule($row, $data);
        $this->entityManager->flush();

        return $this->serializeVehicleNeed($row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listVehicleNeeds(Department $department, User $user): array
    {
        $this->access->assertGrossanlassDepartment($department);
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassVehicleNeed::class)
            ->createQueryBuilder('v')
            ->innerJoin('v.group', 'g')
            ->addSelect('g')
            ->where('v.departmentId = :departmentId')
            ->setParameter('departmentId', $department->getId())
            ->orderBy('v.startsAt', 'ASC')
            ->addOrderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rows as $row) {
            if (!$row instanceof DepartmentGrossanlassVehicleNeed) {
                continue;
            }
            try {
                $this->assertCanSee($department, $user, $row->getGroup());
            } catch (\RuntimeException) {
                continue;
            }
            $item = $this->serializeVehicleNeed($row);
            $item['group_name'] = $row->getGroup()->getName();
            $out[] = $item;
        }

        return $out;
    }

    public function deleteVehicleNeed(Department $department, User $user, Group $group, string $needId): void
    {
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar
        $this->assertBauprojekt($department, $group);
        $this->assertCanEdit($department, $user, $group);
        $row = $this->findVehicleNeed($department, $group, $needId);
        $this->entityManager->remove($row);
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function addMaterial(Department $department, User $user, Group $group, array $data): array
    {
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar
        $this->assertBauprojekt($department, $group);
        $this->assertCanEdit($department, $user, $group);
        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            throw new \InvalidArgumentException('Bezeichnung ist erforderlich');
        }
        $place = $this->places->findForGroup($department, $group->getId());
        if ($place instanceof DepartmentGrossanlassPlace && (!isset($data['location']) || trim((string) $data['location']) === '')) {
            $data['location'] = $place->getName();
        }
        $mode = strtolower(trim((string) ($data['mode'] ?? $data['source'] ?? 'wish')));
        if ($mode === 'direct' || $mode === 'fix') {
            $payload = $data;
            $payload['group_id'] = $group->getId();
            if (!isset($payload['location']) || trim((string) $payload['location']) === '') {
                $payload['location'] = $group->getName();
            }

            return $this->procurement->createLineDirect($department, $user, $payload);
        }

        $created = $this->wishes->createProjectMaterialWish($department, $user, $group, $data);
        $wishId = trim((string) ($created['id'] ?? ''));
        $wish = $wishId !== '' ? $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)->find($wishId) : null;
        if ($wish instanceof ActivityGrossanlassWishLine) {
            $this->procurement->syncWishOrganization($department, $user, $wish);
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateMaterial(Department $department, User $user, Group $group, string $lineId, array $data): array
    {
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar
        $this->assertBauprojekt($department, $group);
        $this->assertCanEdit($department, $user, $group);
        $mode = strtolower(trim((string) ($data['mode'] ?? $data['source'] ?? 'wish')));
        if ($mode === 'direct' || $mode === 'fix') {
            return $this->updateDirectMaterial($department, $group, $lineId, $data);
        }

        $updated = $this->wishes->updateProjectMaterialLine($department, $group, $lineId, $data);
        $wish = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)->find($lineId);
        if ($wish instanceof ActivityGrossanlassWishLine) {
            $this->procurement->syncWishOrganization($department, $user, $wish);
        }

        return $updated;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function updateDirectMaterial(Department $department, Group $group, string $lineId, array $data): array
    {
        $line = $this->entityManager->getRepository(ActivityGrossanlassProcurementLine::class)->find($lineId);
        if (!$line instanceof ActivityGrossanlassProcurementLine
            || $line->getDepartmentId() !== $department->getId()
            || $line->getGroupId() !== $group->getId()
        ) {
            throw new \InvalidArgumentException('Materialzeile nicht gefunden');
        }
        if ($line->getStatus() !== ActivityGrossanlassProcurementLine::STATUS_BEDARF) {
            throw new \InvalidArgumentException('Position kann nur im Status «Bedarf» bearbeitet werden');
        }
        $this->applyMaterialFields($line, $data);
        $line->touchUpdatedAt();
        $this->entityManager->flush();

        return [
            'id' => $line->getId(),
            'label' => $line->getLabel(),
            'quantity' => $line->getQuantity(),
            'quantity_unit' => $line->getQuantityUnit(),
            'pickup_need' => $line->getPickupNeed(),
            'pickup_place' => $line->getPickupPlace(),
            'return_needed' => $line->isReturnNeeded(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyMaterialFields(ActivityGrossanlassProcurementLine $line, array $data): void
    {
        if (isset($data['label'])) {
            $label = trim((string) $data['label']);
            if ($label === '') {
                throw new \InvalidArgumentException('Bezeichnung ist erforderlich');
            }
            $line->setLabel($label);
        }
        if (isset($data['quantity'])) {
            $line->setQuantity(max(1, (int) $data['quantity']));
        }
        if (array_key_exists('pickup_need', $data) || array_key_exists('pickup_place', $data)) {
            $need = strtolower(trim((string) ($data['pickup_need'] ?? '')));
            $line->setPickupNeed(in_array($need, ['can', 'must'], true) ? $need : null);
            $place = trim((string) ($data['pickup_place'] ?? ''));
            $line->setPickupPlace($line->getPickupNeed() !== null && $place !== '' ? mb_substr($place, 0, 255) : null);
        }
        if (array_key_exists('return_needed', $data)) {
            $line->setReturnNeeded(filter_var($data['return_needed'], FILTER_VALIDATE_BOOLEAN));
        }
        if (array_key_exists('quantity_unit', $data)) {
            $line->setQuantityUnit((string) $data['quantity_unit']);
        }
        if (array_key_exists('self_organized', $data)) {
            $line->setSelfOrganized(filter_var($data['self_organized'], FILTER_VALIDATE_BOOLEAN));
        }
        if (array_key_exists('wish_kind', $data)) {
            $kind = trim((string) $data['wish_kind']);
            if (!in_array($kind, ['material', 'fahrzeug'], true)) {
                throw new \InvalidArgumentException('Art ist ungültig');
            }
            $line->setWishKind($kind);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function briefing(
        Department $department,
        User $user,
        Group $group,
        ?DepartmentGrossanlassPlace $place,
    ): array {
        $groupPayload = [
            'id' => $group->getId(),
            'name' => $group->getName(),
            'department_id' => $group->getDepartmentId(),
            'parent_id' => $group->getParentId(),
            'kind' => $group->getGrossanlassKind(),
            'node_type' => $this->nodeTypeOf($group),
            'window_start' => $group->getWindowStart()?->format('Y-m-d'),
            'window_end' => $group->getWindowEnd()?->format('Y-m-d'),
            'build_status' => $group->getBuildStatus(),
            'procurement_progress' => $this->procurementProgress->buildProgressByGroup($department->getId())[$group->getId()] ?? null,
            'description' => $group->getDescription(),
        ];

        $material = $this->wishes->listMaterialWishesForGroup($department, $user, $group);
        foreach ($material as $wish) {
            $wishId = trim((string) ($wish['id'] ?? ''));
            if ($wishId !== '') {
                $this->procurement->syncUncoveredWishDemand($department, $user, $wishId);
            }
        }

        return [
            'group' => $groupPayload,
            'window_start' => $group->getWindowStart()?->format('Y-m-d'),
            'window_end' => $group->getWindowEnd()?->format('Y-m-d'),
            'build_status' => $group->getBuildStatus(),
            'description' => $group->getDescription(),
            'place' => $place instanceof DepartmentGrossanlassPlace ? $this->places->serialize($place) : null,
            'helper_need' => $this->serializeHelperNeed($group),
            'tasks' => $this->serializeTasks($department, $user, $group),
            'vehicles' => $this->serializeVehicleNeeds($group),
            'material' => $material,
            'direct_material' => $this->serializeDirectMaterial($group),
            'material_progress' => $this->materialProgress->forDepartment($department, $group)['items'],
            'packs' => $place instanceof DepartmentGrossanlassPlace
                ? $this->packs->listAtPlace($department, $place->getId())
                : [],
            'einsaetze' => $this->serializeEinsaetze($group),
            'map' => $this->mapSnippet($department, $place),
            'can_edit' => $this->canEdit($department, $user, $group),
            'can_set_build_status' => $this->access->canSetBuildStatus($user, $department, $group),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapSnippet(Department $department, ?DepartmentGrossanlassPlace $place): ?array
    {
        $map = $place?->getMap();
        if ($map === null) {
            $map = $this->entityManager->getRepository(DepartmentGrossanlassMap::class)->findOneBy(
                ['departmentId' => $department->getId()],
                ['name' => 'ASC'],
            );
        }
        if (!$map instanceof DepartmentGrossanlassMap) {
            return null;
        }
        $serialized = $this->maps->serialize($department, $map);

        return [
            'id' => $serialized['id'],
            'name' => $serialized['name'],
            'image_url' => $serialized['image_url'],
            'image_width' => $serialized['image_width'],
            'image_height' => $serialized['image_height'],
            'bounds_north' => $serialized['bounds_north'] ?? null,
            'bounds_south' => $serialized['bounds_south'] ?? null,
            'bounds_east' => $serialized['bounds_east'] ?? null,
            'bounds_west' => $serialized['bounds_west'] ?? null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeDirectMaterial(Group $group): array
    {
        $rows = $this->entityManager->getRepository(ActivityGrossanlassProcurementLine::class)
            ->findBy(
                [
                    'groupId' => $group->getId(),
                    'source' => ActivityGrossanlassProcurementLine::SOURCE_DIRECT,
                ],
                ['createdAt' => 'ASC'],
            );
        $out = [];
        foreach ($rows as $row) {
            if (!$row instanceof ActivityGrossanlassProcurementLine) {
                continue;
            }
            $out[] = [
                'id' => $row->getId(),
                'label' => $row->getLabel(),
                'quantity' => $row->getQuantity(),
                'notes' => $row->getNotes(),
                'status' => $row->getStatus(),
                'source' => 'direct',
                'self_organized' => $row->isSelfOrganized(),
                'pickup_need' => $row->getPickupNeed(),
                'pickup_place' => $row->getPickupPlace(),
                'return_needed' => $row->isReturnNeeded(),
                'quantity_unit' => $row->getQuantityUnit(),
                'wish_kind' => $row->getWishKind(),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeEinsaetze(Group $group): array
    {
        $groupId = trim($group->getId());
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassEinsatz::class)
            ->findBy(['departmentId' => $group->getDepartmentId()], ['startsAt' => 'ASC']);
        $out = [];
        foreach ($rows as $row) {
            if (!$row instanceof DepartmentGrossanlassEinsatz) {
                continue;
            }
            if (trim((string) $row->getGroupId()) !== $groupId) {
                continue;
            }
            $out[] = [
                'id' => $row->getId(),
                'qty' => $row->getQty(),
                'from' => $row->getStartsAt()->format(\DateTimeInterface::ATOM),
                'to' => $row->getEndsAt()->format(\DateTimeInterface::ATOM),
                'status' => $row->getStatus(),
                'delivery' => $row->getDelivery(),
                'who' => $row->getWho(),
                'object_id' => $row->getCommitment()?->getId(),
                'object_name' => $row->getCommitment()?->getName() ?: $row->getWho(),
                'wish_line_id' => $row->getWishLineId(),
                'kind' => $row->getKind(),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeTasks(Department $department, User $user, Group $group): array
    {
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassTask::class)
            ->findBy(['groupId' => $group->getId(), 'isDefault' => false], ['sortOrder' => 'ASC', 'createdAt' => 'ASC']);

        return $this->serializeTaskList($rows, $department, $user, $this->taskWorkflow->assignedHelperTaskIds($user, $department->getId()));
    }

    /**
     * @param list<DepartmentGrossanlassTask> $rows
     * @param list<string>                    $helperTaskIds
     *
     * @return list<array<string, mixed>>
     */
    private function serializeTaskList(array $rows, Department $department, User $user, array $helperTaskIds): array
    {
        $taskIds = array_map(static fn (DepartmentGrossanlassTask $t) => $t->getId(), $rows);
        $steps = $this->taskWorkflow->stepsByTask($taskIds);
        $events = $this->taskWorkflow->eventsByTask($taskIds);
        $canEditByGroup = [];
        $out = [];
        foreach ($rows as $row) {
            $groupId = $row->getGroupId();
            $canEditByGroup[$groupId] ??= $this->canEdit($department, $user, $row->getGroup());
            $perms = $this->taskWorkflow->permissionsFor($user, $row, $canEditByGroup[$groupId], $helperTaskIds);
            $out[] = $this->serializeTask($row, $steps[trim($row->getId())] ?? [], $perms, $events[trim($row->getId())] ?? [], $this->dependencyPayload($row, $department, $user));
        }

        return $out;
    }

    /**
     * Nachricht über die vorhandene Nachrichtenzentrale (Direktnachricht) an die Beteiligten; der Handelnde selbst
     * erhält keine. Ein Zustellproblem darf die Aufgabenaktion nicht scheitern lassen.
     *
     * @param iterable<string> $recipientIds
     */
    private function notifyTask(Department $department, User $actor, DepartmentGrossanlassTask $task, iterable $recipientIds, string $subject, string $what, ?string $note): void
    {
        $title = trim($task->getTitle()) !== '' ? $task->getTitle() : (string) $task->getDescription();
        $text = sprintf('«%s» %s (%s).', $title, $what, $actor->getProfile()?->getDisplayName() ?? 'Unbekannt');
        if ($note !== null && trim($note) !== '') {
            $text .= "\n\n" . trim($note);
        }
        $sent = [];
        foreach ($recipientIds as $recipientId) {
            $recipientId = trim((string) $recipientId);
            if ($recipientId === '' || $recipientId === $actor->getId() || isset($sent[$recipientId])) {
                continue;
            }
            $sent[$recipientId] = true;
            try {
                $recipient = $this->entityManager->getRepository(User::class)->find($recipientId);
                if ($recipient instanceof User) {
                    $this->inbox->sendUserMessage($department, $actor, $recipient, $subject . ': ' . $title, $text);
                }
            } catch (\Throwable) {
                // Benachrichtigung ist ergänzend
            }
        }
    }

    /**
     * Abhängigkeiten der Aufgabe (eigene und die ihres Bauauftrags) und «wartet auf …». Vorgängerdetails nur mit Sichtrecht.
     *
     * @return array{dependencies: list<array<string, mixed>>, waiting: bool, waiting_on: list<string>, waiting_hidden: int}
     */
    private function dependencyPayload(DepartmentGrossanlassTask $task, Department $department, User $user): array
    {
        $all = $this->dependencies->forTask($task);
        $open = $this->dependencies->openOf($all);
        $serialized = $this->dependencies->serialize($all, $this->dependencyVisibility($department, $user), $task->getId());
        $openIds = array_map(static fn ($d) => $d->getId(), $open);
        $openRows = array_filter($serialized, static fn (array $d) => in_array($d['id'], $openIds, true));

        return [
            'dependencies' => array_map(static fn (array $d) => $d + ['open' => in_array($d['id'], $openIds, true), 'inherited' => $d['kind'] === 'group'], $serialized),
            'waiting' => $open !== [],
            'waiting_on' => array_values(array_map(static fn (array $d) => (string) $d['predecessor_title'], array_filter($openRows, static fn (array $d) => $d['predecessor_visible']))),
            'waiting_hidden' => count(array_filter($openRows, static fn (array $d) => !$d['predecessor_visible'])),
        ];
    }

    /**
     * Sichtrecht des Benutzers auf den Vorgänger einer Abhängigkeit (Aufgabe → deren Bauauftrag; Bauauftrag selbst).
     *
     * @return callable(DepartmentGrossanlassDependency, bool=): bool
     */
    private function dependencyVisibility(Department $department, User $user): callable
    {
        $byGroup = [];

        return function (DepartmentGrossanlassDependency $d, bool $incoming = false) use ($department, $user, &$byGroup): bool {
            $predecessorId = trim($incoming ? $d->getSuccessorId() : $d->getPredecessorId());
            $groupId = $predecessorId;
            if ($d->getKind() === DepartmentGrossanlassDependency::KIND_TASK) {
                $task = $this->entityManager->getRepository(DepartmentGrossanlassTask::class)->find($predecessorId);
                if (!$task instanceof DepartmentGrossanlassTask) {
                    return false;
                }
                $groupId = $task->getGroupId();
            }
            if (!isset($byGroup[$groupId])) {
                $group = $this->entityManager->getRepository(Group::class)->find($groupId);
                try {
                    if (!$group instanceof Group) {
                        throw new \RuntimeException('Gruppe nicht gefunden');
                    }
                    $this->assertCanSee($department, $user, $group);
                    $byGroup[$groupId] = true;
                } catch (\Throwable) {
                    $byGroup[$groupId] = false;
                }
            }

            return $byGroup[$groupId];
        };
    }

    /** @return array<string, mixed> */
    private function serializeTaskFor(DepartmentGrossanlassTask $task, Department $department, User $user, Group $group): array
    {
        $steps = $this->taskWorkflow->stepsByTask([$task->getId()]);
        $events = $this->taskWorkflow->eventsByTask([$task->getId()]);
        $perms = $this->taskWorkflow->permissionsFor($user, $task, $this->canEdit($department, $user, $group));

        return $this->serializeTask($task, $steps[trim($task->getId())] ?? [], $perms, $events[trim($task->getId())] ?? [], $this->dependencyPayload($task, $department, $user));
    }

    /**
     * Aufgabe laden und die benötigte Aktion (edit|check|report|unblock|approve) serverseitig prüfen.
     */
    private function taskForWorkflow(Department $department, User $user, Group $group, string $taskId, string $action): DepartmentGrossanlassTask
    {
        $this->assertBauprojekt($department, $group);
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar oder ausführbar
        $task = $this->findTask($department, $group, $taskId);
        $perms = $this->taskWorkflow->permissionsFor($user, $task, $this->canEdit($department, $user, $group));
        if (!$perms[$action]) {
            throw new \RuntimeException('Keine Berechtigung für diese Aktion an der Aufgabe');
        }

        return $task;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTask(DepartmentGrossanlassTask $task, array $steps = [], ?array $permissions = null, array $events = [], array $dependencyPayload = []): array
    {
        return [
            'id' => $task->getId(),
            'group_id' => $task->getGroupId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'sort_order' => $task->getSortOrder(),
            'starts_at' => $task->getStartsAt()?->format('Y-m-d\TH:i:s'),
            'duration_minutes' => $task->getDurationMinutes(),
            'assignee_user_id' => $task->getAssigneeUserId(),
            'status' => $task->getStatus(),
            'helper_need' => $this->serializeTaskHelperNeed($task),
            'steps' => $steps,
            'blocked' => GrossanlassTaskWorkflowService::serializeBlock($task),
            'permissions' => $permissions,
            'events' => $events,
            'order_status' => $this->acceptance->status($task->getGroupId()),
            'rework' => $task->getReworkEventId() === null ? null : ['event_id' => trim($task->getReworkEventId())] + ($this->acceptance->reworkReasons([$task->getReworkEventId()])[trim($task->getReworkEventId())] ?? ['reason' => null, 'at' => null]),
            ...$dependencyPayload,
            'created_at' => $task->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyTaskSchedule(DepartmentGrossanlassTask $task, array $data): void
    {
        if (array_key_exists('starts_at', $data)) {
            $raw = $data['starts_at'];
            if ($raw === null || $raw === '') {
                $task->setStartsAt(null);
            } else {
                $parsed = new \DateTime((string) $raw);
                $task->setStartsAt($parsed);
            }
        }
        if (array_key_exists('duration_minutes', $data)) {
            $mins = $data['duration_minutes'];
            $task->setDurationMinutes($mins === null || $mins === '' ? null : max(0, (int) $mins));
        }
        if (array_key_exists('assignee_user_id', $data)) {
            $raw = $data['assignee_user_id'];
            $task->setAssigneeUserId($raw === null ? null : trim((string) $raw));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeVehicleNeeds(Group $group): array
    {
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassVehicleNeed::class)
            ->findBy(['groupId' => $group->getId()], ['sortOrder' => 'ASC', 'createdAt' => 'ASC']);
        $out = [];
        foreach ($rows as $row) {
            if ($row instanceof DepartmentGrossanlassVehicleNeed) {
                $out[] = $this->serializeVehicleNeed($row);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeVehicleNeed(DepartmentGrossanlassVehicleNeed $row): array
    {
        return [
            'id' => $row->getId(),
            'group_id' => $row->getGroupId(),
            'vehicle_label' => $row->getVehicleLabel(),
            'task_label' => $row->getTaskLabel(),
            'category_label' => $row->getCategoryLabel(),
            'sort_order' => $row->getSortOrder(),
            'starts_at' => $row->getStartsAt()?->format('Y-m-d\TH:i:s'),
            'duration_minutes' => $row->getDurationMinutes(),
            'procurement_line_id' => $row->getProcurementLineId(),
            'created_at' => $row->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function categoryLabel(array $data): ?string
    {
        $category = trim((string) ($data['category_label'] ?? ''));

        return $category === '' ? null : $category;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyVehicleSchedule(DepartmentGrossanlassVehicleNeed $row, array $data): void
    {
        if (array_key_exists('starts_at', $data)) {
            $raw = $data['starts_at'];
            $row->setStartsAt($raw === null || $raw === '' ? null : new \DateTime((string) $raw));
        }
        if (array_key_exists('duration_minutes', $data)) {
            $mins = $data['duration_minutes'];
            $row->setDurationMinutes($mins === null || $mins === '' ? null : max(0, (int) $mins));
        }
    }

    private function findVehicleNeed(Department $department, Group $group, string $needId): DepartmentGrossanlassVehicleNeed
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassVehicleNeed::class)->find($needId);
        if (
            !$row instanceof DepartmentGrossanlassVehicleNeed
            || $row->getDepartmentId() !== $department->getId()
            || $row->getGroupId() !== $group->getId()
        ) {
            throw new \InvalidArgumentException('Fahrzeugwunsch nicht gefunden');
        }

        return $row;
    }

    private function findTask(Department $department, Group $group, string $taskId): DepartmentGrossanlassTask
    {
        $task = $this->entityManager->getRepository(DepartmentGrossanlassTask::class)->find($taskId);
        if (
            !$task instanceof DepartmentGrossanlassTask
            || $task->getDepartmentId() !== $department->getId()
            || $task->getGroupId() !== $group->getId()
            || $task->isDefault() // Standardaufgabe «Allgemein» gehört nur zum Personalbedarf, nicht zur Aufgabenübersicht
        ) {
            throw new \InvalidArgumentException('Aufgabe nicht gefunden');
        }

        return $task;
    }

    /**
     * Helferbedarf: Gesamtzahl, optional aufgeteilt in Funktionen. Die Funktionen sind eine Aufteilung
     * des Gesamtbedarfs und werden nicht zusätzlich gezählt; der Gesamtbedarf ist mindestens ihre Summe.
     *
     * @return array{total: int, functions: list<array{label: string, count: int}>, unspecified: int}
     */
    public function getHelperNeed(Department $department, User $user, Group $group): array
    {
        $this->assertGroup($department, $group);
        $this->assertCanSee($department, $user, $group);

        return $this->serializeHelperNeed($group);
    }

    /**
     * @param array<string, mixed> $data total (int), functions (Liste aus label + count)
     * @return array{total: int, functions: list<array{label: string, count: int}>, unspecified: int}
     */
    public function saveHelperNeed(Department $department, User $user, Group $group, array $data): array
    {
        $this->assertGroup($department, $group);
        $this->archive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar oder ausführbar
        $this->assertCanEdit($department, $user, $group);

        ['total' => $total, 'functions' => $functions] = self::normalizeHelperNeedInput($data);
        // Bedarf am Bauprojekt selbst hängt an der Standardaufgabe «Allgemein».
        $this->staffingNeeds->syncBundle($department, $this->staffingNeeds->ensureDefaultTask($department, $group), $group, $total, $functions);

        return $this->serializeHelperNeed($group);
    }

    /**
     * Prüft Gesamtzahl und Funktionen aus der Anfrage; die Funktionen sind Teilmenge des Gesamtbedarfs (sonst HelperNeedValidationException).
     *
     * @param array<string, mixed> $data
     * @return array{total: int, functions: list<array{label: string, count: int}>}
     */
    private static function normalizeHelperNeedInput(array $data): array
    {
        $functions = [];
        foreach (is_array($data['functions'] ?? null) ? $data['functions'] : [] as $row) {
            $label = is_array($row) ? trim((string) ($row['label'] ?? '')) : '';
            $count = is_array($row) ? (int) ($row['count'] ?? 0) : 0;
            if ($label === '' || $count < 1) {
                continue;
            }
            if (mb_strlen($label) > 120 || $count > 1000) {
                throw new \InvalidArgumentException('Ungültige Funktion oder Anzahl');
            }
            $key = mb_strtolower($label);
            if (isset($functions[$key])) {
                throw new \InvalidArgumentException('Funktion doppelt: ' . $label);
            }
            $functions[$key] = ['label' => $label, 'count' => $count];
        }
        $total = (int) ($data['total'] ?? 0);
        if ($total < 0 || $total > 5000) {
            throw new \InvalidArgumentException('Ungültige Anzahl Helfer');
        }
        $functions = array_values($functions);

        $sum = (int) array_sum(array_column($functions, 'count'));
        if ($sum > $total) {
            throw new HelperNeedValidationException(sprintf(
                'Die Funktionen (%d) übersteigen den Gesamtbedarf (%d). Bitte Gesamtbedarf erhöhen oder Funktionen reduzieren.',
                $sum,
                $total,
            ));
        }

        return ['total' => $total, 'functions' => $functions];
    }

    /**
     * Speichert den Helferbedarf einer Aufgabe (Gesamtzahl + Funktionen) als Bündel ihrer Personalbedarfe.
     *
     * @param array<string, mixed> $data
     */
    private function saveTaskHelperNeed(Department $department, DepartmentGrossanlassTask $task, array $data): void
    {
        ['total' => $total, 'functions' => $functions] = self::normalizeHelperNeedInput($data);
        $this->staffingNeeds->syncBundle($department, $task, $task->getGroup(), $total, $functions);
    }

    /**
     * @return array{total: int, functions: list<array{label: string, count: int}>, unspecified: int}
     */
    private function serializeTaskHelperNeed(DepartmentGrossanlassTask $task): array
    {
        return $this->staffingNeeds->bundleOf($task, $task->getGroup());
    }

    /**
     * Zeitliche Auswertung des Aufgaben-Helferbedarfs. Aufgaben addieren sich nur, soweit sie sich zeitlich
     * überlappen: `peak` ist der höchste gleichzeitige Bedarf, `timeline` die Abschnitte mit gleichem Bedarf.
     * Aufgaben ohne Zeitblock gelten als ganztägig (überlappen alles), `undated` nennt ihren Anteil.
     *
     * @param list<array{starts_at: ?\DateTimeInterface, duration: ?int, total: int}> $tasks
     * @return array{peak: int, undated: int, timeline: list<array{from: string, to: string, count: int}>}
     */
    public static function helperDemandOverTime(array $tasks): array
    {
        $undated = 0;
        $events = [];
        foreach ($tasks as $task) {
            if ($task['total'] < 1) {
                continue;
            }
            if ($task['starts_at'] === null || $task['duration'] === null || $task['duration'] < 1) {
                $undated += $task['total'];
                continue;
            }
            $from = $task['starts_at']->getTimestamp();
            $events[] = [$from, $task['total']];
            $events[] = [$from + $task['duration'] * 60, -$task['total']];
        }
        usort($events, static fn (array $a, array $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        $timeline = [];
        $current = 0;
        $peak = 0;
        $count = count($events);
        for ($i = 0; $i < $count; ++$i) {
            $current += $events[$i][1];
            $next = $events[$i + 1][0] ?? null;
            if ($next === null || $next === $events[$i][0]) {
                continue;
            }
            $peak = max($peak, $current);
            if ($current > 0) {
                $timeline[] = [
                    'from' => date('Y-m-d\TH:i:s', $events[$i][0]),
                    'to' => date('Y-m-d\TH:i:s', $next),
                    'count' => $current + $undated,
                ];
            }
        }

        return ['peak' => $peak + $undated, 'undated' => $undated, 'timeline' => $timeline];
    }

    /**
     * @return list<array{starts_at: ?\DateTimeInterface, duration: ?int, total: int}>
     */
    private function taskNeedRows(Group $group): array
    {
        return $this->staffingNeeds->demandRows([$group->getId()])[$group->getId()] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeHelperNeed(Group $group): array
    {
        // Gesamtzahl und Funktionen des Bauprojekts selbst (Standardaufgabe «Allgemein»); der Bedarf aller
        // Aufgaben kommt zeitlich ausgewertet daneben (`from_tasks`).
        $default = $this->entityManager->getRepository(DepartmentGrossanlassTask::class)->findOneBy(['groupId' => $group->getId(), 'isDefault' => true]);
        $need = $default instanceof DepartmentGrossanlassTask
            ? $this->staffingNeeds->bundleOf($default, $group)
            : ['total' => 0, 'functions' => [], 'unspecified' => 0];
        $need['from_tasks'] = self::helperDemandOverTime($this->taskNeedRows($group));

        return $need;
    }

    /**
     * @param list<array{label: string, count: int}> $rows Zeile mit leerem Label = Gesamtbedarf
     * @return array{total: int, functions: list<array{label: string, count: int}>, unspecified: int}
     */
    public static function helperNeedFromRows(array $rows): array
    {
        $total = 0;
        $functions = [];
        foreach ($rows as $row) {
            if ($row['label'] === DepartmentGrossanlassGroupHelperNeed::TOTAL_LABEL) {
                $total = $row['count'];
            } else {
                $functions[] = $row;
            }
        }
        $sum = (int) array_sum(array_column($functions, 'count'));
        $total = max($total, $sum);

        return ['total' => $total, 'functions' => $functions, 'unspecified' => $total - $sum];
    }

    private function assertGroup(Department $department, Group $group): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if ($group->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Gruppe nicht gefunden');
        }
    }

    private function nodeTypeOf(Group $group): string
    {
        return match (strtolower(trim((string) ($group->getGrossanlassKind() ?? '')))) {
            Group::GROSSANLASS_KIND_TEILBEREICH => 'bauprojekt',
            Group::GROSSANLASS_KIND_AUFTRAG => 'auftrag',
            Group::GROSSANLASS_KIND_BEREICH => 'unterressort',
            default => 'ressort',
        };
    }

    private function assertBauprojekt(Department $department, Group $group): void
    {
        $this->assertGroup($department, $group);
    }

    private function assertCanSee(Department $department, User $user, Group $group): void
    {
        if ($this->access->canManagePlanung($user, $department)
            || $this->access->canSeeAnlassOverview($user, $department)
            || $this->access->userIsMemberInRessortBranch($user, $department->getId(), $group)
        ) {
            return;
        }
        $this->places->assertCanSeePlaces($user, $department);
    }

    private function assertCanEdit(Department $department, User $user, Group $group): void
    {
        if (!$this->canEdit($department, $user, $group)) {
            throw new \RuntimeException('Keine Berechtigung für dieses Bauprojekt');
        }
    }

    private function canEdit(Department $department, User $user, Group $group): bool
    {
        if ($this->access->canManagePlanung($user, $department)) {
            return true;
        }
        if ($this->access->userIsMemberInRessortBranch($user, $department->getId(), $group)) {
            return true;
        }

        return $this->access->canPlanSharedGroup($user, $department, $group);
    }

    private function parseOptionalDate(mixed $value, string $field): ?\DateTime
    {
        if ($value === null || $value === '') {
            return null;
        }
        $raw = substr(trim((string) $value), 0, 10);
        if ($raw === '') {
            return null;
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $raw);
        if ($dt === false) {
            throw new \InvalidArgumentException('Ungültiges Datum: ' . $field);
        }
        $dt->setTime(0, 0);

        return $dt;
    }
}
