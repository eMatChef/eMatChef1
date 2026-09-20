<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Util\GrossanlassIdGenerator;
use PHPUnit\Framework\TestCase;

class GrossanlassIdGeneratorTest extends TestCase
{
    public function testTaskPrefixIsTwelveCharsWithGt(): void
    {
        self::assertSame('gt', GrossanlassIdGenerator::prefix(GrossanlassIdGenerator::TASK));
        self::assertTrue(GrossanlassIdGenerator::matches('gtabcdef0123', GrossanlassIdGenerator::TASK));
        self::assertFalse(GrossanlassIdGenerator::matches('plabcdef0123', GrossanlassIdGenerator::TASK));
    }

    public function testGroupSharePrefixIsGl(): void
    {
        self::assertSame('gl', GrossanlassIdGenerator::prefix(GrossanlassIdGenerator::GROUP_SHARE));
        self::assertTrue(GrossanlassIdGenerator::matches('glabcdef0123', GrossanlassIdGenerator::GROUP_SHARE));
    }
}
