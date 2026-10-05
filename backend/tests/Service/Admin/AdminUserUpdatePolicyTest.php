<?php

declare(strict_types=1);

namespace App\Tests\Service\Admin;

use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\Admin\AdminCapabilityDepartmentScope;
use App\Service\Admin\AdminUserUpdateDeniedException;
use App\Service\Admin\AdminUserUpdatePolicy;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Department-Baum: dep_a (org_1) → dep_a1; dep_b (org_2).
 */
final class AdminUserUpdatePolicyTest extends TestCase
{
    /** @var array<string, list<Membership>> */
    private array $membershipsByUserId = [];

    public function testOrgMayEditSubWithinOwnScope(): void
    {
        $actor = $this->orgChef('org_actor', ['dep_a']);
        $target = $this->subOrgChef('sub_target', ['dep_a1']);

        $this->policy()->assertUpdateAllowed($actor, $target, ['first_name' => 'Neu'], []);
        $this->addToAssertionCount(1);
    }

    public function testOrgMayNotEditSubWithWiderScope(): void
    {
        $actor = $this->orgChef('org_actor', ['dep_a']);
        $target = $this->subOrgChef('sub_target', ['dep_a', 'dep_b']);

        $this->assertDenied(403, fn () => $this->policy()->assertUpdateAllowed($actor, $target, [], []));
    }

    public function testOrgMayNotEditSubOutsideScope(): void
    {
        $actor = $this->orgChef('org_actor', ['dep_a']);
        $target = $this->subOrgChef('sub_target', ['dep_b']);

        $this->assertDenied(403, fn () => $this->policy()->assertUpdateAllowed($actor, $target, [], []));
    }

    public function testOrgMayNotEditUnscopedSubWhenOwnScopeIsLimited(): void
    {
        $actor = $this->orgChef('org_actor', ['dep_a']);
        $target = $this->subOrgChef('sub_target', []);

        $this->assertDenied(403, fn () => $this->policy()->assertUpdateAllowed($actor, $target, [], []));
    }

    public function testOrgMayNotEditOrg(): void
    {
        $actor = $this->orgChef('org_actor', ['dep_a']);
        $target = $this->orgChef('org_target', ['dep_a1']);

        $this->assertDenied(403, fn () => $this->policy()->assertUpdateAllowed($actor, $target, [], []));
    }

    public function testSubMayNotEditOrg(): void
    {
        $actor = $this->subOrgChef('sub_actor', ['dep_a'], true);
        $target = $this->orgChef('org_target', ['dep_a1']);

        $this->assertDenied(403, fn () => $this->policy()->assertUpdateAllowed($actor, $target, [], []));
    }

    public function testSubMayNotEditSub(): void
    {
        $actor = $this->subOrgChef('sub_actor', ['dep_a'], true);
        $target = $this->subOrgChef('sub_target', ['dep_a1']);

        $this->assertDenied(403, fn () => $this->policy()->assertUpdateAllowed($actor, $target, [], []));
    }

    /**
     * AdminCapabilityChecker: Scope nur aus Department-Roots (organisation_ids = []) bedeutet
     * Organisationszugriff null = alle. Der Sub hat damit mehr Org-Zugriff als ein auf org_1
     * begrenzter Org — auch wenn sein Root in org_1 liegt.
     */
    public function testOrganisationScopedOrgMayNotEditRootOnlySubEvenWhenRootIsInsideOrganisation(): void
    {
        $actor = $this->orgChef('org_actor', [], ['org_1']);
        $target = $this->subOrgChef('sub_target', ['dep_a1']);

        $this->assertDenied(403, fn () => $this->policy()->assertUpdateAllowed($actor, $target, [], []));
    }

    public function testOrganisationScopedOrgMayEditSubWithSameOrganisationAndRootInside(): void
    {
        $actor = $this->orgChef('org_actor', [], ['org_1']);
        $target = $this->subOrgChef('sub_target', ['dep_a1'], false, ['org_1']);

        $this->policy()->assertUpdateAllowed($actor, $target, ['nickname' => 'X'], []);
        $this->addToAssertionCount(1);
    }

    public function testOrganisationScopedOrgMayEditSubScopedToSameOrganisation(): void
    {
        $actor = $this->orgChef('org_actor', [], ['org_1']);
        $target = $this->subOrgChef('sub_target', [], false, ['org_1']);

        $this->policy()->assertUpdateAllowed($actor, $target, [], []);
        $this->addToAssertionCount(1);
    }

    public function testOrganisationScopedOrgMayNotEditSubWithRootOutsideOrganisation(): void
    {
        $actor = $this->orgChef('org_actor', [], ['org_1']);
        $target = $this->subOrgChef('sub_target', ['dep_b']);

        $this->assertDenied(403, fn () => $this->policy()->assertUpdateAllowed($actor, $target, [], []));
    }

