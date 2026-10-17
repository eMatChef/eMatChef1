<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassBudgetAreaService;
use PHPUnit\Framework\TestCase;

final class GrossanlassBudgetAreaServiceTest extends TestCase
{
    public function testEachStageIsCountedOnceAndRestRefersToCommittedAmounts(): void
    {
        $f = GrossanlassBudgetAreaService::summarise(1000.0, ['bedarf' => 100.0, 'offerte' => 200.0, 'zusage' => 150.0, 'bestellt' => 300.0, 'effektiv' => 50.0], 5);

        self::assertSame(800.0, $f['expected_chf']);
        self::assertSame(500.0, $f['committed_chf']);
        self::assertSame([200.0, 150.0, 300.0, 50.0], [$f['offers_chf'], $f['commitments_chf'], $f['ordered_chf'], $f['actual_chf']]);
        self::assertSame(500.0, $f['remaining_chf']);
        self::assertFalse($f['over_budget']);
        self::assertSame(5, $f['position_count']);
    }

    public function testOverrunIsReportedForExpectedAndCommittedAmounts(): void
    {
        $f = GrossanlassBudgetAreaService::summarise(500.0, ['offerte' => 400.0, 'bestellt' => 300.0]);

        self::assertTrue($f['over_budget']);
        self::assertSame(200.0, $f['overrun_expected_chf']);
        self::assertSame(0.0, $f['overrun_committed_chf']);
        self::assertSame(200.0, $f['remaining_chf']);

        $g = GrossanlassBudgetAreaService::summarise(250.0, ['bestellt' => 300.0]);
        self::assertSame([50.0, 50.0, -50.0], [$g['overrun_committed_chf'], $g['overrun_expected_chf'], $g['remaining_chf']]);
    }

    public function testTotalIsTheSumOfBothAreas(): void
    {
        $material = ['bedarf' => 10.0, 'zusage' => 20.0, 'bestellt' => 30.5];
        $logistik = ['offerte' => 5.25, 'bestellt' => 100.0, 'effektiv' => 40.0];
        $both = [];
        foreach (GrossanlassBudgetAreaService::STAGES as $stage) {
            $both[$stage] = ($material[$stage] ?? 0.0) + ($logistik[$stage] ?? 0.0);
        }
        $total = GrossanlassBudgetAreaService::summarise(300.0 + 200.0, $both);
        $m = GrossanlassBudgetAreaService::summarise(300.0, $material);
        $l = GrossanlassBudgetAreaService::summarise(200.0, $logistik);

        self::assertSame(round($m['expected_chf'] + $l['expected_chf'], 2), $total['expected_chf']);
        self::assertSame(round($m['committed_chf'] + $l['committed_chf'], 2), $total['committed_chf']);
        self::assertSame(500.0, $total['planned_chf']);
    }

    public function testWithoutLimitAnyExpectedCostCountsAsOverBudget(): void
    {
        self::assertTrue(GrossanlassBudgetAreaService::summarise(0.0, ['bedarf' => 10.0])['over_budget']);
        self::assertFalse(GrossanlassBudgetAreaService::summarise(0.0, [])['over_budget']);
    }
}
