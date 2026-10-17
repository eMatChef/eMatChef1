<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassDependency;
use App\Entity\DepartmentGrossanlassTask;
use App\Entity\Group;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Abhängigkeiten zwischen GA-Aufgaben (Aufgabe → Aufgabe) und Bauaufträgen (Bauauftrag → Bauauftrag), auch ressortübergreifend
 * innerhalb desselben Grossanlass-Departments.
 *
 * Art `sequential` (Standard): Ein Nachfolger «wartet», solange ein Vorgänger nicht abgenommen ist: Aufgabe = `done`; Bauauftrag = formelle Gesamtabnahme
 * ({@see GrossanlassOrderAcceptanceService}), nicht schon «alle Aufgaben done». Ein wartender Bauauftrag lässt auch alle seine Aufgaben warten. Wartende Aufgaben können weder gestartet noch fertig
 * gemeldet werden – ausser die Abhängigkeit wurde mit Begründung vorzeitig freigegeben. Berechtigungen prüft der aufrufende Service.
 *
 * Art `simultaneous` (nur Aufgabe ↔ Aufgabe): reine Koordination ohne Startsperre. Die Zeitfenster beider Aufgaben werden nur
 * verglichen und als Konflikt gemeldet; geändert wird nie etwas automatisch an der anderen Aufgabe.
 */
final class GrossanlassDependencyService
{
    /** @var array<string, list<DepartmentGrossanlassDependency>> aktive Abhängigkeiten je Department (Request-Cache) */
    private array $cache = [];

    public function __construct(private EntityManagerInterface $entityManager, private GrossanlassOrderAcceptanceService $acceptance) {}

    /** Bauauftrag-/Auftrags-Knoten, die Aufgaben tragen (Blätter des Gruppenbaums). */
    public static function isLeafGroup(Group $group): bool
    {
        return in_array($group->getGrossanlassKind(), [Group::GROSSANLASS_KIND_TEILBEREICH, Group::GROSSANLASS_KIND_AUFTRAG], true);
    }

    public function add(Department $department, User $user, string $kind, string $successorId, string $predecessorId, string $mode = DepartmentGrossanlassDependency::MODE_SEQUENTIAL): DepartmentGrossanlassDependency
    {
        $kind = $this->normalizeKind($kind);
        $mode = $this->normalizeMode($mode, $kind);
        $successorId = trim($successorId);
        $predecessorId = trim($predecessorId);
        if ($predecessorId === '') {
            throw new \InvalidArgumentException('Vorgänger ist erforderlich');
        }
        if ($successorId === $predecessorId) {
            throw new \InvalidArgumentException('Eine Abhängigkeit auf sich selbst ist nicht möglich');
        }
        $this->assertExists($department, $kind, $predecessorId);
        $this->assertExists($department, $kind, $successorId);
        foreach ($this->activeFor($department->getId(), $kind) as $existing) {
            $existingSuccessor = trim($existing->getSuccessorId());
            $existingPredecessor = trim($existing->getPredecessorId());
            $sameDirection = $existingSuccessor === $successorId && $existingPredecessor === $predecessorId;
            $reverse = $existingSuccessor === $predecessorId && $existingPredecessor === $successorId;
            // Gleichzeitig ist symmetrisch: zwischen zwei Aufgaben genügt eine Verknüpfung (Art kann geändert werden).
            if ($sameDirection || ($reverse && ($existing->isSimultaneous() || $mode === DepartmentGrossanlassDependency::MODE_SIMULTANEOUS))) {
                throw new \InvalidArgumentException('Diese Aufgaben sind bereits verknüpft');
            }
        }
        if ($mode === DepartmentGrossanlassDependency::MODE_SEQUENTIAL && $this->reaches($department->getId(), $kind, $predecessorId, $successorId)) {
            throw new \InvalidArgumentException('Zyklische Abhängigkeit: Der Vorgänger wartet bereits (direkt oder indirekt) auf diese Aufgabe');
        }
        $dependency = new DepartmentGrossanlassDependency(
            GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::DEPENDENCY, DepartmentGrossanlassDependency::class),
            $department,
            $kind,
            $successorId,
            $predecessorId,
            $user->getId(),
        );
        $dependency->setInitialMode($mode);
        $this->entityManager->persist($dependency);
        $this->cache = [];

