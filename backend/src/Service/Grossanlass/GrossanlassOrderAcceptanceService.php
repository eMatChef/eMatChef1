<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassOrderEvent;
use App\Entity\DepartmentGrossanlassTask;
use App\Entity\Group;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Formelle Gesamtabnahme eines Bauauftrags (Blattknoten mit Aufgaben; keine Ressort-/Strukturgruppen), getrennt von der
 * Abnahme der Einzelaufgaben.
 *
 * Zustand = letztes Ereignis: reported → `review`, accepted → `accepted`, sonst `open`. Angeboten wird die Meldung erst, wenn der
 * Bauauftrag Aufgaben hat und alle (ausser der internen Standardaufgabe «Allgemein») `done` sind. Es gibt keine automatische
 * Abnahme. Eine Rückweisung setzt keine Einzelaufgabe zurück.
 *
 * Absicherung: Solange ein Bauauftrag zur Abnahme gemeldet oder abgenommen ist, sind Änderungen, die die Abnahme ungültig machen
 * würden (Aufgabe anlegen/löschen, Aufgabe wieder öffnen), gesperrt. Dafür gibt es ausdrücklich «Meldung zurückziehen» bzw.
 * «Abnahme widerrufen» (mit Begründung, protokolliert). Berechtigungen prüft der aufrufende Service.
 */
final class GrossanlassOrderAcceptanceService
{
    public const OPEN = 'open';
    public const REVIEW = 'review';
    public const ACCEPTED = 'accepted';

    public function __construct(private EntityManagerInterface $entityManager) {}

    /** Nur echte Bauaufträge (Bauprojekt/Auftrag), keine Ressort-/Bereichs-Knoten. */
    public static function isOrderGroup(Group $group): bool
    {
        return in_array($group->getGrossanlassKind(), [Group::GROSSANLASS_KIND_TEILBEREICH, Group::GROSSANLASS_KIND_AUFTRAG], true);
    }

    /** Zustand aus dem letzten Ereignis; ohne Ereignis `open`. */
    public static function statusFromLastType(?string $type): string
    {
        return match ($type) {
            DepartmentGrossanlassOrderEvent::REPORTED => self::REVIEW,
            DepartmentGrossanlassOrderEvent::ACCEPTED => self::ACCEPTED,
            default => self::OPEN,
        };
    }

    public function status(string $groupId): string
    {
        $type = $this->entityManager->getConnection()->fetchOne(
            'SELECT type FROM department_grossanlass_order_event WHERE group_id = ? ORDER BY seq DESC LIMIT 1',
            [$groupId],
        );

        return self::statusFromLastType($type === false ? null : (string) $type);
    }

    public function isAccepted(string $groupId): bool
    {
        return $this->status($groupId) === self::ACCEPTED;
    }

    /**
     * @param list<string> $taskStatuses Status der relevanten Aufgaben
     */
    public static function eligibleFor(array $taskStatuses): bool
    {
        return $taskStatuses !== [] && count(array_filter($taskStatuses, static fn (string $s) => $s !== DepartmentGrossanlassTask::STATUS_DONE)) === 0;
    }

    /** @return array{total: int, done: int} */
    public function taskCounts(string $groupId): array
    {
        $rows = $this->taskStatuses($groupId);

        return ['total' => count($rows), 'done' => count(array_filter($rows, static fn (string $s) => $s === DepartmentGrossanlassTask::STATUS_DONE))];
    }

    public function report(Department $department, Group $group, User $user): DepartmentGrossanlassOrderEvent
    {
        $this->assertOrder($group);
        $this->assertStatus($group, self::OPEN, 'Der Bauauftrag ist bereits zur Abnahme gemeldet oder abgenommen');
        $this->assertEligible($group);

        return $this->record($department, $group, DepartmentGrossanlassOrderEvent::REPORTED, $user, null);
    }

    public function withdraw(Department $department, Group $group, User $user): DepartmentGrossanlassOrderEvent
    {
        $this->assertOrder($group);
        $this->assertStatus($group, self::REVIEW, 'Der Bauauftrag ist nicht zur Abnahme gemeldet');

        return $this->record($department, $group, DepartmentGrossanlassOrderEvent::WITHDRAWN, $user, null);
    }

    public function accept(Department $department, Group $group, User $user): DepartmentGrossanlassOrderEvent
    {
        $this->assertOrder($group);
        $this->assertStatus($group, self::REVIEW, 'Der Bauauftrag ist nicht zur Abnahme gemeldet');
        $this->assertEligible($group); // zwischenzeitliche Änderungen: nicht mehr alle Aufgaben erledigt

        return $this->record($department, $group, DepartmentGrossanlassOrderEvent::ACCEPTED, $user, null);
    }

