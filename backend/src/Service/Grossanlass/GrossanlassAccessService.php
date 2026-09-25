<?php

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassGroupShare;
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

    /** Status des Bauvorhabens setzt nur der Materialwart. */
    public function canSetBuildStatus(User $user, Department $department): bool
    {
        if (!$department->isGrossanlass()) {
            return false;
        }

        return GrossanlassAccessRoles::normalize($this->gaRole($user, $department)) === 'mw';
    }

    /**
     * Planung anlassweit: MW/CMW (nicht OK-Leitung, nicht Bereichsleitung).
     */
    public function canManagePlanung(User $user, Department $department): bool
    {
        if (!$department->isGrossanlass()) {
            return false;
        }

        $role = GrossanlassAccessRoles::normalize($this->gaRole($user, $department));
        if (GrossanlassAccessRoles::isOneOf($role, ['mw', 'cmw'])) {
            return true;
        }
        if ($role === 'dc') {
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

    public function submitsEinsatzDirectlyFree(User $user, Department $department): bool
    {
        return GrossanlassAccessRoles::submitsEinsatzDirectlyFree($this->gaRole($user, $department));
    }

    /** Bereichsleitung: membership.role = bl. Stern (group leader) ist nur Chef-Flag. */
    public function isBereichsleitung(User $user, Department $department): bool
    {
        if (!$department->isGrossanlass()) {
            return false;
        }

        return GrossanlassAccessRoles::isBereichsleitung($this->gaRole($user, $department));
    }

    public function isInAssignedBranch(User $user, Department $department, Group $group): bool
    {
        if ($group->getDepartmentId() !== $department->getId()) {
            return false;
        }

        return in_array(
            $group->getId(),
            $this->resolveAssignedGroupBranchIds($user, $department->getId()),
            true,
        );
    }

    /** Materialübersicht: anlassweit (MW/CMW/OK) oder Bereichsleitung. */
    public function canSeeMaterialUebersicht(User $user, Department $department): bool
    {
        return $this->canSeeAnlassOverview($user, $department)
            || $this->isBereichsleitung($user, $department);
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

    public function canManageStruktur(User $user, Department $department): bool
    {
        return GrossanlassAccessRoles::canManageStruktur($this->gaRole($user, $department));
    }

    public function canSeeMailSettings(User $user, Department $department): bool
    {
        return GrossanlassAccessRoles::canSeeMailSettings($this->gaRole($user, $department));
    }

    public function isGrossanlassHelper(User $user, Department $department): bool
    {
        return ($this->membershipRole($user, $department) ?? '') === 'u';
    }

    public function canSeeOwnEinsaetze(User $user, Department $department): bool
    {
        return $this->isGrossanlassHelper($user, $department);
    }

    public function canOperateAssignedEinsatz(User $user, Department $department, \App\Entity\DepartmentGrossanlassEinsatz $row): bool
    {
        if ($this->canSeeAnlassOverview($user, $department)) {
            return true;
        }
        if (!$this->isGrossanlassHelper($user, $department)) {
            return false;
        }
        if ($row->getChauffeurUserId() === $user->getId()) {
            return true;
        }
        if ($row->getIssuedToUserId() === $user->getId()) {
            return true;
        }
        $groupId = $row->getGroupId();
        if ($groupId === null || $groupId === '') {
            return false;
        }
        $assignedBranchIds = array_fill_keys(
            $this->resolveAssignedGroupBranchIds($user, $department->getId()),
            true,
        );

        return isset($assignedBranchIds[$groupId]);
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
     * MW/CMW/OK sind direkt frei. Bereichsleitung reicht im eigenen Ast ein.
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
            return $this->isBereichsleitung($user, $department);
        }
        if ($this->isBereichsleitung($user, $department) && $this->isInAssignedBranch($user, $department, $group)) {
            return true;
        }

        return $this->isLeaderOfGroupOrAncestor($user, $group);
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
        return $this->canManageStruktur($user, $department);
    }

    public function canCreateChildGroup(User $user, Department $department, Group $parent, bool $leaderOnly = false): bool
    {
        if (!$department->isGrossanlass()) {
            return false;
        }
        if ($this->canManageStruktur($user, $department)) {
            return true;
        }
        if ($this->isBereichsleitung($user, $department) && $this->isInAssignedBranch($user, $department, $parent)) {
            return true;
        }
        if ($leaderOnly) {
            return $this->groupAccess->isGroupLeaderOfGroup($user, $parent->getId());
        }

        return $this->isLeaderOfGroupOrAncestor($user, $parent);
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

    public function canEditGroup(User $user, Department $department, ?Group $group = null): bool
    {
        if ($this->canManageStruktur($user, $department)) {
            return true;
        }
        if ($group instanceof Group && $group->getDepartmentId() === $department->getId()) {
            if ($this->isBereichsleitung($user, $department) && $this->isInAssignedBranch($user, $department, $group)) {
                return true;
            }

            return $this->isLeaderOfGroupOrAncestor($user, $group)
                || $this->canPlanSharedGroup($user, $department, $group);
        }

        return false;
    }

    /** Heimat-Leitung darf teilen; Empfänger dürfen die Teilung nicht weitergeben. */
    public function canShareGroup(User $user, Department $department, Group $group): bool
    {
        if ($this->canManageStruktur($user, $department)) {
            return true;
        }
        if ($group->getDepartmentId() !== $department->getId()) {
            return false;
        }
        if ($this->isBereichsleitung($user, $department) && $this->isInAssignedBranch($user, $department, $group)) {
            return true;
        }

        return $this->isLeaderOfGroupOrAncestor($user, $group);
    }

    public function canUnshareGroup(User $user, Department $department, Group $source, Group $target): bool
    {
        if ($this->canShareGroup($user, $department, $source)) {
            return true;
        }

        return $this->isLeaderOfGroupOrAncestor($user, $target);
    }

    /**
     * Geteiltes Projekt/Bereich: Leader am Ziel-Ressort (oder Vorfahr) darf mitplanen.
     */
    public function canPlanSharedGroup(User $user, Department $department, Group $group): bool
    {
        $assigned = $this->resolveAssignedGroupBranchIds($user, $department->getId());
        if ($assigned === []) {
            return false;
        }

        $shares = $this->entityManager->getRepository(DepartmentGrossanlassGroupShare::class)
            ->findBy(['departmentId' => $department->getId()]);
        foreach ($shares as $share) {
            if (!$share instanceof DepartmentGrossanlassGroupShare) {
                continue;
            }
            if (!in_array($share->getTargetGroupId(), $assigned, true)) {
                continue;
            }
            $sharedBranch = $this->hierarchy->expandWithDescendants($department->getId(), [$share->getGroupId()]);
            if (!in_array($group->getId(), $sharedBranch, true)) {
                continue;
            }
            $target = $this->entityManager->getRepository(Group::class)->find($share->getTargetGroupId());
            if ($target instanceof Group && $this->isLeaderOfGroupOrAncestor($user, $target)) {
                return true;
            }
        }

        return false;
    }

    public function canDeleteGroup(User $user, Department $department, ?Group $group = null): bool
    {
        if ($this->canManageStruktur($user, $department)) {
            return true;
        }
        if (!$group instanceof Group || $group->getDepartmentId() !== $department->getId()) {
            return false;
        }
        if ($group->getParentId() === null) {
            return false;
        }
        if ($this->isBereichsleitung($user, $department) && $this->isInAssignedBranch($user, $department, $group)) {
            return true;
        }

        return $this->isLeaderOfGroupOrAncestor($user, $group);
    }

    public function canManageGroupMembers(User $user, Department $department, Group $group): bool
    {
        if (!$department->isGrossanlass()) {
            return false;
        }
        if ($this->canManageStruktur($user, $department)) {
            return true;
        }
        if ($this->isBereichsleitung($user, $department) && $this->isInAssignedBranch($user, $department, $group)) {
            return true;
        }
        if ($this->groupAccess->isGroupLeaderOfGroup($user, $group->getId())) {
            return true;
        }

        return false;
    }

    /**
     * Gruppen-IDs, in denen der User Direkt-Beschaffung anlegen/die Offerten pflegen darf
     * (MW/DC: alle Gruppen des Depts; sonst: can_procure-Mitgliedschaft + Nachfahren).
     *
     * @return list<string>|null null = kein Limit (MW sieht alles)
     */
    public function resolveProcurementGroupScope(User $user, Department $department): ?array
    {
        $this->assertGrossanlassDepartment($department);
        if ($this->canManageProcurement($user, $department)) {
            return null;
        }

        /** @var list<GroupMembership> $memberships */
        $memberships = $this->entityManager->getRepository(GroupMembership::class)
            ->createQueryBuilder('gm')
            ->innerJoin('gm.group', 'g')
            ->where('gm.userId = :userId')
            ->andWhere('g.departmentId = :departmentId')
            ->andWhere('gm.canProcure = true')
            ->setParameter('userId', $user->getId())
            ->setParameter('departmentId', $department->getId())
            ->getQuery()
            ->getResult();

        $visible = [];
        foreach ($memberships as $membership) {
            if (!$membership instanceof GroupMembership) {
                continue;
            }
            $branch = $this->hierarchy->expandWithDescendants($department->getId(), [$membership->getGroupId()]);
            foreach ($branch as $id) {
                $visible[$id] = true;
            }
        }

        return array_keys($visible);
    }

    public function canProcureInGroup(User $user, Department $department, Group $group): bool
    {
        if ($this->canManageProcurement($user, $department)) {
            return true;
        }
        if ($this->isLeaderOfGroupOrAncestor($user, $group)) {
            return true;
        }

        $scope = $this->resolveProcurementGroupScope($user, $department);
        if ($scope === null) {
            return true;
        }

        return in_array($group->getId(), $scope, true);
    }

    public function canManageDirectProcurementLine(User $user, Department $department, ActivityGrossanlassProcurementLine $line): bool
    {
        if ($this->canManageProcurement($user, $department)) {
            return true;
        }
        if ($line->getSource() !== ActivityGrossanlassProcurementLine::SOURCE_DIRECT || !$line->isSelfOrganized()) {
            return false;
        }

        return $this->canProcureInGroup($user, $department, $line->getGroup());
    }

    public function canEditQuotesForLine(User $user, Department $department, ActivityGrossanlassProcurementLine $line): bool
    {
        return $this->canManageDirectProcurementLine($user, $department, $line);
    }

    public function userHasProcurementDelegateSomewhere(User $user, Department $department): bool
    {
        $this->assertGrossanlassDepartment($department);
        if ($this->canManageProcurement($user, $department)) {
            return true;
        }
        $scope = $this->resolveProcurementGroupScope($user, $department);

        return $scope !== null && $scope !== [];
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

    private function isLeaderOfGroupOrAncestor(User $user, Group $group): bool
    {
        $current = $group;
        $seen = [];
        while (true) {
            if (isset($seen[$current->getId()])) {
                break;
            }
            $seen[$current->getId()] = true;
            if ($this->groupAccess->isGroupLeaderOfGroup($user, $current->getId())) {
                return true;
            }
            $parentId = $current->getParentId();
            if ($parentId === null || $parentId === '') {
                break;
            }
            $parent = $this->entityManager->getRepository(Group::class)->find($parentId);
            if (!$parent instanceof Group) {
                break;
            }
            $current = $parent;
        }

        return false;
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
