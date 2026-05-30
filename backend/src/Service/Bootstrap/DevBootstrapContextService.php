<?php

declare(strict_types=1);

namespace App\Service\Bootstrap;

use App\Entity\Department;
use App\Entity\Organisation;
use App\Service\Accounting\AccountingCostCenterBootstrapService;
use App\Service\SystemScopeVisibility;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Findet oder legt eine sichtbare Org/Department für Dev-Bootstrap an (Superadmin, Test-User).
 * Keine festen GLOBALORG001/GLOBAL000000-IDs mehr (Paket 15).
 */
final class DevBootstrapContextService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountingCostCenterBootstrapService $accountingCostCenterBootstrap,
    ) {
    }

    public function findOrCreateOrganisation(): Organisation
    {
        foreach ($this->entityManager->getRepository(Organisation::class)->findAll() as $organisation) {
            if ($organisation instanceof Organisation
                && SystemScopeVisibility::isOrganisationIdVisibleForAssignment((string) $organisation->getId())) {
                return $organisation;
            }
        }

        $organisation = new Organisation();
        $organisation->setId(IdGenerator::generateUnique($this->entityManager, Organisation::class));
        $organisation->setName('Bootstrap Organisation');
        $this->entityManager->persist($organisation);
        $this->entityManager->flush();

        return $organisation;
    }

    public function findOrCreateDepartment(Organisation $organisation): Department
    {
        $departments = $this->entityManager->getRepository(Department::class)
            ->findBy(['organisationId' => $organisation->getId()]);

        foreach ($departments as $department) {
            if ($department instanceof Department
                && SystemScopeVisibility::isDepartmentVisibleForAssignment($department)) {
                return $department;
            }
        }

        $department = new Department();
        $department->setId(IdGenerator::generateUnique($this->entityManager, Department::class));
        $department->setName('Bootstrap Department');
        $department->setOrganisation($organisation);
        $this->entityManager->persist($department);
        $this->entityManager->flush();

        $this->accountingCostCenterBootstrap->ensureDefaultCostCenters($this->entityManager, $department);

        return $department;
    }

    /** @return array{0: Organisation, 1: Department} */
    public function findOrCreateOrganisationAndDepartment(): array
    {
        $organisation = $this->findOrCreateOrganisation();
        $department = $this->findOrCreateDepartment($organisation);

        return [$organisation, $department];
    }
}
