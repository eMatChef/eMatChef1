<?php

namespace App\Service;

use App\Enum\DepartmentRole;

/**
 * Service für Rollen-Hierarchie und Permission-Checks
 */
class RoleHierarchyService
{
    /**
     * Prüft ob eine Rolle eine andere Rolle verwalten kann
     * 
     * @param string $managerRole Die Rolle, die verwalten möchte
     * @param string $targetRole Die Rolle, die verwaltet werden soll
     * @return bool
     */
    public function canManageRole(string $managerRole, string $targetRole): bool
    {
        if (!DepartmentRole::isValid($managerRole) || !DepartmentRole::isValid($targetRole)) {
            return false;
        }

        $manager = DepartmentRole::from($managerRole);
        $target = DepartmentRole::from($targetRole);

        return $manager->canManageRole($target);
    }

    /**
     * Gibt alle Rollen zurück, die eine Rolle verwalten kann
     * 
     * @param string $role
     * @return array Array von Rollen-Strings
     */
    public function getManageableRoles(string $role): array
    {
        if (!DepartmentRole::isValid($role)) {
            return [];
        }

        $roleEnum = DepartmentRole::from($role);
        $manageable = $roleEnum->getManageableRoles();

        return array_map(fn($r) => $r->value, $manageable);
    }

    /**
     * Prüft ob ein User eine bestimmte Rolle verwalten kann
     * 
     * @param string $userRole Die Rolle des Users
     * @param string $targetRole Die zu verwaltende Rolle
     * @return bool
     */
    public function userCanManageRole(string $userRole, string $targetRole): bool
    {
        return $this->canManageRole($userRole, $targetRole);
    }
}
