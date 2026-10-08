<?php

declare(strict_types=1);

namespace App\Tests\Wiring;

use App\Controller\DepartmentClockController;
use App\Kernel;
use App\Service\Clock\BusinessClock;
use App\Service\Clock\ClockOriginResolverInterface;
use App\Service\Grossanlass\GrossanlassClockOriginResolver;
use PHPUnit\Framework\TestCase;

/**
 * Prüft den echten, kompilierten Container (nicht nur Unit-Mocks): der Grossanlass-Resolver kommt über das
 * Autoconfigure-Tag «app.clock_origin_resolver» in die BusinessClock des Controllers.
 */
final class BusinessClockWiringTest extends TestCase
{
    public function testGrossanlassResolverIsInjectedViaTaggedIterator(): void
    {
        // Der Container wird nur kompiliert; es wird keine DB-Verbindung aufgebaut.
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'postgresql://wiring:wiring@127.0.0.1:5432/wiring?serverVersion=16';
        $kernel = new Kernel('test', false);
        $kernel->boot();
        try {
            $controller = $kernel->getContainer()->get(DepartmentClockController::class);
            self::assertInstanceOf(DepartmentClockController::class, $controller);

            $clock = (new \ReflectionProperty($controller, 'clock'))->getValue($controller);
            self::assertInstanceOf(BusinessClock::class, $clock);

            $resolvers = (new \ReflectionProperty($clock, 'originResolvers'))->getValue($clock);
            $classes = array_map(static fn (object $r): string => $r::class, iterator_to_array($resolvers, false));

            self::assertContains(GrossanlassClockOriginResolver::class, $classes);
            self::assertSame('app.clock_origin_resolver', ClockOriginResolverInterface::TAG);
        } finally {
            $kernel->shutdown();
        }
    }
}
