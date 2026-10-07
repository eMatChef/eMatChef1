<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Entity\UserSession;
use App\Enum\AuthMethod;
use App\EventListener\RefreshTokenSessionCheckListener;
use App\EventListener\RefreshTokenSessionListener;
use App\Service\Auth\CurrentAuthSession;
use App\Service\Auth\UserSessionManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

final class RefreshTokenSessionListenersTest extends TestCase
{
    public function testRotatedRefreshTokenGetsIssuedSession(): void
    {
        $current = new CurrentAuthSession();
        $session = new UserSession($this->user(), AuthMethod::PASSWORD);
        $current->setIssued($session);
        $token = new RefreshToken();

        (new RefreshTokenSessionListener($current))->prePersist($token);

        self::assertSame($session, $token->getSession());
    }

    public function testExistingSessionAssignmentIsKept(): void
    {
        $current = new CurrentAuthSession();
        $current->setIssued(new UserSession($this->user(), AuthMethod::PASSWORD));
        $existing = new UserSession($this->user(), AuthMethod::GOOGLE);
        $token = (new RefreshToken())->setSession($existing);

        (new RefreshTokenSessionListener($current))->prePersist($token);

        self::assertSame($existing, $token->getSession());
    }

    public function testWithoutIssuedSessionTokenStaysUnassigned(): void
    {
        $token = new RefreshToken();

        (new RefreshTokenSessionListener(new CurrentAuthSession()))->prePersist($token);

        self::assertNull($token->getSession());
    }

    public function testRefreshCheckResolvesSessionForIssuing(): void
    {
        $user = $this->user();
        $session = new UserSession($user, AuthMethod::PASSWORD);
        $token = (new RefreshToken())->setSession($session);
        $manager = $this->createMock(UserSessionManager::class);
        $manager->expects(self::once())->method('resolveForRefresh')->with($token, $user)->willReturn($session);
        $current = new CurrentAuthSession();

        (new RefreshTokenSessionCheckListener($manager, $current))($this->checkPassportEvent($user, $token));

        self::assertSame($session, $current->getIssued());
    }

    public function testRefreshCheckPropagatesRejection(): void
    {
        $user = $this->user();
        $manager = $this->createMock(UserSessionManager::class);
        $manager->method('resolveForRefresh')->willThrowException(new CustomUserMessageAuthenticationException('Sitzung ungültig.'));
        $current = new CurrentAuthSession();

        try {
            (new RefreshTokenSessionCheckListener($manager, $current))($this->checkPassportEvent($user, new RefreshToken()));
            self::fail('Expected rejection');
        } catch (CustomUserMessageAuthenticationException) {
            self::assertNull($current->getIssued());
        }
    }

    private function checkPassportEvent(User $user, RefreshToken $token): CheckPassportEvent
    {
        $passport = new SelfValidatingPassport(new UserBadge('p_u1', static fn (): User => $user));
        $passport->setAttribute('refreshToken', $token);

        return new CheckPassportEvent($this->createMock(AuthenticatorInterface::class), $passport);
    }

    private function user(): User
    {
        $user = new User();
        $user->setId('u1');
        $user->setProfileId('p_u1');

        return $user;
    }
}
