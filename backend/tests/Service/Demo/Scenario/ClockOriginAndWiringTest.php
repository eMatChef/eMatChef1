<?php

declare(strict_types=1);

namespace App\Tests\Service\Demo\Scenario;

use App\Entity\DepartmentGrossanlassConfig;
use App\Tests\Wiring\FreshKernel;
use App\Service\Clock\BusinessClock;
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
        $scenarioResolver = new ScenarioClockOriginResolver($this->registry(new GrossanlassEventScenario($legacy)));

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
            new GrossanlassEventScenario($legacy),
            new MaterialverwaltungScenario(),
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

    public function testCompiledContainerRegistersTheThreeScenariosAndTheResolver(): void
    {
        FreshKernel::run(function (\App\Kernel $kernel): void {
            // Private Services sind im kompilierten Container nur über ihre Verbraucher erreichbar.
            $command = (new Application($kernel))->find('app:demo:status');
            if ($command instanceof \Symfony\Component\Console\Command\LazyCommand) {
                $command = $command->getCommand();
            }
            $registry = (new \ReflectionProperty($command, 'registry'))->getValue($command);
            self::assertInstanceOf(DemoScenarioRegistry::class, $registry);
            self::assertEqualsCanonicalizing(DemoScenarioKey::all(), array_keys($registry->all()));
            foreach ($registry->all() as $scenario) {
                self::assertFalse($scenario->supportsReset(), $scenario->key());
            }

            $clock = (new \ReflectionProperty($command, 'clock'))->getValue($command);
            self::assertInstanceOf(BusinessClock::class, $clock);
            $resolvers = (new \ReflectionProperty($clock, 'originResolvers'))->getValue($clock);
            $classes = array_map(static fn (object $r): string => $r::class, iterator_to_array($resolvers, false));
            self::assertContains(ScenarioClockOriginResolver::class, $classes);
            self::assertContains(GrossanlassClockOriginResolver::class, $classes);
        });
    }
}
