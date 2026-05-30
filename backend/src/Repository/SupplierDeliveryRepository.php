<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupplierDelivery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SupplierDelivery>
 */
class SupplierDeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupplierDelivery::class);
    }

    /**
     * @return list<SupplierDelivery>
     */
    public function findByCompanyId(string $companyId): array
    {
        return $this->createQueryBuilder('d')
            ->innerJoin('d.department', 'dept')
            ->addSelect('dept')
            ->leftJoin('d.lines', 'lines')
            ->addSelect('lines')
            ->leftJoin('lines.catalogItem', 'item')
            ->addSelect('item')
            ->where('d.supplierCompanyId = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('d.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<string> $statuses
     *
     * @return list<SupplierDelivery>
     */
    public function findByDepartmentAndStatuses(string $departmentId, array $statuses): array
    {
        if ($statuses === []) {
            return [];
        }

        return $this->createQueryBuilder('d')
            ->innerJoin('d.supplierCompany', 'company')
            ->addSelect('company')
            ->innerJoin('d.lines', 'lines')
            ->addSelect('lines')
            ->innerJoin('lines.catalogItem', 'item')
            ->addSelect('item')
            ->where('d.departmentId = :departmentId')
            ->andWhere('d.status IN (:statuses)')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('statuses', $statuses)
            ->orderBy('d.deliveredAt', 'DESC')
            ->addOrderBy('d.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
