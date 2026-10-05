<?php

declare(strict_types=1);

namespace App\Tests\Service\Admin;

use App\Entity\Profile;
use App\Entity\User;
use App\Service\Admin\AdminUserEmailChangeRequester;
use App\Service\Admin\AdminUserUpdateDeniedException;
use App\Service\AuditLogger;
use App\Service\UserEmailAliasService;
use App\Service\VerificationEmailService;
use PHPUnit\Framework\TestCase;

final class AdminUserEmailChangeRequesterTest extends TestCase
{
    public function testUsesPendingFlowAndKeepsLoginEmailUntilVerified(): void
    {
        $actor = $this->user('sa_actor', 'admin@example.ch');
        $target = $this->user('user_1', 'old@example.ch');

        $aliases = $this->createMock(UserEmailAliasService::class);
        $aliases->expects(self::once())->method('isEmailTaken')->with('new@example.ch', $target)->willReturn(false);

        $mailer = $this->createMock(VerificationEmailService::class);
        $mailer->expects(self::once())
            ->method('sendPendingEmailChangeVerification')
            ->with($target, 'new@example.ch', self::isType('string'), self::isInstanceOf(\DateTime::class));

        $audit = $this->createMock(AuditLogger::class);
        $audit->expects(self::once())
            ->method('log')
            ->with('profile', 'p_user_1', 'profile_email_change_requested', $actor, $target, null, self::callback(
                static fn (array $changes): bool => $changes['email'] === ['old' => 'old@example.ch', 'new' => 'new@example.ch']
                    && $changes['source'] === ['old' => null, 'new' => 'admin']
            ));

        (new AdminUserEmailChangeRequester($aliases, $mailer, $audit))->request($actor, $target, ' New@Example.ch ');

        self::assertSame('old@example.ch', $target->getProfile()?->getEmail());
        self::assertSame('new@example.ch', $target->getPendingEmail());
        self::assertSame(64, \strlen((string) $target->getEmailVerificationToken()));
        self::assertGreaterThan(new \DateTime(), $target->getEmailVerificationExpiresAt());
    }

    public function testDoesNotTouchOpenRegistrationOfUnverifiedUser(): void
    {
        $target = $this->user('user_1', 'old@example.ch');
        $registrationExpiry = new \DateTime('+5 days');
        $target->setEmailVerified(false);
        $target->setEmailVerificationToken('registration-token');
        $target->setEmailVerificationExpiresAt($registrationExpiry);

        $aliases = $this->createMock(UserEmailAliasService::class);
        $aliases->expects(self::never())->method('isEmailTaken');
        $mailer = $this->createMock(VerificationEmailService::class);
        $mailer->expects(self::never())->method('sendPendingEmailChangeVerification');
        $audit = $this->createMock(AuditLogger::class);
        $audit->expects(self::never())->method('log');

        $this->assertDenied(409, fn () => (new AdminUserEmailChangeRequester($aliases, $mailer, $audit))
            ->request($this->user('sa_actor', 'admin@example.ch'), $target, 'new@example.ch'));

        self::assertFalse($target->isEmailVerified());
        self::assertSame('registration-token', $target->getEmailVerificationToken());
        self::assertSame($registrationExpiry, $target->getEmailVerificationExpiresAt());
        self::assertNull($target->getPendingEmail());
        self::assertSame('old@example.ch', $target->getProfile()?->getEmail());
    }

    public function testRejectsEmailOwnedByAnotherAccount(): void
    {
        $target = $this->user('user_1', 'old@example.ch');

        $aliases = $this->createMock(UserEmailAliasService::class);
        $aliases->method('isEmailTaken')->willReturn(true);
        $mailer = $this->createMock(VerificationEmailService::class);
        $mailer->expects(self::never())->method('sendPendingEmailChangeVerification');
        $audit = $this->createMock(AuditLogger::class);
        $audit->expects(self::never())->method('log');

        $this->assertDenied(409, fn () => (new AdminUserEmailChangeRequester($aliases, $mailer, $audit))
            ->request($this->user('sa_actor', 'admin@example.ch'), $target, 'taken@example.ch'));

        self::assertNull($target->getPendingEmail());
        self::assertSame('old@example.ch', $target->getProfile()?->getEmail());
    }

    public function testRejectsInvalidEmail(): void
    {
        $aliases = $this->createMock(UserEmailAliasService::class);
        $aliases->expects(self::never())->method('isEmailTaken');

        $this->assertDenied(400, fn () => (new AdminUserEmailChangeRequester(
            $aliases,
            $this->createMock(VerificationEmailService::class),
            $this->createMock(AuditLogger::class)
        ))->request($this->user('sa_actor', 'admin@example.ch'), $this->user('user_1', 'old@example.ch'), 'kein-mail'));
    }

    public function testRestoresPreviousPendingStateWhenMailFails(): void
    {
        $target = $this->user('user_1', 'old@example.ch');
        $previousExpiry = new \DateTime('+1 day');
        $target->setPendingEmail('earlier@example.ch');
        $target->setEmailVerificationToken('previous-token');
        $target->setEmailVerificationExpiresAt($previousExpiry);

        $aliases = $this->createMock(UserEmailAliasService::class);
        $aliases->method('isEmailTaken')->willReturn(false);
        $mailer = $this->createMock(VerificationEmailService::class);
        $mailer->method('sendPendingEmailChangeVerification')->willThrowException(new \RuntimeException('smtp down'));
        $audit = $this->createMock(AuditLogger::class);
        $audit->expects(self::never())->method('log');

        $this->assertDenied(400, fn () => (new AdminUserEmailChangeRequester($aliases, $mailer, $audit))
            ->request($this->user('sa_actor', 'admin@example.ch'), $target, 'new@example.ch'));

        self::assertSame('earlier@example.ch', $target->getPendingEmail());
        self::assertSame('previous-token', $target->getEmailVerificationToken());
        self::assertSame($previousExpiry, $target->getEmailVerificationExpiresAt());
        self::assertSame('old@example.ch', $target->getProfile()?->getEmail());
    }

    private function assertDenied(int $expectedStatus, callable $call): void
    {
        try {
            $call();
            self::fail('Expected AdminUserUpdateDeniedException');
        } catch (AdminUserUpdateDeniedException $e) {
            self::assertSame($expectedStatus, $e->statusCode);
        }
    }

    private function user(string $id, string $email): User
    {
        $profile = new Profile();
        $profile->setId('p_' . $id);
        $profile->setEmail($email);

        $user = new User();
        $user->setId($id);
        $user->setProfileId('p_' . $id);
        $user->setProfile($profile);
        $user->setEmailVerified(true);

        return $user;
    }
}
