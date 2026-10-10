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
        FreshKernel::run(function (Kernel $kernel): void {
            $controller = $kernel->getContainer()->get(DepartmentClockController::class);
            self::assertInstanceOf(DepartmentClockController::class, $controller);

            $clock = (new \ReflectionProperty($controller, 'clock'))->getValue($controller);
            self::assertInstanceOf(BusinessClock::class, $clock);

            $resolvers = (new \ReflectionProperty($clock, 'originResolvers'))->getValue($clock);
            $classes = array_map(static fn (object $r): string => $r::class, iterator_to_array($resolvers, false));

            self::assertContains(GrossanlassClockOriginResolver::class, $classes);
            self::assertContains(\App\Service\Demo\Scenario\ScenarioClockOriginResolver::class, $classes);
            self::assertSame('app.clock_origin_resolver', ClockOriginResolverInterface::TAG);
        });
    }
}
