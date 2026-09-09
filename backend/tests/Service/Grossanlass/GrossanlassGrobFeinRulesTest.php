<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassRound;
use App\Service\Grossanlass\GrossanlassMaterialStage;
use App\Service\Grossanlass\GrossanlassProcurementQuantityFreeze;
use PHPUnit\Framework\TestCase;

class GrossanlassGrobFeinRulesTest extends TestCase
{
    public function testMaterialStageDefaultsToGrob(): void
    {
        self::assertSame(
            GrossanlassMaterialStage::GROB,
            GrossanlassMaterialStage::normalize(ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH, null),
        );
        self::assertSame(
            GrossanlassMaterialStage::FEIN,
            GrossanlassMaterialStage::normalize(ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH, 'fein'),
        );
        self::assertNull(GrossanlassMaterialStage::normalize(ActivityGrossanlassRound::PURPOSE_COMPANY_TIP, null));
    }

    public function testCompanyTipRejectsStage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GrossanlassMaterialStage::normalize(ActivityGrossanlassRound::PURPOSE_COMPANY_TIP, 'grob');
    }

    public function testInvalidStageRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GrossanlassMaterialStage::normalize(ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH, 'mittel');
    }

    public function testMergeRejectedWhenAskedQuantitySet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GrossanlassProcurementQuantityFreeze::assertMergeAllowed(
            ActivityGrossanlassProcurementLine::STATUS_BEDARF,
            3,
        );
    }

    public function testMergeAllowedWhenNotFrozen(): void
    {
        GrossanlassProcurementQuantityFreeze::assertMergeAllowed(
            ActivityGrossanlassProcurementLine::STATUS_BEDARF,
            null,
        );
        self::assertTrue(true);
    }

    public function testDeltaIsCurrentMinusAsked(): void
    {
        self::assertNull(GrossanlassProcurementQuantityFreeze::delta(null, 5));
        self::assertSame(-1, GrossanlassProcurementQuantityFreeze::delta(3, 2));
        self::assertSame(2, GrossanlassProcurementQuantityFreeze::delta(1, 3));
    }
}
