<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassDependency;
use App\Entity\DepartmentGrossanlassOrderArchiveLog;
use App\Entity\Group;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Bauaufträge archivieren statt löschen.
 *
 * Physisch gelöscht werden darf ein Bauauftrag nur, solange noch keine Arbeit ausgeführt wurde und nichts Relevantes protokolliert
 * ist ({@see self::historyReasons()}). Sonst wird er mit Begründung archiviert (Soft-Delete): Aufgaben, Abnahmen, Rückweisungen,
 * Nachbesserungen und Historie bleiben vollständig erhalten; ein archivierter Bauauftrag ist nicht mehr bearbeitbar oder ausführbar
 * und wird aus aktiven Listen ausgeblendet (Filter «archivierte anzeigen»). Berechtigte Personen können ihn wiederherstellen.
 * Archivieren löst nie eine Abhängigkeit still auf: wartet noch jemand auf einen nicht abgenommenen Bauauftrag, wird abgelehnt.
 * Berechtigungen prüft der aufrufende Service.
 */
final class GrossanlassOrderArchiveService
{
    /** Gründe, die das physische Löschen verbieten (Schlüssel → Anzeigetext). */
    public const REASON_LABELS = [
        'work' => 'Aufgaben wurden begonnen oder erledigt',
        'checklist' => 'Checklistenpunkte wurden abgehakt',
        'blocked' => 'Aufgaben wurden blockiert',
        'task_events' => 'Aufgaben wurden fertig gemeldet, abgenommen oder zurückgegeben',
        'order_events' => 'Gesamtabnahme oder Rückweisung wurde protokolliert',
        'rework' => 'Nachbesserungsaufgaben bestehen',
        'dependencies' => 'Abhängigkeiten sind protokolliert',
        'archive' => 'Der Bauauftrag wurde bereits archiviert',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassOrderAcceptanceService $acceptance,
        private GrossanlassDependencyService $dependencies,
    ) {}

