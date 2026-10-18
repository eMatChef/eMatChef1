<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassInboundService;
use PHPUnit\Framework\TestCase;

final class GrossanlassInboundServiceTest extends TestCase
{
    public function testPhaseConstantsAreStable(): void
    {
        self::assertSame('expected', GrossanlassInboundService::PHASE_EXPECTED);
        self::assertSame('acceptance', GrossanlassInboundService::PHASE_ACCEPTANCE);
        self::assertSame('tour', GrossanlassInboundService::GROUP_TOUR);
    }
}
