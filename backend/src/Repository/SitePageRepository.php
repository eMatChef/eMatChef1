<?php

namespace App\Repository;

use App\Entity\SitePage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SitePage>
 */
class SitePageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SitePage::class);
    }

    public function findOneBySlug(string $slug): ?SitePage
    {
        return $this->find($slug);
    }
}
