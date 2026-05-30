<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupplierCompany;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SupplierCompany>
 */
class SupplierCompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupplierCompany::class);
    }

    /**
     * @return list<SupplierCompany>
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->setParameter('status', $status)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneActiveByJoinCode(string $joinCode): ?SupplierCompany
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', $joinCode) ?? '');
        if ($normalized === '') {
            return null;
        }

        return $this->createQueryBuilder('c')
            ->andWhere('c.joinCode = :code')
            ->andWhere('c.status = :status')
            ->setParameter('code', $normalized)
            ->setParameter('status', SupplierCompany::STATUS_ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