    /** Rückweisung mit Pflicht-Begründung; Einzelaufgaben bleiben unverändert abgenommen. */
    public function returnOrder(Department $department, Group $group, User $user, string $reason): DepartmentGrossanlassOrderEvent
    {
        $this->assertOrder($group);
        $this->assertStatus($group, self::REVIEW, 'Der Bauauftrag ist nicht zur Abnahme gemeldet');

        return $this->record($department, $group, DepartmentGrossanlassOrderEvent::RETURNED, $user, $this->requiredNote($reason));
    }

    /** Abnahme widerrufen (Pflicht-Begründung), damit Aufgaben wieder geändert werden dürfen. */
    public function reopen(Department $department, Group $group, User $user, string $reason): DepartmentGrossanlassOrderEvent
    {
        $this->assertOrder($group);
        $this->assertStatus($group, self::ACCEPTED, 'Der Bauauftrag ist nicht abgenommen');

        return $this->record($department, $group, DepartmentGrossanlassOrderEvent::REOPENED, $user, $this->requiredNote($reason));
    }

    /**
     * Schutz der Abnahme: Aufgaben anlegen/löschen/wieder öffnen ist nur bei offenem Bauauftrag möglich.
     */
    public function assertTasksMutable(string $groupId): void
    {
        $status = $this->status($groupId);
        if ($status !== self::OPEN) {
            throw new \InvalidArgumentException(
                $status === self::ACCEPTED
                    ? 'Der Bauauftrag ist abgenommen. Bitte zuerst die Gesamtabnahme widerrufen, um Aufgaben zu ändern.'
                    : 'Der Bauauftrag ist zur Abnahme gemeldet. Bitte zuerst die Meldung zurückziehen, um Aufgaben zu ändern.',
            );
        }
    }

    /** Löschen eines Bauauftrags mit laufender oder erfolgter Gesamtabnahme verweigern. */
    public function assertDeletable(array $groupIds): void
    {
        foreach ($groupIds as $groupId) {
            if ($this->status($groupId) !== self::OPEN) {
                throw new \RuntimeException('Löschen nicht möglich: Der Bauauftrag ist zur Abnahme gemeldet oder abgenommen.');
            }
        }
    }

    /**
     * Nachbesserungsaufgaben dürfen nur an eine Rückweisung dieses Bauauftrags geknüpft werden.
     */
    public function assertReworkEvent(Group $group, string $eventId): DepartmentGrossanlassOrderEvent
    {
        $event = $this->entityManager->getRepository(DepartmentGrossanlassOrderEvent::class)->find(trim($eventId));
        if (
            !$event instanceof DepartmentGrossanlassOrderEvent
            || trim($event->getGroupId()) !== trim($group->getId())
            || $event->getType() !== DepartmentGrossanlassOrderEvent::RETURNED
        ) {
            throw new \InvalidArgumentException('Nachbesserungen können nur zu einer Rückweisung dieses Bauauftrags angelegt werden');
        }

        return $event;
    }

