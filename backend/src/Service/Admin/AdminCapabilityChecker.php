<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\Membership;
use App\Entity\Profile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class AdminCapabilityChecker
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdminCapabilityDepartmentScope $departmentScope,
    ) {
    }

    public function isSuperAdmin(User $user): bool
    {
        return \in_array('ROLE_SUPERADMIN', $user->getRoles(), true);
    }

    public function getGlobalRole(User $user): string
    {
        $profile = $user->getProfile();
        if (!$profile) {
            return AdminCapabilityRegistry::GLOBAL_ROLE_NONE;
        }

        return AdminCapabilityRegistry::resolveGlobalRole($profile->getRoles());
    }

    public function hasGlobalAdminRole(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return \in_array($this->getGlobalRole($user), [
            AdminCapabilityRegistry::GLOBAL_ROLE_ORG,
            AdminCapabilityRegistry::GLOBAL_ROLE_SUB,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getEffectiveCapabilities(User $user): array
    {
        if ($this->isSuperAdmin($user)) {
            $all = AdminCapabilityRegistry::emptyCapabilities();
            foreach (AdminCapabilityRegistry::allDotKeys() as $dotKey) {
                self::setNestedBool($all, $dotKey, true);
            }
            $all['scope'] = ['organisation_ids' => [], 'department_root_ids' => []];

            return $all;
        }

        $profile = $user->getProfile();
        if (!$profile) {
            return AdminCapabilityRegistry::emptyCapabilities();
        }

        $globalRole = AdminCapabilityRegistry::resolveGlobalRole($profile->getRoles());

        return AdminCapabilityRegistry::normalizeStored($profile->getAdminCapabilities(), $globalRole);
    }

    public function can(User $user, string $dotKey): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return AdminCapabilityRegistry::can($this->getEffectiveCapabilities($user), $dotKey);
    }

    /**
     * null = alle Organisationen, [] = keiner, list = nur diese IDs.
     *
     * @return list<string>|null
     */
    public function getAccessibleOrganisationIds(User $user): ?array
    {
        if ($this->isSuperAdmin($user)) {
            return null;
        }

        if (!$this->hasGlobalAdminRole($user)) {
            return $this->getMembershipOrganisationIds($user);
        }

        $caps = $this->getEffectiveCapabilities($user);
        $scoped = AdminCapabilityRegistry::scopedOrganisationIds($caps);
        if ($scoped !== []) {
            return $scoped;
        }

        return null;
    }

    public function canAccessOrganisation(User $user, ?string $organisationId): bool
    {
        if ($organisationId === null || $organisationId === '') {
            return true;
        }
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $accessible = $this->getAccessibleOrganisationIds($user);
        if ($accessible === null) {
            return true;
        }

        return \in_array($organisationId, $accessible, true);
    }

    /**
     * null = alle Departments, list = nur diese IDs (inkl. Unterbäume der Wurzeln).
     *
     * @return list<string>|null
     */
    public function getAccessibleDepartmentIds(User $user): ?array
    {
        if ($this->isSuperAdmin($user)) {
            return null;
        }

        if (!$this->hasGlobalAdminRole($user)) {
            return $this->getMembershipDepartmentIds($user);
        }

        $caps = $this->getEffectiveCapabilities($user);
        $rootIds = AdminCapabilityRegistry::scopedDepartmentRootIds($caps);
        if ($rootIds !== []) {
            $expanded = $this->departmentScope->expandSubtreeDepartmentIds($rootIds);
            $orgIds = AdminCapabilityRegistry::scopedOrganisationIds($caps);
            if ($orgIds !== []) {
                return $this->departmentScope->filterDepartmentIdsWithinOrganisations($expanded, $orgIds);
            }

            return $expanded;
        }

        $orgIds = $this->getAccessibleOrganisationIds($user);
        if ($orgIds === null) {
            return null;
        }
        if ($orgIds === []) {
            return [];
        }

        return $this->departmentScope->departmentIdsForOrganisations($orgIds);
    }

    public function canAccessDepartment(User $user, ?string $departmentId): bool
    {
        if ($departmentId === null || $departmentId === '') {
            return true;
        }
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $accessible = $this->getAccessibleDepartmentIds($user);
        if ($accessible === null) {
            return true;
        }

        return \in_array($departmentId, $accessible, true);
    }

    /**
     * @return list<string>
     */
    private function getMembershipDepartmentIds(User $user): array
    {
        $rows = $this->entityManager->getRepository(Membership::class)->findBy(['userId' => $user->getId()]);

        return array_values(array_unique(array_map(static fn (Membership $m) => $m->getDepartmentId(), $rows)));
    }

    /**
     * @return list<string>
     */
    private function getMembershipOrganisationIds(User $user): array
    {
        $rows = $this->entityManager->createQuery(
            'SELECT DISTINCT d.organisationId FROM App\Entity\Membership m
             JOIN App\Entity\Department d WITH d.id = m.departmentId
             WHERE m.userId = :userId'
        )->setParameter('userId', $user->getId())->getResult();

        return array_values(array_unique(array_map(static fn (array $r): string => (string) $r['organisationId'], $rows)));
    }

    /**
     * @param array<string, mixed> $capabilities
     */
    private static function setNestedBool(array &$capabilities, string $dotKey, bool $value): void
    {
        $parts = explode('.', $dotKey);
        $node = &$capabilities;
        $last = array_pop($parts);
        foreach ($parts as $part) {
            if (!isset($node[$part]) || !\is_array($node[$part])) {
                $node[$part] = [];
            }
            $node = &$node[$part];
        }
        $node[$last] = $value;
    }

    public function profileRolesForGlobalRole(string $globalRole): array
    {
        return match ($globalRole) {
            AdminCapabilityRegistry::GLOBAL_ROLE_ORG => ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'],
            AdminCapabilityRegistry::GLOBAL_ROLE_SUB => ['ROLE_USER', 'ROLE_SUBORGCHEF'],
            default => ['ROLE_USER'],
        };
    }

    public function serializeForApi(User $user): array
    {
        $accessibleDeptIds = $this->getAccessibleDepartmentIds($user);

        return [
            'global_admin_role' => $this->getGlobalRole($user),
            'admin_capabilities' => $this->getEffectiveCapabilities($user),
            'accessible_department_ids' => $accessibleDeptIds,
        ];
    }

    public function serializeForProfile(Profile $profile): array
    {
        $globalRole = AdminCapabilityRegistry::resolveGlobalRole($profile->getRoles());

        return [
            'global_admin_role' => $globalRole,
            'admin_capabilities' => AdminCapabilityRegistry::normalizeStored($profile->getAdminCapabilities(), $globalRole),
        ];
    }
}
