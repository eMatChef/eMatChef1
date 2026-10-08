<?php

declare(strict_types=1);

namespace App\Service\Demo\Legacy;

use App\Entity\Activity;
use App\Entity\Department;
use App\Entity\DepartmentCalendarPeriod;
use App\Entity\Group;
use App\Entity\Organisation;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Kennzeichnet die Legacy-Demo-Strukturen der lokalen Entwicklungsdatenbank mit dem Präfix «old-» im Anzeigenamen.
 * Identifikation ausschliesslich über feste IDs (plus Namensprobe als Sicherung). Neben Organisation, Departments und
 * Gruppen betrifft das die sichtbaren Titel darin: Aktivitäten (auch die Grossanlass-Hauptaktivität) und Kalenderperioden
 * der allowlisteten Legacy-Departments. Es werden nur Namen geändert:
 * keine IDs, Codes, Benutzer, Mitgliedschaften, Prozessdaten; nichts wird gelöscht. Idempotent, kein doppeltes Präfix.
 * Neue Szenario-Departments (mit demo_scenario_key) werden nie angefasst.
 */
class LegacyDemoRename
{
    public const PREFIX = 'old-';
    private const MAX_LENGTH = 255;
    private const MAX_CALENDAR_LENGTH = 120;

    /** @var array<string, string> Department-ID => ursprünglicher Name (Inventur vom 8. Oktober 2026) */
    public const DEPARTMENTS = [
        '7aa39b221bab' => 'Demo Grossanlass',
        '3dc94912d836' => 'Demo-Grossanlass-Event',
        '72605b231274' => 'Demo-Grossanlass-Camp',
        '638c8d301090' => 'Demo-Department',
        '7ae5770a1180' => 'Demo-Department-Parent',
    ];

    /** @var array<string, string> Organisation-ID => ursprünglicher Name */
    public const ORGANISATIONS = [
        '5f35b7cde9b5' => 'Demo-Organisation',
    ];

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** Ursprüngliche Legacy-Namen: dürfen von den alten Seed-Diensten nicht (wieder) angelegt werden. */
    public static function isRetiredName(string $name): bool
    {
        return \in_array($name, array_values(self::DEPARTMENTS), true) || \in_array($name, array_values(self::ORGANISATIONS), true) || self::hasPrefix($name);
    }

    public static function hasPrefix(string $name): bool
    {
        return str_starts_with(strtolower(ltrim($name)), self::PREFIX);
    }

    /** Zielname oder null, wenn bereits gekennzeichnet. */
    public static function target(string $name): ?string
    {
        return self::hasPrefix($name) ? null : self::PREFIX . $name;
    }

    /** @return list<array{type: string, id: string, current: string, target: string, status: string, reason: string, parent: string}> */
    public function plan(): array
    {
        $items = [];

        foreach (self::ORGANISATIONS as $id => $original) {
            $org = $this->entityManager->find(Organisation::class, $id);
            $items[] = $this->item('organisation', $id, $org?->getName(), $original, '', function (string $target) use ($org): bool {
                return $org !== null && $this->entityManager->getRepository(Organisation::class)->count(['name' => $target]) > 0;
            });
        }

        foreach (self::DEPARTMENTS as $id => $original) {
            $dept = $this->entityManager->find(Department::class, $id);
            if ($dept instanceof Department && $dept->getDemoScenarioKey() !== null) {
                $items[] = ['type' => 'department', 'id' => $id, 'current' => $dept->getName(), 'target' => $dept->getName(), 'status' => 'skip', 'reason' => 'gehört zu einem neuen Szenario', 'parent' => ''];
                continue;
            }
            $items[] = $this->item('department', $id, $dept?->getName(), $original, '', function (string $target) use ($dept): bool {
                return $dept instanceof Department && $this->entityManager->getRepository(Department::class)->count(['organisationId' => $dept->getOrganisationId(), 'name' => $target]) > 0;
            });
        }

        foreach (array_keys(self::DEPARTMENTS) as $deptId) {
            /** @var list<Group> $groups */
            $groups = $this->entityManager->getRepository(Group::class)->findBy(['departmentId' => $deptId], ['name' => 'ASC']);
            foreach ($groups as $group) {
                $current = $group->getName();
                $target = self::target($current);
                $status = 'rename';
                $reason = '';
                if ($target === null) {
                    $status = 'done';
                    $target = $current;
                } elseif (\strlen($target) > self::MAX_LENGTH) {
                    $status = 'skip';
                    $reason = 'Name wäre zu lang';
                } elseif ($this->entityManager->getRepository(Group::class)->count(['departmentId' => $deptId, 'parentId' => $group->getParentId(), 'name' => $target]) > 0) {
                    $status = 'skip';
                    $reason = 'Zielname existiert bereits';
                }
                $items[] = ['type' => 'group', 'id' => (string) $group->getId(), 'current' => $current, 'target' => $target, 'status' => $status, 'reason' => $reason, 'parent' => $deptId];
            }
        }

        foreach (array_keys(self::DEPARTMENTS) as $deptId) {
            $dept = $this->entityManager->find(Department::class, $deptId);
            if (!$dept instanceof Department || $dept->getDemoScenarioKey() !== null) {
                continue; // nicht vorhanden oder neues Szenario: nie anfassen
            }
            /** @var list<Activity> $activities */
            $activities = $this->entityManager->getRepository(Activity::class)->findBy(['departmentId' => $deptId], ['name' => 'ASC']);
            foreach ($activities as $activity) {
                $items[] = $this->titleItem('activity', (string) $activity->getId(), $activity->getName(), $deptId, self::MAX_LENGTH, fn (string $target): bool => $this->entityManager->getRepository(Activity::class)->count(['departmentId' => $deptId, 'name' => $target]) > 0);
            }
            /** @var list<DepartmentCalendarPeriod> $periods */
            $periods = $this->entityManager->getRepository(DepartmentCalendarPeriod::class)->findBy(['departmentId' => $deptId], ['startDate' => 'ASC']);
            foreach ($periods as $period) {
                $items[] = $this->titleItem('calendar', (string) $period->getId(), $period->getName(), $deptId, self::MAX_CALENDAR_LENGTH, fn (string $target): bool => $this->entityManager->getRepository(DepartmentCalendarPeriod::class)->count(['departmentId' => $deptId, 'name' => $target]) > 0);
            }
        }

        return $items;
    }

