<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserRecoveryCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserRecoveryCode>
 */
class UserRecoveryCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserRecoveryCode::class);
    }
}