        return $dependency;
    }

    public function find(Department $department, string $kind, string $successorId, string $dependencyId): DepartmentGrossanlassDependency
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassDependency::class)->find($dependencyId);
        if (
            !$row instanceof DepartmentGrossanlassDependency
            || $row->isRemoved()
            || $row->getDepartmentId() !== $department->getId()
            || $row->getKind() !== $this->normalizeKind($kind)
            || (trim($row->getSuccessorId()) !== trim($successorId) && !($row->isSimultaneous() && trim($row->getPredecessorId()) === trim($successorId)))
        ) {
            throw new \InvalidArgumentException('Abhängigkeit nicht gefunden');
        }

        return $row;
    }

    /** Art ändern (nacheinander ↔ gleichzeitig); protokolliert. Beim Wechsel zu «nacheinander» wird auf Zyklen geprüft. */
    public function changeMode(DepartmentGrossanlassDependency $dependency, User $user, string $mode): void
    {
        $mode = $this->normalizeMode($mode, $dependency->getKind());
        if ($dependency->getMode() === $mode) {
            return;
        }
        if (
            $mode === DepartmentGrossanlassDependency::MODE_SEQUENTIAL
            && $this->reaches($dependency->getDepartmentId(), $dependency->getKind(), trim($dependency->getPredecessorId()), trim($dependency->getSuccessorId()))
        ) {
            throw new \InvalidArgumentException('Zyklische Abhängigkeit: Der Vorgänger wartet bereits (direkt oder indirekt) auf diese Aufgabe');
        }
        $dependency->changeMode($mode, $user->getId());
        $this->cache = [];
    }

    public function release(DepartmentGrossanlassDependency $dependency, User $user, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Begründung ist erforderlich');
        }
        if (mb_strlen($reason) > 2000) {
            throw new \InvalidArgumentException('Begründung ist zu lang');
        }
        if ($dependency->isSimultaneous()) {
            throw new \InvalidArgumentException('«Gleichzeitig» sperrt nichts und muss nicht freigegeben werden');
        }
        if ($dependency->isReleased()) {
            throw new \InvalidArgumentException('Die Abhängigkeit ist bereits freigegeben');
        }
        $dependency->release($user->getId(), $reason);
        $this->cache = [];
    }

    /** Ausdrückliches Entfernen; die Zeile bleibt als Protokoll (`removed_*`) erhalten. */
    public function remove(DepartmentGrossanlassDependency $dependency, User $user): void
    {
        $dependency->remove($user->getId());
        $this->cache = [];
    }

    /** Vorgänger-Bedingung erfüllt: Aufgabe = abgenommen (`done`); Bauauftrag = formell gesamt abgenommen. */
    public function isComplete(string $kind, string $id): bool
    {
        if ($kind === DepartmentGrossanlassDependency::KIND_TASK) {
            $task = $this->entityManager->getRepository(DepartmentGrossanlassTask::class)->find($id);

            return $task instanceof DepartmentGrossanlassTask && $task->getStatus() === DepartmentGrossanlassTask::STATUS_DONE;
        }
        // Bauauftrag: erst die formelle Gesamtabnahme erfüllt die Abhängigkeit (nicht mehr «alle Aufgaben done»).
        return $this->acceptance->isAccepted($id);
    }

    /**
     * Aktive (nicht entfernte) Abhängigkeiten, die diese Aufgabe betreffen: eigene und die ihres Bauauftrags.
     *
     * @return list<DepartmentGrossanlassDependency>
     */
    public function forTask(DepartmentGrossanlassTask $task): array
    {
        $taskId = trim($task->getId());
        // «Gleichzeitig» ist symmetrisch: auch Verknüpfungen, die von der anderen Aufgabe aus angelegt wurden, gehören hierher.
        $incoming = array_filter(
            $this->activeFor($task->getDepartmentId(), DepartmentGrossanlassDependency::KIND_TASK),
            static fn (DepartmentGrossanlassDependency $d) => $d->isSimultaneous() && trim($d->getPredecessorId()) === $taskId,
        );

        return array_merge(
            $this->activeFor($task->getDepartmentId(), DepartmentGrossanlassDependency::KIND_TASK, successorId: $task->getId()),
            array_values($incoming),
            $this->activeFor($task->getDepartmentId(), DepartmentGrossanlassDependency::KIND_GROUP, successorId: $task->getGroupId()),
        );
    }

    /**
     * Offene Abhängigkeiten (Vorgänger nicht abgenommen, nicht freigegeben).
     *
     * @param list<DepartmentGrossanlassDependency> $dependencies
     *
     * @return list<DepartmentGrossanlassDependency>
     */
    public function openOf(array $dependencies): array
    {
        return array_values(array_filter(
            $dependencies,
            fn (DepartmentGrossanlassDependency $d) => !$d->isSimultaneous() && !$d->isReleased() && !$this->isComplete($d->getKind(), trim($d->getPredecessorId())),
        ));
    }

    /** Start und Fertigmeldung sind bei offenen Abhängigkeiten gesperrt. */
    public function assertCanProceed(DepartmentGrossanlassTask $task, ?callable $canSee = null): void
    {
        $open = $this->openOf($this->forTask($task));
        if ($open !== []) {
            $names = [];
            $hidden = 0;
            foreach ($open as $d) {
                if ($canSee === null || $canSee($d)) {
                    $names[] = $this->titleOf($d->getKind(), trim($d->getPredecessorId()));
                } else {
                    ++$hidden;
                }
            }
            if ($hidden > 0) {
                $names[] = $hidden === 1 ? 'einer nicht sichtbaren Abhängigkeit' : $hidden . ' nicht sichtbaren Abhängigkeiten';
            }

            throw new \InvalidArgumentException('Die Aufgabe wartet auf: ' . implode(', ', $names));
        }
    }

    /**
     * Löschen verweigern, solange aktive Abhängigkeiten (als Vorgänger oder Nachfolger) bestehen.
     *
     * @param list<string> $taskIds
     * @param list<string> $groupIds
     */
    public function assertDeletable(string $departmentId, array $taskIds, array $groupIds): void
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(DepartmentGrossanlassDependency::class, 'd')
            ->where('d.departmentId = :dept')
            ->andWhere('d.removedAt IS NULL')
            ->andWhere('(d.kind = :task AND (d.successorId IN (:tasks) OR d.predecessorId IN (:tasks))) OR (d.kind = :group AND (d.successorId IN (:groups) OR d.predecessorId IN (:groups)))')
            ->setParameter('dept', $departmentId)
            ->setParameter('task', DepartmentGrossanlassDependency::KIND_TASK)
            ->setParameter('group', DepartmentGrossanlassDependency::KIND_GROUP)
            ->setParameter('tasks', $taskIds === [] ? [''] : $taskIds, ArrayParameterType::STRING)
            ->setParameter('groups', $groupIds === [] ? [''] : $groupIds, ArrayParameterType::STRING);
        if ((int) $qb->getQuery()->getSingleScalarResult() > 0) {
            throw new \RuntimeException('Löschen nicht möglich: Es bestehen noch Abhängigkeiten. Bitte zuerst die Abhängigkeiten ausdrücklich entfernen.');
        }
    }

    /** @return list<string> */
    public function taskIdsOfGroups(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        return array_map(
            static fn ($id) => trim((string) $id),
            $this->entityManager->getConnection()->fetchFirstColumn(
                'SELECT id FROM department_grossanlass_task WHERE group_id IN (?)',
                [$groupIds],
                [ArrayParameterType::STRING],
            ),
        );
    }

    /**
     * Titel und Details des Vorgängers nur, wenn `$canSee` (Sichtrecht des Benutzers) zustimmt; sonst nur der neutrale Zustand
     * (`predecessor_visible = false`, `open`), ohne Titel, ID oder Abnahmestand.
     *
     * @param list<DepartmentGrossanlassDependency> $dependencies
     * @param (callable(DepartmentGrossanlassDependency, bool=): bool)|null $canSee `$incoming` = die andere Aufgabe ist der Anleger (nur «gleichzeitig»)
     *
     * @return list<array<string, mixed>>
     */
    public function serialize(array $dependencies, ?callable $canSee = null, ?string $focusTaskId = null): array
    {
        $out = [];
        $names = [];
        $nameOf = function (?string $userId) use (&$names): ?string {
            $userId = $userId !== null ? trim($userId) : null;
            if ($userId === null) {
                return null;
            }
            if (!array_key_exists($userId, $names)) {
                $names[$userId] = $this->entityManager->getRepository(User::class)->find($userId)?->getProfile()?->getDisplayName();
            }

            return $names[$userId];
        };
        foreach ($dependencies as $d) {
            $incoming = $focusTaskId !== null && $d->isSimultaneous() && trim($d->getPredecessorId()) === trim($focusTaskId);
            // Eingehende «gleichzeitig»-Verknüpfung: die andere Aufgabe ist der Anleger, sichtbar als «verknüpfte Aufgabe».
            $predecessorId = trim($incoming ? $d->getSuccessorId() : $d->getPredecessorId());
            $releasedBy = $d->getReleasedByUserId() !== null ? trim($d->getReleasedByUserId()) : null;
            $nameOf($releasedBy);
            $visible = $canSee === null || $canSee($d, $incoming);
            $out[] = [
                'id' => $d->getId(),
                'kind' => $d->getKind(),
                'mode' => $d->getMode(),
                'incoming' => $incoming,
                'schedule' => $d->isSimultaneous() && $visible && $focusTaskId !== null ? $this->scheduleOf(trim($focusTaskId), $predecessorId) : null,
                'created_by_name' => $nameOf($d->getCreatedByUserId()),
                'mode_changed' => $d->getModeChangedAt() !== null ? [
                    'by_name' => $nameOf($d->getModeChangedByUserId()),
                    'at' => $d->getModeChangedAt()->format(\DateTimeInterface::ATOM),
                ] : null,
                'successor_id' => trim($d->getSuccessorId()),
                'predecessor_visible' => $visible,
                'predecessor_id' => $visible ? $predecessorId : null,
                'predecessor_title' => $visible ? $this->titleOf($d->getKind(), $predecessorId) : null,
                'predecessor_complete' => $visible ? $this->isComplete($d->getKind(), $predecessorId) : null,
                'released' => $d->isReleased() ? [
                    'by_user_id' => $releasedBy,
                    'by_name' => $nameOf($releasedBy),
                    'reason' => $d->getReleaseReason(),
                    'at' => $d->getReleasedAt()?->format(\DateTimeInterface::ATOM),
                ] : null,
                'created_at' => $d->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        return $out;
    }

    /**
     * Aktive Abhängigkeiten eines Nachfolgers (Aufgabe oder Bauauftrag).
     *
     * @return list<DepartmentGrossanlassDependency>
     */
    public function activeFor(string $departmentId, string $kind, ?string $successorId = null): array
    {
        $this->cache[$departmentId] ??= array_values($this->entityManager->getRepository(DepartmentGrossanlassDependency::class)
            ->findBy(['departmentId' => $departmentId, 'removedAt' => null], ['createdAt' => 'ASC', 'id' => 'ASC']));

        return array_values(array_filter(
            $this->cache[$departmentId],
            static fn (DepartmentGrossanlassDependency $d) => $d->getKind() === $kind && ($successorId === null || trim($d->getSuccessorId()) === $successorId),
        ));
    }

    /**
     * Zeitvergleich zweier Aufgaben für «gleichzeitig». Konflikt `no_overlap`: die Zeitfenster überschneiden sich nicht;
     * `unscheduled`: mindestens eine Aufgabe hat keine Startzeit. Die Aufgaben werden dabei nie verändert.
     *
     * @return array<string, mixed>|null
     */
    private function scheduleOf(string $ownId, string $partnerId): ?array
    {
        $repository = $this->entityManager->getRepository(DepartmentGrossanlassTask::class);
        $own = $repository->find($ownId);
        $partner = $repository->find($partnerId);
        if (!$own instanceof DepartmentGrossanlassTask || !$partner instanceof DepartmentGrossanlassTask) {
            return null;
        }
        $window = static function (DepartmentGrossanlassTask $t): ?array {
            $start = $t->getStartsAt();
            if ($start === null) {
                return null;
            }

            return [$start->getTimestamp(), $start->getTimestamp() + max(0, (int) $t->getDurationMinutes()) * 60];
        };
        $a = $window($own);
        $b = $window($partner);
        $conflict = match (true) {
            $a === null || $b === null => 'unscheduled',
            $a[0] <= $b[1] && $b[0] <= $a[1] => 'none',
            default => 'no_overlap',
        };

        return [
            'conflict' => $conflict,
            'own' => ['starts_at' => $own->getStartsAt()?->format('Y-m-d\TH:i:s'), 'duration_minutes' => $own->getDurationMinutes()],
            'partner' => ['starts_at' => $partner->getStartsAt()?->format('Y-m-d\TH:i:s'), 'duration_minutes' => $partner->getDurationMinutes()],
            'start_diff_minutes' => $a !== null && $b !== null ? (int) round(($b[0] - $a[0]) / 60) : null,
        ];
    }

    /** Ob `$from` über aktive Vorgänger-Kanten `$target` erreicht (Zyklusprüfung; freigegebene Kanten zählen mit). */
    private function reaches(string $departmentId, string $kind, string $from, string $target): bool
    {
        $seen = [];
        $stack = [$from];
        while ($stack !== []) {
            $current = array_pop($stack);
            if ($current === $target) {
                return true;
            }
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            foreach ($this->activeFor($departmentId, $kind, successorId: $current) as $edge) {
                if ($edge->isSimultaneous()) {
                    continue; // «gleichzeitig» sperrt nichts und kann keinen Wartezyklus bilden
                }
                $stack[] = trim($edge->getPredecessorId());
            }
        }

        return false;
    }

    private function assertExists(Department $department, string $kind, string $id): void
    {
        if ($kind === DepartmentGrossanlassDependency::KIND_TASK) {
            $task = $this->entityManager->getRepository(DepartmentGrossanlassTask::class)->find($id);
            if (!$task instanceof DepartmentGrossanlassTask || $task->getDepartmentId() !== $department->getId() || $task->isDefault()) {
                throw new \InvalidArgumentException('Aufgabe nicht gefunden');
            }
            if ($task->getGroup()->isArchived()) {
                throw new \InvalidArgumentException('Der Bauauftrag dieser Aufgabe ist archiviert');
            }

            return;
        }
        $group = $this->entityManager->getRepository(Group::class)->find($id);
        if (!$group instanceof Group || $group->getDepartmentId() !== $department->getId() || !self::isLeafGroup($group)) {
            throw new \InvalidArgumentException('Bauauftrag nicht gefunden');
        }
        if ($group->isArchived()) {
            throw new \InvalidArgumentException('Der Bauauftrag ist archiviert');
        }
    }

    /** Titel des Nachfolgers (Aufgabe oder Bauauftrag) für Hinweise. */
    public function titleOfSuccessor(DepartmentGrossanlassDependency $dependency): string
    {
        return $this->titleOf($dependency->getKind(), trim($dependency->getSuccessorId()));
    }

    private function titleOf(string $kind, string $id): string
    {
        if ($kind === DepartmentGrossanlassDependency::KIND_TASK) {
            $task = $this->entityManager->getRepository(DepartmentGrossanlassTask::class)->find($id);
            if (!$task instanceof DepartmentGrossanlassTask) {
                return '?';
            }

            return trim($task->getTitle()) !== '' ? $task->getTitle() : (string) $task->getDescription();
        }
        $group = $this->entityManager->getRepository(Group::class)->find($id);

        return $group instanceof Group ? $group->getName() : '?';
    }

    private function normalizeMode(string $mode, string $kind): string
    {
        if (!in_array($mode, [DepartmentGrossanlassDependency::MODE_SEQUENTIAL, DepartmentGrossanlassDependency::MODE_SIMULTANEOUS], true)) {
            throw new \InvalidArgumentException('Ungültige Art der Abhängigkeit');
        }
        if ($mode === DepartmentGrossanlassDependency::MODE_SIMULTANEOUS && $kind !== DepartmentGrossanlassDependency::KIND_TASK) {
            throw new \InvalidArgumentException('«Gleichzeitig» ist nur zwischen Aufgaben möglich');
        }

        return $mode;
    }

    private function normalizeKind(string $kind): string
    {
        if (!in_array($kind, [DepartmentGrossanlassDependency::KIND_TASK, DepartmentGrossanlassDependency::KIND_GROUP], true)) {
            throw new \InvalidArgumentException('Ungültiger Abhängigkeitstyp');
        }

        return $kind;
    }
}
