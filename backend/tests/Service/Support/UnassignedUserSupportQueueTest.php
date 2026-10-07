<?php

declare(strict_types=1);

namespace App\Tests\Service\Support;

use App\Command\EnsureE2eUserCommand;
use App\Entity\AdminJoinRequest;
use App\Entity\AdminJoinRequestEvent;
use App\Entity\Department;
use App\Entity\Organisation;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\Support\UnassignedUserQueueExclusion;
use App\Service\Support\UnassignedUserSupportQueue;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class UnassignedUserSupportQueueTest extends TestCase
{
    /** @var list<User> */
    private array $candidates = [];
    /** @var list<string> */
    private array $systemRequestIds = [];
    /** @var list<AdminJoinRequest> */
    private array $pendingRequests = [];
    /** @var list<string> */
    private array $dql = [];
    /** @var list<array<string, mixed>> */
    private array $parameters = [];
    /** @var list<object> */
    private array $persisted = [];
    private int $flushes = 0;

    public function testQueueRuleExcludesEveryAlternativeProcessInOneQuery(): void
    {
        $this->queue()->findUnassignedUsers();

        $dql = $this->dql[0];
        self::assertStringContainsString('u.state = :activeState', $dql);
        self::assertStringContainsString('NOT EXISTS (SELECT m.userId FROM App\Entity\Membership m WHERE m.userId = u.id)', $dql);
        self::assertStringContainsString('NOT EXISTS (SELECT sm.userId FROM App\Entity\SupplierMembership sm WHERE sm.userId = u.id)', $dql);
        self::assertStringContainsString('NOT EXISTS (SELECT jr.id FROM App\Entity\JoinRequest jr WHERE jr.userId = u.id AND jr.status = :pendingStatus)', $dql);
        // Own pending AdminJoinRequest: pending and without system event.
        self::assertStringContainsString('explicitRequest.status = :pendingStatus AND NOT EXISTS (SELECT explicitEvent.id', $dql);
        // Dismissed: rejected system request (legacy auto request or queue action).
        self::assertStringContainsString('dismissedRequest.status = :rejectedStatus AND EXISTS (SELECT dismissedEvent.id', $dql);
        self::assertSame('active', $this->parameters[0]['activeState']);
        self::assertSame(['auto_created', 'queue_created'], $this->parameters[0]['systemActions']);
    }

    public function testLegacyPendingAutoRequestDoesNotHideTheUser(): void
    {
        $this->queue()->findUnassignedUsers();

        // A pending request only hides the user when it has no system event, so legacy auto requests do not count.
        self::assertStringNotContainsString("explicitRequest.status = :pendingStatus AND EXISTS", $this->dql[0]);
    }

    public function testSuperAdminE2eAndVerifiedAlternativesAreFilteredOut(): void
    {
        $regular = $this->user('regular00001', 'regular@example.test');
        $superAdmin = $this->user('super0000001', 'admin@example.test', ['ROLE_USER', 'ROLE_SUPERADMIN']);
        $smoke = $this->user('smoke0000001', EnsureE2eUserCommand::DEFAULT_EMAIL);
        $midata = $this->user('midata000001', 'midata@example.test');
        $this->candidates = [$regular, $superAdmin, $smoke, $midata];

        $users = $this->queue([$this->exclusion(['midata000001'])])->findUnassignedUsers();

        self::assertSame([$regular], $users);
    }

    public function testExpiredOfferNoLongerExcludesTheUser(): void
    {
        $user = $this->user('midata000001', 'midata@example.test');
        $this->candidates = [$user];

        self::assertSame([$user], $this->queue([$this->exclusion([])])->findUnassignedUsers());
        self::assertFalse($this->queue([$this->exclusion([])])->hasVerifiedAlternative($user));
        self::assertTrue($this->queue([$this->exclusion(['midata000001'])])->hasVerifiedAlternative($user));
    }

    public function testSingleUserCheckUsesTheSameRule(): void
    {
        $user = $this->user('regular00001', 'regular@example.test');
        $this->candidates = [$user];

        self::assertTrue($this->queue()->isUnassigned($user));
        self::assertStringContainsString('u.id = :queueUserId', $this->dql[0]);
        self::assertStringContainsString('NOT EXISTS (SELECT m.userId', $this->dql[0]);

        $this->candidates = [];
        self::assertFalse($this->queue()->isUnassigned($user));
    }

    public function testSupportListExcludesSystemRequestsByEventNotByText(): void
    {
        $queryBuilder = (new QueryBuilder($this->createMock(EntityManagerInterface::class)))
            ->select('ajr')->from(AdminJoinRequest::class, 'ajr');

        $this->queue()->excludeSystemRequests($queryBuilder, 'ajr');

        self::assertStringContainsString(
            'NOT EXISTS (SELECT queueSystemEvent.id FROM App\Entity\AdminJoinRequestEvent queueSystemEvent WHERE queueSystemEvent.adminJoinRequest = ajr AND queueSystemEvent.action IN (:queueSystemActions))',
            $queryBuilder->getDQL(),
        );
        self::assertStringNotContainsString('Unbekannte Abteilung', $queryBuilder->getDQL());
    }

    public function testClaimReusesTheOpenLegacyRequestWithoutWriting(): void
    {
        $user = $this->user('regular00001', 'regular@example.test');
        $legacy = $this->request('ajrlegacy001', $user);
        $this->pendingRequests = [$legacy];
        $this->systemRequestIds = ['ajrlegacy001'];

        $claimed = $this->queue()->claimSystemRequest($user, $this->user('admin0000001', 'a@example.test'));

        self::assertSame($legacy, $claimed);
        self::assertSame([], $this->persisted);
        self::assertSame(0, $this->flushes);
    }

    public function testClaimCreatesAMarkedRequestForAPlainQueueEntryWithoutFlushing(): void
    {
        $user = $this->user('regular00001', 'regular@example.test');
        $own = $this->request('ajrown000001', $user);
        $this->pendingRequests = [$own];

        $claimed = $this->queue()->claimSystemRequest($user, $this->user('admin0000001', 'a@example.test'));

        self::assertNotSame($own, $claimed);
        self::assertSame('pending', $claimed->getStatus());
        self::assertSame('regular00001', $claimed->getUserId());
        self::assertSame(['queue_created'], $this->eventActions());
        self::assertSame(0, $this->flushes);
    }

    public function testDismissStoresRejectedRequestWithEvents(): void
    {
        $user = $this->user('regular00001', 'regular@example.test');
        $admin = $this->user('admin0000001', 'a@example.test');

        $request = $this->queue()->dismiss($user, $admin);

        self::assertSame('rejected', $request->getStatus());
        self::assertSame($admin, $request->getReviewedBy());
        self::assertSame(['queue_created', 'rejected'], $this->eventActions());
        self::assertSame(1, $this->flushes);
    }

    public function testResolveClosesOnlySystemRequestsAsAssignedWithoutReviewer(): void
    {
        $user = $this->user('regular00001', 'regular@example.test');
        $legacy = $this->request('ajrlegacy001', $user);
        $own = $this->request('ajrown000001', $user);
        $this->pendingRequests = [$legacy, $own];
        $this->systemRequestIds = ['ajrlegacy001'];
        $department = (new Department())->setId('dept00000051');
        $department->setOrganisation((new Organisation())->setId('orga00000001'));

        $closed = $this->queue()->resolveForAssignedUser($user, $department, 'midata_onboarding');

        self::assertSame(1, $closed);
        self::assertSame('assigned', $legacy->getStatus());
        self::assertSame('dept00000051', $legacy->getAssignedDepartmentId());
        self::assertNull($legacy->getReviewedBy());
        self::assertSame('pending', $own->getStatus());
        $event = $this->persisted[0];
        self::assertInstanceOf(AdminJoinRequestEvent::class, $event);
        self::assertSame('assigned', $event->getAction());
        self::assertSame('midata_onboarding', $event->getPayload()['source'] ?? null);
        self::assertSame(1, $this->flushes);
    }

    public function testResolveWithoutSystemRequestWritesNothing(): void
    {
        $user = $this->user('regular00001', 'regular@example.test');
        $department = (new Department())->setId('dept00000051');

        self::assertSame(0, $this->queue()->resolveForAssignedUser($user, $department, 'midata_onboarding'));
        self::assertSame([], $this->persisted);
        self::assertSame(0, $this->flushes);
    }

    /**
     * @return list<string>
     */
    private function eventActions(): array
    {
        return array_values(array_map(
            static fn (AdminJoinRequestEvent $event): string => $event->getAction(),
            array_filter($this->persisted, static fn (object $entity): bool => $entity instanceof AdminJoinRequestEvent),
        ));
    }

    /**
     * @param list<string> $excludedIds
     */
    private function exclusion(array $excludedIds): UnassignedUserQueueExclusion
    {
        return new class ($excludedIds) implements UnassignedUserQueueExclusion {
            /**
             * @param list<string> $excludedIds
             */
            public function __construct(private readonly array $excludedIds) {}

            public function excludedUserIds(array $userIds): array
            {
                return array_values(array_intersect($userIds, $this->excludedIds));
            }
        };
    }

    /**
     * @param list<string> $roles
     */
    private function user(string $id, string $email, array $roles = ['ROLE_USER']): User
    {
        $profile = (new Profile())->setId('prof' . substr($id, 4));
        $profile->setEmail($email);
        $profile->setRoles($roles);

        return (new User())->setId($id)->setProfile($profile);
    }

    private function request(string $id, User $user): AdminJoinRequest
    {
        return (new AdminJoinRequest())->setId($id)->setUser($user)
            ->setRequestedDepartmentName('Unbekannte Abteilung')->setStatus('pending');
    }

    /**
     * @param list<UnassignedUserQueueExclusion> $exclusions
     */
    private function queue(array $exclusions = []): UnassignedUserSupportQueue
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('createQueryBuilder')->willReturnCallback(
            static fn (): QueryBuilder => new QueryBuilder($entityManager),
        );
        $entityManager->method('createQuery')->willReturnCallback(function (string $dql): Query {
            $this->dql[] = $dql;
            $query = $this->createMock(Query::class);
            foreach (['setFirstResult', 'setMaxResults', 'setHint', 'setCacheable', 'setCacheMode', 'setCacheRegion', 'setLifetime'] as $method) {
                $query->method($method)->willReturnSelf();
            }
            $query->method('setParameters')->willReturnCallback(function ($parameters) use ($query): Query {
                $values = [];
                foreach ($parameters as $parameter) {
                    $values[$parameter->getName()] = $parameter->getValue();
                }
                $this->parameters[] = $values;

                return $query;
            });
            $query->method('getResult')->willReturnCallback(fn (): array => $this->candidates);
            $query->method('getScalarResult')->willReturnCallback(fn (): array => array_map(
                static fn (string $id): array => ['requestId' => $id],
                $this->systemRequestIds,
            ));

            return $query;
        });
        $requests = $this->createMock(EntityRepository::class);
        $requests->method('findBy')->willReturnCallback(fn (): array => $this->pendingRequests);
        $requests->method('findOneBy')->willReturn(null);
        $entityManager->method('getRepository')->willReturn($requests);
        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $entityManager->method('flush')->willReturnCallback(function (): void {
            $this->flushes++;
        });

        return new UnassignedUserSupportQueue($entityManager, $exclusions);
    }
}