    public function testOrganisationScopedOrgMayNotEditSubScopedToOtherOrganisation(): void
    {
        $actor = $this->orgChef('org_actor', [], ['org_1']);
        $target = $this->subOrgChef('sub_target', [], false, ['org_2']);

        $this->assertDenied(403, fn () => $this->policy()->assertUpdateAllowed($actor, $target, [], []));
    }

    public function testOrgWithOrganisationAndRootScopeMayNotEditSubWithWholeOrganisation(): void
    {
        $actor = $this->orgChef('org_actor', ['dep_a1'], ['org_1']);
        $target = $this->subOrgChef('sub_target', [], false, ['org_1']);

        $this->assertDenied(403, fn () => $this->policy()->assertUpdateAllowed($actor, $target, [], []));
    }

    public function testOrgWithOrganisationAndRootScopeMayEditSubInsideRoot(): void
    {
        $actor = $this->orgChef('org_actor', ['dep_a'], ['org_1']);
        $target = $this->subOrgChef('sub_target', ['dep_a1'], false, ['org_1']);

        $this->policy()->assertUpdateAllowed($actor, $target, [], []);
        $this->addToAssertionCount(1);
    }

    public function testOrganisationScopedOrgMembershipChangesFollowOrganisation(): void
    {
        $actor = $this->orgChef('org_actor', [], ['org_1']);
        $target = $this->normalUser('user_in');
        $existing = [$this->membership($target, 'dep_a1', 'u', true)];

        $policy = $this->policy();
        $policy->assertUpdateAllowed($actor, $target, [
            'memberships' => [
                ['department_id' => 'dep_a1', 'role' => 'u', 'is_primary' => true],
                ['department_id' => 'dep_a', 'role' => 'mw', 'is_primary' => false],
            ],
        ], $existing);

        $this->assertDenied(403, fn () => $policy->assertUpdateAllowed($actor, $target, [
            'memberships' => [
                ['department_id' => 'dep_a1', 'role' => 'u', 'is_primary' => true],
                ['department_id' => 'dep_b', 'role' => 'mw', 'is_primary' => false],
            ],
        ], $existing));
    }

    public function testNormalUserOutsideScopeIsDenied(): void
    {
        $actor = $this->orgChef('org_actor', ['dep_a']);
        $target = $this->normalUser('user_out');
        $existing = [$this->membership($target, 'dep_b', 'mw', true)];

        $this->assertDenied(403, fn () => $this->policy()->assertUpdateAllowed($actor, $target, [], $existing));
    }

    public function testNormalUserWithOneMembershipInScopeIsAllowed(): void
    {
        $actor = $this->orgChef('org_actor', ['dep_a']);
        $target = $this->normalUser('user_mixed');
        $existing = [
            $this->membership($target, 'dep_b', 'mw', true),
            $this->membership($target, 'dep_a1', 'u', false),
        ];

        $this->policy()->assertUpdateAllowed($actor, $target, ['nickname' => 'X'], $existing);
        $this->addToAssertionCount(1);
    }

    public function testUnassignedNormalUserIsAllowed(): void
    {
        $actor = $this->orgChef('org_actor', ['dep_a']);
        $target = $this->normalUser('user_free');

        $this->policy()->assertUpdateAllowed($actor, $target, [
            'memberships' => [['department_id' => 'dep_a1', 'role' => 'u', 'is_primary' => true]],
        ], []);
        $this->addToAssertionCount(1);
    }

    public function testAddingMembershipOutsideScopeIsDenied(): void
    {
        $actor = $this->orgChef('org_actor', ['dep_a']);
        $target = $this->normalUser('user_in');
        $existing = [$this->membership($target, 'dep_a1', 'u', true)];

        $this->assertDenied(403, fn () => $this->policy()->assertUpdateAllowed($actor, $target, [
            'memberships' => [
                ['department_id' => 'dep_a1', 'role' => 'u', 'is_primary' => true],
                ['department_id' => 'dep_b', 'role' => 'mw', 'is_primary' => false],
            ],
        ], $existing));
    }

    public function testRemovingMembershipOutsideScopeIsDenied(): void
    {
        $actor = $this->orgChef('org_actor', ['dep_a']);
        $target = $this->normalUser('user_mixed');
        $existing = [
            $this->membership($target, 'dep_a1', 'u', true),
            $this->membership($target, 'dep_b', 'mw', false),
        ];

        $this->assertDenied(403, fn () => $this->policy()->assertUpdateAllowed($actor, $target, [
            'memberships' => [['department_id' => 'dep_a1', 'role' => 'u', 'is_primary' => true]],
        ], $existing));
    }

