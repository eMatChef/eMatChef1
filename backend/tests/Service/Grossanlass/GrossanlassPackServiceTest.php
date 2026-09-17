<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassPackService;
use App\Util\GrossanlassIdGenerator;
use PHPUnit\Framework\TestCase;

class GrossanlassPackServiceTest extends TestCase
{
    public function testWarningFromPartialPack(): void
    {
        $warning = GrossanlassPackService::warningFrom([
            ['label' => 'Holz', 'qty_needed' => 10, 'qty_packed' => 10],
            ['label' => 'Schrauben', 'qty_needed' => 200, 'qty_packed' => 0],
        ]);

        self::assertSame('Holz 10× gepackt, Schrauben noch nicht im Mat', $warning);
    }

    public function testWarningFromCompleteOrEmptyIsNull(): void
    {
        self::assertNull(GrossanlassPackService::warningFrom([
            ['label' => 'Holz', 'qty_needed' => 10, 'qty_packed' => 10],
        ]));
        self::assertNull(GrossanlassPackService::warningFrom([
            ['label' => 'Holz', 'qty_needed' => 10, 'qty_packed' => 0],
        ]));
        self::assertNull(GrossanlassPackService::warningFrom([]));
    }

    public function testIdPrefixesForPackPlaceAndLine(): void
    {
        self::assertSame('pk', GrossanlassIdGenerator::prefix(GrossanlassIdGenerator::PACK));
        self::assertSame('pn', GrossanlassIdGenerator::prefix(GrossanlassIdGenerator::PACK_LINE));
        self::assertSame('pl', GrossanlassIdGenerator::prefix(GrossanlassIdGenerator::PLACE));
        self::assertNotSame(
            GrossanlassIdGenerator::prefix(GrossanlassIdGenerator::PLACE),
            GrossanlassIdGenerator::prefix(GrossanlassIdGenerator::PROCUREMENT_LINE),
        );
        self::assertTrue(GrossanlassIdGenerator::matches('pk' . str_repeat('a', 10), GrossanlassIdGenerator::PACK));
        self::assertTrue(GrossanlassIdGenerator::matches('pl' . str_repeat('b', 10), GrossanlassIdGenerator::PLACE));
    }
}
