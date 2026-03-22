<?php

namespace App\Service;

use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Bereinigt Accounts ohne Department-Mitgliedschaft nach einer Frist.
 * Globale Admin-Konten (ROLE_SUPERADMIN / ORGANISATIONSCHEF / SUBORGCHEF in profile.roles)
 * werden nie geloescht, auch ohne Membership.
 */
class UnassignedUserCleanupService
{
    /** @var list<string> */
    private const PROTECTED_PROFILE_ROLES = [
        'ROLE_SUPERADMIN',
        'ROLE_ORGANISATIONSCHEF',
        'ROLE_SUBORGCHEF',
    ];

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return User[]
     */
    public function findCandidates(int $days): array
    {
        if ($days < 1) {
            throw new \InvalidArgumentException('Days must be >= 1');
        }

        $cutoff = (new \DateTimeImmutable())->modify("-{$days} days");

        $users = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->leftJoin(Membership::class, 'm', 'WITH', 'm.userId = u.id')
            ->where('u.createdAt <= :cutoff')
            ->andWhere('m.userId IS NULL')
            ->setParameter('cutoff', $cutoff)
            ->orderBy('u.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter(
            $users,
            fn (User $u) => !$this->hasProtectedGlobalAdminRole($u)
        ));
    }

    private function hasProtectedGlobalAdminRole(User $user): bool
    {
        $profile = $user->getProfile();
        if (!$profile) {
            return false;
        }
        $roles = $profile->getRoles();
        foreach (self::PROTECTED_PROFILE_ROLES as $role) {
            if (in_array($role, $roles, true)) {
                return true;
            }
        }

        return false;
    }

    public function preview(int $days): array
    {
        $users = $this->findCandidates($days);
        $items = [];

        foreach ($users as $user) {
            $profile = $user->getProfile();
            $items[] = [
                'user_id' => $user->getId(),
                'email' => $profile ? $profile->getEmail() : null,
                'created_at' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        return [
            'days' => $days,
            'count' => count($items),
            'items' => $items,
        ];
    }

    public function cleanup(int $days, array $userIds = []): array
    {
        $users = $this->findCandidates($days);
        $selectedIds = array_values(array_unique(array_filter(array_map('strval', $userIds))));
        $selectedLookup = array_flip($selectedIds);

        $deletedUsers = 0;
        $deletedProfiles = 0;

        foreach ($users as $user) {
            if (!empty($selectedIds) && !isset($selectedLookup[$user->getId()])) {
                continue;
            }

            $profile = $user->getProfile();
            $this->entityManager->remove($user);
            $deletedUsers++;

            if ($profile) {
                $this->entityManager->remove($profile);
                $deletedProfiles++;
            }
        }

        $this->entityManager->flush();

        return [
            'days' => $days,
            'requested_users' => count($selectedIds) > 0 ? count($selectedIds) : null,
            'deleted_users' => $deletedUsers,
            'deleted_profiles' => $deletedProfiles,
        ];
    }
}
