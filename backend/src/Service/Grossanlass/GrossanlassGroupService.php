<?php

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassCost;
use App\Entity\DepartmentGrossanlassGroupShare;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\DepartmentGrossanlassPlaceGroup;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\User;
use App\Service\GroupAccessService;
use App\Service\GroupHierarchyService;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

class GrossanlassGroupService
{
    public const MAX_DEPTH = 10;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GroupAccessService $groupAccess,
        private GroupHierarchyService $hierarchy,
        private GrossanlassPlaceService $places,
        private GrossanlassProcurementProgress $procurementProgress,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listGroups(Department $department): array
    {
        $this->access->assertGrossanlassDepartment($department);

        $groups = $this->entityManager->getRepository(Group::class)
            ->createQueryBuilder('g')
            ->where('g.departmentId = :departmentId')
            ->setParameter('departmentId', $department->getId())
            ->orderBy('g.sortOrder', 'ASC')
            ->addOrderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();

        $groupIds = array_map(static fn (Group $g) => $g->getId(), $groups);
        $membershipsByGroup = $this->loadMembershipsByGroup($groupIds);
        $allPlacesByGroup = $this->loadAllPlacesByGroup($department);
        $placesByGroup = array_map(static fn (array $list) => $list[0], $allPlacesByGroup);
        $shares = $this->loadShares($department);
        $procurementProgress = $this->procurementProgress->buildProgressByGroup($department->getId());
        $namesById = [];
        foreach ($groups as $group) {
            $namesById[$group->getId()] = $group->getName();
        }

        $result = [];
        foreach ($groups as $group) {
            $result[] = $this->serializeGroup(
                $department,
                $group,
                $membershipsByGroup[$group->getId()] ?? [],
                $placesByGroup[$group->getId()] ?? null,
                $shares,
                $namesById,
                $procurementProgress[$group->getId()] ?? null,
                $allPlacesByGroup[$group->getId()] ?? null,
            );
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createGroup(Department $department, User $user, array $data): array
    {
        $this->access->assertGrossanlassDepartment($department);

        if (!isset($data['name']) || trim((string) $data['name']) === '') {
            throw new \InvalidArgumentException('name ist erforderlich');
        }

        $parent = null;
        if (!empty($data['parent_id'])) {
            $parent = $this->findGroupInDepartment($department, (string) $data['parent_id']);
            if (!$this->access->canCreateChildGroup($user, $department, $parent)) {
                throw new \RuntimeException('Keine Berechtigung, Kind anzulegen');
            }
            $parentDepth = $this->hierarchy->computeDepth($department->getId(), $parent->getId());
            if ($parentDepth >= self::MAX_DEPTH) {
                throw new \InvalidArgumentException('Maximale Hierarchietiefe von ' . self::MAX_DEPTH . ' Ebenen erreicht');
            }
        } else {
            if (!$this->access->canCreateRootRessort($user, $department)) {
                throw new \RuntimeException('Keine Berechtigung, Ressort anzulegen');
            }
        }

        $group = new Group();
        $group->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::GROUP, Group::class));
        $group->setDepartment($department);
        $group->setName(trim((string) $data['name']));
        if ($parent !== null) {
            $group->setParent($parent);
        }
        $group->setGrossanlassKind($this->kindFromChoice($parent, $data['kind'] ?? null));
        $this->assertParentAllowed($group->getGrossanlassKind(), $parent);
        if (isset($data['sort_order'])) {
            $group->setSortOrder((int) $data['sort_order']);
        }
        $this->applyWindow($group, $data);
        $this->applyBuildStatus($department, $user, $group, $data);
        $this->applyDescription($group, $data);

        $this->entityManager->persist($group);
        $this->entityManager->flush();
        $place = $this->syncPlaceForGroup($department, $user, $group, $data);

        return $this->serializeGroup($department, $group, [], $place);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateGroup(Department $department, User $user, Group $group, array $data): array
    {
        $this->access->assertGrossanlassDepartment($department);
        $this->assertGroupBelongsToDepartment($group, $department);

        if (!$this->access->canEditGroup($user, $department, $group)) {
            throw new \RuntimeException('Keine Berechtigung, Ressort zu bearbeiten');
        }

        if (isset($data['name'])) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new \InvalidArgumentException('name darf nicht leer sein');
            }
            $group->setName($name);
        }

        if (array_key_exists('parent_id', $data)) {
            $this->applyParentChange($department, $user, $group, $data);
        }

        if (isset($data['sort_order'])) {
            $group->setSortOrder((int) $data['sort_order']);
        }

