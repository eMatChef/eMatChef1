<?php

declare(strict_types=1);

namespace App\Service\Support;

use App\Entity\AdminJoinRequest;
use App\Entity\AdminJoinRequestEvent;
use App\Entity\Department;
use App\Entity\JoinRequest;
use App\Entity\Membership;
use App\Entity\SupplierMembership;
use App\Entity\User;
use App\Util\E2eSmokeUser;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Read-only queue of users without any department assignment ("Benutzer ohne Zuordnung").
 *
 * Users who asked for something themselves (JoinRequest, own AdminJoinRequest) are handled by those
 * requests. Requests created by the system (legacy auto-creation or an explicit queue action) are
 * recognised by their events, never by their text. Writing happens only in explicit admin actions.
 */
class UnassignedUserSupportQueue
{
    public const UNKNOWN_DEPARTMENT_NAME = 'Unbekannte Abteilung';
    public const EVENT_AUTO_CREATED = 'auto_created';
    public const EVENT_QUEUE_CREATED = 'queue_created';
    /** @var list<string> */
    public const SYSTEM_EVENT_ACTIONS = [self::EVENT_AUTO_CREATED, self::EVENT_QUEUE_CREATED];
    public const QUEUE_LIMIT = 200;

    /**
     * @param iterable<UnassignedUserQueueExclusion> $exclusions
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[AutowireIterator(UnassignedUserQueueExclusion::TAG)]
        private readonly iterable $exclusions = [],
    ) {}

    /**
     * @return list<User>
     */
    public function findUnassignedUsers(): array
    {
        return $this->filterCandidates($this->candidateQuery()->setMaxResults(self::QUEUE_LIMIT)->getQuery()->getResult());
    }

    public function isUnassigned(User $user): bool
    {
        $candidates = $this->candidateQuery()
            ->andWhere('u.id = :queueUserId')
            ->setParameter('queueUserId', $user->getId())
            ->getQuery()
            ->getResult();

        return $this->filterCandidates($candidates) !== [];
    }

    /**
     * Hides system-created requests in the support list; their users are represented by queue entries.
     */
    public function excludeSystemRequests(QueryBuilder $queryBuilder, string $alias): void
    {
        $queryBuilder
            ->andWhere(sprintf(
                'NOT EXISTS (SELECT queueSystemEvent.id FROM %s queueSystemEvent WHERE queueSystemEvent.adminJoinRequest = %s AND queueSystemEvent.action IN (:queueSystemActions))',
                AdminJoinRequestEvent::class,
                $alias,
            ))
            ->setParameter('queueSystemActions', self::SYSTEM_EVENT_ACTIONS);
    }