    /**
     * @param callable(string): bool $targetTaken
     *
     * @return array{type: string, id: string, current: string, target: string, status: string, reason: string, parent: string}
     */
    private function titleItem(string $type, string $id, string $current, string $deptId, int $maxLength, callable $targetTaken): array
    {
        $base = ['type' => $type, 'id' => $id, 'parent' => $deptId, 'reason' => ''];
        $target = self::target($current);
        if ($target === null) {
            return $base + ['current' => $current, 'target' => $current, 'status' => 'done'];
        }
        if (\strlen($target) > $maxLength) {
            return ['reason' => 'Name wäre zu lang'] + $base + ['current' => $current, 'target' => $current, 'status' => 'skip'];
        }
        if ($targetTaken($target)) {
            return ['reason' => 'Zielname existiert bereits'] + $base + ['current' => $current, 'target' => $current, 'status' => 'skip'];
        }

        return $base + ['current' => $current, 'target' => $target, 'status' => 'rename'];
    }

    /** Bestätigungscode: ändert sich, sobald sich der Plan ändert. */
    public static function token(array $items): string
    {
        $renames = array_map(static fn (array $i): string => $i['type'] . ':' . $i['id'] . '=>' . $i['target'], array_values(array_filter($items, static fn (array $i): bool => $i['status'] === 'rename')));
        sort($renames);

        return substr(hash('sha256', implode("\n", $renames)), 0, 10);
    }

    /**
     * Führt den Plan aus (eine Transaktion). Nur Einträge mit Status «rename».
     *
     * @param list<array{type: string, id: string, current: string, target: string, status: string, reason: string, parent: string}> $items
     */
    public function apply(array $items): int
    {
        $count = 0;
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            foreach ($items as $item) {
                if ($item['status'] !== 'rename') {
                    continue;
                }
                $entity = match ($item['type']) {
                    'organisation' => $this->entityManager->find(Organisation::class, $item['id']),
                    'department' => $this->entityManager->find(Department::class, $item['id']),
                    'group' => $this->entityManager->find(Group::class, $item['id']),
                    'activity' => $this->entityManager->find(Activity::class, $item['id']),
                    'calendar' => $this->entityManager->find(DepartmentCalendarPeriod::class, $item['id']),
                    default => null,
                };
                // Erneute Sicherung: nur umbenennen, wenn sich der Name seit der Planung nicht geändert hat.
                if ($entity === null || $entity->getName() !== $item['current'] || self::hasPrefix($entity->getName())) {
                    continue;
                }
                $entity->setName($item['target']);
                ++$count;
            }
            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->entityManager->close();
            throw $e;
        }

        return $count;
    }

    /**
     * @param callable(string): bool $targetTaken
     *
     * @return array{type: string, id: string, current: string, target: string, status: string, reason: string, parent: string}
     */
    private function item(string $type, string $id, ?string $current, string $original, string $parent, callable $targetTaken): array
    {
        $base = ['type' => $type, 'id' => $id, 'parent' => $parent];
        if ($current === null) {
            return $base + ['current' => '', 'target' => '', 'status' => 'skip', 'reason' => 'nicht vorhanden'];
        }
        if (self::hasPrefix($current)) {
            return $base + ['current' => $current, 'target' => $current, 'status' => 'done', 'reason' => ''];
        }
        if ($current !== $original) {
            return $base + ['current' => $current, 'target' => $current, 'status' => 'skip', 'reason' => sprintf('Name weicht von «%s» ab', $original)];
        }
        $target = self::PREFIX . $current;
        if ($targetTaken($target)) {
            return $base + ['current' => $current, 'target' => $current, 'status' => 'skip', 'reason' => 'Zielname existiert bereits'];
        }

        return $base + ['current' => $current, 'target' => $target, 'status' => 'rename', 'reason' => ''];
    }
}
