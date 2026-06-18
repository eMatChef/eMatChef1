<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RepairTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RepairTemplate>
 */
class RepairTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RepairTemplate::class);
    }

    /**
     * @return list<RepairTemplate>
     */
    public function findAllOrdered(bool $activeOnly = false): array
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.name', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('t.isActive = true');
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByTemplateKey(string $templateKey): ?RepairTemplate
    {
        return $this->findOneBy(['templateKey' => $templateKey]);
    }
}
