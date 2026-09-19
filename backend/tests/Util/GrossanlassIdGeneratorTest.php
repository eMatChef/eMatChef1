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
}
