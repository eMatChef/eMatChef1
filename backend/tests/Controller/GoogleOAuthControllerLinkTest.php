<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\GoogleOAuthController;
use App\Entity\User;
use App\Entity\UserSession;
use App\Enum\AuthMethod;
use App\Service\Auth\CurrentAuthSession;
use App\Service\Auth\ExternalIdentityException;
use App\Service\Auth\ExternalIdentityService;
use App\Service\Auth\GoogleOAuthAccountService;
use App\Service\Auth\GoogleOAuthClient;
use App\Service\Auth\GoogleOAuthState;
use App\Service\Auth\MfaChallengeService;
use App\Service\Auth\LinkResultStore;
use App\Service\Auth\OAuthCallbackSessionResolver;
use App\Service\Auth\OAuthStateReplayGuard;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Google verbinden für angemeldete User: gebundener State, kein Login, kein neuer User, keine fremde Identität.
 */
final class GoogleOAuthControllerLinkTest extends TestCase
{
    private GoogleOAuthState $state;

    private CurrentAuthSession $currentSession;

    private UserSession $session;

    private User $me;

    /** @var list<array<string, mixed>> */
    private array $attached = [];

    private ?\Throwable $attachFails = null;

    private bool $loginResolved = false;

    private bool $tokensIssued = false;

    private LinkResultStore $linkResults;

    private ?UserSession $resolvedSession = null;

    private string $googleNonce = '';

    protected function setUp(): void
    {
        $this->state = new GoogleOAuthState('test-secret');
        $this->me = (new User())->setId('user00000001');
        $this->session = new UserSession($this->me, AuthMethod::PASSWORD, 'UA');
        $this->currentSession = new CurrentAuthSession();
        $this->currentSession->setAuthenticated($this->session);
        $this->attached = [];
        $this->attachFails = null;
        $this->loginResolved = false;
        $this->tokensIssued = false;
        $this->linkResults = new LinkResultStore(new ArrayAdapter());
    }

