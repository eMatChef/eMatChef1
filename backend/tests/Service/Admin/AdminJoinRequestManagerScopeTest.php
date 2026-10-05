<?php

declare(strict_types=1);

namespace App\Tests\Service\Admin;

use App\Entity\AdminJoinRequest;
use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\Organisation;
use App\Entity\User;
use App\Service\Admin\AdminCapabilityDepartmentScope;
use App\Service\Admin\AdminJoinRequestManagerScope;
use App\Service\Admin\AdminJoinRequestManagerScopeContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminJoinRequestManagerScopeTest extends TestCase
{
    private Organisation $organisation;
    private Department $managed;
    private Department $child;
    private Department $sibling;
    /** @var list<Membership> */
    private array $memberships = [];

    protected function setUp(): void
    {
        $this->organisation = (new Organisation())->setId('orga00000001');
        $this->managed = $this->department('dept0000000d', $this->organisation);
        $this->child = $this->department('dept0000000c', $this->organisation, $this->managed);
        $this->sibling = $this->department('dept0000000s', $this->organisation);
    }

    public function testDepartmentManagerScopeCoversOwnSubtreeWithinTheOrganisation(): void
    {
        $actor = $this->member('mw');

        $scope = $this->scope()->resolve($actor, 'dept0000000d', AdminJoinRequestManagerScope::VIEW_ROLES);

        self::assertNotNull($scope);
        self::assertSame('orga00000001', $scope->organisationId);
        self::assertSame('mw', $scope->role);
        self::assertSame(['dept0000000d', 'dept0000000c'], $scope->departmentIds);
    }

    public function testDcMayViewButNotAssign(): void
    {
        $actor = $this->member('dc');

        self::assertNotNull($this->scope()->resolve($actor, 'dept0000000d', AdminJoinRequestManagerScope::VIEW_ROLES));
        self::assertNull($this->scope()->resolve($actor, 'dept0000000d', AdminJoinRequestManagerScope::ASSIGN_ROLES));
    }

    public function testOrdinaryMembersAndNonMembersHaveNoScope(): void
    {
        self::assertNull($this->scope()->resolve($this->member('u'), 'dept0000000d', AdminJoinRequestManagerScope::VIEW_ROLES));
        self::assertNull($this->scope()->resolve((new User())->setId('stranger0001'), 'dept0000000d', AdminJoinRequestManagerScope::VIEW_ROLES));
        self::assertNull($this->scope()->resolve($this->member('mw'), '', AdminJoinRequestManagerScope::VIEW_ROLES));
    }

    /**
     * @return iterable<string, array{0: ?string, 1: ?string, 2: bool}>
     */
    public static function requests(): iterable
    {
        yield 'own department as parent' => ['orga00000001', 'dept0000000d', true];
        yield 'sub-department as parent' => ['orga00000001', 'dept0000000c', true];
        yield 'other subtree of the same organisation' => ['orga00000001', 'dept0000000s', false];
        yield 'same organisation without parent department' => ['orga00000001', null, false];
        yield 'other organisation' => ['orgb00000001', 'dept0000000d', false];
        yield 'unknown department without organisation' => [null, null, false];
    }

    #[DataProvider('requests')]
    public function testRequestMustBelongToTheOrganisationAndTheManagedSubtree(
        ?string $organisationId,
        ?string $parentDepartmentId,
        bool $expected,
    ): void {
        $request = (new AdminJoinRequest())
            ->setRequestedOrganisationId($organisationId)
            ->setRequestedParentDepartment(match ($parentDepartmentId) {
                'dept0000000d' => $this->managed,
                'dept0000000c' => $this->child,
                'dept0000000s' => $this->sibling,
                default => null,
            });

        self::assertSame($expected, $this->scope()->contains($request, $this->context('mw')));
    }

    public function testQueryRestrictionUsesTheSameRule(): void
    {
        $queryBuilder = (new QueryBuilder($this->createMock(EntityManagerInterface::class)))
            ->select('ajr')
            ->from(AdminJoinRequest::class, 'ajr');

        $this->scope()->restrictQuery($queryBuilder, 'ajr', $this->context('mw'));

        $dql = $queryBuilder->getDQL();
        self::assertStringContainsString('ajr.requestedOrganisationId = :managerScopeOrganisationId', $dql);
        self::assertStringContainsString('ajr.requestedParentDepartmentId IN (:managerScopeDepartmentIds)', $dql);
        self::assertStringNotContainsString('IS NULL', $dql);
        self::assertSame('orga00000001', $queryBuilder->getParameter('managerScopeOrganisationId')?->getValue());
        self::assertSame(['dept0000000d', 'dept0000000c'], $queryBuilder->getParameter('managerScopeDepartmentIds')?->getValue());
    }

    public function testAssignmentIsLimitedToTheSubtreeAndTheRoleCatalog(): void
    {
        $scope = $this->scope();
        $mw = $this->context('mw');
        $otherOrganisation = $this->department('deptx0000001', (new Organisation())->setId('orgb00000001'));

        self::assertNull($scope->assignmentDenial($mw, $this->child, 'u'));
        self::assertNull($scope->assignmentDenial($mw, $this->managed, 'dc'));
        self::assertSame(403, $scope->assignmentDenial($mw, $this->sibling, 'u')['status'] ?? null);
        self::assertSame(403, $scope->assignmentDenial($mw, $otherOrganisation, 'u')['status'] ?? null);
        self::assertSame(403, $scope->assignmentDenial($mw, $this->child, 'mw')['status'] ?? null);
        self::assertSame(400, $scope->assignmentDenial($mw, $this->child, 'cmw')['status'] ?? null);
        self::assertSame(400, $scope->assignmentDenial($mw, $this->child, 'admin')['status'] ?? null);
    }

    public function testDcMayOnlyAssignRolesBelowItself(): void
    {
        $scope = $this->scope();
        $dc = $this->context('dc');

        self::assertNull($scope->assignmentDenial($dc, $this->child, 'l1'));
        self::assertSame(403, $scope->assignmentDenial($dc, $this->child, 'dc')['status'] ?? null);
        self::assertSame(403, $scope->assignmentDenial($dc, $this->child, 'mw')['status'] ?? null);
    }

    private function context(string $role): AdminJoinRequestManagerScopeContext
    {
        return new AdminJoinRequestManagerScopeContext('dept0000000d', 'orga00000001', $role, ['dept0000000d', 'dept0000000c']);
    }

    private function member(string $role): User
    {
        $user = (new User())->setId('actor0000001');
        $this->memberships[] = (new Membership())->setUser($user)->setDepartment($this->managed)->setRole($role);

        return $user;
    }

    private function department(string $id, Organisation $organisation, ?Department $parent = null): Department
    {
        $department = (new Department())->setId($id);
        $department->setName($id);
        $department->setOrganisation($organisation);
        $department->setParent($parent);

        return $department;
    }

    private function scope(): AdminJoinRequestManagerScope
    {
        $memberships = $this->createMock(EntityRepository::class);
        $memberships->method('findOneBy')->willReturnCallback(function (array $criteria): ?Membership {
            foreach ($this->memberships as $membership) {
                if ($membership->getUserId() === $criteria['userId'] && $membership->getDepartmentId() === $criteria['departmentId']) {
                    return $membership;
                }
            }

            return null;
        });
        $departments = $this->createMock(EntityRepository::class);
        $departments->method('find')->willReturnCallback(
            fn (string $id): ?Department => [
                'dept0000000d' => $this->managed,
                'dept0000000c' => $this->child,
                'dept0000000s' => $this->sibling,
            ][$id] ?? null,
        );
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(
            static fn (string $class): EntityRepository => $class === Membership::class ? $memberships : $departments,
        );

        $departmentScope = $this->createMock(AdminCapabilityDepartmentScope::class);
        $departmentScope->method('expandSubtreeDepartmentIds')->with(['dept0000000d'])
            ->willReturn(['dept0000000d', 'dept0000000c', 'deptx0000099']);
        $departmentScope->method('filterDepartmentIdsWithinOrganisations')
            ->with(['dept0000000d', 'dept0000000c', 'deptx0000099'], ['orga00000001'])
            ->willReturn(['dept0000000d', 'dept0000000c']);

        return new AdminJoinRequestManagerScope($entityManager, $departmentScope);
    }
}
