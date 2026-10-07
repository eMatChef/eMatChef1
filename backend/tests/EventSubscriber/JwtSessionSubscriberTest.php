<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Entity\UserSession;
use App\Enum\AuthMethod;
use App\EventSubscriber\JwtSessionSubscriber;
use App\Repository\UserSessionRepository;
use App\Service\Auth\CurrentAuthSession;
use App\Service\Auth\LegacySessionCutoff;
use App\Service\Auth\UserSessionManager;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\Token\PostRefreshTokenAuthenticationToken;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class JwtSessionSubscriberTest extends TestCase
{
    private CurrentAuthSession $current;

    private TokenStorage $tokenStorage;

    private RequestStack $requestStack;

    /** @var list<AuthMethod> */
    private array $started = [];

    /** @var list<array{UserSession, string}> */
    private array $revoked = [];

    /** @var list<UserSession> */
    private array $touched = [];

    /** @var array<string, UserSession> */
    private array $sessions = [];

    private bool $legacyJwtAllowed = false;

    protected function setUp(): void
    {
        $this->current = new CurrentAuthSession();
        $this->tokenStorage = new TokenStorage();
        $this->requestStack = new RequestStack();
    }

    public function testPasswordLoginStartsOneSessionAndAddsSid(): void
    {
        $user = $this->user('u1');
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'login', $user->getRoles()));
        $this->pushRequest('api_auth_login_check');

        $payload = $this->createJwt($user);

        self::assertSame([AuthMethod::PASSWORD], $this->started);
        self::assertSame($this->current->getIssued()?->getId(), $payload['sid']);
        self::assertSame('p_u1', $payload['username']);
    }

    public function testGoogleCallbackStartsGoogleSession(): void
    {
        $user = $this->user('u1');
        $this->pushRequest('api_auth_google_callback');

        $payload = $this->createJwt($user);

        self::assertSame([AuthMethod::GOOGLE], $this->started);
        self::assertSame($this->current->getIssued()?->getId(), $payload['sid']);
    }

    public function testMiDataCallbackStartsMiDataSession(): void
    {
        $user = $this->user('u1');
        $this->pushRequest('api_auth_midata_callback');

        $payload = $this->createJwt($user);

        self::assertSame([AuthMethod::MIDATA], $this->started);
        self::assertSame($this->current->getIssued()?->getId(), $payload['sid']);
    }

    public function testRefreshReusesResolvedSessionWithoutStartingANewOne(): void
    {
        $user = $this->user('u1');
        $session = new UserSession($user, AuthMethod::PASSWORD);
        $this->current->setIssued($session);
        $this->tokenStorage->setToken(new PostRefreshTokenAuthenticationToken($user, 'refresh', [], (new RefreshToken())->setSession($session)));
        $this->pushRequest('api_token_refresh', 'old-refresh');

        $payload = $this->createJwt($user);

        self::assertSame([], $this->started);
        self::assertSame([], $this->revoked);
        self::assertSame($session->getId(), $payload['sid']);
    }

    public function testRefreshWithoutResolvedSessionIssuesNoJwt(): void
    {
        $user = $this->user('u1');
        $this->tokenStorage->setToken(new PostRefreshTokenAuthenticationToken($user, 'refresh', [], new RefreshToken()));
        $this->pushRequest('api_token_refresh');

        $this->expectException(\LogicException::class);
        $this->createJwt($user);
    }

    public function testUnknownFlowIssuesNoJwt(): void
    {
        $this->pushRequest('api_some_other_route');

        $this->expectException(\LogicException::class);
        $this->createJwt($this->user('u1'));
    }

    public function testOtherFirewallTokenIssuesNoJwt(): void
    {
        $user = $this->user('u1');
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'api', []));
        $this->pushRequest('api_auth_google_callback');

        $this->expectException(\LogicException::class);
        $this->createJwt($user);
    }

    public function testNewLoginKeepsOtherSessionsActive(): void
    {
        $user = $this->user('u1');
        $this->pushRequest('api_auth_midata_callback', 'old-refresh');

        $this->createJwt($user);

        self::assertSame([], $this->revoked);
        self::assertSame([AuthMethod::MIDATA], $this->started);
    }

    public function testValidSessionIsAccepted(): void
    {
        $session = $this->storedSession($this->user('u1'));

        $event = $this->decode(['username' => 'p_u1', 'sid' => $session->getId(), 'iat' => time()]);

        self::assertTrue($event->isValid());
        self::assertSame($session, $this->current->getAuthenticated());
    }

    public function testUnknownSidIsRejected(): void
    {
        $event = $this->decode(['username' => 'p_u1', 'sid' => '00000000-0000-4000-8000-000000000000', 'iat' => time()]);

        self::assertFalse($event->isValid());
        self::assertNull($this->current->getAuthenticated());
    }

    public function testRevokedSessionIsRejected(): void
    {
        $session = $this->storedSession($this->user('u1'))->revoke('logout');

        self::assertFalse($this->decode(['username' => 'p_u1', 'sid' => $session->getId(), 'iat' => time()])->isValid());
    }

    public function testSessionOfAnotherUserIsRejected(): void
    {
        $session = $this->storedSession($this->user('u2'));

        self::assertFalse($this->decode(['username' => 'p_u1', 'sid' => $session->getId(), 'iat' => time()])->isValid());
    }

    public function testNonStringSidIsRejected(): void
    {
        self::assertFalse($this->decode(['username' => 'p_u1', 'sid' => ['x'], 'iat' => time()])->isValid());
    }

    public function testJwtWithoutSidIsAcceptedOnlyWithinTransition(): void
    {
        $this->legacyJwtAllowed = true;
        self::assertTrue($this->decode(['username' => 'p_u1', 'iat' => 1])->isValid());

        $this->legacyJwtAllowed = false;
        self::assertFalse($this->decode(['username' => 'p_u1', 'iat' => time()])->isValid());
    }

    public function testAuthenticatedRequestTouchesLastSeen(): void
    {
        $session = $this->storedSession($this->user('u1'));
        $this->decode(['username' => 'p_u1', 'sid' => $session->getId(), 'iat' => time()]);

        $this->subscriber()->onJwtAuthenticated(new JWTAuthenticatedEvent([], new UsernamePasswordToken($session->getUser(), 'api', [])));

        self::assertSame([$session], $this->touched);
    }

    /**
     * @return array<string, mixed>
     */
    private function createJwt(User $user): array
    {
        $event = new JWTCreatedEvent(['username' => $user->getUserIdentifier()], $user);
        $this->subscriber()->onJwtCreated($event);

        return $event->getData();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function decode(array $payload): JWTDecodedEvent
    {
        $event = new JWTDecodedEvent($payload);
        $this->subscriber()->onJwtDecoded($event);

        return $event;
    }

    private function subscriber(): JwtSessionSubscriber
    {
        $manager = $this->createMock(UserSessionManager::class);
        $manager->method('startSession')->willReturnCallback(function (User $user, AuthMethod $method): UserSession {
            $this->started[] = $method;

            return new UserSession($user, $method);
        });
        $manager->method('revokeSession')->willReturnCallback(function (UserSession $session, string $reason): void {
            $this->revoked[] = [$session, $reason];
        });
        $manager->method('touchLastSeen')->willReturnCallback(function (UserSession $session): void {
            $this->touched[] = $session;
        });

        $repository = $this->createMock(UserSessionRepository::class);
        $repository->method('findOneById')->willReturnCallback(fn (string $sid): ?UserSession => $this->sessions[$sid] ?? null);

        $cutoff = $this->createMock(LegacySessionCutoff::class);
        $cutoff->method('allowsJwtWithoutSid')->willReturnCallback(fn (): bool => $this->legacyJwtAllowed);

        return new JwtSessionSubscriber(
            $manager,
            $repository,
            $this->current,
            $cutoff,
            $this->tokenStorage,
            $this->requestStack,
        );
    }

    private function pushRequest(string $route, ?string $refreshCookie = null): void
    {
        $request = new Request();
        $request->attributes->set('_route', $route);
        if ($refreshCookie !== null) {
            $request->cookies->set('refresh_token', $refreshCookie);
        }
        $this->requestStack->push($request);
    }

    private function storedSession(User $user): UserSession
    {
        $session = new UserSession($user, AuthMethod::PASSWORD);
        $this->sessions[$session->getId()] = $session;

        return $session;
    }

    private function user(string $id): User
    {
        $user = new User();
        $user->setId($id);
        $user->setProfileId('p_' . $id);

        return $user;
    }
}
