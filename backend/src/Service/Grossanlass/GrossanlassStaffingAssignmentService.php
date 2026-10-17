<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassHelper;
use App\Entity\DepartmentGrossanlassHelperAvailability;
use App\Entity\DepartmentGrossanlassStaffingAssignment;
use App\Entity\DepartmentGrossanlassStaffingLog;
use App\Entity\DepartmentGrossanlassStaffingNeed;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Helferzuteilung (V1), ganz oder teilweise (eigenes Fenster innerhalb des Bedarfsfensters). Nur GA-HV/GA-SUBHV.
 *
 * Jeder Schreibweg läuft in einer Transaktion unter Sperre auf dem Helfer ({@see GrossanlassStaffingLocks}) und prüft
 * alle aktiven Zuteilungen des Helfers, auch die gleichzeitig committeten:
 * - ohne Verfügbarkeit, die das Fenster deckt, keine Zuteilung (nicht überschreibbar)
 * - Doppelbuchung (Überschneidung mit einer anderen aktiven Zuteilung) nur mit begründeter Ausnahme `override_reason`,
 *   protokolliert in {@see DepartmentGrossanlassStaffingLog}
 * - Überbesetzung des Bedarfs blockiert nicht, sie wird als Warnung gemeldet
 * Der Aufgabenverantwortliche (`assignee_user_id`) zählt nicht als Zuteilung.
 */
final class GrossanlassStaffingAssignmentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GrossanlassAccessService $access,
        private readonly GrossanlassStaffingLocks $locks,
    ) {
    }

    /**
     * @param array<string, mixed> $data need_id, helper_id, starts_at/ends_at (Default: Fenster des Bedarfs), override_reason
     * @return array{assignment: array<string, mixed>, warnings: list<array<string, mixed>>}
     */
    public function assign(Department $department, User $user, array $data): array
    {
        $this->assertCanManage($department, $user);
        $needId = (string) ($data['need_id'] ?? '');
        $helperId = (string) ($data['helper_id'] ?? '');

        return $this->locks->transactional(function () use ($department, $user, $data, $needId, $helperId): array {
            $helper = $this->locks->lockHelper($department->getId(), $helperId);
            $need = $this->findNeed($department, $needId);
            $this->assertOrderNotArchived($need->getId());
            [$from, $to] = $this->window($need, $data['starts_at'] ?? null, $data['ends_at'] ?? null);
            $override = $this->check($helper, $need, $from, $to, null, $data, $user);

            $assignment = new DepartmentGrossanlassStaffingAssignment(
                GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::STAFFING_ASSIGNMENT, DepartmentGrossanlassStaffingAssignment::class),
                $department->getId(),
                $need->getId(),
                $helper->getId(),
                $from,
                $to,
            );
            $assignment->setCreatedByUserId($user->getId());
            if ($override !== null) {
                $assignment->applyOverride($override['reason'], $user->getId());
            }
            $this->entityManager->persist($assignment);
            $this->log($assignment, DepartmentGrossanlassStaffingLog::ACTION_ASSIGNED, $user, null, ['window' => $this->windowDetails($from, $to), 'need_id' => $need->getId()]);
            if ($override !== null) {
                $this->log($assignment, DepartmentGrossanlassStaffingLog::ACTION_OVERRIDE, $user, $override['reason'], ['conflicts' => $override['conflict_ids']]);
            }
            $this->entityManager->flush();

            return ['assignment' => $this->serialize($assignment), 'warnings' => $this->warnings($need, $override)];
        });
    }

    /**
     * Ändert das Fenster einer Zuteilung (Teilzeit). `version` (optional) schützt vor veraltetem Stand.
     *
     * @param array<string, mixed> $data starts_at, ends_at, override_reason, version
     * @return array{assignment: array<string, mixed>, warnings: list<array<string, mixed>>}
     */
    public function update(Department $department, User $user, string $assignmentId, array $data): array
    {
        $this->assertCanManage($department, $user);
        $row = $this->find($department, $assignmentId);
        $this->assertOrderNotArchived($row->getNeedId());

        return $this->locks->transactional(function () use ($department, $user, $data, $row): array {
            $helper = $this->locks->lockHelper($department->getId(), $row->getHelperId());
            $this->entityManager->refresh($row);
            if (isset($data['version']) && (int) $data['version'] !== $row->getVersion()) {
                throw new GrossanlassStaffingConflict('Die Zuteilung wurde zwischenzeitlich geändert', GrossanlassStaffingConflict::KIND_STALE, ['version' => $row->getVersion()]);
            }
            if (!$row->isActive()) {
                throw new \InvalidArgumentException('Die Zuteilung ist storniert');
            }
            $need = $this->findNeed($department, $row->getNeedId());
            [$from, $to] = $this->window(
                $need,
                array_key_exists('starts_at', $data) ? $data['starts_at'] : $row->getStartsAt(),
                array_key_exists('ends_at', $data) ? $data['ends_at'] : $row->getEndsAt(),
            );
            $override = $this->check($helper, $need, $from, $to, $row->getId(), $data, $user);
            $row->setWindow($from, $to);
            if ($override !== null) {
                $row->applyOverride($override['reason'], $user->getId());
                $this->log($row, DepartmentGrossanlassStaffingLog::ACTION_OVERRIDE, $user, $override['reason'], ['conflicts' => $override['conflict_ids']]);
            }
            $this->log($row, DepartmentGrossanlassStaffingLog::ACTION_CHANGED, $user, null, ['window' => $this->windowDetails($from, $to)]);
            $this->entityManager->flush();

            return ['assignment' => $this->serialize($row), 'warnings' => $this->warnings($need, $override)];
        });
    }

    /** Storniert die Zuteilung (bleibt im Protokoll); wiederholtes Stornieren ist wirkungslos. @return array<string, mixed> */
    public function cancel(Department $department, User $user, string $assignmentId): array
    {
        $this->assertCanManage($department, $user);
        $row = $this->find($department, $assignmentId);
        $this->assertOrderNotArchived($row->getNeedId());

        return $this->locks->transactional(function () use ($department, $user, $row): array {
            $this->locks->lockHelper($department->getId(), $row->getHelperId());
            $this->entityManager->refresh($row);
            if ($row->isActive()) {
                $row->setStatus(DepartmentGrossanlassStaffingAssignment::STATUS_CANCELLED);
                $this->log($row, DepartmentGrossanlassStaffingLog::ACTION_CANCELLED, $user, null, []);
                $this->entityManager->flush();
            }

            return $this->serialize($row);
        });
    }

    /**
     * Prüft Verfügbarkeit und Doppelbuchung. Gibt die Ausnahme zurück, wenn eine begründete Überschneidung zugelassen wird.
     *
     * @param array<string, mixed> $data
     * @return ?array{reason: string, conflict_ids: list<string>}
     */
    private function check(
        DepartmentGrossanlassHelper $helper,
        DepartmentGrossanlassStaffingNeed $need,
        \DateTime $from,
        \DateTime $to,
        ?string $exceptAssignmentId,
        array $data,
        User $user,
    ): ?array {
        if ($helper->isArchived()) {
            throw new GrossanlassStaffingConflict('Der Helfer ist archiviert', GrossanlassStaffingConflict::KIND_HELPER_ARCHIVED, ['helper_id' => $helper->getId()]);
        }
        $availability = array_map(
            static fn (DepartmentGrossanlassHelperAvailability $a) => [$a->getStartsAt(), $a->getEndsAt()],
            $this->entityManager->getRepository(DepartmentGrossanlassHelperAvailability::class)->findBy(['helperId' => $helper->getId()]),
        );
        if (!GrossanlassStaffingTime::isCovered($availability, $from, $to)) {
            throw new GrossanlassStaffingConflict(
                'Ohne erfasste Verfügbarkeit ist keine verbindliche Zuteilung möglich',
                GrossanlassStaffingConflict::KIND_NO_AVAILABILITY,
                ['helper_id' => $helper->getId(), 'starts_at' => $from->format('Y-m-d\TH:i:s'), 'ends_at' => $to->format('Y-m-d\TH:i:s')],
            );
        }
        $conflicts = $this->overlapping($helper->getId(), $from, $to, $exceptAssignmentId);
        if ($conflicts === []) {
            return null;
        }
        $reason = trim((string) ($data['override_reason'] ?? ''));
        if ($reason === '') {
            throw new GrossanlassStaffingConflict(
                'Der Helfer ist in diesem Zeitfenster bereits zugeteilt',
                GrossanlassStaffingConflict::KIND_DOUBLE_BOOKING,
                ['helper_id' => $helper->getId(), 'conflicts' => $conflicts, 'override_possible' => true],
            );
        }
        if (mb_strlen($reason) > 1000) {
            throw new \InvalidArgumentException('Begründung ist zu lang');
        }

        return ['reason' => $reason, 'conflict_ids' => array_column($conflicts, 'assignment_id')];
    }

    /**
     * Alle aktiven Zuteilungen des Helfers, die [from, to) überschneiden (Stand der Datenbank unter der Helfersperre).
     *
     * @return list<array{assignment_id: string, need_id: string, task_id: string, starts_at: string, ends_at: string}>
     */
    private function overlapping(string $helperId, \DateTimeInterface $from, \DateTimeInterface $to, ?string $exceptId): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT a.id, a.need_id, n.task_id, a.starts_at, a.ends_at
             FROM department_grossanlass_staffing_assignment a
             JOIN department_grossanlass_staffing_need n ON n.id = a.need_id
             WHERE a.helper_id = ? AND a.status <> 'cancelled' AND a.starts_at < ? AND a.ends_at > ? AND a.id <> ?
             ORDER BY a.starts_at",
            [$helperId, $to->format('Y-m-d H:i:s'), $from->format('Y-m-d H:i:s'), $exceptId ?? ''],
        );

        return array_map(static fn (array $r) => [
            'assignment_id' => trim((string) $r['id']),
            'need_id' => trim((string) $r['need_id']),
            'task_id' => trim((string) $r['task_id']),
            'starts_at' => (new \DateTime($r['starts_at']))->format('Y-m-d\TH:i:s'),
            'ends_at' => (new \DateTime($r['ends_at']))->format('Y-m-d\TH:i:s'),
        ], $rows);
    }

    /** @return array{0: \DateTime, 1: \DateTime} */
    private function window(DepartmentGrossanlassStaffingNeed $need, mixed $from, mixed $to): array
    {
        if (!$need->isDated()) {
            throw new GrossanlassStaffingConflict('Der Personalbedarf hat kein Zeitfenster; erst mit konkretem Zeitfenster zuteilbar', GrossanlassStaffingConflict::KIND_NEED_UNDATED, ['need_id' => $need->getId()]);
        }
        $start = $from === null || $from === '' ? \DateTime::createFromInterface($need->getStartsAt()) : GrossanlassStaffingTime::parse($from, 'starts_at');
        $end = $to === null || $to === '' ? \DateTime::createFromInterface($need->getEndsAt()) : GrossanlassStaffingTime::parse($to, 'ends_at');
        GrossanlassStaffingTime::assertWindow($start, $end);
        if ($start < $need->getStartsAt() || $end > $need->getEndsAt()) {
            throw new GrossanlassStaffingConflict(
                'Die Zuteilung muss im Zeitfenster des Personalbedarfs liegen',
                GrossanlassStaffingConflict::KIND_OUTSIDE_NEED_WINDOW,
                ['need_id' => $need->getId(), 'need_starts_at' => $need->getStartsAt()->format('Y-m-d\TH:i:s'), 'need_ends_at' => $need->getEndsAt()->format('Y-m-d\TH:i:s')],
            );
        }

        return [$start, $end];
    }

    /**
     * Warnungen nach dem Speichern: Überbesetzung des Bedarfs, zugelassene Doppelbuchung.
     *
     * @param ?array{reason: string, conflict_ids: list<string>} $override
     * @return list<array<string, mixed>>
     */
    private function warnings(DepartmentGrossanlassStaffingNeed $need, ?array $override): array
    {
        $out = [];
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT starts_at, ends_at FROM department_grossanlass_staffing_assignment WHERE need_id = ? AND status <> 'cancelled'",
            [$need->getId()],
        );
        $coverage = GrossanlassStaffingTime::coverage(
            $need->getRequiredCount(),
            $need->getStartsAt(),
            $need->getEndsAt(),
            array_map(static fn (array $r) => [new \DateTime($r['starts_at']), new \DateTime($r['ends_at'])], $rows),
        );
        if ($coverage['overbooked']) {
            $out[] = ['kind' => 'overbooked', 'need_id' => $need->getId(), 'required' => $need->getRequiredCount(), 'peak_assigned' => $coverage['peak_assigned']];
        }
        if ($override !== null) {
            $out[] = ['kind' => 'double_booking_override', 'conflicts' => $override['conflict_ids']];
        }

        return $out;
    }

    /** @param array<string, mixed> $details */
    private function log(DepartmentGrossanlassStaffingAssignment $row, string $action, User $user, ?string $reason, array $details): void
    {
        $this->entityManager->persist(new DepartmentGrossanlassStaffingLog(
            GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::STAFFING_LOG, DepartmentGrossanlassStaffingLog::class),
            $row->getDepartmentId(),
            $row->getId(),
            $row->getHelperId(),
            $action,
            $user->getId(),
            $reason,
            $details,
        ));
    }

    /** @return array{from: string, to: string} */
    private function windowDetails(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return ['from' => $from->format('Y-m-d\TH:i:s'), 'to' => $to->format('Y-m-d\TH:i:s')];
    }

    /**
     * Archivierte Bauaufträge sind nicht mehr bearbeitbar: keine neuen, geänderten oder stornierten Zuteilungen an ihren Bedarfen.
     * Bestehende Zuteilungen bleiben unverändert erhalten.
     */
    private function assertOrderNotArchived(string $needId): void
    {
        $archived = $this->entityManager->getConnection()->fetchOne(
            'SELECT g.archived_at IS NOT NULL
             FROM department_grossanlass_staffing_need n
             JOIN department_grossanlass_task t ON t.id = n.task_id
             JOIN "group" g ON g.id = t.group_id
             WHERE n.id = ?',
            [$needId],
        );
        if ($archived === true || $archived === 't' || $archived === 1 || $archived === '1') {
            throw new \InvalidArgumentException('Der Bauauftrag ist archiviert. Bitte zuerst wiederherstellen, um Zuteilungen zu ändern.');
        }
    }

    private function findNeed(Department $department, string $needId): DepartmentGrossanlassStaffingNeed
    {
        $need = $this->entityManager->find(DepartmentGrossanlassStaffingNeed::class, $needId);
        if (!$need instanceof DepartmentGrossanlassStaffingNeed || $need->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Personalbedarf nicht gefunden');
        }

        return $need;
    }

    private function find(Department $department, string $assignmentId): DepartmentGrossanlassStaffingAssignment
    {
        $row = $this->entityManager->find(DepartmentGrossanlassStaffingAssignment::class, $assignmentId);
        if (!$row instanceof DepartmentGrossanlassStaffingAssignment || $row->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Zuteilung nicht gefunden');
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function serialize(DepartmentGrossanlassStaffingAssignment $a): array
    {
        return [
            'id' => $a->getId(),
            'need_id' => $a->getNeedId(),
            'helper_id' => $a->getHelperId(),
            'starts_at' => $a->getStartsAt()->format('Y-m-d\TH:i:s'),
            'ends_at' => $a->getEndsAt()->format('Y-m-d\TH:i:s'),
            'status' => $a->getStatus(),
            'has_override' => $a->hasOverride(),
            'override_reason' => $a->getOverrideReason(),
            'version' => $a->getVersion(),
        ];
    }

    private function assertCanManage(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManageStaffing($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Zuteilungen und Ausnahmen');
        }
    }
}
