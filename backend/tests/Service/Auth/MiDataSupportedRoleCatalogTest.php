<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Service\Auth\MiDataSupportedRoleCatalog;
use PHPUnit\Framework\TestCase;

final class MiDataSupportedRoleCatalogTest extends TestCase
{
    public function testMapsTechnicalRoleClassesToMembershipRolesAndPriority(): void
    {
        self::assertSame('mw', MiDataSupportedRoleCatalog::membershipRole('Group::Abteilung::Materialwart'));
        self::assertSame('dc', MiDataSupportedRoleCatalog::membershipRole('Group::Abteilung::Abteilungsleitung'));
        self::assertSame('Group::Abteilung', MiDataSupportedRoleCatalog::expectedGroupType('Group::Abteilung::Abteilungsleitung'));
        self::assertTrue(MiDataSupportedRoleCatalog::outranks('Group::Abteilung::Materialwart', 'Group::Abteilung::Abteilungsleitung'));
        self::assertFalse(MiDataSupportedRoleCatalog::isSupported('Group::Abteilung::AbteilungsleitungStv'));
        self::assertNull(MiDataSupportedRoleCatalog::membershipRole('Abteilungsleiter*in'));
    }
}