    public function testChangingRoleOutsideScopeIsDenied(): void
    {
        $actor = $this->orgChef('org_actor', ['dep_a']);
        $target = $this->normalUser('user_mixed');
        $existing = [
            $this->membership($target, 'dep_a1', 'u', true),
            $this->membership($target, 'dep_b', 'u', false),
        ];

        $this->assertDenied(403, fn () => $this->policy()->assertUpdateAllowed($actor, $target, [
            'memberships' => [
                ['department_id' => 'dep_a1', 'role' => 'u', 'is_primary' => true],
                ['department_id' => 'dep_b', 'role' => 'mw', 'is_primary' => false],
            ],
        ], $existing));
    }

    public function testUnchangedMembershipOutsideScopeIsKept(): void
    {
        $actor = $this->orgChef('org_actor', ['dep_a']);
        $target = $this->normalUser('user_mixed');
        $existing = [
            $this->membership($target, 'dep_b', 'mw', true),
            $this->membership($target, 'dep_a1', 'u', false),
        ];

        $this->policy()->assertUpdateAllowed($actor, $target, [
            'memberships' => [
                ['department_id' => 'dep_b', 'role' => 'MW', 'is_primary' => true],
                ['department_id' => 'dep_a1', 'role' => 'dc', 'is_primary' => false],
            ],
        ], $existing);
        $this->addToAssertionCount(1);
    }

    public function testUnchangedEmailIsAllowedForNonSuperadmin(): void
    {
        $actor = $this->orgChef('org_actor', ['dep_a']);
        $target = $this->normalUser('user_free', 'Person@Example.ch');

        $policy = $this->policy();
        $policy->assertUpdateAllowed($actor, $target, ['email' => ' person@example.ch '], []);
        self::assertNull($policy->requestedEmailChange($target, ['email' => ' person@example.ch ']));
    }

    public function testChangedEmailByNonSuperadminIsDenied(): void
    {
        $actor = $this->orgChef('org_actor', ['dep_a']);
        $target = $this->normalUser('user_free', 'person@example.ch');

        $this->assertDenied(403, fn () => $this->policy()->assertUpdateAllowed(
            $actor,
            $target,
            ['email' => 'attacker@example.ch'],
            []
        ));
    }

    public function testInvalidStateIsDeniedForNonSuperadmin(): void
    {
        $actor = $this->orgChef('org_actor', ['dep_a']);
        $target = $this->normalUser('user_free');

        $this->assertDenied(400, fn () => $this->policy()->assertUpdateAllowed($actor, $target, ['state' => 'root'], []));
    }

    public function testInvalidStateIsDeniedForSuperadmin(): void
    {
        $actor = $this->superAdmin('sa_actor');
        $target = $this->normalUser('user_free');

        $this->assertDenied(400, fn () => $this->policy()->assertUpdateAllowed($actor, $target, ['state' => 'root'], []));
    }

    public function testValidStatesAreAccepted(): void
    {
        $actor = $this->superAdmin('sa_actor');
        $target = $this->normalUser('user_free');

        foreach (AdminUserUpdatePolicy::ALLOWED_STATES as $state) {
            $this->policy()->assertUpdateAllowed($actor, $target, ['state' => $state], []);
        }
        $this->addToAssertionCount(1);
    }

    public function testSuperadminManagesAcrossOrganisations(): void
    {
        $actor = $this->superAdmin('sa_actor');
        $orgTarget = $this->orgChef('org_target', ['dep_b']);
        $userTarget = $this->normalUser('user_out');
        $existing = [$this->membership($userTarget, 'dep_b', 'u', true)];

        $policy = $this->policy();
        $policy->assertUpdateAllowed($actor, $orgTarget, ['state' => 'active'], []);
        $policy->assertUpdateAllowed($actor, $userTarget, [
            'memberships' => [
                ['department_id' => 'dep_a', 'role' => 'mw', 'is_primary' => true],
            ],
        ], $existing);
        $this->addToAssertionCount(1);
    }

    public function testSuperadminEmailChangeIsReportedAsPendingRequestNotDenied(): void
    {
        $actor = $this->superAdmin('sa_actor');
        $target = $this->normalUser('user_free', 'person@example.ch');
        $data = ['email' => 'Neu@Example.ch'];

        $policy = $this->policy();
        $policy->assertUpdateAllowed($actor, $target, $data, []);
        self::assertSame('neu@example.ch', $policy->requestedEmailChange($target, $data));
        self::assertSame('person@example.ch', $target->getProfile()?->getEmail());
    }

    public function testSuperadminTargetIsDeniedEvenForSuperadmin(): void
    {
        $actor = $this->superAdmin('sa_actor');
        $target = $this->superAdmin('sa_target');

        $this->assertDenied(403, fn () => $this->policy()->assertUpdateAllowed($actor, $target, [], []));
    }

