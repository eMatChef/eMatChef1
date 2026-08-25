<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Entity\ActivityGrossanlassRound;
use App\Entity\ActivityGrossanlassWishLine;
use App\Service\Grossanlass\GrossanlassCollectorDecision;
use PHPUnit\Framework\TestCase;

class GrossanlassCollectorDecisionTest extends TestCase
{
    public function testMaterialCannotLeavePoolViaCollector(): void
    {
        self::assertFalse(GrossanlassCollectorDecision::canToInquiry(
            ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH,
            ActivityGrossanlassWishLine::STATUS_REQUESTED,
        ));
        self::assertFalse(GrossanlassCollectorDecision::canToMaterial(
            ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH,
            ActivityGrossanlassWishLine::STATUS_REQUESTED,
        ));
        self::assertFalse(GrossanlassCollectorDecision::canDiscard(
            ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH,
            ActivityGrossanlassWishLine::STATUS_REQUESTED,
        ));
    }

    public function testCompanyTipGoesToInquiryOrDiscard(): void
    {
        self::assertTrue(GrossanlassCollectorDecision::canToInquiry(
            ActivityGrossanlassRound::PURPOSE_COMPANY_TIP,
            ActivityGrossanlassWishLine::STATUS_REQUESTED,
        ));
        self::assertTrue(GrossanlassCollectorDecision::canDiscard(
            ActivityGrossanlassRound::PURPOSE_COMPANY_TIP,
            ActivityGrossanlassWishLine::STATUS_REQUESTED,
        ));
        self::assertFalse(GrossanlassCollectorDecision::canToMaterial(
            ActivityGrossanlassRound::PURPOSE_COMPANY_TIP,
            ActivityGrossanlassWishLine::STATUS_REQUESTED,
        ));
    }

    public function testFreeCanBecomeMaterialCompanyOrDiscard(): void
    {
        self::assertTrue(GrossanlassCollectorDecision::canToMaterial(
            ActivityGrossanlassRound::PURPOSE_FREE,
            ActivityGrossanlassWishLine::STATUS_REQUESTED,
        ));
        self::assertTrue(GrossanlassCollectorDecision::canToInquiry(
            ActivityGrossanlassRound::PURPOSE_FREE,
            ActivityGrossanlassWishLine::STATUS_REQUESTED,
        ));
        self::assertTrue(GrossanlassCollectorDecision::canDiscard(
            ActivityGrossanlassRound::PURPOSE_FREE,
            ActivityGrossanlassWishLine::STATUS_REQUESTED,
        ));
    }

    public function testHandledRowsAreLocked(): void
    {
        self::assertFalse(GrossanlassCollectorDecision::canDiscard(
            ActivityGrossanlassRound::PURPOSE_FREE,
            ActivityGrossanlassWishLine::STATUS_ACCEPTED,
        ));
        self::assertFalse(GrossanlassCollectorDecision::canToInquiry(
            ActivityGrossanlassRound::PURPOSE_COMPANY_TIP,
            ActivityGrossanlassWishLine::STATUS_DISCARDED,
        ));
    }
}
