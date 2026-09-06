<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassQuarterHour;
use PHPUnit\Framework\TestCase;

final class GrossanlassQuarterHourTest extends TestCase
{
    public function testSnapsToNearestQuarter(): void
    {
        $dt = new \DateTime('2027-08-20 17:52:00');
        $snapped = GrossanlassQuarterHour::snap($dt);

        self::assertSame('2027-08-20 17:45:00', $snapped->format('Y-m-d H:i:s'));
        self::assertSame('2027-08-20 17:52:00', $dt->format('Y-m-d H:i:s'));
    }

    public function testLeavesQuarterUnchanged(): void
    {
        $dt = new \DateTime('2027-08-20 08:15:00');
        $snapped = GrossanlassQuarterHour::snap($dt);

        self::assertSame('2027-08-20 08:15:00', $snapped->format('Y-m-d H:i:s'));
    }
}
