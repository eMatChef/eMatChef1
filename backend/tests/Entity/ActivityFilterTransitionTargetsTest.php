<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Activity;
use PHPUnit\Framework\TestCase;

final class ActivityFilterTransitionTargetsTest extends TestCase
{
    public function testLogisticsPackedSkipsDirectAtEvent(): void
    {
        $targets = Activity::filterTransitionTargets(
            Activity::STATUS_PACKED,
            'camp',
            Activity::STATUS_TRANSITIONS[Activity::STATUS_PACKED],
        );

        self::assertContains(Activity::STATUS_TRANSPORT_OUT, $targets);
        self::assertNotContains(Activity::STATUS_AT_EVENT, $targets);
    }

    public function testQuickPackedSkipsTransportOut(): void
    {
        $targets = Activity::filterTransitionTargets(
            Activity::STATUS_PACKED,
            'activity',
            Activity::STATUS_TRANSITIONS[Activity::STATUS_PACKED],
        );

        self::assertContains(Activity::STATUS_AT_EVENT, $targets);
        self::assertNotContains(Activity::STATUS_TRANSPORT_OUT, $targets);
    }

    public function testLogisticsAtEventSkipsDirectReturned(): void
    {
        $targets = Activity::filterTransitionTargets(
            Activity::STATUS_AT_EVENT,
            'event',
            Activity::STATUS_TRANSITIONS[Activity::STATUS_AT_EVENT],
        );

        self::assertContains(Activity::STATUS_TRANSPORT_BACK, $targets);
        self::assertNotContains(Activity::STATUS_RETURNED, $targets);
    }

    public function testReturnedSkipsDirectCompleted(): void
    {
        $targets = Activity::filterTransitionTargets(
            Activity::STATUS_RETURNED,
            'activity',
            Activity::STATUS_TRANSITIONS[Activity::STATUS_RETURNED],
        );

        self::assertContains(Activity::STATUS_STORING, $targets);
        self::assertNotContains(Activity::STATUS_COMPLETED, $targets);
    }
}
