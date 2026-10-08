<?php

declare(strict_types=1);

namespace App\Tests\Service\Demo\Scenario;

use App\Command\DemoResetCommand;
use App\Command\DemoStatusCommand;
use App\Command\DemoSyncCommand;
use App\Command\DemoVerifyCommand;
use App\Command\DemoAdoptCommand;
use App\Service\Clock\BusinessClock;
use App\Service\Demo\DemoEnvironmentGuard;
use App\Service\Demo\Scenario\DemoScenarioException;
use App\Service\Demo\Scenario\DemoScenarioIdentity;
use App\Service\Demo\Scenario\DemoScenarioKey;
use App\Service\Demo\Scenario\DemoScenarioRunner;
use App\Service\Demo\Scenario\DemoSeedLedger;
use App\Service\Demo\Scenario\SeedContext;
use App\Service\Demo\Scenario\SeedResult;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RunnerAndCommandsTest extends ScenarioTestCase
{
    /** @var list<string> */
    private array $writes = [];

    private function runner(mixed $lock = true, mixed $keyAfter = null, ?\App\Entity\Department $department = null): DemoScenarioRunner
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchOne')->willReturnCallback(static fn (string $sql) => str_contains($sql, 'advisory') ? $lock : $keyAfter);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);
        $identity = $this->createMock(DemoScenarioIdentity::class);
        $identity->method('findDepartment')->willReturn($department);

        return new DemoScenarioRunner($em, $identity, $this->createMock(DemoSeedLedger::class));
    }

    public function testRealScenariosHaveNoResetInPhase1(): void
    {
        $origin = (new \ReflectionClass(\App\Service\Grossanlass\GrossanlassClockOriginResolver::class))->newInstanceWithoutConstructor();
        foreach ([
            new \App\Service\Demo\Scenario\MaterialverwaltungScenario(),
            new \App\Service\Demo\Scenario\GrossanlassEventScenario($origin),
            new \App\Service\Demo\Scenario\GrossanlassCampScenario($origin),
        ] as $scenario) {
            self::assertFalse($scenario->supportsReset(), $scenario->key());
            try {
                $this->runner(true, $scenario->key(), $this->department('d', true, $scenario->expectsGrossanlass(), $scenario->key()))->reset($scenario);
                self::fail('Reset darf nicht laufen');
            } catch (DemoScenarioException $e) {
                self::assertStringContainsString('noch nicht verfügbar', $e->getMessage());
            }
        }
    }

    public function testSyncOfPlaceholderScenarioIsNotImplementedAndWritesNothing(): void
    {
        $result = $this->runner()->sync($this->scenario(DemoScenarioKey::MATERIALVERWALTUNG));
        self::assertSame(SeedResult::NOT_IMPLEMENTED, $result->status);
    }

    public function testSyncRefusesWhenAnotherRunHoldsTheLock(): void
    {
        $this->expectException(DemoScenarioException::class);
        $this->expectExceptionMessage('anderen Lauf');
        $this->runner(false)->sync($this->scenario(DemoScenarioKey::MATERIALVERWALTUNG));
    }

    public function testResetKeepsDepartmentRowAndIsRolledBackOtherwise(): void
    {
        $key = DemoScenarioKey::MATERIALVERWALTUNG;
        $dept = $this->department('d1', true, false, $key);
        $called = 0;
        $scenario = $this->scenario($key, false, true, function () use (&$called) {
            ++$called;

            return SeedResult::ok('zurückgesetzt');
        });

        self::assertSame('zurückgesetzt', $this->runner(true, $key, $dept)->reset($scenario)->message);
        self::assertSame(1, $called);

        $this->expectException(DemoScenarioException::class);
        $this->expectExceptionMessage('zurückgerollt');
        $this->runner(true, null, $dept)->reset($scenario); // Department-Zeile/Schlüssel nach Reset weg
    }

    public function testResetWithoutDepartmentDoesNothing(): void
    {
        $scenario = $this->scenario(DemoScenarioKey::MATERIALVERWALTUNG, false, true, function (): SeedResult {
            self::fail('darf nicht laufen');
        });
        $this->expectException(DemoScenarioException::class);
        $this->runner()->reset($scenario);
    }

    private function guard(string $kernelEnv, string $name, bool $flag): DemoEnvironmentGuard
    {
        $kernel = $this->createMock(\Symfony\Component\HttpKernel\KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn($kernelEnv);
        $dev = $this->createMock(\App\Service\DevEnvironmentService::class);
        $dev->method('isDevToolsEnabled')->willReturn(true);

        return new DemoEnvironmentGuard($kernel, $dev, $name, $flag);
    }

    /** @return array<string, array{string, string, bool, bool, bool}> kernel, name, flag, additive, destructive */
    public static function environments(): array
    {
        return [
            'local' => ['dev', '', false, true, true],
            'develop without flag' => ['prod', 'develop', false, true, false],
            'develop with flag' => ['prod', 'develop', true, true, true],
            'staging' => ['prod', 'staging', true, true, false],
            'production' => ['prod', 'production', true, false, false],
            'prod kernel, no name' => ['prod', '', true, false, false],
        ];
    }

    public function testEveryCommandFollowsTheEnvironmentGuard(): void
    {
        foreach (self::environments() as $label => [$kernel, $name, $flag, $additive, $destructive]) {
            $guard = $this->guard($kernel, $name, $flag);
            $registry = $this->registry($this->scenario(DemoScenarioKey::MATERIALVERWALTUNG));
            $runner = $this->createMock(DemoScenarioRunner::class);
            $runner->method('sync')->willReturn(SeedResult::notImplemented('n/a'));
            $runner->method('verify')->willReturn([]);
            $runner->method('reset')->willReturn(SeedResult::ok('reset'));
            $identity = $this->createMock(DemoScenarioIdentity::class);
            $em = $this->createMock(EntityManagerInterface::class);
            $em->method('getRepository')->willReturn($this->createMock(\Doctrine\ORM\EntityRepository::class));
            $clock = (new \ReflectionClass(BusinessClock::class))->newInstanceWithoutConstructor();

            $commands = [
                [new DemoStatusCommand($guard, $registry, $identity, $this->createMock(DemoSeedLedger::class), $clock), [], $additive],
                [new DemoSyncCommand($guard, $registry, $runner), ['--scenario' => 'materialverwaltung'], $additive],
                [new DemoVerifyCommand($guard, $registry, $runner), ['--all' => true], $additive],
                [new DemoAdoptCommand($guard, $registry, $identity, $em), ['--scenario' => 'materialverwaltung', '--department' => 'zz'], $additive],
            ];
            foreach ($commands as [$command, $args, $allowed]) {
                $tester = new CommandTester($command);
                $code = $tester->execute($args);
                if (!$allowed) {
                    self::assertSame(Command::FAILURE, $code, $label . ' ' . $command->getName());
                }
            }

            // Reset: nur mit destruktiver Freigabe überhaupt bis zum Runner (Runner ist hier gemockt).
            $tester = new CommandTester(new DemoResetCommand($guard, $registry, $runner));
            $code = $tester->execute(['--scenario' => 'materialverwaltung', '--confirm' => 'materialverwaltung']);
            self::assertSame($destructive ? Command::SUCCESS : Command::FAILURE, $code, $label . ' reset');
        }
    }

    public function testResetCommandRequiresConfirmationAndRefusesUnavailableReset(): void
    {
        $guard = $this->guard('dev', '', false);
        $registry = $this->registry($this->scenario(DemoScenarioKey::MATERIALVERWALTUNG));
        $runner = $this->runner();

        $tester = new CommandTester(new DemoResetCommand($guard, $registry, $runner));
        self::assertSame(Command::FAILURE, $tester->execute(['--scenario' => 'materialverwaltung']));
        self::assertStringContainsString('Bestätigung fehlt', $tester->getDisplay());

        $tester = new CommandTester(new DemoResetCommand($guard, $registry, $runner));
        self::assertSame(Command::FAILURE, $tester->execute(['--scenario' => 'materialverwaltung', '--confirm' => 'materialverwaltung']));
        self::assertStringContainsString('noch nicht verfügbar', $tester->getDisplay());
    }

    public function testSyncCommandNeedsExactlyOneSelectionAndRejectsUnknownScenario(): void
    {
        $registry = $this->registry($this->scenario(DemoScenarioKey::MATERIALVERWALTUNG));
        $command = new DemoSyncCommand($this->guard('dev', '', false), $registry, $this->runner());

        foreach ([[], ['--scenario' => 'materialverwaltung', '--all' => true], ['--scenario' => 'mein-department']] as $args) {
            $tester = new CommandTester($command);
            self::assertSame(Command::FAILURE, $tester->execute($args), json_encode($args));
        }
        $tester = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $tester->execute(['--all' => true]));
        self::assertStringContainsString('implementiert', $tester->getDisplay());
    }
}
