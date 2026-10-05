<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MiDataMembershipCandidate;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MiDataMembershipCandidateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MiDataMembershipCandidate::class);
    }

    /**
     * @return list<MiDataMembershipCandidate> including expired ones, for refreshing after a login
     */
    public function findAllForUser(User $user): array
    {
        return $this->findBy(['userId' => $user->getId(), 'provider' => 'midata']);
    }

    /**
     * @return list<MiDataMembershipCandidate>
     */
    public function findOpenForUser(User $user, \DateTimeInterface $now): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.userId = :userId')
            ->andWhere('c.provider = :provider')
            ->andWhere('c.expiresAt > :now')
            ->setParameter('userId', $user->getId())
            ->setParameter('provider', 'midata')
            ->setParameter('now', $now)
            ->orderBy('c.displayName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
