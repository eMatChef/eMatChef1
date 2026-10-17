<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\DepartmentGrossanlassTask;
use App\Entity\DepartmentGrossanlassTaskEvent;
use App\Entity\DepartmentGrossanlassTaskStep;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Checkliste, Blockierung und Aufgabenrechte einer GA-Aufgabe.
 *
 * Rechte (serverseitig; `$canEdit` kommt aus den bestehenden Ressort-/Planungsrechten des Bauprojekts):
 *  - edit:    Struktur der Checkliste, Blockierung aufheben, alle Aufgabenfelder (wie bisher)
 *  - check:   Checklistenpunkte abhaken = edit ODER Verantwortliche/r ODER zugeteilte/r Helfer/in
 *  - report:  Status setzen und Problem melden (blockieren) = edit ODER Verantwortliche/r
 *  - unblock: nur edit
 *  - approve: Abnahme bzw. Rückgabe einer fertig gemeldeten Aufgabe = edit (Ressort-/Planungsrechte). Keine Pflicht, dass
 *             Melder und Abnehmer verschiedene Personen sind; entscheidend ist die Berechtigung.
 * Ablauf: open/progress → «Fertig gemeldet» (review, nie bei Blockierung) → Abnahme (done) oder Rückgabe mit Begründung (progress).
 * `done` entsteht nur durch Abnahme; der Verlauf liegt in {@see DepartmentGrossanlassTaskEvent}.
 * Eine blockierte Aufgabe kann weder abgehakt noch auf «in Arbeit»/«erledigt» gesetzt werden, bis sie freigegeben ist.
 */
