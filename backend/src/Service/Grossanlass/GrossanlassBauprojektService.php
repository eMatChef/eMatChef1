<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassMap;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\DepartmentGrossanlassTask;
use App\Entity\Group;
use App\Entity\User;
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
        if ($place->getGroupId()) {
            $found = $this->entityManager->getRepository(Group::class)->find($place->getGroupId());
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
            'place' => $this->places->serialize($place),
            'tasks' => [],
            'material' => [],
            'packs' => $this->packs->listAtPlace($department, $place->getId()),
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
        $this->assertCanEdit($department, $user, $group);
        $start = $this->parseOptionalDate($data['window_start'] ?? null, 'window_start');
        $end = $this->parseOptionalDate($data['window_end'] ?? null, 'window_end');
        if ($start !== null && $end !== null && $end < $start) {
            throw new \InvalidArgumentException('Zeitfenster Ende muss nach Start liegen');
        }
        $group->setWindowStart($start);
        $group->setWindowEnd($end);
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

        return $this->serializeTasks($group);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createTask(Department $department, User $user, Group $group, array $data): array
    {
        $this->assertBauprojekt($department, $group);
        $this->assertCanEdit($department, $user, $group);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('Titel ist erforderlich');
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
        $task->setSortOrder($max + 1);
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $this->serializeTask($task);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateTask(Department $department, User $user, Group $group, string $taskId, array $data): array
    {
        $this->assertBauprojekt($department, $group);
        $this->assertCanEdit($department, $user, $group);
        $task = $this->findTask($department, $group, $taskId);
        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);
            if ($title === '') {
                throw new \InvalidArgumentException('Titel ist erforderlich');
            }
            $task->setTitle($title);
        }
        if (array_key_exists('sort_order', $data)) {
            $task->setSortOrder((int) $data['sort_order']);
        }
        $this->entityManager->flush();

        return $this->serializeTask($task);
    }

    public function deleteTask(Department $department, User $user, Group $group, string $taskId): void
    {
        $this->assertBauprojekt($department, $group);
        $this->assertCanEdit($department, $user, $group);
        $task = $this->findTask($department, $group, $taskId);
        $this->entityManager->remove($task);
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function addMaterial(Department $department, User $user, Group $group, array $data): array
    {
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

        return $this->wishes->createProjectMaterialWish($department, $user, $group, $data);
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
            'window_start' => $group->getWindowStart()?->format('Y-m-d'),
            'window_end' => $group->getWindowEnd()?->format('Y-m-d'),
        ];

        return [
            'group' => $groupPayload,
            'window_start' => $group->getWindowStart()?->format('Y-m-d'),
            'window_end' => $group->getWindowEnd()?->format('Y-m-d'),
            'place' => $place instanceof DepartmentGrossanlassPlace ? $this->places->serialize($place) : null,
            'tasks' => $this->serializeTasks($group),
            'material' => $this->wishes->listMaterialWishesForGroup($department, $user, $group),
            'packs' => $place instanceof DepartmentGrossanlassPlace
                ? $this->packs->listAtPlace($department, $place->getId())
                : [],
            'map' => $this->mapSnippet($department, $place),
            'can_edit' => $this->canEdit($department, $user, $group),
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
    private function serializeTasks(Group $group): array
    {
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassTask::class)
            ->findBy(['groupId' => $group->getId()], ['sortOrder' => 'ASC', 'createdAt' => 'ASC']);
        $out = [];
        foreach ($rows as $row) {
            if ($row instanceof DepartmentGrossanlassTask) {
                $out[] = $this->serializeTask($row);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTask(DepartmentGrossanlassTask $task): array
    {
        return [
            'id' => $task->getId(),
            'group_id' => $task->getGroupId(),
            'title' => $task->getTitle(),
            'sort_order' => $task->getSortOrder(),
            'created_at' => $task->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function findTask(Department $department, Group $group, string $taskId): DepartmentGrossanlassTask
    {
        $task = $this->entityManager->getRepository(DepartmentGrossanlassTask::class)->find($taskId);
        if (
            !$task instanceof DepartmentGrossanlassTask
            || $task->getDepartmentId() !== $department->getId()
            || $task->getGroupId() !== $group->getId()
        ) {
            throw new \InvalidArgumentException('Aufgabe nicht gefunden');
        }

        return $task;
    }

    private function assertGroup(Department $department, Group $group): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if ($group->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Gruppe nicht gefunden');
        }
    }

    private function assertBauprojekt(Department $department, Group $group): void
    {
        $this->assertGroup($department, $group);
        $kind = $group->getGrossanlassKind();
        $isBauprojekt = $kind === Group::GROSSANLASS_KIND_TEILBEREICH
            || (($kind === null || $kind === '') && $group->getParentId() !== null);
        if (!$isBauprojekt) {
            throw new \InvalidArgumentException('Aufgaben und Material nur am Bauprojekt');
        }
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

        return $this->access->userIsMemberInRessortBranch($user, $department->getId(), $group);
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
