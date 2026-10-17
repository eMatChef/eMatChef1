<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassStaffingTime;
use PHPUnit\Framework\TestCase;

final class GrossanlassStaffingTimeTest extends TestCase
{
    private static function t(string $hm): \DateTime
    {
        return new \DateTime('2027-06-01 ' . $hm . ':00');
    }

    /** @return array{0: \DateTime, 1: \DateTime} */
    private static function w(string $from, string $to): array
    {
        return [self::t($from), self::t($to)];
    }

    public function testWindowsAreHalfOpen(): void
    {
        self::assertFalse(GrossanlassStaffingTime::overlaps(self::t('08:00'), self::t('10:00'), self::t('10:00'), self::t('12:00')));
        self::assertTrue(GrossanlassStaffingTime::overlaps(self::t('08:00'), self::t('10:01'), self::t('10:00'), self::t('12:00')));
    }

    public function testMergeJoinsOverlappingAndAdjacentWindows(): void
    {
        $merged = GrossanlassStaffingTime::merge([self::w('12:00', '14:00'), self::w('08:00', '10:00'), self::w('10:00', '11:00'), self::w('09:00', '10:30')]);
        self::assertCount(2, $merged);
        self::assertSame('08:00', $merged[0][0]->format('H:i'));
        self::assertSame('11:00', $merged[0][1]->format('H:i'));
        self::assertSame('12:00', $merged[1][0]->format('H:i'));
    }

    public function testIsCoveredNeedsOneContinuousAvailability(): void
    {
        $availability = [self::w('08:00', '10:00'), self::w('10:00', '12:00'), self::w('13:00', '17:00')];
        self::assertTrue(GrossanlassStaffingTime::isCovered($availability, self::t('09:00'), self::t('11:30')), 'direkt anschliessende Fenster gelten zusammen');
        self::assertFalse(GrossanlassStaffingTime::isCovered($availability, self::t('11:00'), self::t('14:00')), 'Lücke 12–13 Uhr');
        self::assertFalse(GrossanlassStaffingTime::isCovered([], self::t('09:00'), self::t('10:00')), 'ohne Verfügbarkeit nie gedeckt');
    }

    public function testPartTimeAssignmentsCoverOneSlotOverTime(): void
    {
        // Eine Person von 08–12 gebraucht: A 08–10, B 10–12 decken zusammen vollständig.
        $c = GrossanlassStaffingTime::coverage(1, self::t('08:00'), self::t('12:00'), [self::w('08:00', '10:00'), self::w('10:00', '12:00')]);
        self::assertSame(1.0, $c['fill_ratio']);
        self::assertSame([], $c['gaps']);
        self::assertSame(1, $c['min_assigned']);
        self::assertFalse($c['overbooked']);
        self::assertCount(1, $c['segments'], 'gleiche Besetzung wird zu einem Abschnitt zusammengefasst');
    }

    public function testGapsAreTimeDependent(): void
    {
        // Zwei Personen 08–12 gebraucht: eine ganztags, eine nur 08–10 → 10–12 fehlt eine.
        $c = GrossanlassStaffingTime::coverage(2, self::t('08:00'), self::t('12:00'), [self::w('08:00', '12:00'), self::w('08:00', '10:00')]);
        self::assertSame(0.75, $c['fill_ratio']);
        self::assertSame(1, $c['min_assigned']);
        self::assertCount(1, $c['gaps']);
        self::assertSame(['from' => '2027-06-01T10:00:00', 'to' => '2027-06-01T12:00:00', 'assigned' => 1, 'missing' => 1], $c['gaps'][0]);
        self::assertSame(240, $c['minutes']);
    }

    public function testOverbookingAndClipping(): void
    {
        $c = GrossanlassStaffingTime::coverage(1, self::t('08:00'), self::t('10:00'), [self::w('07:00', '10:00'), self::w('09:00', '11:00')]);
        self::assertTrue($c['overbooked']);
        self::assertSame(2, $c['peak_assigned']);
        self::assertSame(1.0, $c['fill_ratio'], 'Überbesetzung erhöht die Deckung nicht über 100 %');
    }

    public function testNoAssignmentsAndZeroRequirement(): void
    {
        $empty = GrossanlassStaffingTime::coverage(3, self::t('08:00'), self::t('10:00'), []);
        self::assertSame(0.0, $empty['fill_ratio']);
        self::assertSame(3, $empty['gaps'][0]['missing']);
        $none = GrossanlassStaffingTime::coverage(0, self::t('08:00'), self::t('10:00'), []);
        self::assertNull($none['fill_ratio']);
        self::assertSame([], $none['gaps']);
    }

    public function testParseRejectsGarbageAndEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GrossanlassStaffingTime::parse('kein datum', 'starts_at');
    }

    public function testAssertWindowRejectsEmptyWindow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GrossanlassStaffingTime::assertWindow(self::t('10:00'), self::t('10:00'));
    }
}
