<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\JoinRequestController;
use App\Entity\AdminJoinRequest;
use App\Entity\AdminJoinRequestEvent;
use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\Organisation;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\Admin\AdminCapabilityDepartmentScope;
use App\Service\Admin\AdminJoinRequestManagerScope;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Department managers (mw/dc without global admin role) only handle admin join requests in their scope.
 */
final class JoinRequestControllerAdminScopeTest extends TestCase
{
    private Organisation $organisation;
    private Department $managed;
    private Department $child;
    private Department $sibling;
    private Department $foreign;
    private User $actor;
    private bool $globalAdmin = false;
    private int $leadersInTarget = 1;
    /** @var array<string, AdminJoinRequest> */
    private array $requests = [];
    /** @var list<Membership> */
    private array $memberships = [];
    /** @var list<object> */
    private array $persisted = [];
    /** @var list<string> */
    private array $dql = [];
    /** @var list<QueryBuilder> */
    private array $queryBuilders = [];
    private int $userQueryBuilders = 0;

    protected function setUp(): void
    {
        $this->organisation = (new Organisation())->setId('orga00000001');
        $this->managed = $this->department('dept0000000d', $this->organisation);
        $this->child = $this->department('dept0000000c', $this->organisation, $this->managed);
        $this->sibling = $this->department('dept0000000s', $this->organisation);
        $this->foreign = $this->department('deptx0000001', (new Organisation())->setId('orgb00000001'));
        $this->actor = $this->user('actor0000001');
    }

