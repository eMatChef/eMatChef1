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

    /**
     * @return list<SupplierCatalogItem>
     */
    public function findShopVisibleByCompanyId(string $companyId): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.supplierCompanyId = :companyId')
            ->andWhere('i.isActive = true')
            ->andWhere('i.status = :status')
            ->andWhere('i.visibility != :private')
            ->setParameter('companyId', $companyId)
            ->setParameter('status', SupplierCatalogItem::STATUS_PUBLISHED)
            ->setParameter('private', SupplierCatalogItem::VISIBILITY_PRIVATE)
            ->orderBy('i.name', 'ASC')
            ->addOrderBy('i.sku', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<SupplierCatalogItem>
     */
    public function findPendingGlobalReview(): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.visibility = :global')
            ->andWhere('i.status = :pending')
            ->andWhere('i.isActive = true')
            ->setParameter('global', SupplierCatalogItem::VISIBILITY_GLOBAL)
            ->setParameter('pending', SupplierCatalogItem::STATUS_PENDING_REVIEW)
            ->orderBy('i.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
