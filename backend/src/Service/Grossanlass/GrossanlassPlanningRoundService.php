<?php

namespace App\Service\Grossanlass;

use App\Entity\Activity;
use App\Entity\ActivityGrossanlassRound;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassConfig;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\User;
use App\Service\InboxMessageService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

class GrossanlassPlanningRoundService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private InboxMessageService $inboxMessages,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listRounds(Department $department): array
    {
        $activity = $this->resolveMainActivity($department);
        $this->applyAutoSchedule($department, $activity);

        $rounds = $this->entityManager->getRepository(ActivityGrossanlassRound::class)
            ->createQueryBuilder('r')
            ->where('r.activityId = :activityId')
            ->setParameter('activityId', $activity->getId())
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(fn (ActivityGrossanlassRound $r) => $this->toArray($r), $rounds);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function createRound(Department $department, User $user, array $data): array
    {
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Planungsrunden');
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Name ist erforderlich');
        }

        $activity = $this->resolveMainActivity($department);
        $roundType = (string) ($data['round_type'] ?? ActivityGrossanlassRound::TYPE_RESSORT_WUENSCHE);
        if ($roundType !== ActivityGrossanlassRound::TYPE_RESSORT_WUENSCHE) {
            throw new \InvalidArgumentException('Ungültiger Rundentyp');
        }

        $opensAt = $this->parseOptionalDateTime($data['opens_at'] ?? null);
        $closesAt = $this->parseOptionalDateTime($data['closes_at'] ?? null);
        $this->assertValidWindow($opensAt, $closesAt);

        $round = new ActivityGrossanlassRound();
        $round->setId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, ActivityGrossanlassRound::class, 'gr'));
        $round->setActivity($activity);
        $round->setName($name);
        $round->setRoundType($roundType);
        $round->setStatus(ActivityGrossanlassRound::STATUS_SCHEDULED);
        $round->setOpensAt($opensAt);
        $round->setClosesAt($closesAt);
        $round->setUseAutoSchedule((bool) ($data['use_auto_schedule'] ?? false));
        $round->setCreatedByUser($user);

        $this->entityManager->persist($round);
        $this->entityManager->flush();

        return $this->toArray($round);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateRound(Department $department, User $user, string $roundId, array $data): array
    {
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Planungsrunden');
        }

        $round = $this->findRoundForDepartment($department, $roundId);
        if ($round->getStatus() === ActivityGrossanlassRound::STATUS_CLOSED) {
            throw new \InvalidArgumentException('Geschlossene Runden können nicht bearbeitet werden');
        }

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new \InvalidArgumentException('Name ist erforderlich');
            }
            $round->setName($name);
        }

        if ($round->getStatus() === ActivityGrossanlassRound::STATUS_SCHEDULED) {
            if (array_key_exists('opens_at', $data)) {
                $round->setOpensAt($this->parseOptionalDateTime($data['opens_at']));
            }
            if (array_key_exists('use_auto_schedule', $data)) {
                $round->setUseAutoSchedule((bool) $data['use_auto_schedule']);
            }
        }

        if (array_key_exists('closes_at', $data)) {
            $round->setClosesAt($this->parseOptionalDateTime($data['closes_at']));
        }
        if ($round->getStatus() === ActivityGrossanlassRound::STATUS_OPEN && array_key_exists('use_auto_schedule', $data)) {
            $round->setUseAutoSchedule((bool) $data['use_auto_schedule']);
        }

        $this->assertValidWindow($round->getOpensAt(), $round->getClosesAt());
        $round->touchUpdatedAt();
        $this->entityManager->flush();

        return $this->toArray($round);
    }

    /**
     * @return array<string, mixed>
     */
    public function openRound(Department $department, User $user, string $roundId): array
    {
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Planungsrunden');
        }

        $round = $this->findRoundForDepartment($department, $roundId);
        if ($round->getStatus() === ActivityGrossanlassRound::STATUS_OPEN) {
            return $this->toArray($round);
        }
        if ($round->getStatus() === ActivityGrossanlassRound::STATUS_CLOSED) {
            throw new \InvalidArgumentException('Geschlossene Runden können nicht geöffnet werden');
        }

        $round->setStatus(ActivityGrossanlassRound::STATUS_OPEN);
        $round->setOpenedAt(new \DateTime());
        $round->touchUpdatedAt();
        $this->entityManager->flush();

        $this->notifyRoundOpened($department, $round, $user->getId());

        return $this->toArray($round);
    }

    /**
     * @return array<string, mixed>
     */
    public function closeRound(Department $department, User $user, string $roundId): array
    {
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Planungsrunden');
        }

        $round = $this->findRoundForDepartment($department, $roundId);
        if ($round->getStatus() === ActivityGrossanlassRound::STATUS_CLOSED) {
            return $this->toArray($round);
        }

        $round->setStatus(ActivityGrossanlassRound::STATUS_CLOSED);
        $round->setClosedAt(new \DateTime());
        $round->touchUpdatedAt();
        $this->entityManager->flush();

        return $this->toArray($round);
    }

    private function applyAutoSchedule(Department $department, Activity $activity): void
    {
        $now = new \DateTime();
        $rounds = $this->entityManager->getRepository(ActivityGrossanlassRound::class)
            ->createQueryBuilder('r')
            ->where('r.activityId = :activityId')
            ->andWhere('r.useAutoSchedule = true')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('activityId', $activity->getId())
            ->setParameter('statuses', [
                ActivityGrossanlassRound::STATUS_SCHEDULED,
                ActivityGrossanlassRound::STATUS_OPEN,
            ])
            ->getQuery()
            ->getResult();

        $changed = false;
        foreach ($rounds as $round) {
            if (!$round instanceof ActivityGrossanlassRound) {
                continue;
            }
            if (
                $round->getStatus() === ActivityGrossanlassRound::STATUS_SCHEDULED
                && $round->getOpensAt() !== null
                && $round->getOpensAt() <= $now
            ) {
                $round->setStatus(ActivityGrossanlassRound::STATUS_OPEN);
                $round->setOpenedAt(new \DateTime());
                $round->touchUpdatedAt();
                $changed = true;
                $this->notifyRoundOpened($department, $round, null);
            } elseif (
                $round->getStatus() === ActivityGrossanlassRound::STATUS_OPEN
                && $round->getClosesAt() !== null
                && $round->getClosesAt() <= $now
            ) {
                $round->setStatus(ActivityGrossanlassRound::STATUS_CLOSED);
                $round->setClosedAt(new \DateTime());
                $round->touchUpdatedAt();
                $changed = true;
            }
        }

        if ($changed) {
            $this->entityManager->flush();
        }
    }

    private function notifyRoundOpened(Department $department, ActivityGrossanlassRound $round, ?string $senderUserId): void
    {
        $userIds = $this->collectRessortMemberUserIds($department);
        if ($userIds === []) {
            return;
        }

        $this->inboxMessages->notifyGrossanlassRoundOpened($department, $round, $userIds, $senderUserId);
    }

    /**
     * @return list<string>
     */
    private function collectRessortMemberUserIds(Department $department): array
    {
        $groups = $this->entityManager->getRepository(Group::class)
            ->findBy(['departmentId' => $department->getId()]);

        if ($groups === []) {
            return [];
        }

        $groupIds = array_map(fn (Group $g) => $g->getId(), $groups);
        $memberships = $this->entityManager->getRepository(GroupMembership::class)
            ->createQueryBuilder('gm')
            ->where('gm.groupId IN (:groupIds)')
            ->setParameter('groupIds', $groupIds)
            ->getQuery()
            ->getResult();

        $userIds = [];
        foreach ($memberships as $membership) {
            if ($membership instanceof GroupMembership) {
                $userIds[$membership->getUserId()] = true;
            }
        }

        return array_keys($userIds);
    }

    private function resolveMainActivity(Department $department): Activity
    {
        $config = $department->getGrossanlassConfig();
        if (!$config instanceof DepartmentGrossanlassConfig) {
            throw new \RuntimeException('Grossanlass-Konfiguration fehlt');
        }

        $activity = $config->getMainActivity();
        if ($activity === null) {
            throw new \RuntimeException('Haupt-Aktivität fehlt');
        }

        return $activity;
    }

    public function findRoundForDepartment(Department $department, string $roundId): ActivityGrossanlassRound
    {
        $activity = $this->resolveMainActivity($department);
        $round = $this->entityManager->getRepository(ActivityGrossanlassRound::class)->find($roundId);
        if ($round === null || $round->getActivityId() !== $activity->getId()) {
            throw new \InvalidArgumentException('Planungsrunde nicht gefunden');
        }

        return $round;
    }

    private function parseOptionalDateTime(mixed $value): ?\DateTime
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTime((string) $value);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Ungültiges Datum');
        }
    }

    private function assertValidWindow(?\DateTime $opensAt, ?\DateTime $closesAt): void
    {
        if ($opensAt !== null && $closesAt !== null && $closesAt < $opensAt) {
            throw new \InvalidArgumentException('Ende muss nach Start liegen');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(ActivityGrossanlassRound $round): array
    {
        return [
            'id' => $round->getId(),
            'activity_id' => $round->getActivityId(),
            'name' => $round->getName(),
            'round_type' => $round->getRoundType(),
            'status' => $round->getStatus(),
            'opens_at' => $round->getOpensAt()?->format(\DateTimeInterface::ATOM),
            'closes_at' => $round->getClosesAt()?->format(\DateTimeInterface::ATOM),
            'use_auto_schedule' => $round->isUseAutoSchedule(),
            'opened_at' => $round->getOpenedAt()?->format(\DateTimeInterface::ATOM),
            'closed_at' => $round->getClosedAt()?->format(\DateTimeInterface::ATOM),
            'created_by_user_id' => $round->getCreatedByUserId(),
            'created_at' => $round->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $round->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
