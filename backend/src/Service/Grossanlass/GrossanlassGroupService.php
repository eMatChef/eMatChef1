<?php

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassCost;
use App\Entity\DepartmentGrossanlassGroupShare;
use App\Entity\DepartmentGrossanlassPlace;
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
        $placesByGroup = $this->loadPlacesByGroup($department);
        $shares = $this->loadShares($department);
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
            $group->setGrossanlassKind($this->resolveKindForCreate($parent, $data));
        } else {
            $group->setGrossanlassKind(Group::GROSSANLASS_KIND_RESSORT);
        }
        if (isset($data['sort_order'])) {
            $group->setSortOrder((int) $data['sort_order']);
        }
        $this->applyWindow($group, $data);
        $this->applyBuildStatus($group, $data);
        $this->applyDescription($group, $data);

        $this->entityManager->persist($group);
        $this->entityManager->flush();
        $place = $this->syncPlaceForGroup($department, $group, $data);

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

        if (array_key_exists('parent_id', $data) && $this->access->canManageStruktur($user, $department)) {
            if (empty($data['parent_id'])) {
                $group->setParent(null);
            } else {
                if ($data['parent_id'] === $group->getId()) {
                    throw new \InvalidArgumentException('Gruppe kann nicht sich selbst übergeordnet werden');
                }
                $parent = $this->findGroupInDepartment($department, (string) $data['parent_id']);
                $subtreeIds = $this->hierarchy->expandWithDescendants($department->getId(), [$group->getId()]);
                if (in_array($parent->getId(), $subtreeIds, true)) {
                    throw new \InvalidArgumentException('Übergeordnete Gruppe darf nicht im eigenen Subtree liegen');
                }
                $newDepth = $this->hierarchy->computeDepth($department->getId(), $parent->getId()) + 1;
                $currentDepth = $this->hierarchy->computeDepth($department->getId(), $group->getId());
                $subtreeSpan = $this->hierarchy->computeMaxSubtreeDepth($department->getId(), $group->getId()) - $currentDepth;
                if ($newDepth + $subtreeSpan > self::MAX_DEPTH) {
                    throw new \InvalidArgumentException('Verschieben würde maximale Hierarchietiefe von ' . self::MAX_DEPTH . ' überschreiten');
                }
                $group->setParent($parent);
            }
        }

        if (isset($data['sort_order'])) {
            $group->setSortOrder((int) $data['sort_order']);
        }

        if (array_key_exists('kind', $data) && $this->access->canManageStruktur($user, $department)) {
            $this->applyKindChange($group, $data['kind'] ?? null);
        } elseif ($group->getGrossanlassKind() === null) {
            $group->setGrossanlassKind(
                $group->getParentId() === null
                    ? Group::GROSSANLASS_KIND_RESSORT
                    : Group::GROSSANLASS_KIND_TEILBEREICH,
            );
        }
        $this->applyWindow($group, $data);
        $this->applyBuildStatus($group, $data);
        $this->applyDescription($group, $data);

        $group->updateTimestamps();
        $this->entityManager->flush();
        $place = $this->syncPlaceForGroup($department, $group, $data);

        $members = $this->loadMembershipsByGroup([$group->getId()])[$group->getId()] ?? [];

        return $this->serializeGroup($department, $group, $members, $place);
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

        $isBauprojekt = $this->resolveStoredKind($group) === Group::GROSSANLASS_KIND_TEILBEREICH;
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
            'description' => $group->getDescription(),
            'place' => $place,
            'include_on_map' => is_array($place) && ($place['kind'] ?? '') === GrossanlassPlaceCodes::KIND_AREA,
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
    private function applyBuildStatus(Group $group, array $data): void
    {
        if (!array_key_exists('build_status', $data)) {
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
    private function syncPlaceForGroup(Department $department, Group $group, array $data): ?array
    {
        if ($this->resolveStoredKind($group) === Group::GROSSANLASS_KIND_TEILBEREICH) {
            return $this->places->serialize($this->places->ensureForBauprojekt($department, $group));
        }

        $existing = $this->places->findForGroup($department, $group->getId());
        $include = array_key_exists('include_on_map', $data)
            ? filter_var($data['include_on_map'], FILTER_VALIDATE_BOOLEAN)
            : ($existing instanceof DepartmentGrossanlassPlace
                && $existing->getKind() === GrossanlassPlaceCodes::KIND_AREA);
        if (!$include) {
            $this->places->detachOrgPlaceForGroup($department, $group);

            return null;
        }

        $row = $this->places->ensureForArea($department, $group);
        if (array_key_exists('polygon', $data)) {
            $this->places->applyPolygonData($row, $data['polygon']);
            $this->entityManager->flush();
        }

        return $this->places->serialize($row);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadPlacesByGroup(Department $department): array
    {
        $out = [];
        foreach ($this->places->rows($department) as $row) {
            $groupId = $row->getGroupId();
            if ($groupId === null || $groupId === '') {
                continue;
            }
            if (isset($out[$groupId]) && $row->getKind() !== GrossanlassPlaceCodes::KIND_BAUPROJEKT) {
                continue;
            }
            $out[$groupId] = $this->places->serialize($row);
        }

        return $out;
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

        $places = $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)
            ->createQueryBuilder('p')
            ->where('p.departmentId = :departmentId')
            ->andWhere('p.groupId IN (:groupIds)')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('groupIds', $subtreeIds)
            ->getQuery()
            ->getResult();
        foreach ($places as $place) {
            if (!$place instanceof DepartmentGrossanlassPlace) {
                continue;
            }
            if ($place->getKind() === GrossanlassPlaceCodes::KIND_AREA) {
                $this->entityManager->remove($place);
                continue;
            }
            $place->setGroupId(null);
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

    /**
     * @param array<string, mixed> $data
     */
    private function resolveKindForCreate(Group $parent, array $data): string
    {
        $kind = isset($data['kind']) ? strtolower(trim((string) $data['kind'])) : Group::GROSSANLASS_KIND_TEILBEREICH;
        if (!in_array($kind, [Group::GROSSANLASS_KIND_RESSORT, Group::GROSSANLASS_KIND_TEILBEREICH], true)) {
            throw new \InvalidArgumentException('kind muss ressort (Unterressort) oder teilbereich (Bauprojekt) sein');
        }

        return $kind;
    }

    private function applyKindChange(Group $group, mixed $kindRaw): void
    {
        if ($group->getParentId() === null) {
            $group->setGrossanlassKind(Group::GROSSANLASS_KIND_RESSORT);

            return;
        }

        $kind = strtolower(trim((string) ($kindRaw ?? '')));
        if (!in_array($kind, [Group::GROSSANLASS_KIND_RESSORT, Group::GROSSANLASS_KIND_TEILBEREICH], true)) {
            throw new \InvalidArgumentException('kind muss ressort (Unterressort) oder teilbereich (Bauprojekt) sein');
        }
        $group->setGrossanlassKind($kind);
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
        if ($group->getParentId() === null) {
            return 'ressort';
        }

        return $kind === Group::GROSSANLASS_KIND_RESSORT ? 'unterressort' : 'bauprojekt';
    }
}
