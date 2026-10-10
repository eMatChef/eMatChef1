<?php

declare(strict_types=1);

namespace App\Tests\Service\Display;

use App\Service\Display\DisplayRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class DisplayRateLimiterTest extends TestCase
{
    public function testLimitsAfterMaxHitsAndIsPerSubject(): void
    {
        $limiter = new DisplayRateLimiter(new ArrayAdapter());
        for ($i = 0; $i < 3; $i++) {
            self::assertFalse($limiter->isLimited('b', '1.2.3.4', 3));
            $limiter->hit('b', '1.2.3.4', 600);
        }

        self::assertTrue($limiter->isLimited('b', '1.2.3.4', 3));
        self::assertFalse($limiter->isLimited('b', '5.6.7.8', 3));
        self::assertFalse($limiter->isLimited('other', '1.2.3.4', 3));
    }

    public function testWindowExpiresAndIsNotExtendedByFurtherHits(): void
    {
        $cache = new ArrayAdapter();
        $limiter = new DisplayRateLimiter($cache);
        $limiter->hit('b', 'ip', 1);
        $limiter->hit('b', 'ip', 1);
        self::assertTrue($limiter->isLimited('b', 'ip', 2));

        sleep(2);

        self::assertFalse($limiter->isLimited('b', 'ip', 2));
    }
}