    private function policy(): AdminUserUpdatePolicy
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $departments = [
            $this->department('dep_a', 'org_1', null),
            $this->department('dep_a1', 'org_1', 'dep_a'),
            $this->department('dep_b', 'org_2', null),
        ];
        $departmentRepository = $this->createMock(EntityRepository::class);
        $departmentRepository->method('findAll')->willReturn($departments);
        // AdminCapabilityDepartmentScope::departmentIdsForOrganisations (WHERE d.organisationId IN (:orgIds)).
        $departmentRepository->method('createQueryBuilder')->willReturnCallback(
            fn (): QueryBuilder => $this->departmentIdsByOrganisationQueryBuilder($departments)
        );

        $membershipRepository = $this->createMock(EntityRepository::class);
        $membershipRepository->method('findBy')->willReturnCallback(
            fn (array $criteria): array => $this->membershipsByUserId[$criteria['userId'] ?? ''] ?? []
        );

        $entityManager->method('getRepository')->willReturnCallback(
            static fn (string $class) => $class === Department::class ? $departmentRepository : $membershipRepository
        );

        return new AdminUserUpdatePolicy(
            new AdminCapabilityChecker($entityManager, new AdminCapabilityDepartmentScope($entityManager))
        );
    }

    /**
     * @param list<Department> $departments
     */
    private function departmentIdsByOrganisationQueryBuilder(array $departments): QueryBuilder
    {
        $organisationIds = null;
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnCallback(
            function (string $key, mixed $value) use (&$organisationIds, $queryBuilder): QueryBuilder {
                if ($key === 'orgIds') {
                    $organisationIds = $value;
                }

                return $queryBuilder;
            }
        );
        $queryBuilder->method('getQuery')->willReturnCallback(function () use (&$organisationIds, $departments): Query {
            $query = $this->createMock(Query::class);
            $query->method('getSingleColumnResult')->willReturn(array_values(array_map(
                static fn (Department $d): string => $d->getId(),
                array_filter(
                    $departments,
                    static fn (Department $d): bool => $organisationIds === null
                        || \in_array($d->getOrganisationId(), $organisationIds, true)
                )
            )));

            return $query;
        });

        return $queryBuilder;
    }

    private function assertDenied(int $expectedStatus, callable $call): void
    {
        try {
            $call();
            self::fail('Expected AdminUserUpdateDeniedException');
        } catch (AdminUserUpdateDeniedException $e) {
            self::assertSame($expectedStatus, $e->statusCode);
        }
    }

    private function superAdmin(string $id): User
    {
        return $this->user($id, ['ROLE_USER', 'ROLE_SUPERADMIN'], null);
    }

    /**
     * @param list<string> $departmentRootIds
     * @param list<string> $organisationIds
     */
    private function orgChef(string $id, array $departmentRootIds, array $organisationIds = []): User
    {
        return $this->user($id, ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'], [
            'users' => ['global_manage' => true],
            'scope' => ['organisation_ids' => $organisationIds, 'department_root_ids' => $departmentRootIds],
        ]);
    }

    /**
     * @param list<string> $departmentRootIds
     * @param list<string> $organisationIds
     */
    private function subOrgChef(
        string $id,
        array $departmentRootIds,
        bool $globalManage = false,
        array $organisationIds = [],
    ): User {
        return $this->user($id, ['ROLE_USER', 'ROLE_SUBORGCHEF'], [
            'users' => ['global_manage' => $globalManage],
            'scope' => ['organisation_ids' => $organisationIds, 'department_root_ids' => $departmentRootIds],
        ]);
    }

    private function normalUser(string $id, string $email = 'user@example.ch'): User
    {
        return $this->user($id, ['ROLE_USER'], null, $email);
    }

    /**
     * @param list<string>              $roles
     * @param array<string, mixed>|null $capabilities
     */
    private function user(string $id, array $roles, ?array $capabilities, string $email = 'x@example.ch'): User
    {
        $profile = new Profile();
        $profile->setId('p_' . $id);
        $profile->setEmail($email);
        $profile->setRoles($roles);
        $profile->setAdminCapabilities($capabilities);

        $user = new User();
        $user->setId($id);
        $user->setProfileId('p_' . $id);
        $user->setProfile($profile);

        return $user;
    }

    private function membership(User $user, string $departmentId, string $role, bool $isPrimary): Membership
    {
        $membership = new Membership();
        $membership->setUserId((string) $user->getId());
        $membership->setDepartmentId($departmentId);
        $membership->setRole($role);
        $membership->setIsPrimary($isPrimary);
        $this->membershipsByUserId[(string) $user->getId()][] = $membership;

        return $membership;
    }

    private function department(string $id, string $organisationId, ?string $parentId): Department
    {
        $department = new Department();
        $department->setId($id);
        $department->setOrganisationId($organisationId);
        $department->setParentId($parentId);

        return $department;
    }
}
