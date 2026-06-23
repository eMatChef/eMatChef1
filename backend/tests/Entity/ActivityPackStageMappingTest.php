<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Activity;
use PHPUnit\Framework\TestCase;

/**
 * Parität Frontend packStageQuantities — Status-Mapping für Legacy-Packliste.
 * (Logik spiegelt autoPackStageForStatus / workflowTargetStatusForStage.)
 */
final class ActivityPackStageMappingTest extends TestCase
{
    public function testLogisticsPackedAllowsOnlyTransportOut(): void
    {
        $targets = Activity::filterTransitionTargets(
            Activity::STATUS_PACKED,
            'camp',
            Activity::STATUS_TRANSITIONS[Activity::STATUS_PACKED],
        );

        self::assertSame(['transport_out', 'packing', 'cancelled'], $targets);
    }

    public function testLogisticsTransportOutAllowsAtEvent(): void
    {
        $targets = Activity::filterTransitionTargets(
            Activity::STATUS_TRANSPORT_OUT,
            'camp',
            Activity::STATUS_TRANSITIONS[Activity::STATUS_TRANSPORT_OUT],
        );

        self::assertContains(Activity::STATUS_AT_EVENT, $targets);
    }

    public function testQuickPackedAllowsDirectAtEvent(): void
    {
        $targets = Activity::filterTransitionTargets(
            Activity::STATUS_PACKED,
            'activity',
            Activity::STATUS_TRANSITIONS[Activity::STATUS_PACKED],
        );

        self::assertContains(Activity::STATUS_AT_EVENT, $targets);
        self::assertNotContains(Activity::STATUS_TRANSPORT_OUT, $targets);
    }
}