    public function testManagerPendingListIsRestrictedToTheManagedSubtreeAndCreatesNothing(): void
    {
        $this->actorRole('mw');

        $response = $this->controller()->pendingAdminRequests($this->request('GET', ['department_id' => 'dept0000000d']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $this->userQueryBuilders, 'department managers never trigger the auto-creation');
        self::assertSame([], $this->persisted);
        $this->assertManagerScopeQuery();
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

    public function testGlobalAdminPendingListIsUnchanged(): void
    {
        $this->globalAdmin = true;

        $response = $this->controller()->pendingAdminRequests($this->request('GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $this->userQueryBuilders);
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
        $this->leadersInTarget = 0;
        $this->adminRequest('ajr000000001', 'orgb00000001', 'deptx0000001');

        $response = $this->assign(['target_department_id' => 'dept0000000s', 'target_role' => 'u'], '');

        self::assertSame(200, $response->getStatusCode());
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

    private function assertManagerScopeQuery(): void
    {
        self::assertCount(1, $this->queryBuilders);
        $this->assertScoped($this->queryBuilders[0]);
    }

    private function assertScoped(QueryBuilder $queryBuilder): void
    {
        $dql = $queryBuilder->getDQL();
        self::assertStringContainsString('ajr.requestedOrganisationId = :managerScopeOrganisationId', $dql);
        self::assertStringContainsString('ajr.requestedParentDepartmentId IN (:managerScopeDepartmentIds)', $dql);
        self::assertSame('orga00000001', $queryBuilder->getParameter('managerScopeOrganisationId')?->getValue());
        self::assertSame(['dept0000000d', 'dept0000000c'], $queryBuilder->getParameter('managerScopeDepartmentIds')?->getValue());
    }

    private function createdMembership(): ?Membership
    {
        foreach ($this->persisted as $entity) {
            if ($entity instanceof Membership && $entity->getUserId() === 'applicant0001') {
                return $entity;
            }
        }

        return null;
    }

    private function actorRole(string $role): void
    {
        $this->memberships[] = (new Membership())->setUser($this->actor)->setDepartment($this->managed)->setRole($role);
    }

    private function adminRequest(string $id, ?string $organisationId, ?string $parentId): AdminJoinRequest
    {
        $request = (new AdminJoinRequest())
            ->setId($id)
            ->setUser($this->user('applicant0001'))
            ->setRequestedDepartmentName('Neue Abteilung')
            ->setRequestedOrganisationId($organisationId)
            ->setRequestedParentDepartment($parentId !== null ? $this->departments()[$parentId] : null)
            ->setStatus('pending');
        $this->requests[$id] = $request;

        return $request;
    }

    private function user(string $id): User
    {
        $profile = (new Profile())->setId('prof' . substr($id, 4));
        $profile->setEmail($id . '@example.test');

        return (new User())->setId($id)->setProfile($profile);
    }

    private function department(string $id, Organisation $organisation, ?Department $parent = null): Department
    {
        $department = (new Department())->setId($id);
        $department->setName($id);
        $department->setOrganisation($organisation);
        $department->setParent($parent);

        return $department;
    }

    /**
     * @return array<string, Department>
     */
    private function departments(): array
    {
        return [
            'dept0000000d' => $this->managed,
            'dept0000000c' => $this->child,
            'dept0000000s' => $this->sibling,
            'deptx0000001' => $this->foreign,
        ];
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string> $body
     */
    private function request(string $method, array $query = [], array $body = []): Request
    {
        return new Request($query, [], [], [], [], ['REQUEST_METHOD' => $method], (string) json_encode($body));
    }

    private function controller(): JoinRequestController
    {
        $entityManager = $this->entityManager();
        $capabilities = $this->createMock(AdminCapabilityChecker::class);
        $capabilities->method('can')->willReturnCallback(fn (): bool => $this->globalAdmin);
        $capabilities->method('isSuperAdmin')->willReturnCallback(fn (): bool => $this->globalAdmin);
        $capabilities->method('canAccessDepartment')->willReturn(true);
        $departmentScope = $this->createMock(AdminCapabilityDepartmentScope::class);
        $departmentScope->method('expandSubtreeDepartmentIds')->willReturn(['dept0000000d', 'dept0000000c']);
        $departmentScope->method('filterDepartmentIdsWithinOrganisations')->willReturnArgument(0);

        $overrides = [
            'entityManager' => $entityManager,
            'adminCapabilityChecker' => $capabilities,
            'adminJoinRequestScope' => new AdminJoinRequestManagerScope($entityManager, $departmentScope),
        ];
        $arguments = [];
        foreach ((new \ReflectionMethod(JoinRequestController::class, '__construct'))->getParameters() as $parameter) {
            $type = $parameter->getType();
            $arguments[] = $overrides[$parameter->getName()] ?? match (true) {
                $type instanceof \ReflectionNamedType && $type->isBuiltin() => 'https://app.ematchef.test',
                $type instanceof \ReflectionNamedType => $this->createMock($type->getName()),
                default => null,
            };
        }
        $controller = new JoinRequestController(...$arguments);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($this->actor, 'api', ['ROLE_USER']));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $controller->setContainer($container);

        return $controller;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $query = $this->createMock(Query::class);
        foreach (['setParameter', 'setParameters', 'setFirstResult', 'setMaxResults', 'setHint', 'setCacheable', 'setCacheMode', 'setCacheRegion', 'setLifetime'] as $method) {
            $query->method($method)->willReturnSelf();
        }
        $query->method('getResult')->willReturn([]);
        $query->method('getSingleScalarResult')->willReturnCallback(fn (): int => $this->leadersInTarget);
        $entityManager->method('createQuery')->willReturnCallback(function (string $dql) use ($query): Query {
            $this->dql[] = $dql;

            return $query;
        });

        $repositories = [];
        $repository = function (string $class) use (&$repositories, $entityManager): EntityRepository {
            if (isset($repositories[$class])) {
                return $repositories[$class];
            }
            $repository = $this->createMock(EntityRepository::class);
            $repository->method('find')->willReturnCallback(fn (string $id): ?object => match ($class) {
                AdminJoinRequest::class => $this->requests[$id] ?? null,
                Department::class => $this->departments()[$id] ?? null,
                default => null,
            });
            $repository->method('findOneBy')->willReturnCallback(function (array $criteria) use ($class): ?object {
                if ($class !== Membership::class || !isset($criteria['userId'], $criteria['departmentId'])) {
                    return null;
                }
                foreach ($this->memberships as $membership) {
                    if ($membership->getUserId() === $criteria['userId'] && $membership->getDepartmentId() === $criteria['departmentId']) {
                        return $membership;
                    }
                }

                return null;
            });
            $repository->method('findBy')->willReturn([]);
            $repository->method('createQueryBuilder')->willReturnCallback(function (string $alias) use ($class, $entityManager): QueryBuilder {
                $queryBuilder = (new QueryBuilder($entityManager))->select($alias)->from($class, $alias);
                if ($class === User::class) {
                    $this->userQueryBuilders++;
                } elseif ($class === AdminJoinRequest::class) {
                    $this->queryBuilders[] = $queryBuilder;
                }

                return $queryBuilder;
            });

            return $repositories[$class] = $repository;
        };
        $entityManager->method('getRepository')->willReturnCallback($repository);
        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        return $entityManager;
    }
}
