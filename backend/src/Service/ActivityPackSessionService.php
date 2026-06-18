<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityPackSessionPresence;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

class ActivityPackSessionService
{
    private const TTL_SECONDS = 60;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param array{shelf?: string|null, container_id?: string|null, journey_step?: string|null} $payload
     * @return list<array<string, mixed>>
     */
    public function upsertPresence(Activity $activity, User $user, array $payload): array
    {
        $this->purgeStale($activity->getId());

        $profile = $user->getProfile();
        $displayName = $profile ? $profile->getDisplayName() : 'Unbekannt';

        $existing = $this->entityManager->getRepository(ActivityPackSessionPresence::class)->findOneBy([
            'activityId' => $activity->getId(),
            'userId' => $user->getId(),
        ]);

        if ($existing === null) {
            $existing = new ActivityPackSessionPresence();
            $existing->setId(IdGenerator::generate13('ps'));
            $existing->setActivity($activity);
            $existing->setUserId($user->getId());
            $existing->setDisplayName($displayName);
            $this->entityManager->persist($existing);
        } else {
            $existing->setDisplayName($displayName);
        }

        if (array_key_exists('shelf', $payload)) {
            $shelf = trim((string) ($payload['shelf'] ?? ''));
            $existing->setShelf($shelf !== '' ? $shelf : null);
        }
        if (array_key_exists('container_id', $payload)) {
            $cid = trim((string) ($payload['container_id'] ?? ''));
            $existing->setContainerId($cid !== '' ? $cid : null);
        }
        if (array_key_exists('journey_step', $payload)) {
            $step = trim((string) ($payload['journey_step'] ?? ''));
            $existing->setJourneyStep($step !== '' ? $step : null);
        }
        $existing->touch();

        $this->entityManager->flush();

        return $this->listActive($activity->getId(), $user->getId());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActive(string $activityId, ?string $excludeUserId = null): array
    {
        $this->purgeStale($activityId);

        $qb = $this->entityManager->getRepository(ActivityPackSessionPresence::class)->createQueryBuilder('p')
            ->where('p.activityId = :activityId')
            ->setParameter('activityId', $activityId)
            ->orderBy('p.lastSeenAt', 'DESC');

        if ($excludeUserId !== null) {
            $qb->andWhere('p.userId != :excludeUserId')
                ->setParameter('excludeUserId', $excludeUserId);
        }

        $rows = $qb->getQuery()->getResult();

        return array_map(fn (ActivityPackSessionPresence $p) => [
            'user_id' => $p->getUserId(),
            'display_name' => $p->getDisplayName(),
            'shelf' => $p->getShelf(),
            'container_id' => $p->getContainerId(),
            'journey_step' => $p->getJourneyStep(),
            'last_seen_at' => $p->getLastSeenAt()->format(\DateTimeInterface::ATOM),
        ], $rows);
    }

    private function purgeStale(string $activityId): void
    {
        $cutoff = (new \DateTime())->modify('-' . self::TTL_SECONDS . ' seconds');
        $this->entityManager->createQueryBuilder()
            ->delete(ActivityPackSessionPresence::class, 'p')
            ->where('p.activityId = :activityId')
            ->andWhere('p.lastSeenAt < :cutoff')
            ->setParameter('activityId', $activityId)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
