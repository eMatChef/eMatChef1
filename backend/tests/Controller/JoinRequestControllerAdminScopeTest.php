<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Membership;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Department managers (mw/dc without global admin role) only handle admin join requests in their scope.
 */
final class JoinRequestControllerAdminScopeTest extends TestCase
{
    use JoinRequestControllerTestHarness;

    protected function setUp(): void
    {
        $this->setUpWorld();
    }

    public function testManagerPendingListIsRestrictedToTheManagedSubtreeAndCreatesNothing(): void
    {
        $this->actorRole('mw');

        $response = $this->controller()->pendingAdminRequests($this->request('GET', ['department_id' => 'dept0000000d']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $this->userQueryBuilders);
        self::assertSame([], $this->persisted);
        self::assertSame(0, $this->flushes);
        self::assertCount(1, $this->queryBuilders);
        $this->assertScoped($this->queryBuilders[0]);
    }

    public function testDcPendingAndHistoryUseTheSameScope(): void
    {
        $this->actorRole('dc');
        $controller = $this->controller();

        self::assertSame(200, $controller->pendingAdminRequests($this->request('GET', ['department_id' => 'dept0000000d']))->getStatusCode());
        self::assertSame(200, $controller->adminRequestHistory($this->request('GET', ['department_id' => 'dept0000000d']))->getStatusCode());

        self::assertCount(2, $this->queryBuilders);
        foreach ($this->queryBuilders as $queryBuilder) {
            $this->assertScoped($queryBuilder);
        }
    }

    public function testOrdinaryMemberCannotListRequests(): void
    {
        $this->actorRole('u');
        $controller = $this->controller();

        self::assertSame(403, $controller->pendingAdminRequests($this->request('GET', ['department_id' => 'dept0000000d']))->getStatusCode());
        self::assertSame(403, $controller->adminRequestHistory($this->request('GET', ['department_id' => 'dept0000000d']))->getStatusCode());
    }

    public function testGlobalAdminPendingListHasNoManagerRestriction(): void
    {
        $this->globalAdmin = true;
        $this->superAdmin = true;

        $response = $this->controller()->pendingAdminRequests($this->request('GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('managerScope', $this->queryBuilders[0]->getDQL());
    }

    /**
     * @return iterable<string, array{0: string, 1: ?string, 2: ?string, 3: int}>
     */
    public static function rejectCases(): iterable
    {
        yield 'mw, own department' => ['mw', 'orga00000001', 'dept0000000d', 200];
        yield 'mw, sub-department' => ['mw', 'orga00000001', 'dept0000000c', 200];
        yield 'dc, sub-department' => ['dc', 'orga00000001', 'dept0000000c', 200];
        yield 'other subtree' => ['mw', 'orga00000001', 'dept0000000s', 403];
        yield 'other organisation' => ['mw', 'orgb00000001', 'deptx0000001', 403];
        yield 'organisation without parent' => ['dc', 'orga00000001', null, 403];
        yield 'unknown department without organisation' => ['mw', null, null, 403];
    }

    #[DataProvider('rejectCases')]
    public function testRejectRequiresTheRequestToBeInScope(string $role, ?string $organisationId, ?string $parentId, int $status): void
    {
        $this->actorRole($role);
        $adminRequest = $this->adminRequest('ajr000000001', $organisationId, $parentId);

        $response = $this->controller()->decideAdminRequest(
            'ajr000000001',
            $this->request('PATCH', ['department_id' => 'dept0000000d'], ['status' => 'rejected']),
        );

        self::assertSame($status, $response->getStatusCode());
        self::assertSame($status === 200 ? 'rejected' : 'pending', $adminRequest->getStatus());
    }

    public function testMwAssignsInScopeRequestToSubDepartment(): void
    {
        $this->actorRole('mw');
        $adminRequest = $this->adminRequest('ajr000000001', 'orga00000001', 'dept0000000d');

        $response = $this->assign(['target_department_id' => 'dept0000000c', 'target_role' => 'l1']);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('assigned', $adminRequest->getStatus());
        self::assertSame('l1', $this->createdMembership()?->getRole());
        self::assertSame('dept0000000c', $this->createdMembership()?->getDepartmentId());
    }

    /**
     * @return iterable<string, array{0: array<string, string>, 1: int}>
     */
    public static function deniedAssignments(): iterable
    {
        yield 'target outside the subtree' => [['target_department_id' => 'dept0000000s', 'target_role' => 'u'], 403];
        yield 'target in another organisation' => [['target_department_id' => 'deptx0000001', 'target_role' => 'u'], 403];
        yield 'mw cannot create another mw' => [['target_department_id' => 'dept0000000c', 'target_role' => 'mw'], 403];
        yield 'role not allowed for a Pfadi department' => [['target_department_id' => 'dept0000000c', 'target_role' => 'cmw'], 400];
    }

    /**
     * @param array<string, string> $payload
     */
    #[DataProvider('deniedAssignments')]
    public function testMwAssignmentOutsideScopeOrRankIsDenied(array $payload, int $status): void
    {
        $this->actorRole('mw');
        $adminRequest = $this->adminRequest('ajr000000001', 'orga00000001', 'dept0000000d');

        $response = $this->assign($payload);

        self::assertSame($status, $response->getStatusCode());
        self::assertSame('pending', $adminRequest->getStatus());
        self::assertNull($this->createdMembership());
    }

    public function testMwCannotAssignRequestOutsideItsScope(): void
    {
        $this->actorRole('mw');
        $adminRequest = $this->adminRequest('ajr000000001', 'orgb00000001', 'deptx0000001');

        $response = $this->assign(['target_department_id' => 'dept0000000c', 'target_role' => 'u']);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('pending', $adminRequest->getStatus());
    }

    public function testDcCannotAssignAtAll(): void
    {
        $this->actorRole('dc');
        $this->adminRequest('ajr000000001', 'orga00000001', 'dept0000000d');

        self::assertSame(403, $this->assign(['target_department_id' => 'dept0000000c', 'target_role' => 'u'])->getStatusCode());
        self::assertNull($this->createdMembership());
    }

    public function testBootstrapFallbackNeverPromotesForDepartmentManagers(): void
    {
        $this->actorRole('mw');
        $this->leadersInTarget = 0;
        $this->adminRequest('ajr000000001', 'orga00000001', 'dept0000000d');

        $response = $this->assign(['target_department_id' => 'dept0000000c', 'target_role' => 'u']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('u', $this->createdMembership()?->getRole());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('u', $data['assigned_role']);
        self::assertArrayNotHasKey('role_forced_to_mw_warning', $data);
    }

    public function testGlobalAdminKeepsTheBootstrapFallback(): void
    {
        $this->globalAdmin = true;
        $this->superAdmin = true;
        $this->leadersInTarget = 0;
        $this->adminRequest('ajr000000001', 'orgb00000001', 'deptx0000001');

        $response = $this->assign(['target_department_id' => 'dept0000000s', 'target_role' => 'u'], '');

        self::assertSame(200, $response->getStatusCode());
        self::assertInstanceOf(Membership::class, $this->createdMembership());
        self::assertSame('mw', $this->createdMembership()?->getRole());
    }

    /**
     * @param array<string, string> $payload
     */
    private function assign(array $payload, string $actingDepartmentId = 'dept0000000d'): JsonResponse
    {
        $query = $actingDepartmentId !== '' ? ['department_id' => $actingDepartmentId] : [];

        return $this->controller()->assignAdminRequest('ajr000000001', $this->request('PATCH', $query, $payload));
    }

    private function assertScoped(QueryBuilder $queryBuilder): void
    {
        $dql = $queryBuilder->getDQL();
        self::assertStringContainsString('ajr.requestedOrganisationId = :managerScopeOrganisationId', $dql);
        self::assertStringContainsString('ajr.requestedParentDepartmentId IN (:managerScopeDepartmentIds)', $dql);
        self::assertSame('orga00000001', $queryBuilder->getParameter('managerScopeOrganisationId')?->getValue());
        self::assertSame(['dept0000000d', 'dept0000000c'], $queryBuilder->getParameter('managerScopeDepartmentIds')?->getValue());
    }
}
