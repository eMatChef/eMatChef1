<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\DepartmentGrossanlassEinsatz as Einsatz;
use App\Entity\Group;
use PHPUnit\Framework\TestCase;

/** Freigaberegel der operativen Aufgabenübersicht: nur tatsächlich freigegebene Arbeiten und Fahraufträge. */
final class GrossanlassReleaseRuleTest extends TestCase
{
    /** @dataProvider buildStatuses */
    public function testBuildStatusReleaseForWork(?string $status, bool $released): void
    {
        $group = new Group();
        $group->setBuildStatus($status);
        self::assertSame($released, $group->isBuildReleased());
    }

    /** @return iterable<string, array{?string, bool}> */
    public static function buildStatuses(): iterable
    {
        yield 'unset' => [null, false];
        yield 'planned' => [Group::BUILD_STATUS_PLANNED, false];
        yield 'quoted' => [Group::BUILD_STATUS_QUOTED, false];
        yield 'aborted' => [Group::BUILD_STATUS_ABORTED, false];
        yield 'build' => [Group::BUILD_STATUS_BUILD, true];
        yield 'use' => [Group::BUILD_STATUS_USE, true];
        yield 'teardown' => [Group::BUILD_STATUS_TEARDOWN, true];
        yield 'done' => [Group::BUILD_STATUS_DONE, true];
    }

    /** @dataProvider tripStates */
    public function testTripReleaseForExecution(string $status, bool $tripReleased, bool $released): void
    {
        $row = (new Einsatz())->setDelivery(Einsatz::DELIVERY_TRIP)->setStatus($status);
        if ($tripReleased) {
            $row->setTripReleasedAt(new \DateTime());
        }
        self::assertSame($released, $row->isReleasedForExecution());
    }

    /** @return iterable<string, array{string, bool, bool}> */
    public static function tripStates(): iterable
    {
        yield 'planned' => [Einsatz::STATUS_PLANNED, false, false];
        yield 'pending approval' => [Einsatz::STATUS_PENDING, false, false];
        yield 'pending approval with stale release' => [Einsatz::STATUS_PENDING, true, false];
        yield 'planned and trip released' => [Einsatz::STATUS_PLANNED, true, true];
        yield 'issued' => [Einsatz::STATUS_ISSUED, false, true];
        yield 'returned' => [Einsatz::STATUS_RETURNED, false, true];
    }
}
