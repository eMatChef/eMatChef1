<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassBauprojektService;
use App\Service\Grossanlass\HelperNeedValidationException;
use PHPUnit\Framework\TestCase;

final class GrossanlassHelperNeedTest extends TestCase
{
    public function testFunctionsSplitTheTotalInsteadOfAddingToIt(): void
    {
        $need = GrossanlassBauprojektService::helperNeedFromRows([
            ['label' => '', 'count' => 8],
            ['label' => 'Elektriker', 'count' => 2],
            ['label' => 'Aufbauhelfer', 'count' => 4],
        ]);

        self::assertSame(8, $need['total']);
        self::assertSame(2, $need['unspecified']);
        self::assertCount(2, $need['functions']);
    }

    public function testTotalIsAtLeastTheSumOfFunctions(): void
    {
        $need = GrossanlassBauprojektService::helperNeedFromRows([
            ['label' => '', 'count' => 3],
            ['label' => 'Elektriker', 'count' => 5],
        ]);

        self::assertSame(5, $need['total']);
        self::assertSame(0, $need['unspecified']);
    }

    public function testNothingRecordedMeansZero(): void
    {
        self::assertSame(['total' => 0, 'functions' => [], 'unspecified' => 0], GrossanlassBauprojektService::helperNeedFromRows([]));
    }

    public function testTimeSeparatedTasksAreNotAdded(): void
    {
        $demand = GrossanlassBauprojektService::helperDemandOverTime([
            ['starts_at' => new \DateTime('2027-06-01 08:00'), 'duration' => 120, 'total' => 5],
            ['starts_at' => new \DateTime('2027-06-01 13:00'), 'duration' => 120, 'total' => 4],
        ]);

        self::assertSame(5, $demand['peak']);
        self::assertCount(2, $demand['timeline']);
    }

    public function testOverlappingTasksAddUpAndUndatedCountsAlways(): void
    {
        $demand = GrossanlassBauprojektService::helperDemandOverTime([
            ['starts_at' => new \DateTime('2027-06-01 08:00'), 'duration' => 120, 'total' => 5],
            ['starts_at' => new \DateTime('2027-06-01 09:00'), 'duration' => 120, 'total' => 4],
            ['starts_at' => null, 'duration' => null, 'total' => 2],
        ]);

        self::assertSame(11, $demand['peak']);
        self::assertSame(2, $demand['undated']);
    }

    public function testInputWithFunctionsAboveTotalIsRejected(): void
    {
        $this->expectException(HelperNeedValidationException::class);
        $this->expectExceptionMessage('übersteigen den Gesamtbedarf');
        $this->normalize(['total' => 3, 'functions' => [['label' => 'Elektriker', 'count' => 2], ['label' => 'Koch', 'count' => 2]]]);
    }

    public function testInputWithFunctionsWithinTotalIsKept(): void
    {
        $out = $this->normalize(['total' => 5, 'functions' => [['label' => 'Koch', 'count' => 5]]]);
        self::assertSame(5, $out['total']);
        self::assertSame([['label' => 'Koch', 'count' => 5]], $out['functions']);
    }

    /** @param array<string, mixed> $data */
    private function normalize(array $data): array
    {
        $m = new \ReflectionMethod(GrossanlassBauprojektService::class, 'normalizeHelperNeedInput');

        return $m->invoke(null, $data);
    }
}