    /**
     * Rückweisungsgrund je Ereignis (für die Kennzeichnung von Nachbesserungsaufgaben).
     *
     * @param list<string> $eventIds
     *
     * @return array<string, array{reason: ?string, at: string}>
     */
    public function reworkReasons(array $eventIds): array
    {
        $eventIds = array_values(array_unique(array_filter(array_map('trim', $eventIds))));
        if ($eventIds === []) {
            return [];
        }
        $out = [];
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassOrderEvent::class)->findBy(['id' => $eventIds]) as $event) {
            $out[trim($event->getId())] = ['reason' => $event->getNote(), 'at' => $event->getCreatedAt()->format(\DateTimeInterface::ATOM)];
        }

        return $out;
    }

    /**
     * Verantwortliche der bisherigen Aufgaben des Bauauftrags, häufigste zuerst (nur als Vorschlag für Nachbesserungen).
     *
     * @return list<string>
     */
    public function suggestedAssigneeIds(string $groupId): array
    {
        return array_map(
            static fn ($id) => trim((string) $id),
            $this->entityManager->getConnection()->fetchFirstColumn(
                'SELECT assignee_user_id FROM department_grossanlass_task
                 WHERE group_id = ? AND is_default = false AND assignee_user_id IS NOT NULL
                 GROUP BY assignee_user_id ORDER BY COUNT(*) DESC, assignee_user_id',
                [$groupId],
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Group $group): array
    {
        $statuses = $this->taskStatuses($group->getId());
        $status = $this->status($group->getId());
        $events = $this->entityManager->getRepository(DepartmentGrossanlassOrderEvent::class)->findBy(['groupId' => $group->getId()], ['seq' => 'DESC']);
        $reworkByEvent = [];
        foreach ($this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, title, status, assignee_user_id, rework_event_id FROM department_grossanlass_task
             WHERE group_id = ? AND is_default = false AND rework_event_id IS NOT NULL ORDER BY sort_order, created_at',
            [$group->getId()],
        ) as $row) {
            $reworkByEvent[trim((string) $row['rework_event_id'])][] = [
                'id' => trim((string) $row['id']),
                'title' => (string) $row['title'],
                'status' => (string) $row['status'],
                'assignee_user_id' => $row['assignee_user_id'] !== null ? trim((string) $row['assignee_user_id']) : null,
            ];
        }
        $names = [];
        $log = [];
        foreach ($events as $event) {
            $userId = trim($event->getUserId());
            if (!array_key_exists($userId, $names)) {
                $names[$userId] = $this->entityManager->getRepository(User::class)->find($userId)?->getProfile()?->getDisplayName();
            }
            $log[] = [
                'id' => $event->getId(),
                'type' => $event->getType(),
                'by_user_id' => $userId,
                'by_name' => $names[$userId],
                'note' => $event->getNote(),
                'at' => $event->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'rework_tasks' => $event->getType() === DepartmentGrossanlassOrderEvent::RETURNED ? ($reworkByEvent[trim($event->getId())] ?? []) : null,
            ];
        }

        return [
            'applicable' => self::isOrderGroup($group),
            'status' => $status,
            'tasks_total' => count($statuses),
            'tasks_done' => count(array_filter($statuses, static fn (string $s) => $s === DepartmentGrossanlassTask::STATUS_DONE)),
            'eligible' => self::eligibleFor($statuses),
            'rework_open' => $this->openReworkCount($group->getId()),
            'events' => $log,
        ];
    }

    /** Noch nicht abgenommene Nachbesserungsaufgaben (verhindern die erneute Meldung wie jede offene Aufgabe). */
    private function openReworkCount(string $groupId): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM department_grossanlass_task WHERE group_id = ? AND is_default = false AND rework_event_id IS NOT NULL AND status <> 'done'",
            [$groupId],
        );
    }

    /** @return list<string> */
    private function taskStatuses(string $groupId): array
    {
        return array_map(
            static fn ($s) => (string) $s,
            $this->entityManager->getConnection()->fetchFirstColumn(
                'SELECT status FROM department_grossanlass_task WHERE group_id = ? AND is_default = false',
                [$groupId],
            ),
        );
    }

    private function assertOrder(Group $group): void
    {
        if (!self::isOrderGroup($group)) {
            throw new \InvalidArgumentException('Die Gesamtabnahme gibt es nur für Bauaufträge');
        }
    }

    private function assertStatus(Group $group, string $expected, string $message): void
    {
        if ($this->status($group->getId()) !== $expected) {
            throw new \InvalidArgumentException($message);
        }
    }

    private function assertEligible(Group $group): void
    {
        if (!self::eligibleFor($this->taskStatuses($group->getId()))) {
            throw new \InvalidArgumentException('Die Gesamtabnahme ist erst möglich, wenn der Bauauftrag Aufgaben hat und alle abgenommen (erledigt) sind');
        }
    }

    private function requiredNote(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Begründung ist erforderlich');
        }
        if (mb_strlen($reason) > 2000) {
            throw new \InvalidArgumentException('Begründung ist zu lang');
        }

        return $reason;
    }

    private function record(Department $department, Group $group, string $type, User $user, ?string $note): DepartmentGrossanlassOrderEvent
    {
        $seq = (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COALESCE(MAX(seq), 0) FROM department_grossanlass_order_event WHERE group_id = ?',
            [$group->getId()],
        ) + 1;
        $event = new DepartmentGrossanlassOrderEvent(
            GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::ORDER_EVENT, DepartmentGrossanlassOrderEvent::class),
            $department,
            $group,
            $seq,
            $type,
            $user->getId(),
            $note,
        );
        $this->entityManager->persist($event);
        $this->entityManager->flush(); // sofort sichtbar für Folgeabfragen (Status)

        return $event;
    }
}
