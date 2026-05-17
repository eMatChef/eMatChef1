<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\GroupMembership;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Zentrale Prüfung: Host-Department vs. angenommene Einladung für gemeinsame Aktivitäten.
 */
class ActivityAccessService
{
    private const DEPARTMENT_WIDE_MANAGER_ROLES = ['sa', 'org', 'sub', 'mw', 'dc'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GroupHierarchyService $groupHierarchy
    ) {}

    public function isDepartmentWideManager(string $role): bool
    {
        return in_array($role, self::DEPARTMENT_WIDE_MANAGER_ROLES, true);
    }

    public function canUserSeeExternalActivities(string $role): bool
    {
        return $this->isDepartmentWideManager($role);
    }

    /**
     * Department-Rolle «u»/«user» ohne Gruppenchef in diesem Department.
     * Nur Gruppen-Hierarchie + eigene Aktivitäten; Anlegen nur Typ «activity».
     */
    public function isRestrictedGroupMember(User $user, string $departmentId): bool
    {
        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $departmentId]);
        if (!$membership) {
            return false;
        }

        $role = strtolower(trim((string) ($membership->getRole() ?? '')));
        if (!in_array($role, ['u', 'user'], true)) {
            return false;
        }

        return !$this->isGroupLeaderInDepartment($user, $departmentId);
    }

    public function isGroupLeaderInDepartment(User $user, string $departmentId): bool
    {
        $groupMemberships = $this->entityManager->getRepository(GroupMembership::class)
            ->findBy(['userId' => $user->getId()]);
        foreach ($groupMemberships as $groupMembership) {
            $group = $groupMembership->getGroup();
            if ($group->getDepartmentId() !== $departmentId) {
                continue;
            }
            if ($groupMembership->getRole() === 'leader') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function getUserRootGroupIdsInDepartment(User $user, string $departmentId): array
    {
        $groupMemberships = $this->entityManager->getRepository(GroupMembership::class)
            ->findBy(['userId' => $user->getId()]);
        $ids = [];
        foreach ($groupMemberships as $groupMembership) {
            $group = $groupMembership->getGroup();
            if ($group->getDepartmentId() === $departmentId) {
                $ids[] = $groupMembership->getGroupId();
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Sichtbare Gruppen-IDs inkl. aller Untergruppen (User in Parent sieht Child-Aktivitäten).
     *
     * @return list<string>
     */
    public function getExpandedVisibleGroupIds(User $user, string $departmentId): array
    {
        return $this->groupHierarchy->expandWithDescendants(
            $departmentId,
            $this->getUserRootGroupIdsInDepartment($user, $departmentId)
        );
    }

    public function isDepartmentInviteAccepted(Activity $activity, string $departmentId): bool
    {
        $sid = trim((string) $departmentId);
        $invites = $activity->getInvitedDepartments() ?? [];
        foreach ($invites as $invite) {
            if (!is_array($invite)) {
                continue;
            }
            $iid = trim((string) ($invite['id'] ?? $invite['department_id'] ?? ''));
            if ($iid === '' || $iid !== $sid) {
                continue;
            }

            return ($invite['status'] ?? 'pending') === 'accepted';
        }

        return false;
    }

    public function isInvitedAcceptedMember(User $user, Activity $activity): bool
    {
        $memberships = $this->entityManager->getRepository(Membership::class)
            ->findBy(['userId' => $user->getId()]);
        foreach ($memberships as $membership) {
            $deptId = $membership->getDepartmentId();
            if (!$this->isDepartmentInviteAccepted($activity, $deptId)) {
                continue;
            }
            $invite = $this->findInviteEntryForDepartment($activity, $deptId);
            $inviteGroupId = trim((string) ($invite['group_id'] ?? ''));
            if ($inviteGroupId !== '') {
                $gMem = $this->entityManager->getRepository(GroupMembership::class)->findOneBy([
                    'userId' => $user->getId(),
                    'groupId' => $inviteGroupId,
                ]);
                if ($gMem && $gMem->getRole() === 'leader') {
                    return true;
                }
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function findInviteEntryForDepartment(Activity $activity, string $departmentId): array
    {
        $sid = trim($departmentId);
        foreach ($activity->getInvitedDepartments() ?? [] as $invite) {
            if (!is_array($invite)) {
                continue;
            }
            $iid = trim((string) ($invite['id'] ?? $invite['department_id'] ?? ''));
            if ($iid !== '' && $iid === $sid) {
                return $invite;
            }
        }

        return [];
    }

    public function canUserAccessActivity(User $user, Activity $activity): bool
    {
        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $activity->getDepartmentId()]);
        if (!$membership) {
            return false;
        }

        if ($this->isDepartmentWideManager((string) $membership->getRole())) {
            return true;
        }

        $departmentId = $activity->getDepartmentId();

        if ($this->isRestrictedGroupMember($user, $departmentId)) {
            if (!$this->canUserSeeExternalActivities((string) $membership->getRole()) && $activity->isExternal()) {
                return false;
            }
            if ($activity->getCreatedByUserId() === $user->getId()) {
                return true;
            }
            $groupId = $activity->getGroupId();
            if (!$groupId) {
                return false;
            }
            $userRootGroupIds = $this->getUserRootGroupIdsInDepartment($user, $departmentId);

            return $this->groupHierarchy->isActivityGroupUnderUserGroups(
                $departmentId,
                $groupId,
                $userRootGroupIds
            );
        }

        if ($activity->getCreatedByUserId() === $user->getId() || $activity->getResponsibleUserId() === $user->getId()) {
            return true;
        }

        if (!$this->canUserSeeExternalActivities((string) $membership->getRole()) && $activity->isExternal()) {
            return false;
        }

        $groupId = $activity->getGroupId();
        if (!$groupId) {
            return false;
        }

        $userRootGroupIds = $this->getUserRootGroupIdsInDepartment($user, $departmentId);

        return $this->groupHierarchy->isActivityGroupUnderUserGroups(
            $departmentId,
            $groupId,
            $userRootGroupIds
        );
    }

    /**
     * Materialwart / Abteilungsleitung im eingeladenen Department (Einladung angenommen): abstimmen, Gruppen zuordnen.
     */
    public function isInvitedDepartmentMwOrDc(User $user, Activity $activity): bool
    {
        $memberships = $this->entityManager->getRepository(Membership::class)
            ->findBy(['userId' => $user->getId()]);
        foreach ($memberships as $membership) {
            if (!$this->isDepartmentInviteAccepted($activity, $membership->getDepartmentId())) {
                continue;
            }
            if (in_array($membership->getRole(), ['mw', 'dc'], true)) {
                return true;
            }
        }

        return false;
    }

    public function canUserViewActivity(User $user, Activity $activity): bool
    {
        return $this->canUserAccessActivity($user, $activity)
            || $this->isInvitedAcceptedMember($user, $activity)
            || $this->isInvitedDepartmentMwOrDc($user, $activity);
    }

    public function canUserEditActivity(User $user, Activity $activity): bool
    {
        return $this->canUserViewActivity($user, $activity);
    }

    /**
     * Entwurf: Material hinzufügen/entfernen — Gruppenmitglieder (Host-Gruppe + eingeladene Gruppen),
     * DC/MW des Host- oder angenommenen Gast-Departments.
     * Ohne Gruppe: bei Typ «event» jede Host-Department-Mitgliedschaft; sonst Ersteller/Verantwortlich.
     */
    public function canUserEditDraftActivityMaterial(User $user, Activity $activity): bool
    {
        if (!$activity->isDraft()) {
            return false;
        }

        $uid = $user->getId();
        $hostDeptId = $activity->getDepartmentId();

        $hostMem = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $uid,
            'departmentId' => $hostDeptId,
        ]);
        if ($hostMem) {
            $role = (string) ($hostMem->getRole() ?? '');
            if (in_array($role, ['mw', 'dc'], true)) {
                return true;
            }
        }

        $groupId = $activity->getGroupId();
        if ($groupId) {
            $userRootGroupIds = $this->getUserRootGroupIdsInDepartment($user, $hostDeptId);
            if ($this->groupHierarchy->isActivityGroupUnderUserGroups($hostDeptId, $groupId, $userRootGroupIds)) {
                return true;
            }
        } elseif ($activity->isEvent() && $hostMem !== null) {
            // Event ohne gewählte Gruppe: alle User des Host-Departments
            return true;
        } elseif ($activity->getCreatedByUserId() === $uid || $activity->getResponsibleUserId() === $uid) {
            // Keine Gruppe (nicht Event-Gesamtfall): Ersteller/Verantwortlich dürfen Material erfassen
            return true;
        }

        foreach ($activity->getInvitedDepartments() ?? [] as $inv) {
            if (!is_array($inv) || ($inv['status'] ?? '') !== 'accepted') {
                continue;
            }
            $deptId = trim((string) ($inv['id'] ?? $inv['department_id'] ?? ''));
            if ($deptId === '') {
                continue;
            }
            $mem = $this->entityManager->getRepository(Membership::class)->findOneBy([
                'userId' => $uid,
                'departmentId' => $deptId,
            ]);
            if ($mem && in_array((string) ($mem->getRole() ?? ''), ['mw', 'dc'], true)) {
                return true;
            }
            $ig = trim((string) ($inv['group_id'] ?? ''));
            if ($ig !== '') {
                $gMem = $this->entityManager->getRepository(GroupMembership::class)->findOneBy([
                    'userId' => $uid,
                    'groupId' => $ig,
                ]);
                if ($gMem !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Host-Department: Mitgliedschaft mit Rolle MW oder DC.
     */
    public function isHostDepartmentMwOrDc(User $user, Activity $activity): bool
    {
        $mem = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $activity->getDepartmentId(),
        ]);
        if (!$mem) {
            return false;
        }

        return \in_array((string) ($mem->getRole() ?? ''), ['mw', 'dc'], true);
    }

    /**
     * Host-Department: Mitgliedschaft mit Rolle MW (Materialwart).
     */
    public function isHostDepartmentMw(User $user, Activity $activity): bool
    {
        $mem = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $activity->getDepartmentId(),
        ]);
        if (!$mem) {
            return false;
        }

        return (string) ($mem->getRole() ?? '') === 'mw';
    }

    /**
     * Nach Entwurf: Host-MW/DC dürfen Stammdaten/Texte (PATCH) ändern — nicht u/l1/l2/l3/…
     * (Kein Status-Filter: gilt für alle nicht-Entwurf-Status.)
     */
    public function canUserEditSubmittedActivityDetails(User $user, Activity $activity): bool
    {
        if ($activity->isDraft()) {
            return false;
        }

        return $this->isHostDepartmentMwOrDc($user, $activity);
    }

    /**
     * Materialzeilen nach Entwurf: Host-MW in jedem Status; Host-DC nur bis «gepackt» (Buchungs-/Pack-Workflow).
     */
    public function canHostMwOrDcEditActivityMaterialAfterDraft(User $user, Activity $activity): bool
    {
        if ($activity->isDraft()) {
            return false;
        }
        if ($this->isHostDepartmentMw($user, $activity)) {
            return true;
        }
        if (!$this->isHostDepartmentMwOrDc($user, $activity)) {
            return false;
        }

        return \in_array($activity->getStatus(), [
            Activity::STATUS_SUBMITTED,
            Activity::STATUS_APPROVED,
            Activity::STATUS_PACKING,
            Activity::STATUS_PACKED,
        ], true);
    }

    /**
     * Typ «activity»: Ersteller oder Gruppenmitglied darf Material am Event / Retour buchen.
     */
    public function canUserOperateActivityPackHandoff(User $user, Activity $activity): bool
    {
        if (($activity->getType() ?? '') !== 'activity') {
            return false;
        }

        if ($activity->getCreatedByUserId() === $user->getId()) {
            return true;
        }

        $groupId = $activity->getGroupId();
        if ($groupId === null || $groupId === '') {
            return false;
        }

        $groupMembership = $this->entityManager->getRepository(GroupMembership::class)
            ->findOneBy(['userId' => $user->getId(), 'groupId' => $groupId]);

        return $groupMembership !== null;
    }

    /**
     * Packliste bearbeiten: MW/DC immer (packing…returned); bei Typ «activity» auch Ersteller/Gruppenmitglied ab «gepackt».
     */
    public function canUserEditPackList(User $user, Activity $activity): bool
    {
        $status = $activity->getStatus();
        $editableStatuses = [
            Activity::STATUS_PACKING,
            Activity::STATUS_PACKED,
            Activity::STATUS_AT_EVENT,
            Activity::STATUS_ISSUED,
            Activity::STATUS_RETURNED,
        ];
        if (!\in_array($status, $editableStatuses, true)) {
            return false;
        }

        if ($this->isHostDepartmentMwOrDc($user, $activity)) {
            return true;
        }

        if (!$this->canUserOperateActivityPackHandoff($user, $activity)) {
            return false;
        }

        return \in_array($status, [
            Activity::STATUS_PACKED,
            Activity::STATUS_AT_EVENT,
            Activity::STATUS_ISSUED,
            Activity::STATUS_RETURNED,
        ], true);
    }

    /**
     * Erlaubte Pack-Pipeline-Stufen für Nicht-MW bei Typ «activity» (issued / returned).
     *
     * @return list<string>|null null = keine Einschränkung
     */
    public function allowedPackMoveStagesForUser(User $user, Activity $activity): ?array
    {
        if ($this->isHostDepartmentMwOrDc($user, $activity)) {
            return null;
        }
        if (!$this->canUserOperateActivityPackHandoff($user, $activity)) {
            return [];
        }

        return [
            \App\Service\PackPipelineService::STAGE_AT_EVENT,
            \App\Service\PackPipelineService::STAGE_RETURNED,
        ];
    }
}
