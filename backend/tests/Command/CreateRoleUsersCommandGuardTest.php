<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CreateRoleUsersCommand;
use App\Service\Auth\TotpService;
use App\Service\Bootstrap\DemoGrossanlassSeedService;
use App\Service\Bootstrap\DemoSupplierSeedService;
use App\Service\Bootstrap\DevBootstrapContextService;
use App\Service\DevEnvironmentService;
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
        $devTools = $this->createMock(DevEnvironmentService::class);
        $devTools->method('isDevToolsEnabled')->willReturn(false);

        $command = new CreateRoleUsersCommand(
            $em,
            $this->createMock(UserPasswordHasherInterface::class),
            (new \ReflectionClass(DevBootstrapContextService::class))->newInstanceWithoutConstructor(),
            (new \ReflectionClass(DemoSupplierSeedService::class))->newInstanceWithoutConstructor(),
            (new \ReflectionClass(DemoGrossanlassSeedService::class))->newInstanceWithoutConstructor(),
            $devTools,
            (new \ReflectionClass(TotpService::class))->newInstanceWithoutConstructor(),
        );
        $tester = new CommandTester($command);

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('Dev-Tools sind deaktiviert', $tester->getDisplay());
    }
}
