<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassStaffingNeed;
use App\Entity\DepartmentGrossanlassTask;
use App\Entity\Group;
use App\Util\GrossanlassIdGenerator;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Personalbedarf (V1) an Aufgaben des Bauprojekts. Bedarf am Bauprojekt selbst hängt an der lazy angelegten
 * Standardaufgabe «Allgemein» (`is_default`, nicht in der Aufgabenübersicht). Keine Berechtigungsprüfung hier:
 * der Aufrufer ({@see GrossanlassBauprojektService}) prüft das Bearbeitungsrecht am Bauprojekt.
 *
 * Das Zeitfenster eines Bedarfs ist halboffen [von, bis). Ohne Fenster ist er undatiert und nicht zuteilbar.
 * Die bisherige Aufgaben-Eingabe «Gesamtzahl + Funktionen» ist das *Bündel* der Bedarfe, deren Fenster dem der
 * Aufgabe entspricht ({@see windowOf()}); weitere Bedarfe mit anderem Fenster bleiben davon unberührt.
 */
final class GrossanlassStaffingNeedService
{
    public const DEFAULT_TASK_TITLE = 'Allgemein';

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Fenster der Aufgabe, sonst des Bauprojekts (Enddatum inklusiv, intern exklusives Ende), sonst keines.
     *
     * @return ?array{0: \DateTime, 1: \DateTime}
     */
    public static function windowOf(DepartmentGrossanlassTask $task, Group $group): ?array
    {
        $start = $task->getStartsAt();
        $minutes = $task->getDurationMinutes();
        if ($start !== null && $minutes !== null && $minutes > 0) {
            $from = \DateTime::createFromInterface($start);

            return [$from, (clone $from)->modify('+' . $minutes . ' minutes')];
        }
        $windowStart = $group->getWindowStart();
        $windowEnd = $group->getWindowEnd();
        if ($windowStart !== null && $windowEnd !== null && $windowEnd >= $windowStart) {
            return [
                \DateTime::createFromInterface($windowStart)->setTime(0, 0),
                \DateTime::createFromInterface($windowEnd)->setTime(0, 0)->modify('+1 day'),
            ];
        }

        return null;
    }

    public function ensureDefaultTask(Department $department, Group $group): DepartmentGrossanlassTask
    {
        $repo = $this->entityManager->getRepository(DepartmentGrossanlassTask::class);
        $task = $repo->findOneBy(['groupId' => $group->getId(), 'isDefault' => true]);
        if ($task instanceof DepartmentGrossanlassTask) {
            return $task;
        }
        // Gleichzeitiges Anlegen: der partielle Unique-Index (eine Standardaufgabe je Gruppe) entscheidet, der Verlierer liest nach.
        $this->entityManager->getConnection()->executeStatement(
            'INSERT INTO department_grossanlass_task (id, department_id, group_id, title, sort_order, is_default, created_at)
             VALUES (?, ?, ?, ?, 0, TRUE, NOW()) ON CONFLICT (group_id) WHERE is_default DO NOTHING',
            [
                GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::TASK, DepartmentGrossanlassTask::class),
                $department->getId(),
                $group->getId(),
                self::DEFAULT_TASK_TITLE,
            ],
        );
        $task = $repo->findOneBy(['groupId' => $group->getId(), 'isDefault' => true]);
        if (!$task instanceof DepartmentGrossanlassTask) {
            throw new \RuntimeException('Standardaufgabe konnte nicht angelegt werden');
        }
        $this->entityManager->refresh($task);

