<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupplierMaterialTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SupplierMaterialTemplate>
 */
class SupplierMaterialTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupplierMaterialTemplate::class);
    }

    /**
     * @return list<SupplierMaterialTemplate>
     */
    public function findByCompanyId(string $companyId): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.supplierCompanyId = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByCompanyAndId(string $companyId, string $templateId): ?SupplierMaterialTemplate
    {
        return $this->createQueryBuilder('t')
            ->where('t.supplierCompanyId = :companyId')
            ->andWhere('t.id = :templateId')
            ->setParameter('companyId', $companyId)
            ->setParameter('templateId', $templateId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
