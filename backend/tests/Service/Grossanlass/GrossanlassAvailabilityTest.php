<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassAvailability;
use PHPUnit\Framework\TestCase;

final class GrossanlassAvailabilityTest extends TestCase
{
    /** @return array{id: string, from: \DateTimeImmutable, to: \DateTimeImmutable, qty: int} */
    private static function slot(string $id, string $from, string $to, int $qty): array
    {
        return ['id' => $id, 'from' => new \DateTimeImmutable($from), 'to' => new \DateTimeImmutable($to), 'qty' => $qty];
    }

    public function testTwoOverlappingSlotsAddUp(): void
    {
        $result = GrossanlassAvailability::peak([
            self::slot('a', '2027-06-01 08:00', '2027-06-01 12:00', 6),
            self::slot('b', '2027-06-01 10:00', '2027-06-01 14:00', 5),
        ]);

        self::assertSame(11, $result['peak']);
        self::assertEqualsCanonicalizing(['a', 'b'], $result['ids']);
    }

    public function testThreeOverlappingSlotsAreOverbookedEvenIfEveryPairFits(): void
    {
        // Charge 10: jedes Paar braucht 8, alle drei gleichzeitig 12.
        $slots = [
            self::slot('a', '2027-06-01 08:00', '2027-06-01 16:00', 4),
            self::slot('b', '2027-06-01 09:00', '2027-06-01 17:00', 4),
            self::slot('c', '2027-06-01 10:00', '2027-06-01 18:00', 4),
        ];

        self::assertSame(12, GrossanlassAvailability::peak($slots)['peak']);
        self::assertGreaterThan(10, GrossanlassAvailability::peak($slots)['peak']);
        $groups = GrossanlassAvailability::overbooked($slots, 10);
        self::assertCount(1, $groups);
        self::assertSame(['a', 'b', 'c'], $groups[0]['ids']);
    }

    public function testTouchingWindowsDoNotOverlap(): void
    {
        $result = GrossanlassAvailability::peak([
            self::slot('a', '2027-06-01 08:00', '2027-06-01 12:00', 10),
            self::slot('b', '2027-06-01 12:00', '2027-06-01 16:00', 10),
        ]);

        self::assertSame(10, $result['peak']);
        self::assertCount(1, $result['ids']);
    }

    public function testPartialQuantitiesFitWhenTheyAreNotAtTheSameTime(): void
    {
        $slots = [
            self::slot('a', '2027-06-01 08:00', '2027-06-01 10:00', 8),
            self::slot('b', '2027-06-01 09:00', '2027-06-01 12:00', 2),
            self::slot('c', '2027-06-01 11:00', '2027-06-01 13:00', 8),
        ];

        // a+b = 10 und b+c = 10: nie 18, a und c berühren sich nicht
        self::assertSame(10, GrossanlassAvailability::peak($slots)['peak']);
        self::assertSame([], GrossanlassAvailability::overbooked($slots, 10));
    }

    public function testPeakIsLimitedToTheWindowOfTheNewBooking(): void
    {
        $others = [self::slot('a', '2027-06-01 08:00', '2027-06-01 10:00', 9)];
        $new = self::slot('neu', '2027-06-01 12:00', '2027-06-01 14:00', 5);

        $result = GrossanlassAvailability::peak([...$others, $new], $new['from'], $new['to']);

        self::assertSame(5, $result['peak']);
    }

    public function testUniqueObjectsAllowOnlyOneAtATime(): void
    {
        self::assertSame(1, GrossanlassAvailability::capacity(3, true));
        $slots = [
            self::slot('a', '2027-06-01 08:00', '2027-06-01 12:00', 1),
            self::slot('b', '2027-06-01 11:00', '2027-06-01 13:00', 1),
        ];

        self::assertCount(1, GrossanlassAvailability::overbooked($slots, 1));
        self::assertSame([], GrossanlassAvailability::overbooked([$slots[0]], 1));
    }

    public function testCapacityOfPlainStockIsTheQuantity(): void
    {
        self::assertSame(10, GrossanlassAvailability::capacity(10, false));
        self::assertSame(0, GrossanlassAvailability::capacity(-3, false));
    }
}
