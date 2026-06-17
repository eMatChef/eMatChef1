<?php

namespace App\Repository;

use App\Entity\Department;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Department>
 */
class DepartmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Department::class);
    }

    public function findOneByOrganisationAndName(
        string $organisationId,
        string $name,
        ?string $excludeDepartmentId = null,
    ): ?Department {
        $normalized = mb_strtolower(trim($name));
        if ($normalized === '') {
            return null;
        }

        $qb = $this->createQueryBuilder('d')
            ->where('d.organisationId = :orgId')
            ->andWhere('LOWER(TRIM(d.name)) = :name')
            ->setParameter('orgId', $organisationId)
            ->setParameter('name', $normalized)
            ->setMaxResults(1);

        if ($excludeDepartmentId !== null && $excludeDepartmentId !== '') {
            $qb->andWhere('d.id != :excludeId')
                ->setParameter('excludeId', $excludeDepartmentId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
