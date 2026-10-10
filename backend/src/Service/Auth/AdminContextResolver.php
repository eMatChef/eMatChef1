<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Department;
use App\Entity\Organisation;
use App\Entity\User;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\Admin\AdminCapabilityRegistry;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Wählbare Verwaltungskontexte einer Person für Login-/Session-Antworten (UserNav).
 *
 * Verwaltungskontexte sind keine Department-Mitgliedschaften und vergeben keine operative Rolle:
 *  - Superadmin: ein globaler Systemkontext (kein Department in der Datenbank).
 *  - Orgchef/Suborgchef: je ausdrücklich zugewiesener Organisation ein Organisationskontext und je zugewiesener
 *    Department-Wurzel ein Department-Verwaltungskontext (profile.admin_capabilities.scope); beides kombinierbar.
 *    Ohne Zuweisung gibt es keinen Kontext (kein «alles»).
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
     *     scopes: list<array{kind: string, organisation_id: string, department_id: string|null, name: string, parent_id: string|null}>
     * }
     */
    public function resolve(User $user): array
    {
        $role = $this->adminCapabilities->getGlobalRole($user);

        if ($this->adminCapabilities->isSuperAdmin($user)) {
            return ['global' => true, 'role' => AdminCapabilityRegistry::GLOBAL_ROLE_SUPERADMIN, 'scopes' => []];
        }
        if (!$this->adminCapabilities->hasGlobalAdminRole($user)) {
            return ['global' => false, 'role' => AdminCapabilityRegistry::GLOBAL_ROLE_NONE, 'scopes' => []];
        }

        $scope = $this->adminCapabilities->getScope($user);
        $scopes = [];

        if ($scope['organisation_ids'] !== []) {
            /** @var list<Organisation> $organisations */
            $organisations = $this->entityManager->getRepository(Organisation::class)->findBy(['id' => $scope['organisation_ids']]);
            usort($organisations, static fn (Organisation $a, Organisation $b): int => strcasecmp($a->getName(), $b->getName()));
            foreach ($organisations as $organisation) {
                $scopes[] = [
                    'kind' => 'organisation',
                    'organisation_id' => $organisation->getId(),
                    'department_id' => null,
                    'name' => $organisation->getName(),
                    'parent_id' => null,
                ];
            }
        }
        if ($scope['department_root_ids'] !== []) {
            /** @var list<Department> $departments */
            $departments = $this->entityManager->getRepository(Department::class)->findBy(['id' => $scope['department_root_ids']]);
            usort($departments, static fn (Department $a, Department $b): int => strcasecmp($a->getName(), $b->getName()));
            foreach ($departments as $department) {
                $scopes[] = [
                    'kind' => 'department',
                    'organisation_id' => $department->getOrganisationId(),
                    'department_id' => $department->getId(),
                    'name' => $department->getName(),
                    'parent_id' => $department->getParentId(),
                ];
            }
        }

        return ['global' => false, 'role' => $role, 'scopes' => $scopes];
    }
}
