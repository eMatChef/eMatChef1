<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Service\Auth\HitobitoRole;
use PHPUnit\Framework\TestCase;

final class HitobitoRoleTest extends TestCase
{
    public function testRoleWithoutDatesIsActive(): void
    {
        self::assertTrue($this->role(null, null)->isActiveOn(new \DateTimeImmutable('2026-10-04')));
    }

    public function testRoleWithinDateRangeIsActive(): void
    {
        self::assertTrue($this->role('2026-01-01', '2026-12-31')->isActiveOn(new \DateTimeImmutable('2026-10-04')));
    }

    public function testRoleStartingTodayIsActive(): void
    {
        self::assertTrue($this->role('2026-10-04', null)->isActiveOn(new \DateTimeImmutable('2026-10-04')));
    }

    public function testRoleEndingTodayIsActive(): void
    {
        self::assertTrue($this->role(null, '2026-10-04')->isActiveOn(new \DateTimeImmutable('2026-10-04')));
    }

    public function testFutureRoleIsNotActive(): void
    {
        self::assertFalse($this->role('2026-10-05', null)->isActiveOn(new \DateTimeImmutable('2026-10-04')));
    }

    public function testExpiredRoleIsNotActive(): void
    {
        self::assertFalse($this->role(null, '2026-10-03')->isActiveOn(new \DateTimeImmutable('2026-10-04')));
    }

    public function testNormalizesJsonApiRoleFields(): void
    {
        $role = HitobitoRole::fromJsonApi([
            'type' => 'roles',
            'id' => 'role-123',
            'attributes' => [
                'person_id' => 42,
                'group_id' => 111,
                'type' => 'Group::Member',
                'start_on' => '2026-10-04',
                'end_on' => null,
            ],
        ]);

        self::assertSame('42', $role->personId);
        self::assertSame('111', $role->groupId);
        self::assertSame('Group::Member', $role->type);
        self::assertSame('2026-10-04', $role->startOn?->format('Y-m-d'));
        self::assertNull($role->endOn);
    }

    public function testNormalizesWithRolesUserinfoFields(): void
    {
        $role = HitobitoRole::fromUserInfo([
            'group_id' => 111,
            'group_name' => 'Test Group',
            'role' => 'Group::Member',
            'role_class' => 'Group::Member',
            'role_name' => 'Member',
            'permissions' => ['layer_and_below_read'],
        ], 'person-42');

        self::assertSame('person-42', $role->personId);
        self::assertSame('111', $role->groupId);
        self::assertSame('Group::Member', $role->type);
        self::assertSame('Group::Member', $role->roleClass);
        self::assertSame('Group::Member', $role->role);
        self::assertSame('Member', $role->roleName);
        self::assertSame('Test Group', $role->groupName);
        self::assertSame(['layer_and_below_read'], $role->permissions);
        self::assertNull($role->startOn);
        self::assertNull($role->endOn);
    }

    private function role(?string $startOn, ?string $endOn): HitobitoRole
    {
        return new HitobitoRole(
            'person-1',
            'group-1',
            'Group::Member',
            $startOn !== null ? new \DateTimeImmutable($startOn) : null,
            $endOn !== null ? new \DateTimeImmutable($endOn) : null,
        );
    }
}
