<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\Admin\AdminCapabilityRegistry;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Wählbare Verwaltungskontexte einer Person für Login-/Session-Antworten (UserNav).
 *
 * Verwaltungskontexte sind keine Department-Mitgliedschaften und vergeben keine operative Rolle:
 *  - Superadmin: ein globaler Systemkontext (kein Department in der Datenbank).
 *  - Orgchef/Suborgchef: ein Kontext je Wurzel-Department ihres Scopes (profile.admin_capabilities.scope);
 *    nur Organisations-Scope: die obersten Departments dieser Organisationen; ohne Scope: unbeschränkt (`unrestricted`).
 * Die Zugriffsprüfung bleibt im Backend (AdminCapabilityChecker); diese Liste steuert nur die Auswahl.
 */
final class AdminContextResolver
{
    public function __construct(
        private readonly AdminCapabilityChecker $adminCapabilities,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *     global: bool,
     *     role: string,
     *     unrestricted: bool,
     *     scopes: list<array{department_id: string, name: string, organisation_id: string, parent_id: string|null}>
     * }
     */
    public function resolve(User $user): array
    {
        $role = $this->adminCapabilities->getGlobalRole($user);

        if ($this->adminCapabilities->isSuperAdmin($user)) {
            return ['global' => true, 'role' => AdminCapabilityRegistry::GLOBAL_ROLE_SUPERADMIN, 'unrestricted' => true, 'scopes' => []];
        }
        if (!$this->adminCapabilities->hasGlobalAdminRole($user)) {
            return ['global' => false, 'role' => AdminCapabilityRegistry::GLOBAL_ROLE_NONE, 'unrestricted' => false, 'scopes' => []];
        }

        $caps = $this->adminCapabilities->getEffectiveCapabilities($user);
        $rootIds = AdminCapabilityRegistry::scopedDepartmentRootIds($caps);
        $organisationIds = AdminCapabilityRegistry::scopedOrganisationIds($caps);

        $repository = $this->entityManager->getRepository(Department::class);
        /** @var list<Department> $departments */
        $departments = [];
        if ($rootIds !== []) {
            $departments = $repository->findBy(['id' => $rootIds]);
        } elseif ($organisationIds !== []) {
            $departments = $repository->findBy(['organisationId' => $organisationIds, 'parentId' => null]);
        }
        usort($departments, static fn (Department $a, Department $b): int => strcasecmp($a->getName(), $b->getName()));

        return [
            'global' => false,
            'role' => $role,
            'unrestricted' => $rootIds === [] && $organisationIds === [],
            'scopes' => array_map(static fn (Department $d): array => [
                'department_id' => $d->getId(),
                'name' => $d->getName(),
                'organisation_id' => $d->getOrganisationId(),
                'parent_id' => $d->getParentId(),
            ], $departments),
        ];
    }
}
