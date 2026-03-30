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
}
