<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupplierMembership;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SupplierMembership>
 */
class SupplierMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupplierMembership::class);
    }

    /**
     * @return list<SupplierMembership>
     */
    public function findByCompanyId(string $companyId): array
    {
        return $this->createQueryBuilder('sm')
            ->innerJoin('sm.user', 'u')
            ->innerJoin('u.profile', 'p')
            ->addSelect('u', 'p')
            ->where('sm.supplierCompanyId = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('sm.role', 'ASC')
            ->addOrderBy('p.lastName', 'ASC')
            ->addOrderBy('p.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countAdminsForCompany(string $companyId): int
    {
        return (int) $this->createQueryBuilder('sm')
            ->select('COUNT(sm.userId)')
            ->where('sm.supplierCompanyId = :companyId')
            ->andWhere('sm.role = :role')
            ->setParameter('companyId', $companyId)
            ->setParameter('role', SupplierMembership::ROLE_ADMIN)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
