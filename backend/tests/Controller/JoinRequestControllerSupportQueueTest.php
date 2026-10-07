<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AdminJoinRequest;
use App\Service\AuditLogger;
use App\Service\Support\UnassignedUserSupportQueue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The support list is read-only; unassigned users are a computed queue with explicit admin actions.
 */
final class JoinRequestControllerSupportQueueTest extends TestCase
{
    use JoinRequestControllerTestHarness;

    private UnassignedUserSupportQueue&MockObject $queue;
    private AuditLogger&MockObject $auditLogger;

    protected function setUp(): void
    {
        $this->setUpWorld();
        $this->queue = $this->createMock(UnassignedUserSupportQueue::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->controllerOverrides = [
            'unassignedUserQueue' => $this->queue,
            'auditLogger' => $this->auditLogger,
        ];
    }

    public function testPendingIsReadOnlyAndListsQueueEntriesWithoutRequestId(): void
    {
        $this->globalAdmin = true;
        $this->superAdmin = true;
        $unassigned = $this->user('lonely000001');
        $this->queue->method('findUnassignedUsers')->willReturn([$unassigned]);
        $this->queue->expects(self::exactly(2))->method('excludeSystemRequests');
        $this->queue->expects(self::never())->method('claimSystemRequest');
        $this->queue->expects(self::never())->method('dismiss');
        $controller = $this->controller();

        $first = json_decode((string) $controller->pendingAdminRequests($this->request('GET'))->getContent(), true);
        $second = json_decode((string) $controller->pendingAdminRequests($this->request('GET'))->getContent(), true);

        self::assertSame($first, $second);
        self::assertSame([], $this->persisted);
        self::assertSame(0, $this->flushes);
        self::assertSame(0, $this->removals);
        self::assertSame(0, $this->userQueryBuilders, 'no automatic creation query anymore');
        self::assertCount(1, $first);
        self::assertSame('unassigned_user', $first[0]['request_kind']);
        self::assertNull($first[0]['id']);
        self::assertSame('lonely000001', $first[0]['user_id']);
        self::assertSame('unassigned', $first[0]['status']);
    }

    public function testPendingKeepsLegacySystemRequestsUntouched(): void
    {
        $this->globalAdmin = true;
        $this->superAdmin = true;
        $legacy = $this->adminRequest('ajrlegacy001', null, null, 'lonely000001');
        $this->queue->method('findUnassignedUsers')->willReturn([]);

        $this->controller()->pendingAdminRequests($this->request('GET'));

        self::assertSame('pending', $legacy->getStatus());
        self::assertSame([], $this->persisted);
        self::assertSame(0, $this->flushes);
        self::assertSame(0, $this->removals);
    }

    public function testDepartmentManagersNeverSeeTheQueue(): void
    {
        $this->actorRole('mw');
        $this->queue->expects(self::never())->method('findUnassignedUsers');

        $response = $this->controller()->pendingAdminRequests($this->request('GET', ['department_id' => 'dept0000000d']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], json_decode((string) $response->getContent(), true));
    }

    public function testExplicitQueueAssignmentCreatesAssignedRequestMembershipAndAudit(): void
    {
        $this->globalAdmin = true;
        $this->superAdmin = true;
        $unassigned = $this->user('lonely000001');
        $claimed = (new AdminJoinRequest())->setId('ajrqueue0001')->setUser($unassigned)
            ->setRequestedDepartmentName(UnassignedUserSupportQueue::UNKNOWN_DEPARTMENT_NAME)->setStatus('pending');
        $this->queue->method('isUnassigned')->with($unassigned)->willReturn(true);
        $this->queue->expects(self::once())->method('claimSystemRequest')->with($unassigned, $this->actor)->willReturn($claimed);
        $this->auditLogger->expects(self::once())->method('log');

        $response = $this->controller()->assignUnassignedUser(
            'lonely000001',
            $this->request('POST', [], ['target_department_id' => 'dept0000000c', 'target_role' => 'u']),
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('assigned', $claimed->getStatus());
        self::assertSame('dept0000000c', $claimed->getAssignedDepartmentId());
        self::assertSame('u', $this->createdMembership('lonely000001')?->getRole());
        self::assertGreaterThan(0, $this->flushes);
    }

    public function testExplicitQueueDismissalStoresARejectedRequest(): void
    {
        $this->globalAdmin = true;
        $this->superAdmin = true;
        $unassigned = $this->user('lonely000001');
        $rejected = (new AdminJoinRequest())->setId('ajrqueue0001')->setUser($unassigned)
            ->setRequestedDepartmentName(UnassignedUserSupportQueue::UNKNOWN_DEPARTMENT_NAME)->setStatus('rejected');
        $this->queue->method('isUnassigned')->willReturn(true);
        $this->queue->expects(self::once())->method('dismiss')->with($unassigned, $this->actor)->willReturn($rejected);

        $response = $this->controller()->dismissUnassignedUser('lonely000001');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['success' => true, 'id' => 'ajrqueue0001', 'status' => 'rejected'], json_decode((string) $response->getContent(), true));
    }

    public function testQueueActionsRequireAGlobalAdminAndAStillUnassignedUser(): void
    {
        $this->user('lonely000001');
        $this->actorRole('mw');
        $this->queue->expects(self::never())->method('dismiss');
        $this->queue->expects(self::never())->method('claimSystemRequest');
        $controller = $this->controller();

        self::assertSame(403, $controller->dismissUnassignedUser('lonely000001')->getStatusCode());
        self::assertSame(403, $controller->assignUnassignedUser('lonely000001', $this->request('POST', ['department_id' => 'dept0000000d'], ['target_department_id' => 'dept0000000c']))->getStatusCode());

        $this->globalAdmin = true;
        $this->superAdmin = true;
        $this->queue->method('isUnassigned')->willReturn(false);
        $controller = $this->controller();
        self::assertSame(409, $controller->dismissUnassignedUser('lonely000001')->getStatusCode());
        self::assertSame(404, $controller->dismissUnassignedUser('nobody000001')->getStatusCode());
    }

    /**
     * @return iterable<string, array{0: ?string, 1: int}>
     */
    public static function orgAdminRejects(): iterable
    {
        yield 'own organisation' => ['orga00000001', 200];
        yield 'foreign organisation' => ['orgb00000001', 403];
        yield 'request without organisation (visible to org admins)' => [null, 200];
    }

    #[DataProvider('orgAdminRejects')]
    public function testOrgAdminRejectsOnlyInsideAccessibleOrganisations(?string $organisationId, int $status): void
    {
        $this->globalAdmin = true;
        $this->accessibleOrganisationIds = ['orga00000001'];
        $request = $this->adminRequest('ajr000000001', $organisationId, null);

        $response = $this->controller()->decideAdminRequest('ajr000000001', $this->request('PATCH', [], ['status' => 'rejected']));

        self::assertSame($status, $response->getStatusCode());
        self::assertSame($status === 200 ? 'rejected' : 'pending', $request->getStatus());
    }

    public function testSuperAdminRejectsGlobally(): void
    {
        $this->globalAdmin = true;
        $this->superAdmin = true;
        $this->accessibleOrganisationIds = ['orga00000001'];
        $request = $this->adminRequest('ajr000000001', 'orgb00000001', null);

        $response = $this->controller()->decideAdminRequest('ajr000000001', $this->request('PATCH', [], ['status' => 'rejected']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('rejected', $request->getStatus());
    }

    public function testMineHidesLegacySystemRequestWhileAVerifiedAlternativeIsOpen(): void
    {
        $this->actor = $this->user('applicant0001');
        $this->adminRequest('ajrlegacy001', null, null);
        $this->adminRequest('ajrownreq001', 'orga00000001', 'dept0000000d');
        $this->queue->method('hasVerifiedAlternative')->willReturn(true);
        $this->queue->method('systemRequestIds')->willReturn(['ajrlegacy001']);

        $response = $this->controller()->mine();

        $ids = array_column(json_decode((string) $response->getContent(), true), 'id');
        self::assertSame(['ajrownreq001'], $ids);
        self::assertSame('pending', $this->requests['ajrlegacy001']->getStatus());
        self::assertSame(0, $this->flushes);
    }

    public function testMineShowsLegacySystemRequestWithoutVerifiedAlternative(): void
    {
        $this->actor = $this->user('applicant0001');
        $this->adminRequest('ajrlegacy001', null, null);
        $this->queue->method('hasVerifiedAlternative')->willReturn(false);
        $this->queue->expects(self::never())->method('systemRequestIds');

        $ids = array_column(json_decode((string) $this->controller()->mine()->getContent(), true), 'id');

        self::assertSame(['ajrlegacy001'], $ids);
    }
}
