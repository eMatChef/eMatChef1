<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\RecreateTestUsersCommand;
use App\Entity\Membership;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\Demo\DemoEnvironmentGuard;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RecreateTestUsersCommandGuardTest extends TestCase
{
    public function testBlockedWithoutDestructiveApprovalAndTouchesNothing(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getRepository');
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('remove');
        $em->expects(self::never())->method('flush');
        $guard = $this->createMock(DemoEnvironmentGuard::class);
        $guard->method('destructiveDenial')->willReturn('gesperrt');

        $tester = new CommandTester(new RecreateTestUsersCommand($em, $this->createMock(UserPasswordHasherInterface::class), $guard));

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('gesperrt', $tester->getDisplay());
    }

    public function testAbortsWhenAnExistingAccountHasMemberships(): void
    {
        $profile = new Profile();
        $profile->setId('p1');
        $user = new User();
        $user->setId('u1');

        $repos = [
            Profile::class => $this->repo($profile),
            User::class => $this->repo($user),
            Membership::class => $this->repo(new Membership()),
        ];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(static fn (string $c) => $repos[$c]);
        $em->expects(self::never())->method('remove');
        $em->expects(self::never())->method('persist');
        $guard = $this->createMock(DemoEnvironmentGuard::class);
        $guard->method('destructiveDenial')->willReturn(null);

        $tester = new CommandTester(new RecreateTestUsersCommand($em, $this->createMock(UserPasswordHasherInterface::class), $guard));

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('Mitgliedschaften', $tester->getDisplay());
    }

    private function repo(object $found): EntityRepository
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($found);

        return $repo;
    }
}
