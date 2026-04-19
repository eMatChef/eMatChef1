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
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

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

        $managerRoles = ['sa', 'org', 'sub', 'mw', 'dc'];
        if (in_array($membership->getRole(), $managerRoles, true)) {
            return true;
        }

        if ($activity->getCreatedByUserId() === $user->getId() || $activity->getResponsibleUserId() === $user->getId()) {
            return true;
        }

        $groupId = $activity->getGroupId();
        if (!$groupId) {
            return false;
        }

        $groupMembership = $this->entityManager->getRepository(GroupMembership::class)
            ->findOneBy(['userId' => $user->getId(), 'groupId' => $groupId]);

        return $groupMembership !== null;
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
            $gm = $this->entityManager->getRepository(GroupMembership::class)->findOneBy([
                'userId' => $uid,
                'groupId' => $groupId,
            ]);
            if ($gm !== null) {
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
}
