<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MiDataDepartmentOnboarding;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MiDataDepartmentOnboardingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MiDataDepartmentOnboarding::class);
    }

    public function findOneForUserAndExternalGroup(User $user, string $externalDepartmentGroupId): ?MiDataDepartmentOnboarding
    {
        return $this->findOneBy([
            'userId' => $user->getId(),
            'provider' => 'midata',
            'externalDepartmentGroupId' => $externalDepartmentGroupId,
        ]);
    }

    /**
     * @param list<string> $userIds
     *
     * @return list<string> user IDs with at least one open, not expired offer
     */
    public function findUserIdsWithOpenOffer(array $userIds, \DateTimeInterface $now): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('o')
            ->select('DISTINCT o.userId AS userId')
            ->where('o.userId IN (:userIds)')
            ->andWhere('o.provider = :provider')
            ->andWhere('o.completedAt IS NULL')
            ->andWhere('o.expiresAt > :now')
            ->setParameter('userIds', $userIds)
            ->setParameter('provider', 'midata')
            ->setParameter('now', $now)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): string => (string) $row['userId'], $rows);
    }

    /**
     * @return list<MiDataDepartmentOnboarding>
     */
    public function findOpenForUser(User $user, \DateTimeInterface $now): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.userId = :userId')
            ->andWhere('o.provider = :provider')
            ->andWhere('o.completedAt IS NULL')
            ->andWhere('o.expiresAt > :now')
            ->setParameter('userId', $user->getId())
            ->setParameter('provider', 'midata')
            ->setParameter('now', $now)
            ->orderBy('o.departmentName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