    /**
     * Gründe je Bauauftrag, warum nicht physisch gelöscht werden darf (leer/fehlend = noch löschbar).
     *
     * @return array<string, list<string>>
     */
    public function historyReasons(string $departmentId): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT DISTINCT group_id, reason FROM (
                SELECT t.group_id, 'work' AS reason FROM department_grossanlass_task t
                    WHERE t.department_id = :d AND t.is_default = false AND t.status <> 'open'
                UNION ALL SELECT t.group_id, 'checklist' FROM department_grossanlass_task_step s
                    JOIN department_grossanlass_task t ON t.id = s.task_id WHERE t.department_id = :d AND s.is_done = true
                UNION ALL SELECT t.group_id, 'blocked' FROM department_grossanlass_task t
                    WHERE t.department_id = :d AND t.is_default = false AND t.blocked_reason IS NOT NULL
                UNION ALL SELECT t.group_id, 'task_events' FROM department_grossanlass_task_event e
                    JOIN department_grossanlass_task t ON t.id = e.task_id WHERE t.department_id = :d
                UNION ALL SELECT e.group_id, 'order_events' FROM department_grossanlass_order_event e WHERE e.department_id = :d
                UNION ALL SELECT t.group_id, 'rework' FROM department_grossanlass_task t
                    WHERE t.department_id = :d AND t.rework_event_id IS NOT NULL
                UNION ALL SELECT x.successor_id, 'dependencies' FROM department_grossanlass_dependency x
                    WHERE x.department_id = :d AND x.kind = 'group'
                UNION ALL SELECT x.predecessor_id, 'dependencies' FROM department_grossanlass_dependency x
                    WHERE x.department_id = :d AND x.kind = 'group'
                UNION ALL SELECT t.group_id, 'dependencies' FROM department_grossanlass_dependency x
                    JOIN department_grossanlass_task t ON t.id = x.successor_id WHERE x.department_id = :d AND x.kind = 'task'
                UNION ALL SELECT t.group_id, 'dependencies' FROM department_grossanlass_dependency x
                    JOIN department_grossanlass_task t ON t.id = x.predecessor_id WHERE x.department_id = :d AND x.kind = 'task'
                UNION ALL SELECT l.group_id, 'archive' FROM department_grossanlass_order_archive_log l WHERE l.department_id = :d
            ) h",
            ['d' => $departmentId],
        );
        $out = [];
        foreach ($rows as $row) {
            $out[trim((string) $row['group_id'])][] = (string) $row['reason'];
        }

        return $out;
    }

    /**
     * Physisches Löschen nur ohne Arbeit und ohne relevante Protokolle; sonst stattdessen archivieren.
     */
    public function assertPhysicallyDeletable(Group $group): void
    {
        if (!GrossanlassOrderAcceptanceService::isOrderGroup($group)) {
            return;
        }
        $reasons = $this->historyReasons($group->getDepartmentId())[trim($group->getId())] ?? [];
        if ($reasons !== []) {
            $labels = array_map(static fn (string $r) => self::REASON_LABELS[$r] ?? $r, $reasons);

            throw new \RuntimeException(sprintf('Der Bauauftrag «%s» kann nicht gelöscht werden (%s). Bitte stattdessen archivieren.', $group->getName(), implode('; ', $labels)));
        }
    }

    /** Archivierte Bauaufträge sind nicht mehr bearbeitbar oder ausführbar. */
    public function assertNotArchived(Group $group): void
    {
        if ($group->isArchived()) {
            throw new \InvalidArgumentException('Der Bauauftrag ist archiviert. Bitte zuerst wiederherstellen, um ihn zu bearbeiten.');
        }
    }

    public function archive(Department $department, Group $group, User $user, string $reason): DepartmentGrossanlassOrderArchiveLog
    {
        $this->assertOrder($group);
        $this->assertNotArchivedForAction($group);
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Begründung ist erforderlich');
        }
        if (mb_strlen($reason) > 2000) {
            throw new \InvalidArgumentException('Begründung ist zu lang');
        }
        if ($this->acceptance->status($group->getId()) === GrossanlassOrderAcceptanceService::REVIEW) {
            throw new \InvalidArgumentException('Der Bauauftrag ist zur Abnahme gemeldet. Bitte zuerst die Abnahme entscheiden oder die Meldung zurückziehen.');
        }
        $waiting = $this->waitingOnThisOrder($department, $group);
        if ($waiting !== []) {
            throw new \InvalidArgumentException(sprintf(
                'Andere Aufgaben oder Bauaufträge warten noch auf diesen Bauauftrag (%s). Bitte die Abhängigkeit zuerst ausdrücklich entfernen oder freigeben.',
                implode(', ', $waiting),
            ));
        }
        $group->archive($user->getId(), $reason);

        return $this->log($department, $group, DepartmentGrossanlassOrderArchiveLog::ARCHIVED, $user, $reason);
    }

    public function restore(Department $department, Group $group, User $user, ?string $note): DepartmentGrossanlassOrderArchiveLog
    {
        $this->assertOrder($group);
        if (!$group->isArchived()) {
            throw new \InvalidArgumentException('Der Bauauftrag ist nicht archiviert');
        }
        $note = trim((string) $note);
        $group->restore();

        return $this->log($department, $group, DepartmentGrossanlassOrderArchiveLog::RESTORED, $user, $note === '' ? null : $note);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Group $group): array
    {
        $names = [];
        $log = [];
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassOrderArchiveLog::class)->findBy(['groupId' => $group->getId()], ['createdAt' => 'DESC', 'id' => 'DESC']) as $row) {
            $userId = trim($row->getUserId());
            $names[$userId] ??= $this->entityManager->getRepository(User::class)->find($userId)?->getProfile()?->getDisplayName();
            $log[] = [
                'id' => $row->getId(),
                'type' => $row->getType(),
                'by_user_id' => $userId,
                'by_name' => $names[$userId],
                'note' => $row->getNote(),
                'at' => $row->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }
        $reasons = $this->historyReasons($group->getDepartmentId())[trim($group->getId())] ?? [];

        return [
            'archived' => $group->isArchived(),
            'archived_at' => $group->getArchivedAt()?->format(\DateTimeInterface::ATOM),
            'archive_reason' => $group->getArchiveReason(),
            'has_history' => $reasons !== [],
            'history_reasons' => $reasons,
            'log' => $log,
        ];
    }

    /**
     * Titel der Aufgaben/Bauaufträge, die auf diesen (noch nicht abgenommenen) Bauauftrag oder seine Aufgaben warten.
     *
     * @return list<string>
     */
    private function waitingOnThisOrder(Department $department, Group $group): array
    {
        $taskIds = array_map('trim', $this->dependencies->taskIdsOfGroups([$group->getId()]));
        $names = [];
        foreach ($this->dependencies->activeFor($department->getId(), DepartmentGrossanlassDependency::KIND_GROUP) as $dependency) {
            if (trim($dependency->getPredecessorId()) === trim($group->getId()) && $this->dependencies->openOf([$dependency]) !== []) {
                $names[] = $this->dependencies->titleOfSuccessor($dependency);
            }
        }
        foreach ($this->dependencies->activeFor($department->getId(), DepartmentGrossanlassDependency::KIND_TASK) as $dependency) {
            if (in_array(trim($dependency->getPredecessorId()), $taskIds, true) && $this->dependencies->openOf([$dependency]) !== []) {
                $names[] = $this->dependencies->titleOfSuccessor($dependency);
            }
        }

        return array_values(array_unique($names));
    }

    private function assertOrder(Group $group): void
    {
        if (!GrossanlassOrderAcceptanceService::isOrderGroup($group)) {
            throw new \InvalidArgumentException('Archivieren gibt es nur für Bauaufträge, nicht für Ressort- oder Strukturgruppen');
        }
    }

    private function assertNotArchivedForAction(Group $group): void
    {
        if ($group->isArchived()) {
            throw new \InvalidArgumentException('Der Bauauftrag ist bereits archiviert');
        }
    }

    private function log(Department $department, Group $group, string $type, User $user, ?string $note): DepartmentGrossanlassOrderArchiveLog
    {
        $row = new DepartmentGrossanlassOrderArchiveLog(
            GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::ARCHIVE_LOG, DepartmentGrossanlassOrderArchiveLog::class),
            $department,
            $group,
            $type,
            $user->getId(),
            $note,
        );
        $this->entityManager->persist($row);

        return $row;
    }
}
