<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Entity\JoinRequest;
use App\Entity\Membership;
use App\Entity\User;
use App\Service\AuditLogger;
use App\Service\Auth\DepartmentJoinFlowService;
use App\Service\Auth\DepartmentJoinOutcomeStatus;
use App\Service\Auth\HitobitoOAuthSession;
use App\Service\Auth\MiDataDepartmentMembershipVerifier;
use App\Service\Auth\MiDataDepartmentVerificationResult;
use App\Service\Auth\MiDataDepartmentVerificationStatus;
use App\Service\Auth\MiDataGroupMembershipSynchronizer;
use App\Service\Auth\MiDataGroupMembershipSyncResult;
use App\Service\JoinRequestNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DepartmentJoinFlowServiceTest extends TestCase
{
    public function testConfirmedJoinCreatesUserMembershipAndReviewerlessApprovedRequest(): void
    {
        $fixture = $this->flowFixture(MiDataDepartmentVerificationStatus::CONFIRMED);
        $fixture['groupMembershipSynchronizer']->expects(self::once())
            ->method('sync')
            ->with($fixture['user'], $fixture['department'], $fixture['verification'])
            ->willReturn(new MiDataGroupMembershipSyncResult(created: ['grp-00000001']));
        $fixture['entityManager']->expects(self::exactly(2))->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$fixture): void {
                $fixture['persisted'][] = $entity;
            });
        $fixture['entityManager']->expects(self::once())->method('flush');
        $fixture['auditLogger']->expects(self::once())->method('log');
        $fixture['notifier']->expects(self::never())->method('notifyJoinRequestCreated');

        $result = $fixture['service']->submitJoinCode(
            $fixture['user'],
            'department-1',
            $this->oauthSession(),
        );

        self::assertSame(DepartmentJoinOutcomeStatus::MEMBERSHIP_CONFIRMED, $result->status);
        $membership = $fixture['persisted'][0];
        $request = $fixture['persisted'][1];
        self::assertInstanceOf(Membership::class, $membership);
        self::assertSame('u', $membership->getRole());
        self::assertTrue($membership->getIsPrimary());
        self::assertInstanceOf(JoinRequest::class, $request);
        self::assertSame('approved', $request->getStatus());
        self::assertNull($request->getReviewedBy());
    }

    #[DataProvider('existingRoles')]
    public function testConfirmedJoinNeverOverwritesAnExistingMembershipRole(string $role): void
    {
        $membership = (new Membership())
            ->setUser($this->user())
            ->setDepartment($this->department())
            ->setRole($role);
        $fixture = $this->flowFixture(MiDataDepartmentVerificationStatus::CONFIRMED, $membership);
        $fixture['entityManager']->expects(self::once())->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$fixture): void {
                $fixture['persisted'][] = $entity;
            });
        $fixture['entityManager']->expects(self::once())->method('flush');

        $result = $fixture['service']->submitJoinCode(
            $fixture['user'],
            'department-1',
            $this->oauthSession(),
        );

        self::assertSame(DepartmentJoinOutcomeStatus::ALREADY_MEMBER, $result->status);
        self::assertSame($role, $membership->getRole());
        self::assertCount(1, $fixture['persisted']);
        self::assertInstanceOf(JoinRequest::class, $fixture['persisted'][0]);
    }

    public static function existingRoles(): iterable
    {
        yield 'ordinary user' => ['u'];
        yield 'manager' => ['mw'];
        yield 'camp manager' => ['cmw'];
        yield 'department chief' => ['dc'];
        yield 'group leader' => ['bl'];
        yield 'communication' => ['komm'];
        yield 'sponsor' => ['spon'];
        yield 'level 1' => ['l1'];
        yield 'level 2' => ['l2'];
        yield 'level 3' => ['l3'];
    }

    public function testNotConfirmedCreatesOnlyOnePendingRequestAndNotifiesManagers(): void
    {
        $fixture = $this->flowFixture(MiDataDepartmentVerificationStatus::NOT_CONFIRMED);
        $fixture['entityManager']->expects(self::once())->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$fixture): void {
                $fixture['persisted'][] = $entity;
            });
        $fixture['entityManager']->expects(self::once())->method('flush');
        $fixture['notifier']->expects(self::once())->method('notifyJoinRequestCreated');

        $result = $fixture['service']->submitJoinCode($fixture['user'], 'department-1', $this->oauthSession());

        self::assertSame(DepartmentJoinOutcomeStatus::REQUEST_CREATED, $result->status);
        self::assertCount(1, $fixture['persisted']);
        self::assertInstanceOf(JoinRequest::class, $fixture['persisted'][0]);
        self::assertSame('pending', $fixture['persisted'][0]->getStatus());
    }

    public function testOAuthCallbackRequiresManualRequestSubmissionAfterUnconfirmedVerification(): void
    {
        $fixture = $this->flowFixture(MiDataDepartmentVerificationStatus::NOT_CONFIRMED);
        $fixture['entityManager']->expects(self::never())->method('persist');
        $fixture['entityManager']->expects(self::never())->method('flush');
        $fixture['notifier']->expects(self::never())->method('notifyJoinRequestCreated');

        $result = $fixture['service']->verifyJoinCodeForOAuthCallback(
            $fixture['user'],
            'department-1',
            $this->oauthSession(),
        );

        self::assertSame(DepartmentJoinOutcomeStatus::MANUAL_REQUEST_REQUIRED, $result->status);
        self::assertSame($fixture['department'], $result->department);
    }

    public function testOAuthCallbackReportsAnExistingPendingRequestWithoutCreatingAnother(): void
    {
        $pending = (new JoinRequest())
            ->setId('request-123456')
            ->setUser($this->user())
            ->setDepartment($this->department());
        $fixture = $this->flowFixture(MiDataDepartmentVerificationStatus::NOT_CONFIRMED, null, $pending);
        $fixture['entityManager']->expects(self::never())->method('persist');
        $fixture['entityManager']->expects(self::never())->method('flush');

        $result = $fixture['service']->verifyJoinCodeForOAuthCallback(
            $fixture['user'],
            'department-1',
            $this->oauthSession(),
        );

        self::assertSame(DepartmentJoinOutcomeStatus::REQUEST_ALREADY_PENDING, $result->status);
        self::assertSame($pending, $result->joinRequest);
    }

    public function testNotApplicableIdentityOrDepartmentMappingFallsBackToManualRequest(): void
    {
        $fixture = $this->flowFixture(MiDataDepartmentVerificationStatus::NOT_APPLICABLE);
        $fixture['entityManager']->expects(self::once())->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$fixture): void {
                $fixture['persisted'][] = $entity;
            });
        $fixture['entityManager']->expects(self::once())->method('flush');
        $fixture['notifier']->expects(self::once())->method('notifyJoinRequestCreated');

        $result = $fixture['service']->submitJoinCode($fixture['user'], 'department-1');

        self::assertSame(DepartmentJoinOutcomeStatus::REQUEST_CREATED, $result->status);
        self::assertCount(1, $fixture['persisted']);
        self::assertInstanceOf(JoinRequest::class, $fixture['persisted'][0]);
        self::assertSame('pending', $fixture['persisted'][0]->getStatus());
    }

    public function testExistingPendingRequestIsNotDuplicated(): void
    {
        $pending = (new JoinRequest())
            ->setId('request-123456')
            ->setUser($this->user())
            ->setDepartment($this->department());
        $fixture = $this->flowFixture(MiDataDepartmentVerificationStatus::NOT_CONFIRMED, null, $pending);
        $fixture['entityManager']->expects(self::never())->method('persist');
        $fixture['entityManager']->expects(self::never())->method('flush');
        $fixture['notifier']->expects(self::never())->method('notifyJoinRequestCreated');

        $result = $fixture['service']->submitJoinCode($fixture['user'], 'department-1', $this->oauthSession());

        self::assertSame(DepartmentJoinOutcomeStatus::REQUEST_ALREADY_PENDING, $result->status);
        self::assertSame($pending, $result->joinRequest);
    }

    public function testUnavailableVerificationDoesNotChangeMembershipOrRequests(): void
    {
        $fixture = $this->flowFixture(MiDataDepartmentVerificationStatus::UNAVAILABLE);
        $fixture['entityManager']->expects(self::never())->method('persist');
        $fixture['entityManager']->expects(self::never())->method('flush');
        $fixture['notifier']->expects(self::never())->method('notifyJoinRequestCreated');

        $result = $fixture['service']->submitJoinCode($fixture['user'], 'department-1', $this->oauthSession());

        self::assertSame(DepartmentJoinOutcomeStatus::VERIFICATION_UNAVAILABLE, $result->status);
    }

    public function testConfirmedVerificationClosesAnExistingPendingRequestWithoutInventingReviewer(): void
    {
        $pending = (new JoinRequest())
            ->setId('request-123456')
            ->setUser($this->user())
            ->setDepartment($this->department());
        $fixture = $this->flowFixture(MiDataDepartmentVerificationStatus::CONFIRMED, null, $pending);
        $fixture['entityManager']->expects(self::once())->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$fixture): void {
                $fixture['persisted'][] = $entity;
            });
        $fixture['entityManager']->expects(self::once())->method('flush');

        $result = $fixture['service']->submitJoinCode($fixture['user'], 'department-1', $this->oauthSession());

        self::assertSame(DepartmentJoinOutcomeStatus::MEMBERSHIP_CONFIRMED, $result->status);
        self::assertSame('approved', $pending->getStatus());
        self::assertNull($pending->getReviewedBy());
        self::assertCount(1, $fixture['persisted']);
        self::assertInstanceOf(Membership::class, $fixture['persisted'][0]);
    }

    public function testRepeatedConfirmedJoinDoesNotCreateDuplicateMembershipOrRequest(): void
    {
        $membership = (new Membership())
            ->setUser($this->user())
            ->setDepartment($this->department())
            ->setRole('mw');
        $approvedRequest = (new JoinRequest())
            ->setId('request-123456')
            ->setUser($this->user())
            ->setDepartment($this->department())
            ->setStatus('approved');
        $fixture = $this->flowFixture(
            MiDataDepartmentVerificationStatus::CONFIRMED,
            $membership,
            null,
            $approvedRequest,
        );
        $fixture['groupMembershipSynchronizer']->expects(self::once())
            ->method('sync')
            ->willReturn(new MiDataGroupMembershipSyncResult());
        $fixture['entityManager']->expects(self::never())->method('persist');
        $fixture['entityManager']->expects(self::once())->method('flush');

        $result = $fixture['service']->submitJoinCode($fixture['user'], 'department-1', $this->oauthSession());

        self::assertSame(DepartmentJoinOutcomeStatus::ALREADY_MEMBER, $result->status);
        self::assertSame('mw', $membership->getRole());
    }

    /**
     * @return array<string, mixed>
     */
    private function flowFixture(
        MiDataDepartmentVerificationStatus $verificationStatus,
        ?Membership $membership = null,
        ?JoinRequest $pendingRequest = null,
        ?JoinRequest $approvedRequest = null,
    ): array {
        $user = $this->user();
        $department = $this->department();
        $repositories = [];
        foreach ([Department::class, DepartmentSetting::class, Membership::class, JoinRequest::class] as $entityClass) {
            $repositories[$entityClass] = $this->getMockBuilder(EntityRepository::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['find', 'findOneBy', 'count'])
                ->getMock();
        }
        $repositories[Department::class]->method('find')->willReturn($department);
        $repositories[Membership::class]->method('findOneBy')->willReturn($membership);
        $repositories[Membership::class]->method('count')->willReturn(0);
        $repositories[JoinRequest::class]->method('findOneBy')->willReturnCallback(
            static function (array $criteria) use ($pendingRequest, $approvedRequest): ?JoinRequest {
                if (($criteria['status'] ?? null) === 'pending') {
                    return $pendingRequest;
                }
                if (($criteria['status'] ?? null) === 'approved') {
                    return $approvedRequest;
                }

                return null;
            },
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(
            static fn (string $entityClass): EntityRepository => $repositories[$entityClass],
        );
        $verifier = $this->createMock(MiDataDepartmentMembershipVerifier::class);
        $verification = new MiDataDepartmentVerificationResult($verificationStatus);
        $verifier->method('verify')->willReturn($verification);
        $groupMembershipSynchronizer = $this->createMock(MiDataGroupMembershipSynchronizer::class);
        $groupMembershipSynchronizer->method('sync')->willReturn(new MiDataGroupMembershipSyncResult());
        $auditLogger = $this->createMock(AuditLogger::class);
        $notifier = $this->createMock(JoinRequestNotifier::class);
        $logger = $this->createMock(LoggerInterface::class);
        $persisted = [];

        return [
            'service' => new DepartmentJoinFlowService(
                $entityManager,
                $verifier,
                $groupMembershipSynchronizer,
                $auditLogger,
                $notifier,
                $logger,
            ),
            'user' => $user,
            'department' => $department,
            'entityManager' => $entityManager,
            'auditLogger' => $auditLogger,
            'notifier' => $notifier,
            'groupMembershipSynchronizer' => $groupMembershipSynchronizer,
            'verification' => $verification,
            'persisted' => &$persisted,
        ];
    }

    private function user(): User
    {
        $user = new User();
        $user->setId('user-123456');

        return $user;
    }

    private function department(): Department
    {
        return (new Department())->setId('department-1');
    }

    private function oauthSession(): HitobitoOAuthSession
    {
        return new HitobitoOAuthSession(
            'midata',
            'access-token',
            new \App\Service\Auth\MiDataOAuthUserInfo('person-1', null, false, null, null),
        );
    }
}