    public function testLinkStartReturnsGoogleUrlWithBoundStatePkceAndNonce(): void
    {
        $response = $this->controller($this->me)->linkStart(
            Request::create('/api/auth/link/google', 'POST', [], [], [], [], (string) json_encode(['redirect' => '/dashboard'])),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $url = json_decode((string) $response->getContent(), true)['authorization_url'];
        self::assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        self::assertSame('S256', $q['code_challenge_method']);
        self::assertNotEmpty($q['nonce']);

        $cookie = array_values(array_filter($response->headers->getCookies(), static fn (Cookie $c): bool => $c->getName() === GoogleOAuthState::COOKIE_NAME))[0];
        self::assertTrue($cookie->isHttpOnly());
        $verified = $this->state->verifyDetailed((string) $cookie->getValue(), (string) $q['state']);
        self::assertSame('user00000001', $verified['link_user_id']);
        self::assertSame($this->session->getId(), $verified['session_id']);
        self::assertSame($q['nonce'], $verified['nonce']);
        self::assertSame('/dashboard', $verified['redirect']);
    }

    public function testLinkStartRejectsAnonymousSessionlessAndUnconfiguredAndOpenRedirects(): void
    {
        self::assertSame(401, $this->controller(null)->linkStart(Request::create('/api/auth/link/google', 'POST'))->getStatusCode());

        $this->currentSession->setAuthenticated(null);
        self::assertSame(409, $this->controller($this->me)->linkStart(Request::create('/api/auth/link/google', 'POST'))->getStatusCode());

        $this->currentSession->setAuthenticated($this->session);
        self::assertSame(503, $this->controller($this->me, false)->linkStart(Request::create('/api/auth/link/google', 'POST'))->getStatusCode());

        $response = $this->controller($this->me)->linkStart(
            Request::create('/api/auth/link/google', 'POST', [], [], [], [], (string) json_encode(['redirect' => 'https://evil.example/x'])),
        );
        $cookie = array_values(array_filter($response->headers->getCookies(), static fn (Cookie $c): bool => $c->getName() === GoogleOAuthState::COOKIE_NAME))[0];
        $state = json_decode((string) $response->getContent(), true)['authorization_url'];
        parse_str((string) parse_url($state, PHP_URL_QUERY), $q);
        self::assertSame('', $this->state->verifyDetailed((string) $cookie->getValue(), (string) $q['state'])['redirect']);
    }

    public function testCallbackLinksTheIdentityToTheOriginalUserWithoutLoginOrNewUser(): void
    {
        $issued = $this->state->issue('/dashboard', 'user00000001', $this->session->getId());
        $this->googleNonce = $issued['nonce'];

        $response = $this->controller($this->me)->callback($this->callbackRequest($issued));

        self::assertSame([['user00000001', 'google', 'g-sub-1', 'anna@gmail.test', 'Anna Muster']], array_map(
            static fn (array $a): array => [$a['user'], $a['provider'], $a['sub'], $a['email'], $a['name']],
            $this->attached,
        ));
        self::assertFalse($this->loginResolved, 'kein resolveOrCreate: nie ein neuer User, nie ein Wechsel der Identität');
        self::assertFalse($this->tokensIssued, 'keine neuen Tokens');
        $location = (string) $response->headers->get('Location');
        self::assertStringStartsWith('https://app.ematchef.test/dashboard?', $location);
        self::assertSame('https://app.ematchef.test/dashboard?profile_security=1', $location, 'URL trägt nur den Hinweis, kein Ergebnis');
        self::assertSame(['provider' => 'google', 'status' => 'linked', 'reason' => null], $this->linkResults->take($this->session->getId()));
        self::assertNull($this->linkResults->take($this->session->getId()), 'Ergebnis ist einmalig');
        $clear = array_values(array_filter($response->headers->getCookies(), static fn (Cookie $c): bool => $c->getName() === GoogleOAuthState::COOKIE_NAME));
        self::assertSame(1, $clear[0]->getExpiresTime());
    }

    public function testCallbackBoundToAnotherSessionOrUserLinksNothing(): void
    {
        foreach ([
            [$this->me, 'other-session-id', 'user00000001'],
            [(new User())->setId('user00000002'), $this->session->getId(), 'user00000001'],
        ] as [$loggedIn, $sid, $linkUser]) {
            $issued = $this->state->issue('/dashboard', $linkUser, $sid);
            $this->googleNonce = $issued['nonce'];

            $response = $this->controller($loggedIn)->callback($this->callbackRequest($issued));

            self::assertSame('https://app.ematchef.test/dashboard?profile_security=1', (string) $response->headers->get('Location'));
            self::assertSame('session_mismatch', $this->linkResults->take($this->resolvedSession->getId())['reason'] ?? 'none');
        }
        self::assertSame([], $this->attached);
        self::assertFalse($this->loginResolved);
    }

    public function testExpiredOrMissingJwtGivesASafeWayBackToLoginAndLinksNothing(): void
    {
        $issued = $this->state->issue('/dashboard', 'user00000001', $this->session->getId());
        $this->googleNonce = $issued['nonce'];

        $response = $this->controller(null)->callback($this->callbackRequest($issued));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://app.ematchef.test/login?oauth=error&reason=session_expired', $response->headers->get('Location'));
        self::assertSame([], $this->attached);
        self::assertFalse($this->loginResolved);
        self::assertFalse($this->tokensIssued);
    }

    public function testAbortAtGoogleDuringLinkReturnsToTheProfileNotToLogin(): void
    {
        $issued = $this->state->issue('/dashboard', 'user00000001', $this->session->getId());
        $request = Request::create('/api/auth/google/callback', 'GET', ['error' => 'access_denied', 'state' => $issued['token']]);
        $request->cookies->set(GoogleOAuthState::COOKIE_NAME, $issued['cookieValue']);

        $location = (string) $this->controller($this->me)->callback($request)->headers->get('Location');

        self::assertSame('https://app.ematchef.test/dashboard?profile_security=1', $location);
        self::assertSame(['provider' => 'google', 'status' => 'error', 'reason' => 'denied'], $this->linkResults->take($this->session->getId()));
        self::assertSame([], $this->attached);
    }

    /** @return iterable<string, array{string|null, string}> Rückweg des Logins je Einstiegskontext => Ziel nach erfolgreichem Callback */
    public static function loginEntries(): iterable
    {
        yield 'normal login' => [null, 'https://app.ematchef.test/login?oauth=ok'];
        yield 'email invitation' => ['/pending-assignment?join_code=AB12CD34&invite_id=inv1&department_id=d1&auto_join=1', 'https://app.ematchef.test/pending-assignment?join_code=AB12CD34&invite_id=inv1&department_id=d1&auto_join=1'];
        yield 'QR / join code' => ['/pending-assignment?join_code=AB12CD34', 'https://app.ematchef.test/pending-assignment?join_code=AB12CD34'];
        yield 'protected route' => ['/d0000000001/materials?filter=x', 'https://app.ematchef.test/d0000000001/materials?filter=x'];
        yield 'login page as return path' => ['/login', 'https://app.ematchef.test/login?oauth=ok'];
    }

    #[DataProvider('loginEntries')]
    public function testNormalLoginNavigatesToTheOriginalEntryAndNeverOpensProfileSecurity(?string $redirect, string $expected): void
    {
        $issued = $this->state->issue($redirect);
        $this->googleNonce = $issued['nonce'];

        $response = $this->controller(null)->callback($this->callbackRequest($issued));

        self::assertSame($expected, $response->headers->get('Location'));
        self::assertStringNotContainsString('profile_security', (string) $response->headers->get('Location'));
        self::assertTrue($this->loginResolved);
        self::assertSame([], $this->attached);
        self::assertNull($this->linkResults->take($this->session->getId()), 'ein Login erzeugt nie ein Link-Ergebnis');
    }

    public function testReturnMarkerInAUserSuppliedLoginRedirectNeverReachesTheNavigation(): void
    {
        $request = Request::create('/api/auth/google', 'GET', ['redirect' => '/d0000000001?profile_security=1&oauth=linked']);
        $start = $this->controller(null)->start($request);
        parse_str((string) parse_url((string) $start->headers->get('Location'), PHP_URL_QUERY), $q);
        $cookie = array_values(array_filter($start->headers->getCookies(), static fn (Cookie $c): bool => $c->getName() === GoogleOAuthState::COOKIE_NAME))[0];
        $this->googleNonce = (string) $q['nonce'];

        $response = $this->controller(null)->callback($this->callbackRequest(['token' => (string) $q['state'], 'cookieValue' => (string) $cookie->getValue()]));

        self::assertSame('https://app.ematchef.test/d0000000001', $response->headers->get('Location'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function abortedEntries(): iterable
    {
        yield 'invitation' => ['/pending-assignment?join_code=AB12CD34&invite_id=inv1&department_id=d1', 'redirect=%2Fpending-assignment%3Fjoin_code%3DAB12CD34%26invite_id%3Dinv1%26department_id%3Dd1'];
        yield 'join code' => ['/pending-assignment?join_code=AB12CD34', 'redirect=%2Fpending-assignment%3Fjoin_code%3DAB12CD34'];
        yield 'protected route' => ['/d0000000001/materials', 'redirect=%2Fd0000000001%2Fmaterials'];
    }

    #[DataProvider('abortedEntries')]
    public function testAbortedLoginReturnsToTheOriginalEntryWithAMessage(string $redirect, string $expectedQuery): void
    {
        $issued = $this->state->issue($redirect);
        $request = Request::create('/api/auth/google/callback', 'GET', ['error' => 'access_denied', 'state' => $issued['token']]);
        $request->cookies->set(GoogleOAuthState::COOKIE_NAME, $issued['cookieValue']);

        $location = (string) $this->controller(null)->callback($request)->headers->get('Location');

        self::assertStringStartsWith('https://app.ematchef.test/login?oauth=error&reason=denied&', $location);
        self::assertStringContainsString($expectedQuery, $location);
        self::assertStringNotContainsString('profile_security', $location);
    }

    public function testAbortedPlainLoginHasNoReturnContext(): void
    {
        $issued = $this->state->issue(null);
        $request = Request::create('/api/auth/google/callback', 'GET', ['error' => 'access_denied', 'state' => $issued['token']]);
        $request->cookies->set(GoogleOAuthState::COOKIE_NAME, $issued['cookieValue']);

        self::assertSame('https://app.ematchef.test/login?oauth=error&reason=denied', $this->controller(null)->callback($request)->headers->get('Location'));
    }

    public function testNormalLoginIsNotAffectedByTheNewNonceAndPkceChecks(): void
    {
        $issued = $this->state->issue('/dashboard');
        $this->googleNonce = $issued['nonce'];

        $response = $this->controller(null)->callback($this->callbackRequest($issued));

        self::assertTrue($this->loginResolved, 'regulärer Login läuft über resolveOrCreate');
        self::assertSame([], $this->attached);
        self::assertStringNotContainsString('reason=', (string) $response->headers->get('Location'));
    }

    public function testNormalLoginWithWrongNonceIsRejectedWithoutLogin(): void
    {
        $issued = $this->state->issue('/dashboard');
        $this->googleNonce = 'someone-elses-nonce';

        $response = $this->controller(null)->callback($this->callbackRequest($issued));

        self::assertFalse($this->loginResolved);
        self::assertStringContainsString('reason=invalid_state', (string) $response->headers->get('Location'));
    }

    public function testLoginStateIssuedBeforeTheUpdateWithoutNonceStillWorks(): void
    {
        $old = $this->state->issue('/dashboard');
        $payload = json_decode((string) base64_decode(strtr(explode('.', $old['cookieValue'])[0], '-_', '+/')), true);
        unset($payload['oidc'], $payload['v'], $payload['link'], $payload['sid']);
        $json = (string) json_encode($payload);
        $legacyCookie = rtrim(strtr(base64_encode($json), '+/', '-_'), '=') . '.' . hash_hmac('sha256', $json, 'test-secret');
        $this->googleNonce = 'irrelevant';

        $response = $this->controller(null)->callback($this->callbackRequest(['token' => $old['token'], 'cookieValue' => $legacyCookie]));

        self::assertTrue($this->loginResolved, 'Cookies aus der Zeit vor dem Update (ohne Nonce/PKCE) dürfen laufende Logins nicht brechen');
        self::assertStringNotContainsString('reason=', (string) $response->headers->get('Location'));
    }

    public function testCallbackStateIsSingleUse(): void
    {
        $issued = $this->state->issue('/dashboard', 'user00000001', $this->session->getId());
        $this->googleNonce = $issued['nonce'];
        $controller = $this->controller($this->me);

        $first = $controller->callback($this->callbackRequest($issued));
        $replay = $controller->callback($this->callbackRequest($issued));

        self::assertSame('https://app.ematchef.test/dashboard?profile_security=1', $first->headers->get('Location'));
        self::assertSame('linked', $this->linkResults->take($this->session->getId())['status'] ?? null);
        self::assertStringContainsString('reason=invalid_state', (string) $replay->headers->get('Location'));
        self::assertCount(1, $this->attached);
    }

    public function testCallbackWithWrongNonceFromGoogleLinksNothing(): void
    {
        $issued = $this->state->issue('/dashboard', 'user00000001', $this->session->getId());
        $this->googleNonce = 'someone-elses-nonce';

        $response = $this->controller($this->me)->callback($this->callbackRequest($issued));

        self::assertSame('https://app.ematchef.test/dashboard?profile_security=1', $response->headers->get('Location'));
        self::assertSame('invalid_state', $this->linkResults->take($this->session->getId())['reason'] ?? null);
        self::assertSame([], $this->attached);
    }

    public function testIdentityOfAnotherUserIsReportedAsConflict(): void
    {
        $this->attachFails = new ExternalIdentityException(ExternalIdentityException::LINK_CONFLICT, 'x');
        $issued = $this->state->issue('/dashboard', 'user00000001', $this->session->getId());
        $this->googleNonce = $issued['nonce'];

        $response = $this->controller($this->me)->callback($this->callbackRequest($issued));

        self::assertSame('https://app.ematchef.test/dashboard?profile_security=1', $response->headers->get('Location'));
        self::assertSame(['provider' => 'google', 'status' => 'error', 'reason' => 'link_conflict'], $this->linkResults->take($this->session->getId()));
    }

    public function testDeniedAtGoogleAndInvalidCookieNeverReachLinking(): void
    {
        $denied = $this->controller($this->me)->callback(Request::create('/api/auth/google/callback', 'GET', ['error' => 'access_denied']));
        self::assertStringContainsString('reason=denied', (string) $denied->headers->get('Location'));

        $issued = $this->state->issue('/dashboard', 'user00000001', $this->session->getId());
        $noCookie = $this->controller($this->me)->callback(Request::create('/api/auth/google/callback', 'GET', ['state' => $issued['token'], 'code' => 'c']));
        self::assertStringContainsString('reason=invalid_state', (string) $noCookie->headers->get('Location'));
        self::assertSame([], $this->attached);
    }

    /** @param array{token: string, cookieValue: string} $issued */
    private function callbackRequest(array $issued): Request
    {
        $request = Request::create('/api/auth/google/callback', 'GET', ['state' => $issued['token'], 'code' => 'auth-code']);
        $request->cookies->set(GoogleOAuthState::COOKIE_NAME, $issued['cookieValue']);

        return $request;
    }

    private function controller(?User $loggedIn, bool $configured = true): GoogleOAuthController
    {
        $http = new MockHttpClient(function (string $method, string $url): MockResponse {
            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                $claims = rtrim(strtr(base64_encode((string) json_encode(['nonce' => $this->googleNonce, 'sub' => 'g-sub-1'])), '+/', '-_'), '=');

                return new MockResponse((string) json_encode(['access_token' => 'AT-SECRET', 'id_token' => 'h.' . $claims . '.s']));
            }

            return new MockResponse((string) json_encode(['sub' => 'g-sub-1', 'email' => 'anna@gmail.test', 'email_verified' => true, 'given_name' => 'Anna', 'family_name' => 'Muster']));
        });
        $client = new GoogleOAuthClient($http, 'https://app.ematchef.test', $configured ? 'id' : '', $configured ? 'secret' : '', '');

        $account = $this->createMock(GoogleOAuthAccountService::class);
        $account->method('resolveOrCreate')->willReturnCallback(function (): User {
            $this->loginResolved = true;

            return $this->me;
        });
        $identities = $this->createMock(ExternalIdentityService::class);
        $identities->method('attach')->willReturnCallback(function (User $user, string $provider, string $sub, ?string $email, ?string $name): \App\Entity\ExternalIdentity {
            if ($this->attachFails !== null) {
                throw $this->attachFails;
            }
            $this->attached[] = ['user' => $user->getId(), 'provider' => $provider, 'sub' => $sub, 'email' => $email, 'name' => $name];

            return new \App\Entity\ExternalIdentity();
        });
        // OAuth-Firewall ohne Benutzer: die Sitzung kommt aus dem geprüften BEARER-Cookie (hier: wer eingeloggt ist).
        $resolver = $this->createMock(OAuthCallbackSessionResolver::class);
        $resolver->method('resolve')->willReturnCallback(fn (): ?UserSession => $this->resolvedSession = $loggedIn === null
            ? null
            : ($loggedIn === $this->me ? $this->session : new UserSession($loggedIn, AuthMethod::PASSWORD, 'UA')));
        $success = $this->createMock(AuthenticationSuccessHandler::class);
        $success->method('handleAuthenticationSuccess')->willReturnCallback(function () {
            $this->tokensIssued = true;

            return new \Symfony\Component\HttpFoundation\Response();
        });

        $controller = new GoogleOAuthController(
            $client,
            $this->state,
            $account,
            $this->createMock(MfaChallengeService::class),
            $identities,
            $this->currentSession,
            new OAuthStateReplayGuard(new ArrayAdapter()),
            $resolver,
            $this->linkResults,
            $success,
        );
        $storage = new TokenStorage();
        if ($loggedIn !== null) {
            $storage->setToken(new UsernamePasswordToken($loggedIn, 'api', ['ROLE_USER']));
        }
        $container = new Container();
        $container->set('security.token_storage', $storage);
        $controller->setContainer($container);

        return $controller;
    }
}
