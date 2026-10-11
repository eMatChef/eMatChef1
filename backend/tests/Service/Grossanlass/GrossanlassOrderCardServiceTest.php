<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassOrderCardService;
use PHPUnit\Framework\TestCase;

final class GrossanlassOrderCardServiceTest extends TestCase
{
    public function testSummarizeCountsMaterialTasksAndStaffing(): void
    {
        $now = new \DateTime('2027-06-10 12:00:00');
        $items = [
            ['group_id' => 'g1', 'required' => 4, 'open' => 0, 'at_place' => 4],
            ['group_id' => 'g1', 'required' => 2, 'open' => 2, 'at_place' => 0],
            ['group_id' => 'g1', 'required' => 0, 'open' => 0, 'at_place' => 0],
            ['group_id' => 'other', 'required' => 1, 'open' => 0, 'at_place' => 1],
        ];
        $tasks = [
            ['group_id' => 'g1', 'assignee' => 'u1', 'starts_at' => new \DateTime('2027-06-10 08:00'), 'duration' => 60],
            ['group_id' => 'g1', 'assignee' => 'u1', 'starts_at' => new \DateTime('2027-06-11 08:00'), 'duration' => 60],
            ['group_id' => 'g1', 'assignee' => null, 'starts_at' => null, 'duration' => null],
        ];

        $cards = GrossanlassOrderCardService::summarize(['g1', 'g2'], $items, $tasks, $now, ['g1' => 4], ['g1' => ['u1', 'u2']]);

        self::assertSame(['items' => 2, 'covered_items' => 1, 'at_place_items' => 1], $cards[0]['material']);
        self::assertSame(['total' => 3, 'scheduled' => 2, 'done' => 1], $cards[0]['tasks']);
        self::assertSame(['need' => 4, 'assigned' => 2], $cards[0]['staffing']);
        self::assertSame(0, $cards[1]['material']['items']);
        self::assertSame(0, $cards[1]['tasks']['total']);
        self::assertSame(['need' => null, 'assigned' => 0], $cards[1]['staffing']);
    }
}
