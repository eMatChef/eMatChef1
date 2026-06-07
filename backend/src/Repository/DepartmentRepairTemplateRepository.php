<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DepartmentRepairTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DepartmentRepairTemplate>
 */
class DepartmentRepairTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DepartmentRepairTemplate::class);
    }

    /**
     * @return list<DepartmentRepairTemplate>
     */
    public function findByDepartmentId(string $departmentId, bool $activeOnly = false): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('t.templateKey', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('t.isActive = true');
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByDepartmentAndKey(string $departmentId, string $templateKey): ?DepartmentRepairTemplate
    {
        return $this->findOneBy([
            'departmentId' => $departmentId,
            'templateKey' => $templateKey,
        ]);
    }
}
