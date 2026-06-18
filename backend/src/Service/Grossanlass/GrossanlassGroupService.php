<?php

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\User;
use App\Service\GroupAccessService;
use App\Service\GroupHierarchyService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

class GrossanlassGroupService
{
    public const MAX_DEPTH = 10;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GroupAccessService $groupAccess,
        private GroupHierarchyService $hierarchy,
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

        $result = [];
        foreach ($groups as $group) {
            $result[] = $this->serializeGroup($department, $group, $membershipsByGroup[$group->getId()] ?? []);
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
                throw new \RuntimeException('Keine Berechtigung, Bauprojekt anzulegen');
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
        $group->setId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, Group::class, 'grp'));
        $group->setDepartment($department);
        $group->setName(trim((string) $data['name']));
        if ($parent !== null) {
            $group->setParent($parent);
        }
        if (isset($data['sort_order'])) {
            $group->setSortOrder((int) $data['sort_order']);
        }

        $this->entityManager->persist($group);
        $this->entityManager->flush();

        return $this->serializeGroup($department, $group, []);
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

        if (!$this->access->canEditGroup($user, $department)) {
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

        $group->updateTimestamps();
        $this->entityManager->flush();

        $members = $this->loadMembershipsByGroup([$group->getId()])[$group->getId()] ?? [];

        return $this->serializeGroup($department, $group, $members);
    }

    public function deleteGroup(Department $department, User $user, Group $group): void
    {
        $this->access->assertGrossanlassDepartment($department);
        $this->assertGroupBelongsToDepartment($group, $department);

        if (!$this->access->canDeleteGroup($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung, Ressort zu löschen');
        }

        $subtreeIds = $this->hierarchy->expandWithDescendants($department->getId(), [$group->getId()]);
        if ($this->countMembershipsInGroups($subtreeIds) > 0) {
            throw new \RuntimeException('Löschen nicht möglich: Im Subtree sind noch Mitglieder zugewiesen');
        }

        if ($this->hasWishReferences($subtreeIds)) {
            throw new \RuntimeException('Löschen nicht möglich: Es bestehen noch Wunsch-Referenzen auf Knoten im Subtree');
        }

        $this->deleteSubtreeGroups($department->getId(), $group->getId());
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
            && !$this->access->canManagePlanung($user, $department);

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

        if (!$this->access->canManagePlanung($user, $department)) {
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
     *
     * @return array<string, mixed>
     */
    private function serializeGroup(Department $department, Group $group, array $members): array
    {
        $level = $this->hierarchy->computeDepth($department->getId(), $group->getId());
        $leaders = array_values(array_filter($members, static fn (array $m) => $m['is_leader']));

        return [
            'id' => $group->getId(),
            'name' => $group->getName(),
            'department_id' => $group->getDepartmentId(),
            'parent_id' => $group->getParentId(),
            'sort_order' => $group->getSortOrder(),
            'level' => $level,
            'kind' => $group->getParentId() === null ? 'ressort' : 'teilbereich',
            'member_count' => count($members),
            'leader_count' => count($leaders),
            'members' => array_values($members),
            'leaders' => $leaders,
            'created_at' => $group->getCreatedAt()->format('c'),
            'updated_at' => $group->getUpdatedAt()->format('c'),
        ];
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
            ->select('COUNT(gm.id)')
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
        // Phase 4: activity_grossanlass_wish_line — noch nicht implementiert
        return false;
    }

    private function deleteSubtreeGroups(string $departmentId, string $rootGroupId): void
    {
        $subtreeIds = $this->hierarchy->expandWithDescendants($departmentId, [$rootGroupId]);
        usort($subtreeIds, function (string $a, string $b) use ($departmentId): int {
            return $this->hierarchy->computeDepth($departmentId, $b)
                <=> $this->hierarchy->computeDepth($departmentId, $a);
        });

        foreach ($subtreeIds as $groupId) {
            $group = $this->entityManager->getRepository(Group::class)->find($groupId);
            if ($group !== null) {
                $this->entityManager->remove($group);
            }
        }
        $this->entityManager->flush();
    }
}
