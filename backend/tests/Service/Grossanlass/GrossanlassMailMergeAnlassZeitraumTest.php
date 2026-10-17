<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassMailMergeService;
use PHPUnit\Framework\TestCase;

class GrossanlassMailMergeAnlassZeitraumTest extends TestCase
{
    public function testEventPeriodSpansSetupToTeardown(): void
    {
        self::assertSame(
            '19. Mai bis 23. Juni 2027',
            GrossanlassMailMergeService::formatAnlassZeitraum(new \DateTime('2027-05-19 13:00'), new \DateTime('2027-06-23 12:00')),
        );
    }

    public function testPeriodAcrossYearsNamesBothYears(): void
    {
        self::assertSame(
            '30. Dezember 2026 bis 2. Januar 2027',
            GrossanlassMailMergeService::formatAnlassZeitraum(new \DateTime('2026-12-30 08:00'), new \DateTime('2027-01-02 17:00')),
        );
    }

    public function testSingleDayAndInvertedRange(): void
    {
        self::assertSame('4. Juni 2027', GrossanlassMailMergeService::formatAnlassZeitraum(new \DateTime('2027-06-04 16:00'), new \DateTime('2027-06-04 23:00')));
        self::assertSame('4. Juni 2027', GrossanlassMailMergeService::formatAnlassZeitraum(new \DateTime('2027-06-04 16:00'), new \DateTime('2027-06-01 10:00')));
    }

    public function testPlaceholderIsBuiltinAndZeitraumtextStaysSeparate(): void
    {
        self::assertContains('ANLASSZEITRAUM', GrossanlassMailMergeService::BUILTIN_PLACEHOLDERS);
        self::assertContains('ZEITRAUMTEXT', GrossanlassMailMergeService::BUILTIN_PLACEHOLDERS);
    }
}
