<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }
    
    /**
     * Find user by profile ID (used as user identifier)
     */
    public function findOneByProfileId(string $profileId): ?User
    {
        return $this->findOneBy(['profileId' => $profileId]);
    }
}
