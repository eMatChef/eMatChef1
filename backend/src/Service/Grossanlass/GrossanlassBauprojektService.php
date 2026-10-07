<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassEinsatz;
use App\Entity\DepartmentGrossanlassMap;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\DepartmentGrossanlassTask;
use App\Entity\DepartmentGrossanlassVehicleNeed;
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
        private GrossanlassProcurementService $procurement,
        private GrossanlassProcurementProgress $procurementProgress,
        private GrossanlassMaterialProgressService $materialProgress,
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
        $this->assertCanEdit($department, $user, $group);
        $start = $this->parseOptionalDate($data['window_start'] ?? null, 'window_start');
        $end = $this->parseOptionalDate($data['window_end'] ?? null, 'window_end');
        if ($start !== null && $end !== null && $end < $start) {
            throw new \InvalidArgumentException('Zeitfenster Ende muss nach Start liegen');
        }
        $group->setWindowStart($start);
        $group->setWindowEnd($end);
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
        $description = trim((string) ($data['description'] ?? ''));
        if ($title === '' && $description === '') {
            throw new \InvalidArgumentException('Titel oder Beschrieb ist erforderlich');
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
    public function createVehicleNeed(Department $department, User $user, Group $group, array $data): array
    {
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
            'tasks' => $this->serializeTasks($group),
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
            'description' => $task->getDescription(),
            'sort_order' => $task->getSortOrder(),
            'starts_at' => $task->getStartsAt()?->format('Y-m-d\TH:i:s'),
            'duration_minutes' => $task->getDurationMinutes(),
            'assignee_user_id' => $task->getAssigneeUserId(),
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

    private function nodeTypeOf(Group $group): string
    {
        return match (strtolower(trim((string) ($group->getGrossanlassKind() ?? '')))) {
            Group::GROSSANLASS_KIND_TEILBEREICH => 'bauprojekt',
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
