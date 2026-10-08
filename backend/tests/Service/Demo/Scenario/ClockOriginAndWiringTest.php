<?php

declare(strict_types=1);

namespace App\Tests\Service\Demo\Scenario;

use App\Entity\DepartmentGrossanlassConfig;
use App\Tests\Wiring\FreshKernel;
use App\Service\Clock\BusinessClock;
use App\Service\Demo\Organisation\DemoOrganisationSeeder;
use App\Service\Demo\Scenario\DemoScenarioKey;
use App\Service\Demo\Scenario\DemoScenarioRegistry;
use App\Service\Demo\Scenario\GrossanlassEventScenario;
use App\Service\Demo\Scenario\MaterialverwaltungScenario;
use App\Service\Demo\Scenario\ScenarioClockOriginResolver;
use App\Service\Grossanlass\GrossanlassClockOriginResolver;
use Symfony\Bundle\FrameworkBundle\Console\Application;

final class ClockOriginAndWiringTest extends ScenarioTestCase
{
    private function gaDepartment(?string $key): \App\Entity\Department
    {
        $d = $this->department('d1', true, true, $key);
        $config = new DepartmentGrossanlassConfig();
        $config->setPlannedEventStart(new \DateTime('2026-11-11 00:00:00'));
        $d->setGrossanlassConfig($config);

        return $d;
    }

    public function testKeyedDepartmentsUseTheScenarioResolverUnkeyedKeepTheGrossanlassOne(): void
    {
        $legacy = new GrossanlassClockOriginResolver();
        $scenarioResolver = new ScenarioClockOriginResolver($this->registry(new GrossanlassEventScenario($this->createMock(DemoOrganisationSeeder::class), $legacy)));

        $unkeyed = $this->gaDepartment(null);
        self::assertTrue($legacy->supports($unkeyed));
        self::assertFalse($scenarioResolver->supports($unkeyed));

        $keyed = $this->gaDepartment(DemoScenarioKey::GROSSANLASS_EVENT);
        self::assertFalse($legacy->supports($keyed));
        self::assertTrue($scenarioResolver->supports($keyed));
        // Bestehender Ausgangspunkt unverändert: Anlassbeginn − 5 Tage, 09:00
        self::assertSame('2026-11-06 09:00:00', $scenarioResolver->originFor($keyed)->format('Y-m-d H:i:s'));
    }

    public function testScenarioWithoutOriginFallsBackToRealTimeAndUnregisteredKeyIsIgnored(): void
    {
        $resolver = new ScenarioClockOriginResolver($this->registry($this->scenario(DemoScenarioKey::MATERIALVERWALTUNG)));
        self::assertFalse($resolver->supports($this->department('d1', true, false, DemoScenarioKey::MATERIALVERWALTUNG)));
        self::assertFalse($resolver->supports($this->department('d2', true, true, DemoScenarioKey::GROSSANLASS_CAMP)));
        self::assertFalse($resolver->supports($this->department('d3')));
    }

    public function testBusinessClockResetUsesScenarioOriginForKeyedAndLegacyOriginForUnkeyedDepartments(): void
    {
        $legacy = new GrossanlassClockOriginResolver();
        $scenarioResolver = new ScenarioClockOriginResolver($this->registry(
            new GrossanlassEventScenario($this->createMock(DemoOrganisationSeeder::class), $legacy),
            new MaterialverwaltungScenario($this->createMock(DemoOrganisationSeeder::class)),
        ));
        $kernel = $this->createMock(\Symfony\Component\HttpKernel\KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn('prod');
        $clock = new BusinessClock($this->createMock(\Doctrine\ORM\EntityManagerInterface::class), $kernel, [$legacy, $scenarioResolver]);

        $unkeyed = $this->gaDepartment(null);
        $keyed = $this->gaDepartment(DemoScenarioKey::GROSSANLASS_EVENT);
        $material = $this->department('m1', true, false, DemoScenarioKey::MATERIALVERWALTUNG);
        $realDept = $this->department('r1', false, true);

        self::assertSame('2026-11-06 09:00:00', $clock->originFor($unkeyed)->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-06 09:00:00', $clock->originFor($keyed)->format('Y-m-d H:i:s'));
        // Szenario ohne Ausgangspunkt und echtes Department: reale Zeit, kein Resolver greift
        foreach ([$material, $realDept] as $d) {
            self::assertEqualsWithDelta(time(), $clock->originFor($d)->getTimestamp(), 5);
        }
        // Reihenfolge der Resolver ist egal
        $reversed = new BusinessClock($this->createMock(\Doctrine\ORM\EntityManagerInterface::class), $kernel, [$scenarioResolver, $legacy]);
        self::assertEquals($clock->originFor($keyed), $reversed->originFor($keyed));

        // Reset verändert nur den Offset des Departments, nichts sonst
        $clock->reset($keyed);
        self::assertNotNull($keyed->getDemoClockOffsetSeconds());
        self::assertSame(DemoScenarioKey::GROSSANLASS_EVENT, $keyed->getDemoScenarioKey());
    }

    public function testCompiledContainerTagsTheThreeScenariosAndBothClockResolvers(): void
    {
        FreshKernel::run(function (\App\Kernel $kernel): void {
            $container = $kernel->getContainer();
            $scenarios = $container->getParameter('wiring.tagged.app.demo_scenario');
            self::assertEqualsCanonicalizing([
                \App\Service\Demo\Scenario\MaterialverwaltungScenario::class,
                GrossanlassEventScenario::class,
                \App\Service\Demo\Scenario\GrossanlassCampScenario::class,
            ], $scenarios);

            $resolvers = $container->getParameter('wiring.tagged.app.clock_origin_resolver');
            self::assertContains(ScenarioClockOriginResolver::class, $resolvers);
            self::assertContains(GrossanlassClockOriginResolver::class, $resolvers);

            // Alle fünf Commands sind registriert.
            $app = new Application($kernel);
            foreach (['status', 'sync', 'verify', 'adopt', 'reset'] as $name) {
                self::assertTrue($app->has('app:demo:' . $name), $name);
            }
        });
    }
}
