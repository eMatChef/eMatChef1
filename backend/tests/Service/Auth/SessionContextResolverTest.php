<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassConfig;
use App\Entity\Membership;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\Auth\SessionContextResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class SessionContextResolverTest extends TestCase
{
    public function testValidLastUsedDepartmentWithMembershipIsUsed(): void
    {
        $a = $this->department('dep_a');
        $b = $this->department('dep_b');
        $user = $this->user($b);

        $context = $this->resolve($user, [
            $this->membership($user, $a, 'mw', true),
            $this->membership($user, $b, 'u', false),
        ]);

        self::assertSame('dep_a', $context['primary_department']);
        self::assertSame('dep_b', $context['last_used_department']);
    }

    public function testLastUsedDepartmentWithoutMembershipFallsBackToPrimary(): void
    {
        $a = $this->department('dep_a');
        $b = $this->department('dep_b');
        $user = $this->user($this->department('dep_gone'));

        $context = $this->resolve($user, [
            $this->membership($user, $a, 'u', false),
            $this->membership($user, $b, 'mw', true),
        ]);

        self::assertSame('dep_b', $context['primary_department']);
        self::assertSame('dep_b', $context['last_used_department']);
    }

    public function testWithoutLastUsedDepartmentPrimaryIsUsed(): void
    {
        $a = $this->department('dep_a');
        $b = $this->department('dep_b');
        $user = $this->user(null);

        $context = $this->resolve($user, [
            $this->membership($user, $a, 'u', false),
            $this->membership($user, $b, 'mw', true),
        ]);

        self::assertSame('dep_b', $context['primary_department']);
        self::assertSame('dep_b', $context['last_used_department']);
    }

    public function testWithoutPrimaryTheFirstDepartmentIsUsed(): void
    {
        $a = $this->department('dep_a');
        $b = $this->department('dep_b');
        $user = $this->user(null);

        $context = $this->resolve($user, [
            $this->membership($user, $a, 'u', false),
            $this->membership($user, $b, 'mw', false),
        ]);

        self::assertSame('dep_a', $context['primary_department']);
        self::assertSame('dep_a', $context['last_used_department']);
    }

    /**
     * Bestehendes Verhalten: bei mehreren is_primary gewinnt die letzte.
     */
    public function testLastPrimaryMembershipWinsAsBefore(): void
    {
        $a = $this->department('dep_a');
        $b = $this->department('dep_b');
        $user = $this->user(null);

        $context = $this->resolve($user, [
            $this->membership($user, $a, 'u', true),
            $this->membership($user, $b, 'mw', true),
        ]);

        self::assertSame('dep_b', $context['primary_department']);
    }

    public function testWithoutMembershipsEverythingIsEmpty(): void
    {
        $user = $this->user($this->department('dep_gone'));

        self::assertSame([
            'departments' => [],
            'primary_department' => null,
            'last_used_department' => null,
        ], $this->resolve($user, []));
    }

    /**
     * Superadmin ohne Department: Backend behandelt ihn wie jeden User ohne Membership
     * (die Superadmin-Sonderbehandlung liegt im Frontend-Store).
     */
    public function testSuperadminWithoutDepartmentBehavesAsBefore(): void
    {
        $user = $this->user(null);
        $user->getProfile()?->setRoles(['ROLE_USER', 'ROLE_SUPERADMIN']);

        self::assertSame([
            'departments' => [],
            'primary_department' => null,
            'last_used_department' => null,
        ], $this->resolve($user, []));
    }

    public function testDepartmentEntriesKeepExistingFields(): void
    {
        $a = $this->department('dep_a');
        $user = $this->user(null);

        $context = $this->resolve($user, [$this->membership($user, $a, 'dc', true)]);

        self::assertSame([[
            'id' => 'dep_a',
            'name' => 'Department dep_a',
            'organisation_id' => 'org_1',
            'role' => 'dc',
            'is_primary' => true,
            'is_grossanlass' => false,
        ]], $context['departments']);
    }

    public function testGrossanlassDepartmentKeepsFlagAndConfig(): void
    {
        $ga = $this->department('dep_ga');
        $ga->setIsGrossanlass(true);
        $config = new DepartmentGrossanlassConfig();
        $config->setDepartment($ga);
        $config->setStatus('draft');
        $config->setStrukturModus('verschachtelt');
        $config->setPlannedEventStart(new \DateTime('2027-09-02T00:00:00+00:00'));
        $config->setPlannedEventEnd(new \DateTime('2027-09-04T23:59:59+00:00'));
        $config->setLocationText('Event Platz');
        $ga->setGrossanlassConfig($config);
        $user = $this->user(null);

        $context = $this->resolve($user, [$this->membership($user, $ga, 'cmw', true)]);

        $entry = $context['departments'][0];
        self::assertSame(
            ['id', 'name', 'organisation_id', 'role', 'is_primary', 'is_grossanlass', 'grossanlass_config'],
            array_keys($entry)
        );
        self::assertTrue($entry['is_grossanlass']);
        self::assertSame('draft', $entry['grossanlass_config']['status']);
        self::assertSame('verschachtelt', $entry['grossanlass_config']['struktur_modus']);
        self::assertSame('2027-09-02T00:00:00+00:00', $entry['grossanlass_config']['planned_event_start']);
        self::assertSame('2027-09-04T23:59:59+00:00', $entry['grossanlass_config']['planned_event_end']);
        self::assertSame('Event Platz', $entry['grossanlass_config']['location_text']);
    }

    public function testGrossanlassDepartmentWithoutConfigHasNoConfigKey(): void
    {
        $ga = $this->department('dep_ga');
        $ga->setIsGrossanlass(true);
        $user = $this->user(null);

        $entry = $this->resolve($user, [$this->membership($user, $ga, 'u', true)])['departments'][0];

        self::assertTrue($entry['is_grossanlass']);
        self::assertArrayNotHasKey('grossanlass_config', $entry);
    }

    /**
     * @param list<Membership> $memberships
     *
     * @return array{departments: list<array<string, mixed>>, primary_department: string|null, last_used_department: string|null}
     */
    private function resolve(User $user, array $memberships): array
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($memberships);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        foreach (['innerJoin', 'leftJoin', 'addSelect', 'where'] as $method) {
            $queryBuilder->method($method)->willReturnSelf();
        }
        $queryBuilder->expects(self::once())->method('setParameter')->with('userId', $user->getId())->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('createQueryBuilder')->with('m')->willReturn($queryBuilder);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(Membership::class)->willReturn($repository);

        return (new SessionContextResolver($entityManager))->resolve($user);
    }

    private function user(?Department $lastUsed): User
    {
        $profile = new Profile();
        $profile->setId('profile_1');
        $profile->setEmail('user@example.ch');
        $profile->setRoles(['ROLE_USER']);

        $user = new User();
        $user->setId('user_1');
        $user->setProfileId('profile_1');
        $user->setProfile($profile);
        $user->setLastUsedDepartment($lastUsed);

        return $user;
    }

    private function department(string $id): Department
    {
        $department = new Department();
        $department->setId($id);
        $department->setName('Department ' . $id);
        $department->setOrganisationId('org_1');

        return $department;
    }

    private function membership(User $user, Department $department, string $role, bool $isPrimary): Membership
    {
        $membership = new Membership();
        $membership->setUser($user);
        $membership->setDepartment($department);
        $membership->setRole($role);
        $membership->setIsPrimary($isPrimary);

        return $membership;
    }
}
