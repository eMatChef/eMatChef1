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

    /** Basissicht wie «u» — L1–L3 vorerst gleich; Gruppenchef ohne Extra-Rechte. */
    private const BASIC_MEMBER_ROLES = ['u', 'user', 'l1', 'l2', 'l3'];

    /** Department-Rolle für Berechtigungsvergleich: l1–l3 wie «u». */
    public static function normalizeBasicMemberDepartmentRole(?string $role): ?string
    {
        if ($role === null || $role === '') {
            return null;
        }
        $r = strtolower(trim($role));
        if (\in_array($r, ['l1', 'l2', 'l3', 'user'], true)) {
            return 'u';
        }

        return $r;
    }

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
     * Basissicht (u, l1–l3): Gruppen-Hierarchie, Anlegen/Einreichen nur Typ «activity».
     * Gruppenchef zählt nicht extra — Rechte später gezielt aktivierbar.
     */
    public function isRestrictedGroupMember(User $user, string $departmentId): bool
    {
        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $departmentId]);
        if (!$membership) {
            return false;
        }

        $role = strtolower(trim((string) ($membership->getRole() ?? '')));

        return in_array($role, self::BASIC_MEMBER_ROLES, true);
    }

    /**
     * Darf group_id leer bleiben (= ganze Abteilung) oder jede Department-Gruppe wählen.
     */
    public function canSelectDepartmentGroupLevel(User $user, string $departmentId): bool
    {
        $role = $this->departmentRoleForUser($user, $departmentId);
        if ($role === null) {
            return false;
        }

        return \in_array($role, ['l1', 'l2', 'l3', 'mw', 'dc', 'matwart', 'depchef'], true);
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
     * Darf User einen Aktivitätstyp in diesem Department anlegen?
     */
    public function canUserCreateActivityType(User $user, string $departmentId, string $type): bool
    {
        $type = strtolower(trim($type));
        $role = $this->departmentRoleForUser($user, $departmentId);
        if ($role === null) {
            return false;
        }

        if ($type === 'external') {
            return \in_array($role, ['mw', 'dc', 'matwart', 'depchef'], true);
        }

        if ($type === 'activity') {
            return true;
        }

        if (\in_array($type, ['camp', 'event'], true)) {
            if (\in_array($role, ['mw', 'dc', 'matwart', 'depchef'], true)) {
                return true;
            }
            if (\in_array($role, ['sa', 'org', 'sub'], true)) {
                return true;
            }
            if (\in_array($role, ['l1', 'l2', 'l3'], true)) {
                return true;
            }
            // Department-Rolle «u»: nur mit Gruppenchef (★).
            if (\in_array($role, ['u', 'user'], true) && $this->isGroupLeaderInDepartment($user, $departmentId)) {
                return true;
            }

            return false;
        }

        return false;
    }

    /**
     * Lager/Event: Ersteller, Gruppenchef der Host-Gruppe oder Gruppenchef einer angenommenen Gast-Gruppe.
     */
    public function canUserSubmitCampOrEvent(User $user, Activity $activity): bool
    {
        if (!$activity->isDraft()) {
            return false;
        }
        if (!\in_array($activity->getType() ?? '', ['camp', 'event'], true)) {
            return false;
        }
        if ($activity->getCreatedByUserId() === $user->getId()) {
            return true;
        }

        $groupId = $activity->getGroupId();
        if ($groupId !== null && $groupId !== '') {
            $gMem = $this->entityManager->getRepository(GroupMembership::class)->findOneBy([
                'userId' => $user->getId(),
                'groupId' => $groupId,
            ]);
            if ($gMem !== null && $gMem->getRole() === 'leader') {
                return true;
            }
        }

        foreach ($this->getAcceptedInviteEntriesForUser($user, $activity) as $entry) {
            $inviteGroupId = trim((string) ($entry['invite']['group_id'] ?? ''));
            if ($inviteGroupId === '') {
                continue;
            }
            $gMem = $this->entityManager->getRepository(GroupMembership::class)->findOneBy([
                'userId' => $user->getId(),
                'groupId' => $inviteGroupId,
            ]);
            if ($gMem !== null && $gMem->getRole() === 'leader') {
                return true;
            }
        }

        return false;
    }

    private function departmentRoleForUser(User $user, string $departmentId): ?string
    {
        $mem = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if ($mem === null) {
            return null;
        }

        return strtolower(trim((string) ($mem->getRole() ?? '')));
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
     * Sichtbare Gruppen-IDs: Untergruppen (Parent → Child) und Parent-Gruppen (Child → Parent),
     * damit Lager/Event der eigenen Gruppe auch für Mitglieder in Untergruppen in Listen erscheinen.
     *
     * @return list<string>
     */
    public function getExpandedVisibleGroupIds(User $user, string $departmentId): array
    {
        $roots = $this->getUserRootGroupIdsInDepartment($user, $departmentId);
        $descendants = $this->groupHierarchy->expandWithDescendants($departmentId, $roots);
        $ancestors = $this->groupHierarchy->expandWithAncestors($departmentId, $roots);

        return array_values(array_unique(array_merge($descendants, $ancestors)));
    }

    /**
     * Lager/Event: Zugriff im gesamten Gruppenzweig (Parent- oder Child-Seite).
     * Andere Typen: nur Abwärts (User-Gruppe und Untergruppen).
     *
     * @param list<string> $userRootGroupIds
     */
    private function canAccessActivityViaGroupHierarchy(
        Activity $activity,
        string $departmentId,
        array $userRootGroupIds
    ): bool {
        $groupId = $activity->getGroupId();
        if ($groupId === null || $groupId === '') {
            return false;
        }

        if (\in_array($activity->getType() ?? '', ['camp', 'event'], true)) {
            return $this->groupHierarchy->isInSameGroupBranch($departmentId, $groupId, $userRootGroupIds);
        }

        return $this->groupHierarchy->isActivityGroupUnderUserGroups($departmentId, $groupId, $userRootGroupIds);
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
        foreach ($this->getAcceptedInviteEntriesForUser($user, $activity) as $entry) {
            $inviteGroupId = trim((string) ($entry['invite']['group_id'] ?? ''));
            if ($inviteGroupId === '') {
                return true;
            }
            if ($this->isUserMemberOfInviteGroup($user, $entry['department_id'], $inviteGroupId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{department_id: string, invite: array<string, mixed>}>
     */
    public function getAcceptedInviteEntriesForUser(User $user, Activity $activity): array
    {
        $entries = [];
        $memberships = $this->entityManager->getRepository(Membership::class)
            ->findBy(['userId' => $user->getId()]);
        foreach ($memberships as $membership) {
            $deptId = trim((string) $membership->getDepartmentId());
            if ($deptId === '' || !$this->isDepartmentInviteAccepted($activity, $deptId)) {
                continue;
            }
            $entries[] = [
                'department_id' => $deptId,
                'invite' => $this->findInviteEntryForDepartment($activity, $deptId),
            ];
        }

        return $entries;
    }

    /** Department-Kontext des Betrachters (Host oder angenommene Gast-Einladung). */
    public function resolveViewerDepartmentForActivity(
        User $user,
        Activity $activity,
        ?string $preferredDepartmentId = null,
    ): ?string {
        $preferredDepartmentId = trim((string) $preferredDepartmentId);
        $hostId = trim((string) $activity->getDepartmentId());

        if ($preferredDepartmentId !== '') {
            if ($preferredDepartmentId === $hostId && $this->userHasDepartmentMembership($user, $hostId)) {
                return $hostId;
            }
            if (
                $this->isDepartmentInviteAccepted($activity, $preferredDepartmentId)
                && $this->userHasDepartmentMembership($user, $preferredDepartmentId)
            ) {
                return $preferredDepartmentId;
            }
        }

        if ($hostId !== '' && $this->userHasDepartmentMembership($user, $hostId)) {
            return $hostId;
        }

        $guestEntries = $this->getAcceptedInviteEntriesForUser($user, $activity);

        return $guestEntries[0]['department_id'] ?? null;
    }

    private function userHasDepartmentMembership(User $user, string $departmentId): bool
    {
        $departmentId = trim($departmentId);
        if ($departmentId === '') {
            return false;
        }

        return $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]) !== null;
    }

    public function canInvitedDepartmentMwAssignGroup(User $user, Activity $activity, string $departmentId): bool
    {
        $departmentId = trim($departmentId);
        if ($departmentId === '' || !$this->isDepartmentInviteAccepted($activity, $departmentId)) {
            return false;
        }
        $mem = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$mem) {
            return false;
        }

        return \in_array((string) ($mem->getRole() ?? ''), ['mw', 'dc'], true);
    }

    /**
     * Gast-Abteilung: Kontext für Gruppenzuordnung in der Detailansicht.
     *
     * @return array<string, mixed>|null
     */
    public function getGuestInviteContextForViewer(User $user, Activity $activity): ?array
    {
        foreach ($this->getAcceptedInviteEntriesForUser($user, $activity) as $entry) {
            if (!$this->canInvitedDepartmentMwAssignGroup($user, $activity, $entry['department_id'])) {
                continue;
            }

            return [
                'department_id' => $entry['department_id'],
                'group_id' => $entry['invite']['group_id'] ?? null,
                'group_name' => $entry['invite']['group_name'] ?? null,
                'can_assign_group' => true,
            ];
        }

        return null;
    }

    public function canUserSeeInvitedActivityInList(
        User $user,
        Activity $activity,
        string $viewerDepartmentId,
        string $membershipRole,
        bool $isRestrictedMember,
    ): bool {
        if ($activity->isExternal() && !$this->isDepartmentWideManager($membershipRole)) {
            return false;
        }
        if (!$isRestrictedMember) {
            return true;
        }

        $invite = $this->findInviteEntryForDepartment($activity, $viewerDepartmentId);
        $inviteGroupId = trim((string) ($invite['group_id'] ?? ''));
        if ($inviteGroupId === '') {
            return false;
        }

        return $this->isUserMemberOfInviteGroup($user, $viewerDepartmentId, $inviteGroupId);
    }

    private function isUserMemberOfInviteGroup(User $user, string $departmentId, string $groupId): bool
    {
        $groupId = trim($groupId);
        if ($groupId === '') {
            return false;
        }

        $gMem = $this->entityManager->getRepository(GroupMembership::class)->findOneBy([
            'userId' => $user->getId(),
            'groupId' => $groupId,
        ]);
        if ($gMem === null) {
            return false;
        }

        $userRootGroupIds = $this->getUserRootGroupIdsInDepartment($user, $departmentId);

        return $this->groupHierarchy->isInSameGroupBranch($departmentId, $groupId, $userRootGroupIds);
    }

    /**
     * @return array<string, mixed>
     */
    public function findInviteEntryForDepartment(Activity $activity, string $departmentId): array
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
            $userRootGroupIds = $this->getUserRootGroupIdsInDepartment($user, $departmentId);

            return $this->canAccessActivityViaGroupHierarchy($activity, $departmentId, $userRootGroupIds);
        }

        if ($activity->getCreatedByUserId() === $user->getId() || $activity->getResponsibleUserId() === $user->getId()) {
            return true;
        }

        if (!$this->canUserSeeExternalActivities((string) $membership->getRole()) && $activity->isExternal()) {
            return false;
        }

        $userRootGroupIds = $this->getUserRootGroupIdsInDepartment($user, $departmentId);

        return $this->canAccessActivityViaGroupHierarchy($activity, $departmentId, $userRootGroupIds);
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

        return $this->canUserEditActivityMaterialLinesAsGroupMember($user, $activity, false);
    }

    /**
     * Nach Einreichung, vor «Annehmen & Packen»: u/l1/l2/l3 (und Gruppe) dürfen noch Material ergänzen.
     */
    public function canUserAddMaterialBeforePacking(User $user, Activity $activity): bool
    {
        if (!\in_array($activity->getStatus(), [Activity::STATUS_SUBMITTED, Activity::STATUS_APPROVED], true)) {
            return false;
        }

        return $this->canUserEditActivityMaterialLinesAsGroupMember($user, $activity, true);
    }

    /**
     * Gruppen-/Ersteller-Zugriff auf Materiallisten (Entwurf oder «vergessen»-Nachbuch vor packing).
     *
     * @param bool $basicMemberOnly true: nur u/l1/l2/l3 (Host + eingeladene Gruppen), keine MW/DC
     */
    private function canUserEditActivityMaterialLinesAsGroupMember(
        User $user,
        Activity $activity,
        bool $basicMemberOnly,
    ): bool {
        $uid = $user->getId();
        $hostDeptId = $activity->getDepartmentId();

        $hostMem = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $uid,
            'departmentId' => $hostDeptId,
        ]);
        if ($hostMem) {
            $role = (string) ($hostMem->getRole() ?? '');
            if ($basicMemberOnly) {
                if (!\in_array($role, ['u', 'user', 'l1', 'l2', 'l3'], true)) {
                    return false;
                }
            } elseif (\in_array($role, ['mw', 'dc'], true)) {
                return true;
            }
        } elseif ($basicMemberOnly) {
            return false;
        }

        $groupId = $activity->getGroupId();
        if ($groupId) {
            $userRootGroupIds = $this->getUserRootGroupIdsInDepartment($user, $hostDeptId);
            if ($this->canAccessActivityViaGroupHierarchy($activity, $hostDeptId, $userRootGroupIds)) {
                return true;
            }
        } elseif ($activity->isEvent() && $hostMem !== null && !$basicMemberOnly) {
            // Event ohne gewählte Gruppe: alle User des Host-Departments
            return true;
        } elseif ($activity->getCreatedByUserId() === $uid || $activity->getResponsibleUserId() === $uid) {
            // Keine Gruppe (nicht Event-Gesamtfall): Ersteller/Verantwortlich dürfen Material erfassen
            return true;
        }

        foreach ($activity->getInvitedDepartments() ?? [] as $inv) {
            if (!\is_array($inv) || ($inv['status'] ?? '') !== 'accepted') {
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
            if ($mem) {
                $invRole = (string) ($mem->getRole() ?? '');
                if ($basicMemberOnly) {
                    if (!\in_array($invRole, ['u', 'user', 'l1', 'l2', 'l3'], true)) {
                        continue;
                    }
                } elseif (\in_array($invRole, ['mw', 'dc'], true)) {
                    return true;
                }
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
     * Materialzeilen nach Entwurf: Host-MW/DC bis einschliesslich «Am Event» (danach nur noch Packliste/Retour).
     *
     * @return list<string>
     */
    private function statusesAllowingHostMaterialEditAfterDraft(): array
    {
        return [
            Activity::STATUS_SUBMITTED,
            Activity::STATUS_APPROVED,
            Activity::STATUS_PACKING,
            Activity::STATUS_PACKED,
            Activity::STATUS_AT_EVENT,
        ];
    }

    public function canHostMwOrDcEditActivityMaterialAfterDraft(User $user, Activity $activity): bool
    {
        if ($activity->isDraft()) {
            return false;
        }
        if (!\in_array($activity->getStatus(), $this->statusesAllowingHostMaterialEditAfterDraft(), true)) {
            return false;
        }
        if ($this->isHostDepartmentMw($user, $activity)) {
            return true;
        }

        return $this->isHostDepartmentMwOrDc($user, $activity);
    }

    /**
     * Verbrauchsmaterial-Nachlieferung (POST items mit replenishment): MW/DC oder Gruppe/Ersteller ab «Am Event».
     */
    public function canUserRequestConsumableReplenishment(User $user, Activity $activity): bool
    {
        if (!\in_array($activity->getStatus(), [Activity::STATUS_AT_EVENT, Activity::STATUS_RETURNED], true)) {
            return false;
        }
        if (!$activity->canReportIssues()) {
            return false;
        }
        if ($this->canHostMwOrDcEditActivityMaterialAfterDraft($user, $activity)) {
            return true;
        }

        return $this->canUserOperateActivityPackHandoff($user, $activity);
    }

    /**
     * Typ «activity», «camp», «event»: Ersteller oder Gruppenmitglied (bis Leader) darf
     * ab «gepackt» Material in der Pack-Pipeline bewegen (bis Retour gemeldet).
     */
    public function canUserOperateActivityPackHandoff(User $user, Activity $activity): bool
    {
        if (!\in_array($activity->getType() ?? '', ['activity', 'camp', 'event'], true)) {
            return false;
        }

        if ($activity->getCreatedByUserId() === $user->getId()) {
            return true;
        }

        $groupId = $activity->getGroupId();
        if ($groupId !== null && $groupId !== '') {
            $departmentId = $activity->getDepartmentId();
            $userRootGroupIds = $this->getUserRootGroupIdsInDepartment($user, $departmentId);
            if (\in_array($activity->getType() ?? '', ['camp', 'event'], true)) {
                if ($this->groupHierarchy->isInSameGroupBranch($departmentId, $groupId, $userRootGroupIds)) {
                    return true;
                }
            } else {
                $groupMembership = $this->entityManager->getRepository(GroupMembership::class)
                    ->findOneBy(['userId' => $user->getId(), 'groupId' => $groupId]);
                if ($groupMembership !== null) {
                    return true;
                }
            }
        }

        foreach ($this->getAcceptedInviteEntriesForUser($user, $activity) as $entry) {
            $inviteGroupId = trim((string) ($entry['invite']['group_id'] ?? ''));
            if ($inviteGroupId === '') {
                continue;
            }
            if ($this->isUserMemberOfInviteGroup($user, $entry['department_id'], $inviteGroupId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Packliste bearbeiten: MW/DC immer (packing…returned); bei activity/camp/event auch Ersteller/Gruppe ab «gepackt».
     */
    public function canUserEditPackList(User $user, Activity $activity): bool
    {
        $status = $activity->getStatus();
        $editableStatuses = [
            Activity::STATUS_PACKING,
            Activity::STATUS_PACKED,
            Activity::STATUS_AT_EVENT,
            Activity::STATUS_RETURNED,
        ];
        if (!\in_array($status, $editableStatuses, true)) {
            return false;
        }

        if ($this->isHostDepartmentMwOrDc($user, $activity) || $this->isInvitedDepartmentMwOrDc($user, $activity)) {
            return true;
        }

        if (!$this->canUserOperateActivityPackHandoff($user, $activity)) {
            return false;
        }

        // Ab «Retour gemeldet» nur MW/DC (Einlagern / Ausgepackt) — Gruppe hat Übergabe abgeschlossen.
        return \in_array($status, [
            Activity::STATUS_PACKED,
            Activity::STATUS_AT_EVENT,
        ], true);
    }

    /**
     * Erlaubte Pack-Pipeline-Stufen für Nicht-MW (Gruppe/Ersteller).
     *
     * @return list<string>|null null = keine Einschränkung (MW/DC)
     */
    public function allowedPackMoveStagesForUser(User $user, Activity $activity): ?array
    {
        if ($this->isHostDepartmentMwOrDc($user, $activity)) {
            return null;
        }
        if (!$this->canUserOperateActivityPackHandoff($user, $activity)) {
            return [];
        }

        $type = $activity->getType() ?? '';
        if (\in_array($type, ['camp', 'event'], true)) {
            return [
                \App\Service\PackPipelineService::STAGE_TRANSPORT_TO,
                \App\Service\PackPipelineService::STAGE_AT_EVENT,
                \App\Service\PackPipelineService::STAGE_TRANSPORT_BACK,
                \App\Service\PackPipelineService::STAGE_RETURNED,
            ];
        }

        return [
            \App\Service\PackPipelineService::STAGE_AT_EVENT,
            \App\Service\PackPipelineService::STAGE_RETURNED,
        ];
    }
}
