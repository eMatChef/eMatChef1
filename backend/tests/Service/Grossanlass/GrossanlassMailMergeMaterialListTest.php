<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassMailMergeService;
use PHPUnit\Framework\TestCase;

class GrossanlassMailMergeMaterialListTest extends TestCase
{
    public function testFormatIncludesQuantityAndLabelOnly(): void
    {
        $html = GrossanlassMailMergeService::formatMaterialListHtml([
            ['quantity' => 2, 'label' => 'Gator'],
            ['quantity' => 1, 'label' => 'Anhänger'],
        ]);
        self::assertSame('2× Gator<br>1× Anhänger', $html);
    }

    public function testFormatWithoutQuantityListsLabelsOnly(): void
    {
        $html = GrossanlassMailMergeService::formatMaterialListHtml([
            ['quantity' => 12, 'label' => 'Schraube M8x40'],
            ['quantity' => 4, 'label' => 'Schraube M10x50'],
        ], false);
        self::assertSame('Schraube M8x40<br>Schraube M10x50', $html);
        self::assertStringNotContainsString('12×', $html);
    }

    public function testFormatIgnoresNotesAndLocationIfPassed(): void
    {
        $html = GrossanlassMailMergeService::formatMaterialListHtml([
            [
                'quantity' => 2,
                'label' => 'Gator',
                'location' => 'Auf dem gelände',
                'notes' => 'intern, nicht in die Mail',
            ],
        ]);
        self::assertSame('2× Gator', $html);
        self::assertStringNotContainsString('gelände', $html);
        self::assertStringNotContainsString('intern', $html);
    }

    public function testFormatSkipsEmptyLabels(): void
    {
        self::assertSame('', GrossanlassMailMergeService::formatMaterialListHtml([
            ['quantity' => 3, 'label' => '  '],
        ]));
    }
}