        $requestedKind = $data['kind'] ?? $group->getGrossanlassKind();
        if (!$this->access->canManageStruktur($user, $department)
            && $this->isLeafKind($this->resolveStoredKind($group)) !== $this->isLeafKind($this->kindFromChoice($group->getParent(), $requestedKind))) {
            // Leitungen dürfen Bauprojekt ↔ Auftrag wechseln, aber kein Element zur Struktur machen (oder umgekehrt).
            $requestedKind = $group->getGrossanlassKind();
        }
        $this->applyStructuralKind($group, $requestedKind);
        $this->applyWindow($group, $data);
        $this->applyBuildStatus($department, $user, $group, $data);
        $this->applyDescription($group, $data);

        $group->updateTimestamps();
        $this->entityManager->flush();
        $place = $this->syncPlaceForGroup($department, $user, $group, $data);

        $members = $this->loadMembershipsByGroup([$group->getId()])[$group->getId()] ?? [];

        return $this->serializeGroup($department, $group, $members, $place, places: $this->serializedPlacesOf($department, $group));
    }

    public function deleteGroup(Department $department, User $user, Group $group): void
    {
        $this->access->assertGrossanlassDepartment($department);
        $this->assertGroupBelongsToDepartment($group, $department);

        if (!$this->access->canDeleteGroup($user, $department, $group)) {
            throw new \RuntimeException('Keine Berechtigung, Ressort zu löschen');
        }

        $subtreeIds = $this->hierarchy->expandWithDescendants($department->getId(), [$group->getId()]);
        if ($this->countMembershipsInGroups($subtreeIds) > 0) {
            throw new \RuntimeException('Löschen nicht möglich: Im Subtree sind noch Mitglieder zugewiesen');
        }

        $isBauprojekt = $this->isLeafKind($this->resolveStoredKind($group));
        if (!$isBauprojekt && $this->hasWishReferences($subtreeIds)) {
            throw new \RuntimeException('Löschen nicht möglich: Es bestehen noch Wunsch-Referenzen auf Knoten im Subtree');
        }

        $this->deleteSubtreeGroups($department->getId(), $group->getId());
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function shareGroup(Department $department, User $user, Group $group, array $data): array
    {
        $this->access->assertGrossanlassDepartment($department);
        $this->assertGroupBelongsToDepartment($group, $department);
        if (!$this->access->canShareGroup($user, $department, $group)) {
            throw new \RuntimeException('Keine Berechtigung, zu teilen');
        }

        $targetId = trim((string) ($data['target_group_id'] ?? ''));
        if ($targetId === '') {
            throw new \InvalidArgumentException('target_group_id ist erforderlich');
        }
        $target = $this->findGroupInDepartment($department, $targetId);
        $targetKind = $this->resolveStoredKind($target);
        $targetIsBauprojekt = $this->resolveNodeType($target, $targetKind) === 'bauprojekt';
        $reason = GrossanlassGroupShareRules::forbiddenReason(
            $group->getId(),
            $target->getId(),
            $this->hierarchy->expandWithDescendants($department->getId(), [$group->getId()]),
            $this->hierarchy->expandWithDescendants($department->getId(), [$target->getId()]),
            $targetIsBauprojekt,
        );
        if ($reason !== null) {
            throw new \InvalidArgumentException($reason);
        }

        $existing = $this->entityManager->getRepository(DepartmentGrossanlassGroupShare::class)->findOneBy([
            'groupId' => $group->getId(),
            'targetGroupId' => $target->getId(),
        ]);
        if ($existing instanceof DepartmentGrossanlassGroupShare) {
            return $this->serializeShare($existing, $group, $target);
        }

        $row = new DepartmentGrossanlassGroupShare();
        $row->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::GROUP_SHARE,
            DepartmentGrossanlassGroupShare::class,
        ));
        $row->setDepartment($department);
        $row->setGroupId($group->getId());
        $row->setTargetGroupId($target->getId());
        $this->entityManager->persist($row);
        $this->entityManager->flush();

        return $this->serializeShare($row, $group, $target);
    }

    public function unshareGroup(Department $department, User $user, Group $group, string $shareId): void
    {
        $this->access->assertGrossanlassDepartment($department);
        $this->assertGroupBelongsToDepartment($group, $department);

        $row = $this->entityManager->getRepository(DepartmentGrossanlassGroupShare::class)->find($shareId);
        if (!$row instanceof DepartmentGrossanlassGroupShare
            || $row->getDepartmentId() !== $department->getId()
            || $row->getGroupId() !== $group->getId()
        ) {
            throw new \InvalidArgumentException('Teilung nicht gefunden');
        }
        $target = $this->findGroupInDepartment($department, $row->getTargetGroupId());
        if (!$this->access->canUnshareGroup($user, $department, $group, $target)) {
            throw new \RuntimeException('Keine Berechtigung, die Teilung aufzuheben');
        }

        $this->entityManager->remove($row);
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function addMember(Department $department, User $user, Group $group, array $data): array
    {
        $this->access->assertGrossanlassDepartment($department);
        $this->assertGroupBelongsToDepartment($group, $department);

        if (!$this->access->canManageGroupMembers($user, $department, $group)) {
            throw new \RuntimeException('Keine Berechtigung, Mitglieder zu verwalten');
        }

        if (!isset($data['user_id'])) {
            throw new \InvalidArgumentException('user_id ist erforderlich');
        }

        $memberUser = $this->entityManager->getRepository(User::class)->find($data['user_id']);
        if ($memberUser === null) {
            throw new \InvalidArgumentException('User nicht gefunden');
        }
        if ($memberUser->hasSuperAdminProfile()) {
            throw new \InvalidArgumentException('Superadmin-Konten können keiner Gruppe zugewiesen werden');
        }
        if (!$this->groupAccess->userHasDepartmentMembership((string) $data['user_id'], $department->getId())) {
            throw new \InvalidArgumentException('Benutzer ist kein Mitglied dieser Abteilung');
        }

        $existing = $this->entityManager->getRepository(GroupMembership::class)
            ->findOneBy(['userId' => $data['user_id'], 'groupId' => $group->getId()]);
        if ($existing !== null) {
            throw new \RuntimeException('User ist bereits Mitglied dieser Gruppe');
        }

        $leaderOnly = $this->groupAccess->isGroupLeaderOnlyManager($user, $group)
            && !$this->access->canManageStruktur($user, $department);

        $role = $data['role'] ?? 'member';
        if (!in_array($role, ['leader', 'member'], true)) {
            throw new \InvalidArgumentException('Ungültige Rolle. Erlaubt: leader, member');
        }
        if ($leaderOnly) {
            $role = 'member';
        }

        $membership = new GroupMembership();
        $membership->setUser($memberUser);
        $membership->setGroup($group);
        $membership->setRole($role);
        $membership->setIsPrimary($leaderOnly ? false : (bool) ($data['is_primary'] ?? false));

        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        return array_merge(
            $this->serializeGroupMember($membership),
            ['group_id' => $group->getId()]
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateMember(Department $department, User $user, Group $group, string $userId, array $data): array
    {
        $this->access->assertGrossanlassDepartment($department);
        $this->assertGroupBelongsToDepartment($group, $department);

        if (!$this->access->canManageGroupMembers($user, $department, $group)) {
            throw new \RuntimeException('Keine Berechtigung, Gruppenmitglieder zu bearbeiten');
        }

        $membership = $this->entityManager->getRepository(GroupMembership::class)
            ->findOneBy(['userId' => $userId, 'groupId' => $group->getId()]);
        if ($membership === null) {
            throw new \InvalidArgumentException('Mitgliedschaft nicht gefunden');
        }

        if ($membership->getUser()->hasSuperAdminProfile()) {
            throw new \RuntimeException('Superadmin-Konten haben keine Gruppenrollen in der Verwaltung');
        }

        if (isset($data['role'])) {
            if (!in_array($data['role'], ['leader', 'member'], true)) {
                throw new \InvalidArgumentException('Ungültige Rolle');
            }
            $membership->setRole((string) $data['role']);
        }
        if (isset($data['is_primary'])) {
            $membership->setIsPrimary((bool) $data['is_primary']);
        }
        if (array_key_exists('can_procure', $data)) {
            $membership->setCanProcure((bool) $data['can_procure']);
        }

        $this->entityManager->flush();

        return array_merge(
            $this->serializeGroupMember($membership),
            ['group_id' => $group->getId()]
        );
    }

    public function removeMember(Department $department, User $user, Group $group, string $userId): void
    {
        $this->access->assertGrossanlassDepartment($department);
        $this->assertGroupBelongsToDepartment($group, $department);

        if (!$this->access->canManageGroupMembers($user, $department, $group)) {
            throw new \RuntimeException('Keine Berechtigung, Mitglieder zu entfernen');
        }

        $membership = $this->entityManager->getRepository(GroupMembership::class)
            ->findOneBy(['userId' => $userId, 'groupId' => $group->getId()]);
        if ($membership === null) {
            throw new \InvalidArgumentException('Mitgliedschaft nicht gefunden');
        }

        $this->entityManager->remove($membership);
        $this->entityManager->flush();
    }

    private function findGroupInDepartment(Department $department, string $groupId): Group
    {
        $group = $this->entityManager->getRepository(Group::class)->find($groupId);
        if ($group === null || $group->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Gruppe nicht gefunden');
        }

        return $group;
    }

    private function assertGroupBelongsToDepartment(Group $group, Department $department): void
    {
        if ($group->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Gruppe gehört nicht zu diesem Department');
        }
    }

    /**
     * @param list<string> $groupIds
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function loadMembershipsByGroup(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        $memberships = $this->entityManager->getRepository(GroupMembership::class)
            ->createQueryBuilder('gm')
            ->innerJoin('gm.user', 'u')
            ->innerJoin('u.profile', 'p')
            ->addSelect('u', 'p')
            ->where('gm.groupId IN (:groupIds)')
            ->setParameter('groupIds', $groupIds)
            ->orderBy('gm.role', 'ASC')
            ->getQuery()
            ->getResult();

        $byGroup = [];
        foreach ($memberships as $membership) {
            if (!$membership instanceof GroupMembership) {
                continue;
            }
            $user = $membership->getUser();
            if ($user->hasSuperAdminProfile()) {
                continue;
            }
            $gid = $membership->getGroupId();
            if (!isset($byGroup[$gid])) {
                $byGroup[$gid] = [];
            }
            $byGroup[$gid][] = $this->serializeGroupMember($membership);
        }

        return $byGroup;
    }

    /**
     * @param list<array<string, mixed>> $members
     * @param array<string, mixed>|null  $place
     * @param list<DepartmentGrossanlassGroupShare> $shares
     * @param array<string, string> $namesById
     *
     * @return array<string, mixed>
     */
    private function serializeGroup(
        Department $department,
        Group $group,
        array $members,
        ?array $place = null,
        array $shares = [],
        array $namesById = [],
        ?string $procurementProgress = null,
        ?array $places = null,
    ): array {
        $level = $this->hierarchy->computeDepth($department->getId(), $group->getId());
        $leaders = array_values(array_filter($members, static fn (array $m) => $m['is_leader']));
        $kind = $this->resolveStoredKind($group);
        $nodeType = $this->resolveNodeType($group, $kind);
        $names = $namesById;

        $sharedWith = [];
        $sharedFrom = [];
        foreach ($shares as $share) {
            if (!$share instanceof DepartmentGrossanlassGroupShare) {
                continue;
            }
            if ($share->getGroupId() === $group->getId()) {
                $sharedWith[] = [
                    'id' => $share->getId(),
                    'target_group_id' => $share->getTargetGroupId(),
                    'target_name' => $names[$share->getTargetGroupId()] ?? $share->getTargetGroupId(),
                ];
            }
            if ($share->getTargetGroupId() === $group->getId()) {
                $sharedFrom[] = [
                    'id' => $share->getId(),
                    'group_id' => $share->getGroupId(),
                    'group_name' => $names[$share->getGroupId()] ?? $share->getGroupId(),
                ];
            }
        }

        return [
            'id' => $group->getId(),
            'name' => $group->getName(),
            'department_id' => $group->getDepartmentId(),
            'parent_id' => $group->getParentId(),
            'sort_order' => $group->getSortOrder(),
            'level' => $level,
            'kind' => $kind,
            'node_type' => $nodeType,
            'window_start' => $group->getWindowStart()?->format('Y-m-d'),
            'window_end' => $group->getWindowEnd()?->format('Y-m-d'),
            'build_status' => $group->getBuildStatus(),
            'procurement_progress' => $procurementProgress,
            'description' => $group->getDescription(),
            'place' => $place,
            'places' => $places ?? ($place !== null ? [$place] : []),
            'include_on_map' => is_array($place) && ($nodeType === 'auftrag' || ($place['kind'] ?? '') === GrossanlassPlaceCodes::KIND_AREA),
            'member_count' => count($members),
            'leader_count' => count($leaders),
            'members' => array_values($members),
            'leaders' => $leaders,
            'shared_with' => $sharedWith,
            'shared_from' => $sharedFrom,
            'created_at' => $group->getCreatedAt()->format('c'),
            'updated_at' => $group->getUpdatedAt()->format('c'),
        ];
    }

    /**
     * @return list<DepartmentGrossanlassGroupShare>
     */
    private function loadShares(Department $department): array
    {
        /** @var list<DepartmentGrossanlassGroupShare> $rows */
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassGroupShare::class)
            ->findBy(['departmentId' => $department->getId()]);

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeShare(DepartmentGrossanlassGroupShare $row, Group $source, Group $target): array
    {
        return [
            'id' => $row->getId(),
            'group_id' => $source->getId(),
            'group_name' => $source->getName(),
            'target_group_id' => $target->getId(),
            'target_name' => $target->getName(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyWindow(Group $group, array $data): void
    {
        if (!array_key_exists('window_start', $data) && !array_key_exists('window_end', $data)) {
            return;
        }
        $nodeType = $this->resolveNodeType($group, $this->resolveStoredKind($group));
        if ($nodeType === 'ressort') {
            $group->setWindowStart(null);
            $group->setWindowEnd(null);

            return;
        }

        $start = $this->parseOptionalDate($data['window_start'] ?? null, 'window_start');
        $end = $this->parseOptionalDate($data['window_end'] ?? null, 'window_end');
        if ($start !== null && $end !== null && $end < $start) {
            throw new \InvalidArgumentException('Zeitfenster Ende muss nach Start liegen');
        }
        $group->setWindowStart($start);
        $group->setWindowEnd($end);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyBuildStatus(Department $department, User $user, Group $group, array $data): void
    {
        if (!array_key_exists('build_status', $data)) {
            return;
        }
        if (!$this->access->canSetBuildStatus($user, $department, $group)) {
            return;
        }
        $nodeType = $this->resolveNodeType($group, $this->resolveStoredKind($group));
        if ($nodeType === 'ressort') {
            $group->setBuildStatus(null);

            return;
        }
        $raw = $data['build_status'];
        if ($raw === null || $raw === '') {
            $group->setBuildStatus(null);

            return;
        }
        $status = strtolower(trim((string) $raw));
        if (!in_array($status, Group::BUILD_STATUSES, true)) {
            throw new \InvalidArgumentException('Ungültiger Bauvorhaben-Status');
        }
        $group->setBuildStatus($status);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyDescription(Group $group, array $data): void
    {
        if (!array_key_exists('description', $data)) {
            return;
        }
        $raw = $data['description'];
        $group->setDescription($raw === null ? null : (string) $raw);
    }

    private function parseOptionalDate(mixed $value, string $field): ?\DateTime
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTime::createFromInterface($value)->setTime(0, 0);
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

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function syncPlaceForGroup(Department $department, User $user, Group $group, array $data): ?array
    {
        // Bauprojekte haben immer einen Standort; Bereiche und Aufträge nur auf Wunsch (include_on_map).
        $isProject = $this->resolveStoredKind($group) === Group::GROSSANLASS_KIND_TEILBEREICH;
        $existing = $this->places->findForGroup($department, $group->getId());
        if (!$isProject) {
            $include = array_key_exists('include_on_map', $data)
                ? filter_var($data['include_on_map'], FILTER_VALIDATE_BOOLEAN)
                : $existing instanceof DepartmentGrossanlassPlace;
            if (!$include) {
                // Nur die Zuordnung dieser Gruppe entfällt; der Standort bleibt für alle anderen bestehen.
                $this->places->detachOrgPlaceForGroup($department, $group);

                return null;
            }
        }

        $requested = isset($data['place_kind']) ? (string) $data['place_kind'] : '';
        $kind = match ($requested) {
            'area' => GrossanlassPlaceCodes::KIND_AREA,
            'point' => GrossanlassPlaceCodes::KIND_BAUPROJEKT,
            default => $existing instanceof DepartmentGrossanlassPlace && in_array($existing->getKind(), [GrossanlassPlaceCodes::KIND_AREA, GrossanlassPlaceCodes::KIND_BAUPROJEKT], true)
                ? $existing->getKind()
                : ($isProject || $this->resolveStoredKind($group) === Group::GROSSANLASS_KIND_AUFTRAG
                    ? GrossanlassPlaceCodes::KIND_BAUPROJEKT
                    : GrossanlassPlaceCodes::KIND_AREA),
        };
        $placeName = isset($data['place_name']) ? trim((string) $data['place_name']) : '';

        $linkPlaceId = isset($data['place_id']) ? trim((string) $data['place_id']) : '';
        if ($linkPlaceId !== '') {
            // Verknüpfen ändert nur die Zuordnung dieser Gruppe; der Standort bleibt unverändert.
            $row = $this->places->linkExistingForGroup($department, $group, $linkPlaceId);
            if ($placeName !== '' && $placeName !== $row->getName()) {
                $this->assertMayChangeSharedPlace($department, $user, $row);
                $row->setName($placeName);
                $this->entityManager->flush();
            }

            return $this->places->serialize($row);
        }

        if ($existing instanceof DepartmentGrossanlassPlace && $this->places->isShared($existing)) {
            // Gemeinsam genutzter Standort: Änderungen wirken auf alle verknüpften Gruppen.
            $changesPlace = ($placeName !== '' && $placeName !== $existing->getName())
                || $kind !== $existing->getKind()
                || (array_key_exists('polygon', $data) && $this->polygonDiffers($existing, $data['polygon']));
            if ($changesPlace) {
                $this->assertMayChangeSharedPlace($department, $user, $existing);
            }
        }

        $row = $this->places->ensureForGroup($department, $group, $kind, $placeName !== '' ? $placeName : null);
        if ($kind === GrossanlassPlaceCodes::KIND_AREA && array_key_exists('polygon', $data) && $this->polygonDiffers($row, $data['polygon'])) {
            $this->places->applyPolygonData($row, $data['polygon']);
            $this->entityManager->flush();
        }

        return $this->places->serialize($row);
    }

    /**
     * Eine Gruppenberechtigung genügt nicht, um einen Standort zu ändern, den auch andere Gruppen nutzen.
     */
    private function assertMayChangeSharedPlace(Department $department, User $user, DepartmentGrossanlassPlace $place): void
    {
        if (!$this->places->isShared($place)) {
            return;
        }
        if (!$this->places->canManagePlaces($user, $department)) {
            throw new \RuntimeException('Dieser Standort wird von mehreren Gruppen genutzt — Änderungen nur in der Standortverwaltung.');
        }
    }

    private function polygonDiffers(DepartmentGrossanlassPlace $place, mixed $polygon): bool
    {
        return GrossanlassPlaceCodes::normalizePolygon($polygon) !== GrossanlassPlaceCodes::normalizePolygon($place->getPolygon());
    }

    /**
     * @return array<string, list<array<string, mixed>>> Gruppen-ID => Standorte, Hauptstandort zuerst
     */
    private function loadAllPlacesByGroup(Department $department): array
    {
        $rows = $this->places->rows($department);
        $groupIdsByPlace = $this->places->groupIdsByPlace($rows);
        $out = [];
        foreach ($this->places->placesByGroup($department, $rows) as $groupId => $list) {
            foreach ($list as $place) {
                $out[$groupId][] = $this->places->serialize($place, $groupIdsByPlace[$place->getId()] ?? []);
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializedPlacesOf(Department $department, Group $group): array
    {
        return array_map(
            fn (DepartmentGrossanlassPlace $p) => $this->places->serialize($p),
            $this->places->placesForGroup($department, $group->getId()),
        );
    }

    /**
     * Zusätzlichen Standortpunkt verknüpfen (place_id) oder neu anlegen (name, latitude, longitude).
     * Das Polygon der Gruppe bleibt unverändert.
     *
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>> alle Standorte der Gruppe
     */
    public function addGroupPlace(Department $department, User $user, Group $group, array $data): array
    {
        $this->assertMayEditGroupPlaces($department, $user, $group);
        $placeId = trim((string) ($data['place_id'] ?? ''));
        if ($placeId !== '') {
            $row = $this->places->getPlace($department, $placeId);
            if ($row->getKind() === GrossanlassPlaceCodes::KIND_AREA) {
                $this->places->linkExistingForGroup($department, $group, $placeId);
            } else {
                $this->places->linkExistingForGroup($department, $group, $placeId, true);
            }
        } else {
            $this->places->createPointForGroup($department, $group, $data);
        }

        return $this->serializedPlacesOf($department, $group);
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    public function updateGroupPlace(Department $department, User $user, Group $group, string $placeId, array $data): array
    {
        $this->assertMayEditGroupPlaces($department, $user, $group);
        $row = $this->places->getPlace($department, $placeId);
        $this->assertMayChangeSharedPlace($department, $user, $row);
        $this->places->updatePointForGroup($department, $group, $placeId, $data);

        return $this->serializedPlacesOf($department, $group);
    }

    /**
     * Entkoppelt den Standort von der Gruppe. Der Standort selbst (Name, Position, QR-Code) bleibt bestehen.
     *
     * @return list<array<string, mixed>>
     */
    public function removeGroupPlace(Department $department, User $user, Group $group, string $placeId): array
    {
        $this->assertMayEditGroupPlaces($department, $user, $group);
        $row = $this->places->getPlace($department, $placeId);
        $this->places->unlinkGroup($row, $group->getId());

        return $this->serializedPlacesOf($department, $group);
    }

    private function assertMayEditGroupPlaces(Department $department, User $user, Group $group): void
    {
        $this->access->assertGrossanlassDepartment($department);
        $this->assertGroupBelongsToDepartment($group, $department);
        if (!$this->access->canEditGroup($user, $department, $group)) {
            throw new \RuntimeException('Keine Berechtigung, Ressort zu bearbeiten');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeGroupMember(GroupMembership $membership): array
    {
        $user = $membership->getUser();
        $profile = $user->getProfile();

        return [
            'user_id' => $user->getId(),
            'name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
            'first_name' => $profile?->getFirstName(),
            'last_name' => $profile?->getLastName(),
            'nickname' => $profile?->getNickname(),
            'email' => $profile ? $profile->getEmail() : '',
            'avatar_initials' => $profile?->getAvatarInitials(),
            'background_color' => $profile?->getBackgroundColor(),
            'text_color' => $profile?->getTextColor(),
            'role' => $membership->getRole(),
            'role_label' => $membership->getRoleLabel(),
            'is_leader' => $membership->isLeader(),
            'is_primary' => $membership->getIsPrimary(),
            'can_procure' => $membership->getCanProcure(),
        ];
    }

    /**
     * @param list<string> $groupIds
     */
    private function countMembershipsInGroups(array $groupIds): int
    {
        if ($groupIds === []) {
            return 0;
        }

        return (int) $this->entityManager->getRepository(GroupMembership::class)
            ->createQueryBuilder('gm')
            ->select('COUNT(gm.userId)')
            ->where('gm.groupId IN (:groupIds)')
            ->setParameter('groupIds', $groupIds)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<string> $groupIds
     */
    private function hasWishReferences(array $groupIds): bool
    {
        if ($groupIds === []) {
            return false;
        }

        return (int) $this->entityManager->getRepository(\App\Entity\ActivityGrossanlassWishLine::class)
            ->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->where('w.groupId IN (:groupIds)')
            ->setParameter('groupIds', $groupIds)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    private function deleteSubtreeGroups(string $departmentId, string $rootGroupId): void
    {
        $subtreeIds = $this->hierarchy->expandWithDescendants($departmentId, [$rootGroupId]);
        usort($subtreeIds, function (string $a, string $b) use ($departmentId): int {
            return $this->hierarchy->computeDepth($departmentId, $b)
                <=> $this->hierarchy->computeDepth($departmentId, $a);
        });

        if ($subtreeIds === []) {
            return;
        }

        // Orte werden nur entkoppelt (Zuordnung entfernt), nie mit der Gruppe gelöscht.
        $links = $this->entityManager->getRepository(DepartmentGrossanlassPlaceGroup::class)
            ->createQueryBuilder('l')
            ->where('IDENTITY(l.group) IN (:groupIds)')
            ->setParameter('groupIds', $subtreeIds)
            ->getQuery()
            ->getResult();
        foreach ($links as $link) {
            if ($link instanceof DepartmentGrossanlassPlaceGroup && $link->getPlace()->getDepartmentId() === $departmentId) {
                $this->places->unlinkGroup($link->getPlace(), $link->getGroup()->getId());
            }
        }

        $costs = $this->entityManager->getRepository(DepartmentGrossanlassCost::class)
            ->createQueryBuilder('c')
            ->where('c.payerGroupId IN (:groupIds)')
            ->setParameter('groupIds', $subtreeIds)
            ->getQuery()
            ->getResult();
        foreach ($costs as $cost) {
            if ($cost instanceof DepartmentGrossanlassCost) {
                $cost->setPayerGroup(null);
            }
        }

        foreach ($subtreeIds as $groupId) {
            $group = $this->entityManager->getRepository(Group::class)->find($groupId);
            if ($group !== null) {
                $this->entityManager->remove($group);
            }
        }
        $this->entityManager->flush();
    }

    private function isLeafKind(?string $kind): bool
    {
        return $kind === Group::GROSSANLASS_KIND_TEILBEREICH || $kind === Group::GROSSANLASS_KIND_AUFTRAG;
    }

    /**
     * Bauprojekte und Aufträge hängen nur an Ressort/Bereich und tragen selbst keine Kinder.
     * Nur Ressorts dürfen ohne Parent stehen.
     */
    private function assertParentAllowed(?string $kind, ?Group $parent): void
    {
        if ($parent === null) {
            if ($kind !== Group::GROSSANLASS_KIND_RESSORT) {
                throw new \InvalidArgumentException('Bereich, Bauprojekt und Auftrag benötigen ein übergeordnetes Element');
            }

            return;
        }
        if ($this->isLeafKind($this->resolveStoredKind($parent))) {
            throw new \InvalidArgumentException('Unter einem Bauprojekt oder Auftrag können keine Elemente liegen');
        }
    }

    /**
     * Verschieben ändert nur parent_id: Wünsche, Aufgaben und Standortverknüpfungen hängen an der Gruppen-ID.
     * Ressorts/Bereiche verschiebt nur die Strukturverwaltung; Bauprojekte und Aufträge auch Leitungen,
     * sofern sie am Ziel Kinder anlegen dürfen.
     *
     * @param array<string, mixed> $data
     */
    private function applyParentChange(Department $department, User $user, Group $group, array $data): void
    {
        $newParentId = empty($data['parent_id']) ? null : (string) $data['parent_id'];
        if ($newParentId === $group->getParentId()) {
            return;
        }
        $kind = $this->kindFromChoice($group->getParent(), $data['kind'] ?? $group->getGrossanlassKind());
        $isLeaf = $this->isLeafKind($this->resolveStoredKind($group));

        $parent = null;
        if ($newParentId !== null) {
            if ($newParentId === $group->getId()) {
                throw new \InvalidArgumentException('Gruppe kann nicht sich selbst übergeordnet werden');
            }
            $parent = $this->findGroupInDepartment($department, $newParentId);
        }

        if (!$this->access->canManageStruktur($user, $department)) {
            if (!$isLeaf) {
                return;
            }
            if ($parent === null || !$this->access->canCreateChildGroup($user, $department, $parent)) {
                throw new \RuntimeException('Keine Berechtigung, in dieses Element zu verschieben');
            }
        }

        if ($parent === null) {
            if ($this->isLeafKind($kind)) {
                $this->assertParentAllowed($kind, null);
            }
            $group->setParent(null);

            return;
        }

        $subtreeIds = $this->hierarchy->expandWithDescendants($department->getId(), [$group->getId()]);
        if (in_array($parent->getId(), $subtreeIds, true)) {
            throw new \InvalidArgumentException('Übergeordnete Gruppe darf nicht im eigenen Subtree liegen');
        }
        $this->assertParentAllowed($kind, $parent);
        $newDepth = $this->hierarchy->computeDepth($department->getId(), $parent->getId()) + 1;
        $currentDepth = $this->hierarchy->computeDepth($department->getId(), $group->getId());
        $subtreeSpan = $this->hierarchy->computeMaxSubtreeDepth($department->getId(), $group->getId()) - $currentDepth;
        if ($newDepth + $subtreeSpan > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('Verschieben würde maximale Hierarchietiefe von ' . self::MAX_DEPTH . ' überschreiten');
        }
        $group->setParent($parent);
    }

    private function applyStructuralKind(Group $group, mixed $requested): void
    {
        $group->setGrossanlassKind($this->kindFromChoice($group->getParent(), $requested));
    }

    private function kindFromChoice(?Group $parent, mixed $requested): string
    {
        $kind = strtolower(trim((string) ($requested ?? '')));
        if ($kind === Group::GROSSANLASS_KIND_BEREICH || $kind === 'unterressort') {
            return Group::GROSSANLASS_KIND_BEREICH;
        }
        if ($kind === Group::GROSSANLASS_KIND_RESSORT) {
            return Group::GROSSANLASS_KIND_RESSORT;
        }
        if ($kind === Group::GROSSANLASS_KIND_TEILBEREICH) {
            return Group::GROSSANLASS_KIND_TEILBEREICH;
        }
        if ($kind === Group::GROSSANLASS_KIND_AUFTRAG) {
            return Group::GROSSANLASS_KIND_AUFTRAG;
        }

        return $parent instanceof Group
            ? Group::GROSSANLASS_KIND_BEREICH
            : Group::GROSSANLASS_KIND_RESSORT;
    }

    private function resolveStoredKind(Group $group): string
    {
        $stored = $group->getGrossanlassKind();
        if ($stored !== null && $stored !== '') {
            return $stored;
        }

        return $group->getParentId() === null
            ? Group::GROSSANLASS_KIND_RESSORT
            : Group::GROSSANLASS_KIND_TEILBEREICH;
    }

    private function resolveNodeType(Group $group, string $kind): string
    {
        unset($group);

        return match (strtolower($kind)) {
            Group::GROSSANLASS_KIND_TEILBEREICH => 'bauprojekt',
            Group::GROSSANLASS_KIND_AUFTRAG => 'auftrag',
            Group::GROSSANLASS_KIND_BEREICH => 'unterressort',
            default => 'ressort',
        };
    }
}
