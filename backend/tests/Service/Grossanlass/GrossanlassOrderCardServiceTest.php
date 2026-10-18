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
            ['group_id' => 'g1', 'assignee' => 'u1', 'starts_at' => new \DateTime('2027-06-10 08:00'), 'duration' => 60, 'status' => 'done'],
            ['group_id' => 'g1', 'assignee' => 'u1', 'starts_at' => new \DateTime('2027-06-11 08:00'), 'duration' => 60],
            ['group_id' => 'g1', 'assignee' => null, 'starts_at' => null, 'duration' => null],
        ];

        $cards = GrossanlassOrderCardService::summarize(['g1', 'g2'], $items, $tasks, $now, ['g1' => 4], ['g1' => ['u1', 'u2']]);

        self::assertSame(['items' => 2, 'covered_items' => 1, 'at_place_items' => 1], $cards[0]['material']);
        self::assertSame(['total' => 3, 'scheduled' => 2, 'done' => 1, 'planned' => 1, 'in_window' => 0, 'overdue' => 0], $cards[0]['tasks']);
        self::assertSame(['need' => 4, 'assigned' => 2], $cards[0]['staffing']);
        self::assertSame(0, $cards[1]['material']['items']);
        self::assertSame(0, $cards[1]['tasks']['total']);
        self::assertSame(['need' => null, 'assigned' => 0], $cards[1]['staffing']);
    }

    public function testTaskTimingFollowsBusinessClock(): void
    {
        $tasks = [
            ['group_id' => 'g1', 'assignee' => null, 'starts_at' => new \DateTime('2027-05-10 08:00'), 'duration' => 120, 'status' => 'open'],
            ['group_id' => 'g1', 'assignee' => null, 'starts_at' => new \DateTime('2027-05-12 08:00'), 'duration' => 60, 'status' => 'done'],
            ['group_id' => 'g1', 'assignee' => null, 'starts_at' => null, 'duration' => null, 'status' => 'open'],
        ];
        $at = static fn (string $now): array => GrossanlassOrderCardService::summarize(['g1'], [], $tasks, new \DateTime($now))[0]['tasks'];

        // Demo-Uhr 04.04.2027: alles geplant, nichts überfällig
        self::assertSame(['total' => 3, 'scheduled' => 2, 'done' => 1, 'planned' => 1, 'in_window' => 0, 'overdue' => 0], $at('2027-04-04 09:00:00'));
        // während der Aufgabe
        self::assertSame(1, $at('2027-05-10 09:00:00')['in_window']);
        // nach dem Termin: offene Aufgabe überfällig, erledigte nie
        $after = $at('2027-06-01 09:00:00');
        self::assertSame(1, $after['overdue']);
        self::assertSame(0, $after['planned']);
        self::assertSame(1, $after['done']);
    }

    public function testAllTasksDoneDoesNotCloseOrder(): void
    {
        $tasks = [
            ['group_id' => 'open', 'assignee' => null, 'starts_at' => null, 'duration' => null, 'status' => 'done'],
            ['group_id' => 'review', 'assignee' => null, 'starts_at' => null, 'duration' => null, 'status' => 'done'],
            ['group_id' => 'accepted', 'assignee' => null, 'starts_at' => null, 'duration' => null, 'status' => 'done'],
        ];
        $cards = GrossanlassOrderCardService::summarize(
            ['open', 'review', 'accepted', 'none'],
            [],
            $tasks,
            new \DateTime('2027-06-10 12:00:00'),
            [],
            [],
            ['review' => 'review', 'accepted' => 'accepted'],
        );

        // Aufgabenfortschritt bleibt unabhängig; der Bauauftragsstatus kommt nur aus Fertigmeldung/Abnahme.
        self::assertSame(1, $cards[0]['tasks']['done']);
        self::assertSame(['open', 'review', 'accepted', 'open'], array_column($cards, 'order_status'));
    }
}
