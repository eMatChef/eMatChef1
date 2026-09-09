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

    /**
     * Struktur anlassweit: MW, CMW, OK-Leitung (dc). Nicht Gmail, nicht Beschaffung.
     */
    public function canManagePlanung(User $user, Department $department): bool
    {
        if (!$department->isGrossanlass()) {
            return false;
        }

        return $this->groupAccess->canFullyManageDepartmentGroups($user, $department->getId());
    }

    public function canWorkMailbox(User $user, Department $department): bool
    {
        return GrossanlassAccessRoles::canWorkMailbox($this->gaRole($user, $department));
    }

    public function canTakeInquiry(User $user, Department $department): bool
    {
        return GrossanlassAccessRoles::canTakeInquiry($this->gaRole($user, $department));
    }

    public function canCreateMailDrafts(User $user, Department $department): bool
    {
        return GrossanlassAccessRoles::canCreateMailDrafts($this->gaRole($user, $department));
    }

    public function canSendMail(User $user, Department $department): bool
    {
        return GrossanlassAccessRoles::canSendMail($this->gaRole($user, $department));
    }

    public function canConnectGmail(User $user, Department $department): bool
    {
        return GrossanlassAccessRoles::canConnectGmail($this->gaRole($user, $department));
    }

    public function canApproveEinsatz(User $user, Department $department): bool
    {
        return GrossanlassAccessRoles::canApproveEinsatz($this->gaRole($user, $department));
    }

    public function canReleaseTrip(User $user, Department $department): bool
    {
        return GrossanlassAccessRoles::canReleaseTrip($this->gaRole($user, $department));
    }

    public function canManageProcurement(User $user, Department $department): bool
    {
        return GrossanlassAccessRoles::canManageProcurement($this->gaRole($user, $department));
    }

    public function canSeeAnlassOverview(User $user, Department $department): bool
    {
        return GrossanlassAccessRoles::canSeeAnlassOverview($this->gaRole($user, $department));
    }

    public function canOperateAusgabe(User $user, Department $department): bool
    {
        return GrossanlassAccessRoles::canOperateAusgabe($this->gaRole($user, $department));
    }

    public function canVerifyDriveCard(User $user, Department $department): bool
    {
        return GrossanlassAccessRoles::canVerifyDriveCard($this->gaRole($user, $department));
    }

    /**
     * Leader am Knoten reicht ein; MW/CMW/OK-L sind direkt frei.
     */
    public function canSubmitEinsatz(User $user, Department $department, ?Group $group = null): bool
    {
        if (!$department->isGrossanlass()) {
            return false;
        }
        if (GrossanlassAccessRoles::submitsEinsatzDirectlyFree($this->membershipRole($user, $department) ?? '')) {
            return true;
        }
        if ($group === null) {
            return false;
        }

        return $this->groupAccess->isGroupLeaderOfGroup($user, $group->getId());
    }

    /** Nur Materialwart (nicht CMW/DC): Formular-Builder bearbeiten. */
    public function canManageGrossanlassForm(User $user, Department $department): bool
    {
        if (!$department->isGrossanlass()) {
            return false;
        }

        return $this->isDepartmentMaterialwart($user, $department->getId());
    }

    /** Nur Materialwart: eingereichte Antworten annehmen. */
    public function canAcceptWishResponses(User $user, Department $department): bool
    {
        return $this->canManageGrossanlassForm($user, $department);
    }

    public function membershipRole(User $user, Department $department): ?string
    {
        $membership = $this->entityManager->getRepository(\App\Entity\Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $department->getId(),
        ]);
        if ($membership === null) {
            return null;
        }

        return GrossanlassAccessRoles::normalize((string) ($membership->getRole() ?? ''));
    }

    private function gaRole(User $user, Department $department): string
    {
        if (!$department->isGrossanlass()) {
            return '';
        }

        return $this->membershipRole($user, $department) ?? '';
    }

    private function isDepartmentMaterialwart(User $user, string $departmentId): bool
    {
        $membership = $this->entityManager->getRepository(\App\Entity\Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if ($membership === null) {
            return false;
        }

        $role = GrossanlassAccessRoles::normalize((string) ($membership->getRole() ?? ''));

        return $role === 'mw';
    }

    public function canCreateRootRessort(User $user, Department $department): bool
    {
        return $this->canManagePlanung($user, $department);
    }

    public function canCreateChildGroup(User $user, Department $department, Group $parent, bool $leaderOnly = false): bool
    {
        if (!$department->isGrossanlass()) {
            return false;
        }
        if ($this->canManagePlanung($user, $department)) {
            return true;
        }
        if ($leaderOnly) {
            return $this->groupAccess->isGroupLeaderOfGroup($user, $parent->getId());
        }

        return $this->userIsMemberInRessortBranch($user, $department->getId(), $parent);
    }

    public function canSelectRessortForWish(User $user, Department $department, Group $group, bool $leaderOnly = false): bool
    {
        if ($group->getParentId() !== null && $group->getParentId() !== '') {
            $kind = $group->getGrossanlassKind();
            if ($kind === null || $kind === '' || $kind === Group::GROSSANLASS_KIND_TEILBEREICH) {
                return false;
            }
        }

        return $this->canSelectBauprojektForWish($user, $department, $group, $leaderOnly);
    }

    public function canSelectBauprojektForWish(User $user, Department $department, Group $group, bool $leaderOnly = false): bool
    {
        if (!$department->isGrossanlass()) {
            return false;
        }
        if ($this->canManagePlanung($user, $department)) {
            return true;
        }
        if (!$leaderOnly) {
            return $this->userIsMemberInRessortBranch($user, $department->getId(), $group);
        }

        $rootId = $this->findRootRessortId($group);
        if ($this->groupAccess->isGroupLeaderOfGroup($user, $rootId)) {
            return $this->userIsMemberInRessortBranch($user, $department->getId(), $group);
        }

        return $this->groupAccess->isGroupLeaderOfGroup($user, $group->getId());
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
            ->select('COUNT(gm.userId)')
            ->where('gm.userId = :userId')
            ->andWhere('gm.groupId IN (:groupIds)')
            ->setParameter('userId', $user->getId())
            ->setParameter('groupIds', $branchIds)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $membership > 0;
    }

    /**
     * Gruppen-IDs im eigenen Ressort-Baum: direkte GroupMembership + Nachfahren (keine Geschwister-Zweige).
     *
     * @return list<string>
     */
    public function resolveAssignedGroupBranchIds(User $user, string $departmentId): array
    {
        /** @var list<GroupMembership> $memberships */
        $memberships = $this->entityManager->getRepository(GroupMembership::class)
            ->createQueryBuilder('gm')
            ->innerJoin('gm.group', 'g')
            ->where('gm.userId = :userId')
            ->andWhere('g.departmentId = :departmentId')
            ->setParameter('userId', $user->getId())
            ->setParameter('departmentId', $departmentId)
            ->getQuery()
            ->getResult();

        $visible = [];
        foreach ($memberships as $membership) {
            if (!$membership instanceof GroupMembership) {
                continue;
            }
            $branch = $this->hierarchy->expandWithDescendants($departmentId, [$membership->getGroupId()]);
            foreach ($branch as $id) {
                $visible[$id] = true;
            }
        }

        return array_keys($visible);
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
