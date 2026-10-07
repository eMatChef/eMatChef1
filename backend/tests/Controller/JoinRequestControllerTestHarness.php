<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\JoinRequestController;
use App\Entity\AdminJoinRequest;
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
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * In-memory data layer for JoinRequestController support-request tests.
 *
 * Organisation A has department D (managed) with sub-department C and a separate root department S;
 * organisation B has department X.
 */
trait JoinRequestControllerTestHarness
{
    private Organisation $organisation;
    private Department $managed;
    private Department $child;
    private Department $sibling;
    private Department $foreign;
    private User $actor;
    private bool $globalAdmin = false;
    private bool $superAdmin = false;
    /** @var list<string>|null null = all organisations */
    private ?array $accessibleOrganisationIds = null;
    private int $leadersInTarget = 1;
    /** @var array<string, AdminJoinRequest> */
    private array $requests = [];
    /** @var array<string, User> */
    private array $users = [];
    /** @var list<Membership> */
    private array $memberships = [];
    /** @var list<object> */
    private array $persisted = [];
    private int $flushes = 0;
    private int $removals = 0;
    /** @var list<QueryBuilder> */
    private array $queryBuilders = [];
    private int $userQueryBuilders = 0;
    /** @var array<string, object> */
    private array $controllerOverrides = [];

    private function setUpWorld(): void
    {
        $this->organisation = (new Organisation())->setId('orga00000001');
        $this->managed = $this->department('dept0000000d', $this->organisation);
        $this->child = $this->department('dept0000000c', $this->organisation, $this->managed);
        $this->sibling = $this->department('dept0000000s', $this->organisation);
        $this->foreign = $this->department('deptx0000001', (new Organisation())->setId('orgb00000001'));
        $this->actor = $this->user('actor0000001');
    }

    private function actorRole(string $role): void
    {
        $this->memberships[] = (new Membership())->setUser($this->actor)->setDepartment($this->managed)->setRole($role);
    }

    private function adminRequest(string $id, ?string $organisationId, ?string $parentId, string $userId = 'applicant0001'): AdminJoinRequest
    {
        $request = (new AdminJoinRequest())
            ->setId($id)
            ->setUser($this->user($userId))
            ->setRequestedDepartmentName('Neue Abteilung')
            ->setRequestedOrganisationId($organisationId)
            ->setRequestedParentDepartment($parentId !== null ? $this->departments()[$parentId] : null)
            ->setStatus('pending');
        $this->requests[$id] = $request;

        return $request;
    }

    private function user(string $id): User
    {
        if (isset($this->users[$id])) {
            return $this->users[$id];
        }
        $profile = (new Profile())->setId('prof' . substr($id, 4));
        $profile->setEmail($id . '@example.test');

        return $this->users[$id] = (new User())->setId($id)->setProfile($profile);
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

    private function createdMembership(string $userId = 'applicant0001'): ?Membership
    {
        foreach ($this->persisted as $entity) {
            if ($entity instanceof Membership && $entity->getUserId() === $userId) {
                return $entity;
            }
        }

        return null;
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
        $capabilities->method('isSuperAdmin')->willReturnCallback(fn (): bool => $this->superAdmin);
        $capabilities->method('getAccessibleOrganisationIds')->willReturnCallback(fn (): ?array => $this->accessibleOrganisationIds);
        $capabilities->method('canAccessDepartment')->willReturn(true);
        $departmentScope = $this->createMock(AdminCapabilityDepartmentScope::class);
        $departmentScope->method('expandSubtreeDepartmentIds')->willReturn(['dept0000000d', 'dept0000000c']);
        $departmentScope->method('filterDepartmentIdsWithinOrganisations')->willReturnArgument(0);

        $overrides = $this->controllerOverrides + [
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
        $entityManager->method('createQuery')->willReturn($query);

        $repositories = [];
        $repository = function (string $class) use (&$repositories, $entityManager): EntityRepository {
            if (isset($repositories[$class])) {
                return $repositories[$class];
            }
            $repository = $this->createMock(EntityRepository::class);
            $repository->method('find')->willReturnCallback(fn (string $id): ?object => match ($class) {
                AdminJoinRequest::class => $this->requests[$id] ?? null,
                Department::class => $this->departments()[$id] ?? null,
                User::class => $this->users[$id] ?? null,
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
            $repository->method('findBy')->willReturnCallback(function (array $criteria) use ($class): array {
                if ($class !== AdminJoinRequest::class || !isset($criteria['userId'])) {
                    return [];
                }

                return array_values(array_filter(
                    $this->requests,
                    static fn (AdminJoinRequest $request): bool => $request->getUserId() === $criteria['userId'],
                ));
            });
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
        $entityManager->method('flush')->willReturnCallback(function (): void {
            $this->flushes++;
        });
        $entityManager->method('remove')->willReturnCallback(function (): void {
            $this->removals++;
        });

        return $entityManager;
    }
}