    /**
     * @param list<AdminJoinRequest> $requests
     *
     * @return list<string> IDs of the given requests that were created by the system
     */
    public function systemRequestIds(array $requests): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (AdminJoinRequest $request): ?string => $request->getId(),
            $requests,
        )));
        if ($ids === []) {
            return [];
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(event.adminJoinRequest) AS requestId')
            ->from(AdminJoinRequestEvent::class, 'event')
            ->where('event.adminJoinRequest IN (:requestIds)')
            ->andWhere('event.action IN (:systemActions)')
            ->setParameter('requestIds', $ids)
            ->setParameter('systemActions', self::SYSTEM_EVENT_ACTIONS)
            ->getQuery()
            ->getScalarResult();

        return array_values(array_unique(array_map(static fn (array $row): string => (string) $row['requestId'], $rows)));
    }

    /**
     * True when a verified alternative (e.g. an open MiData offer) supersedes the generic process.
     */
    public function hasVerifiedAlternative(User $user): bool
    {
        return $this->excludedByAlternatives([(string) $user->getId()]) !== [];
    }

    /**
     * Explicit admin action: reuse the user's open system request or create one for the queue entry. Not flushed.
     */
    public function claimSystemRequest(User $user, User $actor): AdminJoinRequest
    {
        $existing = $this->findOpenSystemRequests($user);
        if ($existing !== []) {
            return $existing[0];
        }

        $request = new AdminJoinRequest();
        $request->setId(IdGenerator::generateUnique($this->entityManager, AdminJoinRequest::class));
        $request->setUser($user);
        $request->setRequestedDepartmentName(self::UNKNOWN_DEPARTMENT_NAME);
        $request->setStatus('pending');
        $this->entityManager->persist($request);
        $this->logEvent($request, $actor, self::EVENT_QUEUE_CREATED, ['source' => 'unassigned_queue']);

        return $request;
    }

    /**
     * Explicit admin action: the user no longer appears in the queue.
     */
    public function dismiss(User $user, User $actor): AdminJoinRequest
    {
        $request = $this->claimSystemRequest($user, $actor);
        $request->setReviewedBy($actor);
        $request->setStatus('rejected');
        $this->logEvent($request, $actor, 'rejected', ['status' => 'rejected', 'source' => 'unassigned_queue']);
        $this->entityManager->flush();

        return $request;
    }

    /**
     * Closes open system requests after the user received a verified department, without inventing a reviewer.
     *
     * @return int number of closed requests
     */
    public function resolveForAssignedUser(User $user, Department $department, string $source): int
    {
        $requests = $this->findOpenSystemRequests($user);
        foreach ($requests as $request) {
            $request->setAssignedDepartment($department);
            $request->setReviewedBy(null);
            $request->setStatus('assigned');
            $this->logEvent($request, $user, 'assigned', [
                'source' => $source,
                'assigned_department_id' => $department->getId(),
            ]);
        }
        if ($requests !== []) {
            $this->entityManager->flush();
        }

        return count($requests);
    }

    /**
     * @return list<AdminJoinRequest>
     */
    private function findOpenSystemRequests(User $user): array
    {
        $requests = $this->entityManager->getRepository(AdminJoinRequest::class)->findBy(
            ['userId' => $user->getId(), 'status' => 'pending'],
            ['createdAt' => 'ASC'],
        );
        $systemIds = $this->systemRequestIds($requests);

        return array_values(array_filter(
            $requests,
            static fn (AdminJoinRequest $request): bool => in_array($request->getId(), $systemIds, true),
        ));
    }

    private function candidateQuery(): QueryBuilder
    {
        $explicitPendingRequest = sprintf(
            'SELECT explicitRequest.id FROM %s explicitRequest WHERE explicitRequest.userId = u.id AND explicitRequest.status = :pendingStatus'
            . ' AND NOT EXISTS (SELECT explicitEvent.id FROM %s explicitEvent WHERE explicitEvent.adminJoinRequest = explicitRequest AND explicitEvent.action IN (:systemActions))',
            AdminJoinRequest::class,
            AdminJoinRequestEvent::class,
        );
        $dismissedSystemRequest = sprintf(
            'SELECT dismissedRequest.id FROM %s dismissedRequest WHERE dismissedRequest.userId = u.id AND dismissedRequest.status = :rejectedStatus'
            . ' AND EXISTS (SELECT dismissedEvent.id FROM %s dismissedEvent WHERE dismissedEvent.adminJoinRequest = dismissedRequest AND dismissedEvent.action IN (:systemActions))',
            AdminJoinRequest::class,
            AdminJoinRequestEvent::class,
        );

        return $this->entityManager->createQueryBuilder()
            ->select('u', 'p')
            ->from(User::class, 'u')
            ->innerJoin('u.profile', 'p')
            ->where('u.state = :activeState')
            ->andWhere(sprintf('NOT EXISTS (SELECT m.userId FROM %s m WHERE m.userId = u.id)', Membership::class))
            ->andWhere(sprintf('NOT EXISTS (SELECT sm.userId FROM %s sm WHERE sm.userId = u.id)', SupplierMembership::class))
            ->andWhere(sprintf('NOT EXISTS (SELECT jr.id FROM %s jr WHERE jr.userId = u.id AND jr.status = :pendingStatus)', JoinRequest::class))
            ->andWhere('NOT EXISTS (' . $explicitPendingRequest . ')')
            ->andWhere('NOT EXISTS (' . $dismissedSystemRequest . ')')
            ->setParameter('activeState', 'active')
            ->setParameter('pendingStatus', 'pending')
            ->setParameter('rejectedStatus', 'rejected')
            ->setParameter('systemActions', self::SYSTEM_EVENT_ACTIONS)
            ->orderBy('u.createdAt', 'ASC');
    }

    /**
     * @param array<mixed> $candidates
     *
     * @return list<User>
     */
    private function filterCandidates(array $candidates): array
    {
        $users = array_values(array_filter(
            $candidates,
            static fn (mixed $user): bool => $user instanceof User
                && !$user->hasSuperAdminProfile()
                && !E2eSmokeUser::isExcluded($user->getProfile()?->getEmail()),
        ));
        $excluded = $this->excludedByAlternatives(array_map(static fn (User $user): string => (string) $user->getId(), $users));

        return array_values(array_filter(
            $users,
            static fn (User $user): bool => !in_array($user->getId(), $excluded, true),
        ));
    }

    /**
     * @param list<string> $userIds
     *
     * @return list<string>
     */
    private function excludedByAlternatives(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $excluded = [];
        foreach ($this->exclusions as $exclusion) {
            foreach ($exclusion->excludedUserIds($userIds) as $userId) {
                $excluded[$userId] = true;
            }
        }

        return array_map('strval', array_keys($excluded));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function logEvent(AdminJoinRequest $request, User $actor, string $action, array $payload): void
    {
        $event = new AdminJoinRequestEvent();
        $event->setId(IdGenerator::generateUnique($this->entityManager, AdminJoinRequestEvent::class));
        $event->setAdminJoinRequest($request);
        $event->setUser($actor);
        $event->setAction($action);
        $event->setPayload($payload);
        $this->entityManager->persist($event);
    }
}
