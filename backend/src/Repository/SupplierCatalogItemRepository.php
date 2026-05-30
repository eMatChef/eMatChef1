<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupplierCatalogItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SupplierCatalogItem>
 */
class SupplierCatalogItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupplierCatalogItem::class);
    }

    /**
     * @return list<SupplierCatalogItem>
     */
    public function findByCompanyId(string $companyId): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.supplierCompanyId = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('i.name', 'ASC')
            ->addOrderBy('i.sku', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
