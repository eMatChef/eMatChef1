<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Address;
use App\Entity\SupplierCompany;
use App\Repository\SupplierCompanyRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lieferanten für MW-Picker, Import und Wizard: aktive Firmen, Legacy-global, department-lokal.
 */
class MaterialWizardSupplierService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierCompanyRepository $supplierCompanyRepository,
    ) {
    }

    /**
     * @return Address[]
     */
    public function listForDepartment(string $departmentId): array
    {
        return $this->mergeUniqueById(
            $this->loadActiveSupplierCompanyAddresses(),
            $this->loadGlobalLegacySuppliers(),
            $this->loadDepartmentSuppliers($departmentId),
        );
    }

    /**
     * Katalog-Lieferanten für Import-Auflösung (ohne department-lokal).
     *
     * @return Address[]
     */
    public function listCatalogSuppliers(): array
    {
        return $this->mergeUniqueById(
            $this->loadActiveSupplierCompanyAddresses(),
            $this->loadGlobalLegacySuppliers(),
        );
    }

    /**
     * @return Address[]
     */
    private function loadActiveSupplierCompanyAddresses(): array
    {
        $companies = $this->supplierCompanyRepository->findByStatus(SupplierCompany::STATUS_ACTIVE);
        $addresses = [];

        foreach ($companies as $company) {
            $address = $company->getSupplierAddress();
            if ($address === null && $company->getSupplierAddressId()) {
                $address = $this->entityManager->find(Address::class, $company->getSupplierAddressId());
            }
            if ($address !== null && !$address->isDeleted()) {
                $addresses[] = $address;
            }
        }

        usort($addresses, fn (Address $a, Address $b) => strcasecmp(
            (string) ($a->getName() ?: $a->getCompany() ?: ''),
            (string) ($b->getName() ?: $b->getCompany() ?: ''),
        ));

        return $addresses;
    }

    /**
     * @return Address[]
     */
    private function loadGlobalLegacySuppliers(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Address::class, 'a')
            ->where('a.scope = :scope')
            ->andWhere('a.type = :type')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('scope', Address::SCOPE_GLOBAL)
            ->setParameter('type', 'supplier')
            ->orderBy('a.name', 'ASC')
            ->addOrderBy('a.company', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Address[]
     */
    private function loadDepartmentSuppliers(string $departmentId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Address::class, 'a')
            ->where('a.scope = :scope')
            ->andWhere('a.departmentId = :departmentId')
            ->andWhere('a.type = :type')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('scope', Address::SCOPE_DEPARTMENT)
            ->setParameter('departmentId', $departmentId)
            ->setParameter('type', 'supplier')
            ->orderBy('a.name', 'ASC')
            ->addOrderBy('a.company', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Address[] ...$groups
     *
     * @return Address[]
     */
    private function mergeUniqueById(array ...$groups): array
    {
        $seen = [];
        $merged = [];

        foreach ($groups as $group) {
            foreach ($group as $address) {
                $id = $address->getId();
                if ($id === null || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $merged[] = $address;
            }
        }

        return $merged;
    }
}
