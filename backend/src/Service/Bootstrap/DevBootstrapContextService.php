<?php

declare(strict_types=1);

namespace App\Service\Bootstrap;

use App\Entity\Department;
use App\Entity\Organisation;
use App\Service\Accounting\AccountingCostCenterBootstrapService;
use App\Service\Workshop\WorkshopSparePartsCategoryBootstrapService;
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
        private WorkshopSparePartsCategoryBootstrapService $workshopSparePartsCategoryBootstrap,
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
        $this->workshopSparePartsCategoryBootstrap->ensure($department);

        return $department;
    }

    /** @return array{0: Organisation, 1: Department} */
    public function findOrCreateOrganisationAndDepartment(): array
    {
        $organisation = $this->findOrCreateOrganisation();
        $department = $this->findOrCreateDepartment($organisation);

        return [$organisation, $department];
    }

    /**
     * Department für Demo-Rollen-User mit eindeutiger Ownership (statt «erstes sichtbares Department»).
     *
     * - Mit `$departmentId`: muss `demo_mode` tragen, oder `$markDemo` markiert es ausdrücklich (Operator-Entscheid).
     * - Ohne ID: genau ein Department mit `demo_mode` und ohne Grossanlass; bei keinem nur, wenn die DB
     *   überhaupt kein sichtbares Department hat (dann wird ein neues Demo-Department angelegt).
     * - Sonst Abbruch: Rollen-User mit bekanntem Passwort werden nie einem fremden Department zugeordnet.
     *
     * @return array{0: Organisation, 1: Department}
     *
     * @throws \RuntimeException wenn die Ownership nicht eindeutig ist
     */
    public function findOwnedDemoOrganisationAndDepartment(?string $departmentId = null, bool $markDemo = false): array
    {
        $repo = $this->entityManager->getRepository(Department::class);

        if ($departmentId !== null && $departmentId !== '') {
            $department = $repo->find($departmentId);
            if (!$department instanceof Department) {
                throw new \RuntimeException(sprintf('Department «%s» nicht gefunden.', $departmentId));
            }
            if (!$department->isDemoMode()) {
                if (!$markDemo) {
                    throw new \RuntimeException(sprintf(
                        'Department «%s» ist kein Demo-Department (demo_mode). Mit --mark-department-demo ausdrücklich freigeben.',
                        $department->getName(),
                    ));
                }
                $department->setDemoMode(true);
                $this->entityManager->flush();
            }

            return [$department->getOrganisation(), $department];
        }

        $demo = array_values(array_filter(
            $repo->findBy(['demoMode' => true]),
            static fn (Department $d): bool => !$d->isGrossanlass(),
        ));
        if (\count($demo) === 1) {
            return [$demo[0]->getOrganisation(), $demo[0]];
        }
        if (\count($demo) > 1) {
            throw new \RuntimeException('Mehrere Demo-Departments gefunden. Mit --department=<id> eindeutig wählen.');
        }

        $visible = array_filter(
            $repo->findAll(),
            static fn (Department $d): bool => SystemScopeVisibility::isDepartmentVisibleForAssignment($d),
        );
        if ($visible !== []) {
            throw new \RuntimeException(
                'Kein eindeutiges Demo-Department. Die Datenbank enthält bestehende Departments; Rollen-User werden keinem '
                . 'davon automatisch zugeordnet. Mit --department=<id> [--mark-department-demo] ausdrücklich wählen.',
            );
        }

        $organisation = $this->findOrCreateOrganisation();
        $department = $this->findOrCreateDepartment($organisation);
        $department->setDemoMode(true);
        $this->entityManager->flush();

        return [$organisation, $department];
    }
}
