<?php

namespace App\Repository;

use App\Entity\ExternalIdentity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ExternalIdentityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExternalIdentity::class);
    }

    public function findOneByProviderAndExternalUserId(string $provider, string $externalUserId): ?ExternalIdentity
    {
        return $this->findOneBy([
            'provider' => $provider,
            'externalUserId' => $externalUserId,
        ]);
    }
}
