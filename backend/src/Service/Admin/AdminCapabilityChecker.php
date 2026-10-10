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
     * Explizit zugewiesener Verwaltungs-Scope von Orgchef/Suborgchef (profile.admin_capabilities.scope).
     * Ohne Zuweisung ist er leer, und ein leerer Scope gibt keine hierarchischen Verwaltungsrechte.
     *
     * @return array{organisation_ids: list<string>, department_root_ids: list<string>}
     */
    public function getScope(User $user): array
    {
        if ($this->isSuperAdmin($user) || !$this->hasGlobalAdminRole($user)) {
            return ['organisation_ids' => [], 'department_root_ids' => []];
        }
        $caps = $this->getEffectiveCapabilities($user);

        return [
            'organisation_ids' => AdminCapabilityRegistry::scopedOrganisationIds($caps),
            'department_root_ids' => AdminCapabilityRegistry::scopedDepartmentRootIds($caps),
        ];
    }

    /** Superadmin oder Orgchef/Suborgchef mit mindestens einer expliziten Zuweisung. */
    public function hasAdministrativeScope(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }
        $scope = $this->getScope($user);

        return $scope['organisation_ids'] !== [] || $scope['department_root_ids'] !== [];
    }

    /**
     * Organisationen mit Verwaltungsrecht auf Organisationsebene (nur explizit zugewiesene Organisations-Scopes).
     * null = alle (Superadmin), [] = keine.
     *
     * @return list<string>|null
     */
    public function getAdministeredOrganisationIds(User $user): ?array
    {
        if ($this->isSuperAdmin($user)) {
            return null;
        }

        return $this->getScope($user)['organisation_ids'];
    }

    /**
     * Sichtbare Organisationen. null = alle (Superadmin), [] = keine. Orgchef/Suborgchef: explizit zugewiesene
     * Organisationen plus die Organisationen ihrer Department-Wurzeln (nur zur Anzeige; Verwaltung auf Organisations-
     * ebene braucht eine Organisations-Zuweisung). Ohne Zuweisung: keine. Übrige Benutzer: Organisationen ihrer Mitgliedschaften.
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

        $scope = $this->getScope($user);
        $ids = $scope['organisation_ids'];
        if ($scope['department_root_ids'] !== []) {
            $ids = array_merge($ids, $this->departmentScope->organisationIdsForDepartments($scope['department_root_ids']));
        }

        return array_values(array_unique($ids));
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

    /** Verwaltung auf Organisationsebene: Superadmin oder ausdrücklich zugewiesene Organisation. */
    public function canAdministerOrganisation(User $user, ?string $organisationId): bool
    {
        if ($organisationId === null || $organisationId === '') {
            return false;
        }
        $administered = $this->getAdministeredOrganisationIds($user);

        return $administered === null || \in_array($organisationId, $administered, true);
    }

    /**
     * null = alle Departments (Superadmin), [] = keine, list = Verwaltungsbereich. Orgchef/Suborgchef: Vereinigung aller
     * Departments der zugewiesenen Organisationen und der Unterbäume der zugewiesenen Department-Wurzeln. Parent- und
     * Geschwister-Departments einer Wurzel gehören nicht dazu. Ohne Zuweisung: keine.
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

        $scope = $this->getScope($user);
        $ids = [];
        if ($scope['department_root_ids'] !== []) {
            $ids = $this->departmentScope->expandSubtreeDepartmentIds($scope['department_root_ids']);
        }
        if ($scope['organisation_ids'] !== []) {
            $ids = array_merge($ids, $this->departmentScope->departmentIdsForOrganisations($scope['organisation_ids']));
        }

        return array_values(array_unique($ids));
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
     * Verwaltungszuständigkeit (nicht Mitgliedschaft): Superadmin überall, Orgchef/Suborgchef nur im Department-Baum
     * ihres Scopes. Eine blosse Mitgliedschaft begründet hier keinen Zugriff, und die Verwaltungsrolle ersetzt keine
     * operative Rolle: Aufrufer prüfen die Mitgliedschaftsrolle weiterhin selbst.
     */
    public function canAdministerDepartment(User $user, ?string $departmentId): bool
    {
        if ($departmentId === null || $departmentId === '') {
            return false;
        }
        if ($this->isSuperAdmin($user)) {
            return true;
        }
        if (!$this->hasGlobalAdminRole($user)) {
            return false;
        }

        return $this->canAccessDepartment($user, $departmentId);
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
