<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class UserCheckerTest extends TestCase
{
    public function testActiveUserIsAccepted(): void
    {
        $checker = new UserChecker();
        $user = $this->user('active');

        $checker->checkPreAuth($user);
        $checker->checkPostAuth($user);
        $this->addToAssertionCount(1);
    }

    public function testInactiveUserIsRejected(): void
    {
        $this->expectException(AccountStatusException::class);

        (new UserChecker())->checkPostAuth($this->user('inactive'));
    }

    public function testDisabledUserIsRejected(): void
    {
        $this->expectException(AccountStatusException::class);

        (new UserChecker())->checkPostAuth($this->user('disabled'));
    }

    public function testUnknownStateIsRejected(): void
    {
        $this->expectException(AccountStatusException::class);

        (new UserChecker())->checkPostAuth($this->user('pending'));
    }

    public function testPreAuthDoesNotRevealAccountStateBeforeCredentialsAreChecked(): void
    {
        (new UserChecker())->checkPreAuth($this->user('disabled'));
        $this->addToAssertionCount(1);
    }

    public function testIgnoresForeignUserImplementations(): void
    {
        (new UserChecker())->checkPostAuth(new InMemoryUser('x', null));
        $this->addToAssertionCount(1);
    }

    private function user(string $state): User
    {
        $user = new User();
        $user->setId('user_1');
        $user->setProfileId('profile_1');
        $user->setState($state);

        return $user;
    }
}
