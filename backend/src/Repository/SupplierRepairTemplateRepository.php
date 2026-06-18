<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupplierRepairTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SupplierRepairTemplate>
 */
class SupplierRepairTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupplierRepairTemplate::class);
    }

    /**
     * @return list<SupplierRepairTemplate>
     */
    public function findByCompanyId(string $companyId, bool $activeOnly = false): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.supplierCompanyId = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('t.templateKey', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('t.isActive = true');
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByCompanyAndKey(string $companyId, string $templateKey): ?SupplierRepairTemplate
    {
        return $this->findOneBy([
            'supplierCompanyId' => $companyId,
            'templateKey' => $templateKey,
        ]);
    }
}