        return $task;
    }

    /** @return list<DepartmentGrossanlassStaffingNeed> */
    public function listForTask(string $taskId): array
    {
        return array_values($this->entityManager->getRepository(DepartmentGrossanlassStaffingNeed::class)
            ->findBy(['taskId' => $taskId], ['sortOrder' => 'ASC', 'startsAt' => 'ASC', 'label' => 'ASC']));
    }

    public function find(Department $department, string $needId): DepartmentGrossanlassStaffingNeed
    {
        $need = $this->entityManager->find(DepartmentGrossanlassStaffingNeed::class, $needId);
        if (!$need instanceof DepartmentGrossanlassStaffingNeed || $need->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Personalbedarf nicht gefunden');
        }

        return $need;
    }

    /**
     * Neuer Bedarf. Fehlt `starts_at`/`ends_at` im Input, gilt das Fenster der Aufgabe; ein ausdrückliches `null` macht ihn undatiert.
     *
     * @param array<string, mixed> $data label, required_count, starts_at, ends_at, sort_order
     */
    public function create(Department $department, DepartmentGrossanlassTask $task, Group $group, array $data): DepartmentGrossanlassStaffingNeed
    {
        $need = new DepartmentGrossanlassStaffingNeed(
            GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::STAFFING_NEED, DepartmentGrossanlassStaffingNeed::class),
            $department->getId(),
            $task->getId(),
        );
        $this->applyFields($need, $data);
        if (array_key_exists('starts_at', $data) || array_key_exists('ends_at', $data)) {
            $need->setWindow($this->optionalTime($data['starts_at'] ?? null, 'starts_at'), $this->optionalTime($data['ends_at'] ?? null, 'ends_at'));
        } else {
            $window = self::windowOf($task, $group);
            $need->setWindow($window[0] ?? null, $window[1] ?? null);
        }
        $this->entityManager->persist($need);
        $this->entityManager->flush();

        return $need;
    }

    /** @param array<string, mixed> $data */
    public function update(DepartmentGrossanlassStaffingNeed $need, array $data): DepartmentGrossanlassStaffingNeed
    {
        $this->applyFields($need, $data);
        if (array_key_exists('starts_at', $data) || array_key_exists('ends_at', $data)) {
            $from = array_key_exists('starts_at', $data) ? $this->optionalTime($data['starts_at'], 'starts_at') : $need->getStartsAt();
            $to = array_key_exists('ends_at', $data) ? $this->optionalTime($data['ends_at'], 'ends_at') : $need->getEndsAt();
            $this->assertAssignmentsInside($need->getId(), $from, $to);
            $need->setWindow($from, $to);
        }
        $this->entityManager->flush();

        return $need;
    }

    public function delete(DepartmentGrossanlassStaffingNeed $need): void
    {
        $ids = $this->activeAssignmentIds($need->getId());
        if ($ids !== []) {
            throw new GrossanlassStaffingConflict('Der Personalbedarf hat noch Zuteilungen', GrossanlassStaffingConflict::KIND_IN_USE, ['assignment_ids' => $ids]);
        }
        $this->entityManager->remove($need);
        $this->entityManager->flush();
    }

    /**
     * Bündel der Aufgabe im Format der bisherigen Eingabe: Gesamtzahl, Funktionen, nicht aufgeteilter Rest.
     *
     * @return array{total: int, functions: list<array{label: string, count: int}>, unspecified: int}
     */
    public function bundleOf(DepartmentGrossanlassTask $task, Group $group): array
    {
        $functions = [];
        $rest = 0;
        foreach ($this->bundleNeeds($task, $group) as $need) {
            if ($need->getLabel() === '') {
                $rest += $need->getRequiredCount();
            } else {
                $functions[] = ['label' => $need->getLabel(), 'count' => $need->getRequiredCount()];
            }
        }
        $sum = (int) array_sum(array_column($functions, 'count'));

        return ['total' => $sum + $rest, 'functions' => $functions, 'unspecified' => $rest];
    }

    /**
     * Setzt das Bündel der Aufgabe: je Funktion ein Bedarf, der Rest (Gesamt − Σ Funktionen) ein Bedarf ohne Funktion.
     * Entfernte Bedarfe mit Zuteilungen werden auf 0 gesetzt statt gelöscht, damit keine Zuteilung verloren geht.
     *
     * @param list<array{label: string, count: int}> $functions
     */
    public function syncBundle(Department $department, DepartmentGrossanlassTask $task, Group $group, int $total, array $functions): void
    {
        $wanted = [];
        foreach ($functions as $f) {
            $wanted[$f['label']] = $f['count'];
        }
        $rest = $total - (int) array_sum($wanted);
        if ($rest > 0) {
            $wanted[''] = $rest;
        }
        $window = self::windowOf($task, $group);
        $existing = [];
        foreach ($this->bundleNeeds($task, $group) as $need) {
            $existing[$need->getLabel()] = $need;
        }
        $order = 0;
        foreach ($wanted as $label => $count) {
            $label = (string) $label;
            if (isset($existing[$label])) {
                $existing[$label]->setRequiredCount($count);
                $existing[$label]->setSortOrder($order);
                unset($existing[$label]);
            } else {
                $need = new DepartmentGrossanlassStaffingNeed(
                    GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::STAFFING_NEED, DepartmentGrossanlassStaffingNeed::class),
                    $department->getId(),
                    $task->getId(),
                );
                $need->setLabel($label)->setRequiredCount($count)->setSortOrder($order)->setWindow($window[0] ?? null, $window[1] ?? null);
                $this->entityManager->persist($need);
            }
            ++$order;
        }
        foreach ($existing as $need) {
            if ($this->activeAssignmentIds($need->getId()) === []) {
                $this->entityManager->remove($need);
            } else {
                $need->setRequiredCount(0);
            }
        }
        $this->entityManager->flush();
    }

    /**
     * Fenster aller Aufgaben der Gruppe vor einer Änderung von Aufgabenzeit oder Bauprojekt-Fenster.
     *
     * @return array<string, ?array{0: \DateTime, 1: \DateTime}>
     */
    public function snapshotWindows(Group $group): array
    {
        $out = [];
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassTask::class)->findBy(['groupId' => $group->getId()]) as $task) {
            $out[$task->getId()] = self::windowOf($task, $group);
        }

        return $out;
    }

    /**
     * Zieht die Bündel-Bedarfe nach, deren Fenster dem alten Aufgabenfenster entsprach (Bedarfe mit eigenem Fenster bleiben).
     *
     * @param array<string, ?array{0: \DateTime, 1: \DateTime}> $before aus {@see snapshotWindows()}
     */
    public function realign(Group $group, array $before): void
    {
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassTask::class)->findBy(['groupId' => $group->getId()]) as $task) {
            $old = $before[$task->getId()] ?? null;
            $new = self::windowOf($task, $group);
            if (self::sameWindow($old, $new)) {
                continue;
            }
            foreach ($this->listForTask($task->getId()) as $need) {
                if (self::sameWindow($old, $need->isDated() ? [$need->getStartsAt(), $need->getEndsAt()] : null)) {
                    $this->assertAssignmentsInside($need->getId(), $new[0] ?? null, $new[1] ?? null);
                    $need->setWindow($new[0] ?? null, $new[1] ?? null);
                }
            }
        }
        $this->entityManager->flush();
    }

    /**
     * Bedarf je Gruppe für die Spitzenbedarf-Auswertung ({@see GrossanlassBauprojektService::helperDemandOverTime()}):
     * jede Bedarfszeile mit Fenster als Start und Dauer; undatierte zählen immer mit.
     *
     * @param list<string> $groupIds
     * @return array<string, list<array{starts_at: ?\DateTimeInterface, duration: ?int, total: int}>>
     */
    public function demandRows(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT t.group_id, n.starts_at, n.ends_at, n.required_count
             FROM department_grossanlass_staffing_need n
             JOIN department_grossanlass_task t ON t.id = n.task_id
             WHERE t.group_id IN (?) AND n.required_count > 0',
            [$groupIds],
            [ArrayParameterType::STRING],
        );
        $out = [];
        foreach ($rows as $row) {
            $from = $row['starts_at'] !== null ? new \DateTime($row['starts_at']) : null;
            $to = $row['ends_at'] !== null ? new \DateTime($row['ends_at']) : null;
            $out[trim((string) $row['group_id'])][] = [
                'starts_at' => $from,
                'duration' => $from !== null && $to !== null ? intdiv($to->getTimestamp() - $from->getTimestamp(), 60) : null,
                'total' => (int) $row['required_count'],
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function serialize(DepartmentGrossanlassStaffingNeed $need): array
    {
        return [
            'id' => $need->getId(),
            'task_id' => $need->getTaskId(),
            'label' => $need->getLabel(),
            'required_count' => $need->getRequiredCount(),
            'starts_at' => $need->getStartsAt()?->format('Y-m-d\TH:i:s'),
            'ends_at' => $need->getEndsAt()?->format('Y-m-d\TH:i:s'),
            'dated' => $need->isDated(),
            'sort_order' => $need->getSortOrder(),
        ];
    }

    /** @return list<DepartmentGrossanlassStaffingNeed> */
    private function bundleNeeds(DepartmentGrossanlassTask $task, Group $group): array
    {
        $window = self::windowOf($task, $group);

        return array_values(array_filter(
            $this->listForTask($task->getId()),
            static fn (DepartmentGrossanlassStaffingNeed $n) => self::sameWindow($window, $n->isDated() ? [$n->getStartsAt(), $n->getEndsAt()] : null),
        ));
    }

    /**
     * @param ?array{0: \DateTimeInterface, 1: \DateTimeInterface} $a
     * @param ?array{0: \DateTimeInterface, 1: \DateTimeInterface} $b
     */
    private static function sameWindow(?array $a, ?array $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return $a[0]->getTimestamp() === $b[0]->getTimestamp() && $a[1]->getTimestamp() === $b[1]->getTimestamp();
    }

    /** @param array<string, mixed> $data */
    private function applyFields(DepartmentGrossanlassStaffingNeed $need, array $data): void
    {
        if (array_key_exists('label', $data)) {
            $label = trim((string) $data['label']);
            if (mb_strlen($label) > 120) {
                throw new \InvalidArgumentException('Funktion ist zu lang');
            }
            $need->setLabel($label);
        }
        $count = $data['required_count'] ?? $data['count'] ?? null;
        if ($count !== null) {
            if (!is_numeric($count) || (int) $count < 0 || (int) $count > 5000) {
                throw new \InvalidArgumentException('Ungültige Anzahl Helfer');
            }
            $need->setRequiredCount((int) $count);
        }
        if (array_key_exists('sort_order', $data)) {
            $need->setSortOrder((int) $data['sort_order']);
        }
    }

    private function optionalTime(mixed $value, string $field): ?\DateTime
    {
        return $value === null || $value === '' ? null : GrossanlassStaffingTime::parse($value, $field);
    }

    /** Aktive Zuteilungen müssen im (neuen) Bedarfsfenster liegen; ein undatierter Bedarf darf keine haben. */
    private function assertAssignmentsInside(string $needId, ?\DateTimeInterface $from, ?\DateTimeInterface $to): void
    {
        $outside = [];
        foreach ($this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT id, starts_at, ends_at FROM department_grossanlass_staffing_assignment WHERE need_id = ? AND status <> 'cancelled'",
            [$needId],
        ) as $row) {
            $aFrom = new \DateTime($row['starts_at']);
            $aTo = new \DateTime($row['ends_at']);
            if ($from === null || $to === null || $aFrom < $from || $aTo > $to) {
                $outside[] = trim((string) $row['id']);
            }
        }
        if ($outside !== []) {
            throw new GrossanlassStaffingConflict('Zuteilungen liegen ausserhalb des neuen Zeitfensters', GrossanlassStaffingConflict::KIND_OUTSIDE_NEED_WINDOW, ['assignment_ids' => $outside]);
        }
    }

    /** @return list<string> */
    private function activeAssignmentIds(string $needId): array
    {
        return array_map(
            static fn ($id) => trim((string) $id),
            $this->entityManager->getConnection()->fetchFirstColumn(
                "SELECT id FROM department_grossanlass_staffing_assignment WHERE need_id = ? AND status <> 'cancelled'",
                [$needId],
            ),
        );
    }
}