final class GrossanlassTaskWorkflowService
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    /**
     * @return array{edit: bool, check: bool, report: bool, unblock: bool, approve: bool}
     */
    public static function permissions(bool $canEdit, bool $isResponsible, bool $isAssignedHelper): array
    {
        return [
            'edit' => $canEdit,
            'check' => $canEdit || $isResponsible || $isAssignedHelper,
            'report' => $canEdit || $isResponsible,
            'unblock' => $canEdit,
            'approve' => $canEdit,
        ];
    }

    /**
     * @return array{edit: bool, check: bool, report: bool, unblock: bool, approve: bool}
     */
    public function permissionsFor(User $user, DepartmentGrossanlassTask $task, bool $canEdit, ?array $helperTaskIds = null): array
    {
        $responsible = $task->getAssigneeUserId() !== null && $task->getAssigneeUserId() === $user->getId();
        $helper = $helperTaskIds !== null
            ? in_array($task->getId(), $helperTaskIds, true)
            : in_array($task->getId(), $this->assignedHelperTaskIds($user, $task->getDepartmentId()), true);

        return self::permissions($canEdit, $responsible, $helper);
    }

    /**
     * Aufgaben des Departments, an denen der Benutzer als Helfer (verknüpftes Konto, aktive Zuteilung) eingeteilt ist.
     *
     * @return list<string>
     */
    public function assignedHelperTaskIds(User $user, string $departmentId): array
    {
        $rows = $this->entityManager->getConnection()->fetchFirstColumn(
            "SELECT DISTINCT n.task_id
             FROM department_grossanlass_staffing_assignment a
             JOIN department_grossanlass_staffing_need n ON n.id = a.need_id
             JOIN department_grossanlass_helper h ON h.id = a.helper_id
             WHERE a.department_id = ? AND h.user_id = ? AND a.status <> 'cancelled'",
            [$departmentId, $user->getId()],
        );

        return array_map(static fn ($id) => trim((string) $id), $rows);
    }

    /**
     * @param list<string> $taskIds
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function stepsByTask(array $taskIds): array
    {
        if ($taskIds === []) {
            return [];
        }
        $rows = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(DepartmentGrossanlassTaskStep::class, 's')
            ->where('s.taskId IN (:ids)')
            ->setParameter('ids', $taskIds, ArrayParameterType::STRING)
            ->orderBy('s.sortOrder', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
        $out = [];
        foreach ($rows as $row) {
            $out[trim($row->getTaskId())][] = self::serializeStep($row);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function serializeBlock(DepartmentGrossanlassTask $task): ?array
    {
        if (!$task->isBlocked()) {
            return null;
        }

        return [
            'reason' => $task->getBlockedReason(),
            'by_user_id' => $task->getBlockedByUserId(),
            'at' => $task->getBlockedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public static function serializeStep(DepartmentGrossanlassTaskStep $step): array
    {
        return [
            'id' => $step->getId(),
            'label' => $step->getLabel(),
            'sort_order' => $step->getSortOrder(),
            'done' => $step->isDone(),
            'done_by_user_id' => $step->getDoneByUserId(),
            'done_at' => $step->getDoneAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Direkter Statuswechsel (PATCH): nur «offen»/«in Arbeit». «Fertig gemeldet» und «erledigt» entstehen ausschliesslich
     * über Fertigmeldung und Abnahme. Eine fertig gemeldete oder abgeschlossene Aufgabe wieder zu öffnen braucht das Abnahmerecht.
     */
    public function assertStatusChangeAllowed(DepartmentGrossanlassTask $task, string $status, bool $canApprove): void
    {
        if ($status === $task->getStatus()) {
            return;
        }
        if ($status === DepartmentGrossanlassTask::STATUS_REVIEW || $status === DepartmentGrossanlassTask::STATUS_DONE) {
            throw new \InvalidArgumentException('Eine Aufgabe wird über «Fertig melden» und die Abnahme abgeschlossen');
        }
        if (!$canApprove && in_array($task->getStatus(), [DepartmentGrossanlassTask::STATUS_REVIEW, DepartmentGrossanlassTask::STATUS_DONE], true)) {
            throw new \RuntimeException('Keine Berechtigung, eine fertig gemeldete oder abgeschlossene Aufgabe zu ändern');
        }
        if ($task->isBlocked() && $status !== DepartmentGrossanlassTask::STATUS_OPEN) {
            throw new \InvalidArgumentException('Die Aufgabe ist blockiert und muss zuerst freigegeben werden');
        }
    }

    /** Fertigmeldung (optionaler Kommentar). Checkliste, Fotos und Kommentar sind keine Voraussetzung. */
    public function reportDone(DepartmentGrossanlassTask $task, User $user, ?string $note): DepartmentGrossanlassTaskEvent
    {
        if ($task->isBlocked()) {
            throw new \InvalidArgumentException('Eine blockierte Aufgabe kann nicht fertig gemeldet werden');
        }
        if (!in_array($task->getStatus(), [DepartmentGrossanlassTask::STATUS_OPEN, DepartmentGrossanlassTask::STATUS_PROGRESS], true)) {
            throw new \InvalidArgumentException('Die Aufgabe ist bereits fertig gemeldet oder abgeschlossen');
        }
        $task->setStatus(DepartmentGrossanlassTask::STATUS_REVIEW);

        return $this->record($task, DepartmentGrossanlassTaskEvent::REPORTED, $user, $this->normalizeNote($note, false));
    }

    public function accept(DepartmentGrossanlassTask $task, User $user): DepartmentGrossanlassTaskEvent
    {
        $this->assertInReview($task);
        $task->setStatus(DepartmentGrossanlassTask::STATUS_DONE);

        return $this->record($task, DepartmentGrossanlassTaskEvent::ACCEPTED, $user, null);
    }

    /** Rückgabe zur Nachbearbeitung: Begründung ist Pflicht, die Aufgabe geht zurück auf «in Arbeit». */
    public function returnForRework(DepartmentGrossanlassTask $task, User $user, string $reason): DepartmentGrossanlassTaskEvent
    {
        $this->assertInReview($task);
        $reason = (string) $this->normalizeNote($reason, true);
        $task->setStatus(DepartmentGrossanlassTask::STATUS_PROGRESS);

        return $this->record($task, DepartmentGrossanlassTaskEvent::RETURNED, $user, $reason);
    }

    /**
     * Beteiligte für die Fertigmeldung: Leitungen der Gruppe und ihrer Eltern (potenzielle Abnehmer).
     *
     * @return list<string>
     */
    public function approverCandidateIds(Group $group): array
    {
        $ids = [];
        $seen = [];
        $current = $group;
        while ($current instanceof Group && !isset($seen[$current->getId()])) {
            $seen[$current->getId()] = true;
            foreach ($this->entityManager->getRepository(GroupMembership::class)->findBy(['groupId' => $current->getId(), 'role' => 'leader']) as $membership) {
                $ids[trim($membership->getUserId())] = true;
            }
            $parentId = $current->getParentId();
            $current = $parentId ? $this->entityManager->getRepository(Group::class)->find($parentId) : null;
        }

        return array_keys($ids);
    }

    /** Wer hat zuletzt fertig gemeldet (Empfänger der Abnahme-/Rückgabe-Nachricht). */
    public function lastReporterId(DepartmentGrossanlassTask $task): ?string
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassTaskEvent::class)
            ->findOneBy(['taskId' => $task->getId(), 'type' => DepartmentGrossanlassTaskEvent::REPORTED], ['createdAt' => 'DESC']);

        return $row instanceof DepartmentGrossanlassTaskEvent ? trim($row->getUserId()) : null;
    }

    /**
     * @param list<string> $taskIds
     *
     * @return array<string, list<array<string, mixed>>> Verlauf je Aufgabe, neueste zuerst
     */
    public function eventsByTask(array $taskIds): array
    {
        if ($taskIds === []) {
            return [];
        }
        $rows = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(DepartmentGrossanlassTaskEvent::class, 'e')
            ->where('e.taskId IN (:ids)')
            ->setParameter('ids', $taskIds, ArrayParameterType::STRING)
            ->orderBy('e.createdAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();
        $names = [];
        foreach (array_unique(array_map(static fn (DepartmentGrossanlassTaskEvent $e) => trim($e->getUserId()), $rows)) as $userId) {
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            $names[$userId] = $user?->getProfile()?->getDisplayName() ?? null;
        }
        $out = [];
        foreach ($rows as $row) {
            $out[trim($row->getTaskId())][] = [
                'id' => $row->getId(),
                'type' => $row->getType(),
                'by_user_id' => trim($row->getUserId()),
                'by_name' => $names[trim($row->getUserId())] ?? null,
                'note' => $row->getNote(),
                'at' => $row->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        return $out;
    }

    public function addStep(DepartmentGrossanlassTask $task, string $label): DepartmentGrossanlassTaskStep
    {
        $label = $this->normalizeLabel($label);
        $max = (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COALESCE(MAX(sort_order), -1) FROM department_grossanlass_task_step WHERE task_id = ?',
            [$task->getId()],
        );
        $step = new DepartmentGrossanlassTaskStep(
            GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::TASK_STEP, DepartmentGrossanlassTaskStep::class),
            $task->getId(),
        );
        $step->setLabel($label)->setSortOrder($max + 1);
        $this->entityManager->persist($step);

        return $step;
    }

    /**
     * @param array<string, mixed> $data label (nur edit), done (check)
     */
    public function updateStep(DepartmentGrossanlassTask $task, string $stepId, array $data, User $user, array $perms): DepartmentGrossanlassTaskStep
    {
        $step = $this->findStep($task, $stepId);
        if (array_key_exists('label', $data)) {
            if (!$perms['edit']) {
                throw new \RuntimeException('Keine Berechtigung, die Checkliste zu bearbeiten');
            }
            $step->setLabel($this->normalizeLabel((string) $data['label']));
        }
        if (array_key_exists('done', $data)) {
            if (!$perms['check']) {
                throw new \RuntimeException('Keine Berechtigung, Checklistenpunkte abzuhaken');
            }
            if ($task->isBlocked()) {
                throw new \InvalidArgumentException('Die Aufgabe ist blockiert und muss zuerst freigegeben werden');
            }
            $done = (bool) $data['done'];
            if ($done !== $step->isDone()) {
                $step->markDone($done, $user->getId());
            }
        }

        return $step;
    }

    public function deleteStep(DepartmentGrossanlassTask $task, string $stepId): void
    {
        $this->entityManager->remove($this->findStep($task, $stepId));
    }

    /**
     * @param list<string> $orderedIds vollständige Reihenfolge aller Punkte der Aufgabe
     */
    public function reorder(DepartmentGrossanlassTask $task, array $orderedIds): void
    {
        $steps = $this->entityManager->getRepository(DepartmentGrossanlassTaskStep::class)->findBy(['taskId' => $task->getId()]);
        $byId = [];
        foreach ($steps as $step) {
            $byId[trim($step->getId())] = $step;
        }
        $ids = array_values(array_map('strval', $orderedIds));
        if (count($ids) !== count($byId) || count(array_unique($ids)) !== count($ids) || array_diff($ids, array_keys($byId)) !== []) {
            throw new \InvalidArgumentException('Reihenfolge muss alle Checklistenpunkte der Aufgabe genau einmal enthalten');
        }
        foreach ($ids as $index => $id) {
            $byId[$id]->setSortOrder($index);
        }
    }

    public function block(DepartmentGrossanlassTask $task, User $user, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Begründung ist erforderlich');
        }
        if (mb_strlen($reason) > 2000) {
            throw new \InvalidArgumentException('Begründung ist zu lang');
        }
        if ($task->isBlocked()) {
            throw new \InvalidArgumentException('Die Aufgabe ist bereits blockiert');
        }
        if (in_array($task->getStatus(), [DepartmentGrossanlassTask::STATUS_REVIEW, DepartmentGrossanlassTask::STATUS_DONE], true)) {
            throw new \InvalidArgumentException('Eine fertig gemeldete oder erledigte Aufgabe kann nicht blockiert werden');
        }
        $task->block($reason, $user->getId());
    }

    public function unblock(DepartmentGrossanlassTask $task): void
    {
        $task->unblock();
    }

    private function assertInReview(DepartmentGrossanlassTask $task): void
    {
        if ($task->getStatus() !== DepartmentGrossanlassTask::STATUS_REVIEW) {
            throw new \InvalidArgumentException('Die Aufgabe ist nicht fertig gemeldet');
        }
    }

    private function record(DepartmentGrossanlassTask $task, string $type, User $user, ?string $note): DepartmentGrossanlassTaskEvent
    {
        $event = new DepartmentGrossanlassTaskEvent(
            GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::TASK_EVENT, DepartmentGrossanlassTaskEvent::class),
            $task->getId(),
            $type,
            $user->getId(),
            $note,
        );
        $this->entityManager->persist($event);

        return $event;
    }

    private function normalizeNote(?string $note, bool $required): ?string
    {
        $note = trim((string) $note);
        if ($note === '') {
            if ($required) {
                throw new \InvalidArgumentException('Begründung ist erforderlich');
            }

            return null;
        }
        if (mb_strlen($note) > 2000) {
            throw new \InvalidArgumentException('Text ist zu lang');
        }

        return $note;
    }

    private function findStep(DepartmentGrossanlassTask $task, string $stepId): DepartmentGrossanlassTaskStep
    {
        $step = $this->entityManager->getRepository(DepartmentGrossanlassTaskStep::class)->find($stepId);
        if (!$step instanceof DepartmentGrossanlassTaskStep || trim($step->getTaskId()) !== trim($task->getId())) {
            throw new \InvalidArgumentException('Checklistenpunkt nicht gefunden');
        }

        return $step;
    }

    private function normalizeLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            throw new \InvalidArgumentException('Bezeichnung ist erforderlich');
        }
        if (mb_strlen($label) > 255) {
            throw new \InvalidArgumentException('Bezeichnung ist zu lang');
        }

        return $label;
    }
}
