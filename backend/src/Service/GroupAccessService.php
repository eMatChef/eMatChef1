<?php

namespace App\Service;

use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Berechtigungen für Gruppen-Verwaltung (MW/DC vs. Gruppenchef).
 */
class GroupAccessService
{
    private const DEPARTMENT_GROUP_MANAGER_ROLES = ['mw', 'dc', 'sa', 'org', 'sub'];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function canFullyManageDepartmentGroups(User $user, string $departmentId): bool
    {
        $role = $this->departmentRoleForUser($user, $departmentId);

        return $role !== null && \in_array($role, self::DEPARTMENT_GROUP_MANAGER_ROLES, true);
    }

    public function isGroupLeaderOfGroup(User $user, string $groupId): bool
    {
        $membership = $this->entityManager->getRepository(GroupMembership::class)->findOneBy([
            'userId' => $user->getId(),
            'groupId' => $groupId,
        ]);

        return $membership !== null && $membership->getRole() === 'leader';
    }

    /** MW/DC oder Gruppenchef dieser Gruppe. */
    public function canManageGroupMembers(User $user, Group $group): bool
    {
        if ($this->canFullyManageDepartmentGroups($user, $group->getDepartmentId())) {
            return true;
        }

        return $this->isGroupLeaderOfGroup($user, $group->getId());
    }

    /** Nur Gruppenchef (ohne MW/DC) — eingeschränkt auf Hinzufügen als Mitglied. */
    public function isGroupLeaderOnlyManager(User $user, Group $group): bool
    {
        return $this->canManageGroupMembers($user, $group)
            && !$this->canFullyManageDepartmentGroups($user, $group->getDepartmentId());
    }

    public function userHasDepartmentMembership(string $userId, string $departmentId): bool
    {
        return $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $userId,
            'departmentId' => $departmentId,
        ]) !== null;
    }

    private function departmentRoleForUser(User $user, string $departmentId): ?string
    {
        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if ($membership === null) {
            return null;
        }

        return strtolower(trim((string) ($membership->getRole() ?? '')));
    }
}
