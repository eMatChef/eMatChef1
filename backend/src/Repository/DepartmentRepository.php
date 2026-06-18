<?php

namespace App\Repository;

use App\Entity\Department;
use App\Util\DepartmentNameMatcher;
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

    public function findConflictingByOrganisationAndName(
        string $organisationId,
        string $name,
        ?string $excludeDepartmentId = null,
    ): ?Department {
        if (trim($name) === '') {
            return null;
        }

        $qb = $this->createQueryBuilder('d')
            ->where('d.organisationId = :orgId')
            ->setParameter('orgId', $organisationId);

        if ($excludeDepartmentId !== null && $excludeDepartmentId !== '') {
            $qb->andWhere('d.id != :excludeId')
                ->setParameter('excludeId', $excludeDepartmentId);
        }

        /** @var list<Department> $departments */
        $departments = $qb->getQuery()->getResult();
        foreach ($departments as $department) {
            if (DepartmentNameMatcher::conflict($name, $department->getName())) {
                return $department;
            }
        }

        return null;
    }
}
