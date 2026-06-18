<?php

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\User;
use App\Service\GroupAccessService;
use App\Service\GroupHierarchyService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Berechtigungen für Grossanlass-Planung (Ressorts = Groups).
 */
class GrossanlassAccessService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GroupAccessService $groupAccess,
        private GroupHierarchyService $hierarchy,
    ) {}

    public function assertGrossanlassDepartment(Department $department): void
    {
        if (!$department->isGrossanlass()) {
            throw new \InvalidArgumentException('Kein Grossanlass-Department');
        }
    }

    public function canManagePlanung(User $user, Department $department): bool
    {
        if (!$department->isGrossanlass()) {
            return false;
        }

        return $this->groupAccess->canFullyManageDepartmentGroups($user, $department->getId());
    }

    public function canCreateRootRessort(User $user, Department $department): bool
    {
        return $this->canManagePlanung($user, $department);
    }

    public function canCreateChildGroup(User $user, Department $department, Group $parent): bool
    {
        if (!$department->isGrossanlass()) {
            return false;
        }
        if ($this->canManagePlanung($user, $department)) {
            return true;
        }

        return $this->userIsMemberInRessortBranch($user, $department->getId(), $parent);
    }

    public function canEditGroup(User $user, Department $department): bool
    {
        return $this->canManagePlanung($user, $department);
    }

    public function canDeleteGroup(User $user, Department $department): bool
    {
        return $this->canManagePlanung($user, $department);
    }

    public function canManageGroupMembers(User $user, Department $department, Group $group): bool
    {
        if (!$department->isGrossanlass()) {
            return false;
        }
        if ($this->canManagePlanung($user, $department)) {
            return true;
        }
        if ($this->groupAccess->isGroupLeaderOfGroup($user, $group->getId())) {
            return true;
        }

        return $this->userIsMemberInRessortBranch($user, $department->getId(), $group);
    }

    public function userIsMemberInRessortBranch(User $user, string $departmentId, Group $group): bool
    {
        $rootId = $this->findRootRessortId($group);
        $branchIds = $this->hierarchy->expandWithDescendants($departmentId, [$rootId]);
        if ($branchIds === []) {
            return false;
        }

        $membership = $this->entityManager->getRepository(GroupMembership::class)
            ->createQueryBuilder('gm')
            ->select('COUNT(gm.id)')
            ->where('gm.userId = :userId')
            ->andWhere('gm.groupId IN (:groupIds)')
            ->setParameter('userId', $user->getId())
            ->setParameter('groupIds', $branchIds)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $membership > 0;
    }

    public function findRootRessortId(Group $group): string
    {
        $current = $group;
        $seen = [];
        while ($current->getParentId() !== null && $current->getParentId() !== '') {
            if (isset($seen[$current->getId()])) {
                break;
            }
            $seen[$current->getId()] = true;
            $parent = $this->entityManager->getRepository(Group::class)->find($current->getParentId());
            if ($parent === null) {
                break;
            }
            $current = $parent;
        }

        return $current->getId();
    }
}
