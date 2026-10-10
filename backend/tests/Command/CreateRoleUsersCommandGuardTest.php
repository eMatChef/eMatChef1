<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CreateRoleUsersCommand;
use App\Service\Auth\TotpService;
use App\Service\Bootstrap\DemoGrossanlassSeedService;
use App\Service\Bootstrap\DemoSupplierSeedService;
use App\Service\Bootstrap\DevBootstrapContextService;
use App\Service\Demo\DemoEnvironmentGuard;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateRoleUsersCommandGuardTest extends TestCase
{
    public function testDemoAccountsAreNotCreatedWhenDevToolsAreDisabled(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');
        $guard = $this->createMock(DemoEnvironmentGuard::class);
        $guard->method('additiveDenial')->willReturn('Dev-Tools sind deaktiviert');

        self::assertSame(1, ($tester = new CommandTester($this->command($em, $guard)))->execute([]));
        self::assertStringContainsString('Dev-Tools sind deaktiviert', $tester->getDisplay());
    }

    public function testDeleteDemoUsersIsBlockedWithoutDestructiveApproval(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getRepository');
        $em->expects(self::never())->method('remove');
        $em->expects(self::never())->method('flush');
        $guard = $this->createMock(DemoEnvironmentGuard::class);
        $guard->method('additiveDenial')->willReturn(null);
        $guard->method('destructiveDenial')->willReturn('Löschen gesperrt');

        $tester = new CommandTester($this->command($em, $guard));

        self::assertSame(1, $tester->execute(['--delete-demo-users' => true]));
        self::assertStringContainsString('Löschen gesperrt', $tester->getDisplay());
    }

    public function testAmbiguousDemoDepartmentAbortsBeforeAnyWrite(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('remove');
        $em->expects(self::never())->method('flush');
        $guard = $this->createMock(DemoEnvironmentGuard::class);
        $guard->method('additiveDenial')->willReturn(null);
        $guard->method('destructiveDenial')->willReturn('x');
        $context = $this->createMock(DevBootstrapContextService::class);
        $context->method('findOwnedDemoOrganisationAndDepartment')
            ->willThrowException(new \RuntimeException('Kein eindeutiges Demo-Department'));

        $tester = new CommandTester($this->command($em, $guard, $context));

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('Kein eindeutiges Demo-Department', $tester->getDisplay());
    }

    private function command(
        EntityManagerInterface $em,
        DemoEnvironmentGuard $guard,
        ?DevBootstrapContextService $context = null,
    ): CreateRoleUsersCommand {
        return new CreateRoleUsersCommand(
            $em,
            $this->createMock(UserPasswordHasherInterface::class),
            $context ?? (new \ReflectionClass(DevBootstrapContextService::class))->newInstanceWithoutConstructor(),
            (new \ReflectionClass(DemoSupplierSeedService::class))->newInstanceWithoutConstructor(),
            (new \ReflectionClass(DemoGrossanlassSeedService::class))->newInstanceWithoutConstructor(),
            $guard,
            (new \ReflectionClass(TotpService::class))->newInstanceWithoutConstructor(),
        );
    }
}
