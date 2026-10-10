<?php

declare(strict_types=1);

namespace App\Tests\Service\Demo\Legacy;

use App\Command\DemoLegacyRenameCommand;
use App\Command\DemoGrossanlassEventJobsCommand;
use App\Entity\Department;
use App\Entity\Organisation;
use App\Service\Bootstrap\DemoGrossanlassEventJobsSeedService;
use App\Service\Bootstrap\DemoGrossanlassSeedService;
use App\Service\Demo\DemoEnvironmentGuard;
use App\Service\Demo\Legacy\LegacyDemoRename;
use App\Service\DevEnvironmentService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class LegacyDemoRenameTest extends TestCase
{
    public function testNamingRulesNeverDoublePrefix(): void
    {
        self::assertSame('old-Demo Grossanlass', LegacyDemoRename::target('Demo Grossanlass'));
        self::assertNull(LegacyDemoRename::target('old-Demo Grossanlass'));
        self::assertNull(LegacyDemoRename::target('OLD-Bauten'));
        self::assertSame('old-Altold-x', LegacyDemoRename::target('Altold-x'));
        self::assertTrue(LegacyDemoRename::isRetiredName('Demo Grossanlass'));
        self::assertTrue(LegacyDemoRename::isRetiredName('old-Irgendwas'));
        self::assertFalse(LegacyDemoRename::isRetiredName('Demo Grossanlass Event'));
        self::assertFalse(LegacyDemoRename::isRetiredName('Demo Materialverwaltung'));
    }

    public function testAllowlistCoversTheFiveInventoriedDepartmentsAndTheOrganisation(): void
    {
        self::assertSame(['7aa39b221bab', '3dc94912d836', '72605b231274', '638c8d301090', '7ae5770a1180'], array_keys(LegacyDemoRename::DEPARTMENTS));
        self::assertSame(['5f35b7cde9b5'], array_keys(LegacyDemoRename::ORGANISATIONS));
    }

    public function testTokenChangesWithThePlanAndIgnoresAlreadyDoneItems(): void
    {
        $a = [['type' => 'department', 'id' => '1', 'target' => 'old-a', 'status' => 'rename'], ['type' => 'group', 'id' => '2', 'target' => 'old-b', 'status' => 'done']];
        $b = [['type' => 'department', 'id' => '1', 'target' => 'old-a', 'status' => 'rename']];
        $c = [['type' => 'department', 'id' => '1', 'target' => 'old-z', 'status' => 'rename']];
        self::assertSame(LegacyDemoRename::token($a), LegacyDemoRename::token($b));
        self::assertNotSame(LegacyDemoRename::token($a), LegacyDemoRename::token($c));
    }

    private function guard(string $kernelEnv, string $name, bool $flag = false): DemoEnvironmentGuard
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn($kernelEnv);
        $dev = $this->createMock(DevEnvironmentService::class);
        $dev->method('isDevToolsEnabled')->willReturn(true);

        return new DemoEnvironmentGuard($kernel, $dev, $name, $flag);
    }

    /** @return iterable<string, array{string, string, bool, bool}> */
    public static function environments(): iterable
    {
        yield 'local dev kernel' => ['dev', '', true];
        yield 'explicit local' => ['dev', 'local', true];
        yield 'develop (even with destructive flag)' => ['prod', 'develop', false];
        yield 'staging' => ['prod', 'staging', false];
        yield 'production' => ['prod', 'production', false];
        yield 'prod kernel without name' => ['prod', '', false];
        yield 'staging name on dev kernel' => ['dev', 'staging', false];
        yield 'develop name on dev kernel' => ['dev', 'develop', false];
    }

    #[DataProvider('environments')]
    public function testOnlyTheLocalEnvironmentMayRun(string $kernel, string $name, bool $allowed): void
    {
        self::assertSame($allowed, $this->guard($kernel, $name, true)->localOnlyDenial() === null);
    }

    /** @param list<array<string, string>> $items */
    private function command(DemoEnvironmentGuard $guard, array $items, ?LegacyDemoRename &$rename = null): CommandTester
    {
        $rename = $this->createMock(LegacyDemoRename::class);
        $rename->method('plan')->willReturn($items);

        return new CommandTester(new DemoLegacyRenameCommand($guard, $rename));
    }

    /** @return list<array<string, string>> */
    private function plan(): array
    {
        return [
            ['type' => 'department', 'id' => '7aa39b221bab', 'current' => 'Demo Grossanlass', 'target' => 'old-Demo Grossanlass', 'status' => 'rename', 'reason' => '', 'parent' => ''],
            ['type' => 'group', 'id' => 'g1', 'current' => 'Bauten', 'target' => 'old-Bauten', 'status' => 'rename', 'reason' => '', 'parent' => '7aa39b221bab'],
        ];
    }

    public function testCommandIsDryRunByDefaultAndNeedsTheTokenToExecute(): void
    {
        $items = $this->plan();
        $token = LegacyDemoRename::token($items);

        $tester = $this->command($this->guard('dev', ''), $items, $rename);
        $rename->expects(self::never())->method('apply');
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Dry-Run', $tester->getDisplay());
        self::assertStringContainsString('--confirm=' . $token, $tester->getDisplay());

        foreach ([['--execute' => true], ['--execute' => true, '--confirm' => 'falsch'], ['--confirm' => $token]] as $args) {
            $t = $this->command($this->guard('dev', ''), $items, $r);
            $r->expects(self::never())->method('apply');
            $code = $t->execute($args);
            self::assertSame(isset($args['--execute']) ? Command::FAILURE : Command::SUCCESS, $code, json_encode($args));
        }

        $t = $this->command($this->guard('dev', ''), $items, $r);
        $r->expects(self::once())->method('apply')->with($items)->willReturn(2);
        self::assertSame(Command::SUCCESS, $t->execute(['--execute' => true, '--confirm' => $token]));
        self::assertStringContainsString('2 Namen', $t->getDisplay());
    }

    public function testCommandRefusesEverywhereExceptLocalEvenAsDryRun(): void
    {
        foreach ([['prod', 'develop'], ['prod', 'staging'], ['prod', 'production'], ['prod', '']] as [$kernel, $name]) {
            $t = $this->command($this->guard($kernel, $name, true), $this->plan(), $rename);
            $rename->expects(self::never())->method('plan');
            $rename->expects(self::never())->method('apply');
            self::assertSame(Command::FAILURE, $t->execute(['--execute' => true, '--confirm' => 'x']), "$kernel/$name");
        }
    }

    public function testNothingToDoWhenEverythingIsAlreadyMarked(): void
    {
        $items = [['type' => 'department', 'id' => '1', 'current' => 'old-x', 'target' => 'old-x', 'status' => 'done', 'reason' => '', 'parent' => '']];
        $t = $this->command($this->guard('dev', ''), $items, $rename);
        $rename->expects(self::never())->method('apply');
        self::assertSame(Command::SUCCESS, $t->execute(['--execute' => true, '--confirm' => 'egal']));
        self::assertStringContainsString('Nichts zu tun', $t->getDisplay());
    }

    public function testSeedServiceNeverRecreatesRetiredLegacyNames(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null); // Legacy-Department wurde umbenannt: unter dem alten Namen nichts mehr da
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');
        $seed = (new \ReflectionClass(DemoGrossanlassSeedService::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(DemoGrossanlassSeedService::class, 'entityManager'))->setValue($seed, $em);
        $org = new Organisation();
        $org->setId('org_t1');

        foreach (['Demo Grossanlass', 'Demo-Grossanlass-Event', 'old-Demo Grossanlass'] as $name) {
            try {
                $seed->ensureDepartment($org, $this->createMock(\App\Entity\User::class), $name);
                self::fail($name);
            } catch (\RuntimeException $e) {
                self::assertStringContainsString('ausgemusterten', $e->getMessage());
            }
        }
        $this->expectException(\RuntimeException::class);
        $seed->assertLegacyDepartmentAvailable($org); // Vorabprüfung für app:create-role-users --with-ga-demo
    }

    public function testEventJobsSeedRefusesRenamedLegacyDepartments(): void
    {
        $service = (new \ReflectionClass(DemoGrossanlassEventJobsSeedService::class))->newInstanceWithoutConstructor();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ausgemustertes Legacy-Department');
        $service->seedByName('old-Demo-Grossanlass-Event', true);
    }
}
