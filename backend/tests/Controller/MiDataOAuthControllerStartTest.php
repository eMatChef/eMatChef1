<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\MiDataOAuthController;
use App\Entity\User;
use App\Service\Auth\DepartmentJoinFlowService;
use App\Service\Auth\HitobitoOAuthClient;
use App\Service\Auth\MiDataDepartmentOnboardingService;
use App\Service\Auth\MiDataOAuthAccountService;
use App\Service\Auth\MiDataOAuthState;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Only a normal MiData login forces re-authentication; link and onboarding flows do not.
 */
final class MiDataOAuthControllerStartTest extends TestCase
{
    private MiDataOAuthState $state;

    private \App\Service\Auth\CurrentAuthSession $currentSession;

    private \App\Service\Auth\AdminMfaGuard $mfaGuard;

    private ?string $guardDenial = null;

    private string $lastResolvedSessionId = '';

    private \App\Service\Auth\LinkResultStore $linkResults;

    protected function setUp(): void
    {
        $this->state = new MiDataOAuthState('test-secret');
        $this->currentSession = new \App\Service\Auth\CurrentAuthSession();
        $this->currentSession->setAuthenticated(new \App\Entity\UserSession($this->user(), \App\Enum\AuthMethod::PASSWORD, 'UA'));
        $this->guardDenial = null;
        $this->linkResults = new \App\Service\Auth\LinkResultStore(new \Symfony\Component\Cache\Adapter\ArrayAdapter());
        $this->mfaGuard = $this->createMock(\App\Service\Auth\AdminMfaGuard::class);
        $this->mfaGuard->method('denialReason')->willReturnCallback(fn (): ?string => $this->guardDenial);
    }

    public function testNormalLoginRequestsPromptLogin(): void
    {
        $response = $this->controller()->start(Request::create('/api/auth/midata', 'GET', ['redirect' => '/dashboard']));

        $query = $this->authorizationQuery($response);
        self::assertSame('login', $query['prompt']);
        $verified = $this->verifiedState($response, $query);
        self::assertSame('/dashboard', $verified['redirect']);
        self::assertNull($verified['link_user_id']);
    }

    public function testLinkFlowDoesNotRequestPromptLogin(): void
    {
        $response = $this->controller($this->user())->linkStart(
            Request::create('/api/auth/link/midata', 'GET', ['redirect' => '/account/security']),
        );

        $query = $this->authorizationQuery($response);
        self::assertArrayNotHasKey('prompt', $query);
        $verified = $this->verifiedState($response, $query);
        self::assertSame('/account/security', $verified['redirect']);
        self::assertSame('user00000001', $verified['link_user_id']);
    }

    public function testOnboardingFlowDoesNotRequestPromptLogin(): void
    {
        $response = $this->controller($this->user())->linkStart(
            Request::create('/api/auth/link/midata', 'GET', ['redirect' => '/pending-assignment?midata_onboarding=0a1b2c3d4e5f']),
        );

        $query = $this->authorizationQuery($response);
        self::assertArrayNotHasKey('prompt', $query);
        $verified = $this->verifiedState($response, $query);
        self::assertSame('0a1b2c3d4e5f', $this->state->extractDepartmentOnboardingIntent($verified['redirect']));
        self::assertSame('user00000001', $verified['link_user_id']);
    }

    public function testNormalLoginKeepsStateNonceAndPkce(): void
    {
        $response = $this->controller()->start(Request::create('/api/auth/midata'));

        $query = $this->authorizationQuery($response);
        $verified = $this->verifiedState($response, $query);
        self::assertSame($verified['nonce'], $query['nonce']);
        self::assertSame('S256', $query['code_challenge_method']);
        self::assertSame(
            rtrim(strtr(base64_encode(hash('sha256', $verified['code_verifier'], true)), '+/', '-_'), '='),
            $query['code_challenge'],
        );
        self::assertSame('https://app.ematchef.test/api/auth/midata/callback', $query['redirect_uri']);
    }

    public function testProfileLinkStartReturnsUrlAndBindsStateToUserSessionAndProfileLink(): void
    {
        $response = $this->controller($this->user())->profileLinkStart(
            Request::create('/api/auth/link/midata', 'POST', [], [], [], [], (string) json_encode(['redirect' => '/dashboard'])),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $url = json_decode((string) $response->getContent(), true)['authorization_url'];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        // Ein weiteres Konto wird bewusst gewählt: Anmeldung wird erzwungen; PKCE und Nonce bleiben aktiv.
        self::assertSame('login', $query['prompt']);
        self::assertSame('S256', $query['code_challenge_method']);
        $cookie = array_values(array_filter($response->headers->getCookies(), static fn (Cookie $c): bool => $c->getName() === MiDataOAuthState::COOKIE_NAME))[0];
        $verified = $this->state->verify((string) $cookie->getValue(), (string) $query['state']);
        self::assertSame('user00000001', $verified['link_user_id']);
        self::assertSame($this->currentSession->getAuthenticated()->getId(), $verified['session_id']);
        self::assertTrue($verified['profile_link']);
        self::assertSame('/dashboard', $verified['redirect']);
    }

    public function testProfileLinkStartRequiresLoginAndSession(): void
    {
        self::assertSame(401, $this->controller()->profileLinkStart(Request::create('/api/auth/link/midata', 'POST'))->getStatusCode());

        $this->currentSession->setAuthenticated(null);
        self::assertSame(409, $this->controller($this->user())->profileLinkStart(Request::create('/api/auth/link/midata', 'POST'))->getStatusCode());
    }

    public function testBrowserLinkStartIsDeniedWithoutFreshStepUpOrRecentLogin(): void
    {
        $this->guardDenial = 'step_up_required';

        $response = $this->controller($this->user())->linkStart(Request::create('/api/auth/link/midata', 'GET', ['redirect' => '/pending-assignment?x=1']));

        self::assertSame(302, $response->getStatusCode());
        $location = (string) $response->headers->get('Location');
        self::assertStringStartsWith('https://app.ematchef.test/pending-assignment?', $location);
        self::assertStringContainsString('reason=step_up_required', $location);
        self::assertSame([], $response->headers->getCookies());
    }

    public function testCallbackIsRejectedWhenBoundToAnotherSessionAndNeverLinks(): void
    {
        $issued = $this->state->issue('/dashboard', 'user00000001', 'other-session-id', true);
        $request = Request::create('/api/auth/midata/callback', 'GET', ['state' => $issued['token'], 'code' => 'abc']);
        $request->cookies->set(MiDataOAuthState::COOKIE_NAME, $issued['cookieValue']);

        $response = $this->controller($this->user())->callback($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://app.ematchef.test/dashboard?profile_security=1', $response->headers->get('Location'));
        self::assertSame('session_mismatch', $this->linkResults->take($this->currentSession->getAuthenticated()->getId())['reason'] ?? null);
    }

    public function testCallbackIsRejectedForAnotherUser(): void
    {
        $sid = $this->currentSession->getAuthenticated()->getId();
        foreach ([[(new User())->setId('user00000002'), 'user00000001']] as [$loggedIn, $linkUser]) {
            $issued = $this->state->issue('/dashboard', $linkUser, $sid, true);
            $request = Request::create('/api/auth/midata/callback', 'GET', ['state' => $issued['token'], 'code' => 'abc']);
            $request->cookies->set(MiDataOAuthState::COOKIE_NAME, $issued['cookieValue']);

            $response = $this->controller($loggedIn)->callback($request);

            self::assertStringContainsString('profile_security=1', (string) $response->headers->get('Location'));
            self::assertSame('session_mismatch', $this->linkResults->take($this->lastResolvedSessionId)['reason'] ?? null);
        }
    }

    public function testExpiredOrMissingJwtGivesASafeWayBackToLogin(): void
    {
        $issued = $this->state->issue('/dashboard', 'user00000001', $this->currentSession->getAuthenticated()->getId(), true);
        $request = Request::create('/api/auth/midata/callback', 'GET', ['state' => $issued['token'], 'code' => 'abc']);
        $request->cookies->set(MiDataOAuthState::COOKIE_NAME, $issued['cookieValue']);

        $response = $this->controller(null)->callback($request);

        self::assertSame('https://app.ematchef.test/login?oauth=error&provider=midata&reason=session_expired', $response->headers->get('Location'));
    }

    public function testAbortAtMiDataDuringProfileLinkReturnsToTheProfile(): void
    {
        $issued = $this->state->issue('/dashboard', 'user00000001', $this->currentSession->getAuthenticated()->getId(), true);
        $request = Request::create('/api/auth/midata/callback', 'GET', ['error' => 'access_denied', 'state' => $issued['token']]);
        $request->cookies->set(MiDataOAuthState::COOKIE_NAME, $issued['cookieValue']);

        $location = (string) $this->controller($this->user())->callback($request)->headers->get('Location');

        self::assertSame('https://app.ematchef.test/dashboard?profile_security=1', $location);
        self::assertSame(['provider' => 'midata', 'status' => 'error', 'reason' => 'denied'], $this->linkResults->take($this->currentSession->getAuthenticated()->getId()));
    }

    public function testAbortAtMiDataDuringNormalLoginStillGoesToLogin(): void
    {
        $issued = $this->state->issue('/dashboard');
        $request = Request::create('/api/auth/midata/callback', 'GET', ['error' => 'access_denied', 'state' => $issued['token']]);
        $request->cookies->set(MiDataOAuthState::COOKIE_NAME, $issued['cookieValue']);

        $location = (string) $this->controller()->callback($request)->headers->get('Location');

        self::assertStringStartsWith('https://app.ematchef.test/login?oauth=error&provider=midata&reason=denied', $location);
        self::assertStringNotContainsString('profile_security', $location, 'normaler Login: nie zurück in das Profil');
        self::assertStringContainsString('redirect=%2Fdashboard', $location, 'ursprünglicher Einstieg bleibt erhalten');
    }

    public function testBrowserLinkStartOnlyAppliesThePolicyForTheFirstMiDataLink(): void
    {
        $this->guardDenial = 'reauth_required';
        $user = $this->user();
        $user->addExternalIdentity((new \App\Entity\ExternalIdentity())->setId('idn000000001')->setProvider('midata')->setExternalUserId('m1'));

        // Wer MiData schon verbunden hat, bestätigt in Onboarding/Gruppenimport nur dieses Konto: kein Step-up nötig
        $response = $this->controller($user)->linkStart(Request::create('/api/auth/link/midata', 'GET', ['redirect' => '/dashboard']));

        self::assertStringStartsWith('https://db.scout.ch/oauth/authorize?', (string) $response->headers->get('Location'));
    }

    public function testIntentIsDerivedFromTheEntryContextAtStart(): void
    {
        $login = $this->controller()->start(Request::create('/api/auth/midata', 'GET', ['redirect' => '/pending-assignment?join_code=AB12CD34']));
        $q = $this->authorizationQuery($login);
        self::assertSame(\App\Service\Auth\AuthIntent::JOIN_CODE, $this->verifiedState($login, $q)['intent']);

        $invite = $this->controller()->start(Request::create('/api/auth/midata', 'GET', ['redirect' => '/pending-assignment?join_code=AB12&invite_id=i1&department_id=d1']));
        self::assertSame(\App\Service\Auth\AuthIntent::INVITATION, $this->verifiedState($invite, $this->authorizationQuery($invite))['intent']);

        $onboarding = $this->controller($this->user())->linkStart(Request::create('/api/auth/link/midata', 'GET', ['redirect' => '/pending-assignment?midata_onboarding=0a1b2c3d4e5f']));
        self::assertSame(\App\Service\Auth\AuthIntent::MIDATA_ONBOARDING, $this->verifiedState($onboarding, $this->authorizationQuery($onboarding))['intent']);

        $profile = $this->controller($this->user())->profileLinkStart(Request::create('/api/auth/link/midata', 'POST', [], [], [], [], (string) json_encode(['redirect' => '/dashboard'])));
        parse_str((string) parse_url(json_decode((string) $profile->getContent(), true)['authorization_url'], PHP_URL_QUERY), $pq);
        self::assertSame(\App\Service\Auth\AuthIntent::LINK_IDENTITY, $this->verifiedState($profile, $pq)['intent']);
    }

    public function testReturnMarkerInAUserSuppliedLoginRedirectIsStrippedAtStart(): void
    {
        $response = $this->controller()->start(Request::create('/api/auth/midata', 'GET', ['redirect' => '/d0000000001?profile_security=1&oauth=linked']));

        self::assertSame('/d0000000001', $this->verifiedState($response, $this->authorizationQuery($response))['redirect']);
    }

    public function testAbortedOnboardingOrInvitationReturnsToTheOriginalEntry(): void
    {
        foreach (['/pending-assignment?midata_onboarding=0a1b2c3d4e5f', '/pending-assignment?join_code=AB12CD34&invite_id=i1&department_id=d1'] as $entry) {
            $issued = $this->state->issue($entry);
            $request = Request::create('/api/auth/midata/callback', 'GET', ['error' => 'access_denied', 'state' => $issued['token']]);
            $request->cookies->set(MiDataOAuthState::COOKIE_NAME, $issued['cookieValue']);

            $location = (string) $this->controller()->callback($request)->headers->get('Location');

            self::assertStringStartsWith('https://app.ematchef.test/login?oauth=error&provider=midata&reason=denied&redirect=', $location);
            self::assertStringContainsString(rawurlencode(explode('?', $entry)[0]), $location);
            self::assertStringNotContainsString('profile_security', $location);
        }
    }

    public function testCallbackStateIsSingleUse(): void
    {
        $issued = $this->state->issue('/dashboard', 'user00000001', 'other-session-id', true);
        $request = Request::create('/api/auth/midata/callback', 'GET', ['state' => $issued['token'], 'code' => 'abc']);
        $request->cookies->set(MiDataOAuthState::COOKIE_NAME, $issued['cookieValue']);
        $controller = $this->controller($this->user());

        $first = $controller->callback($request);
        $replay = $controller->callback($request);

        self::assertStringContainsString('profile_security=1', (string) $first->headers->get('Location'));
        self::assertStringContainsString('reason=invalid_state', (string) $replay->headers->get('Location'));
    }

    public function testCallbackWithoutValidCookieIsInvalidState(): void
    {
        $issued = $this->state->issue('/dashboard', 'user00000001', 'sid', true);

        $response = $this->controller($this->user())->callback(Request::create('/api/auth/midata/callback', 'GET', ['state' => $issued['token'], 'code' => 'abc']));

        self::assertStringContainsString('reason=invalid_state', (string) $response->headers->get('Location'));
    }

    /**
     * @return array<string, mixed>
     */
    private function authorizationQuery(Response $response): array
    {
        self::assertSame(302, $response->getStatusCode());
        $location = (string) $response->headers->get('Location');
        self::assertStringStartsWith('https://db.scout.ch/oauth/authorize?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{nonce: string, code_verifier: string, redirect: string, link_user_id: ?string, session_id: ?string, profile_link: bool}
     */
    private function verifiedState(Response $response, array $query): array
    {
        $cookies = array_values(array_filter(
            $response->headers->getCookies(),
            static fn (Cookie $cookie): bool => $cookie->getName() === MiDataOAuthState::COOKIE_NAME,
        ));
        self::assertCount(1, $cookies);
        self::assertTrue($cookies[0]->isHttpOnly());
        $verified = $this->state->verify((string) $cookies[0]->getValue(), (string) $query['state']);
        self::assertNotNull($verified);

        return $verified;
    }

    private function controller(?User $user = null): MiDataOAuthController
    {
        $oauthClient = new HitobitoOAuthClient(
            new MockHttpClient(static fn (): MockResponse => new MockResponse((string) json_encode([
                'issuer' => 'https://db.scout.ch',
                'authorization_endpoint' => 'https://db.scout.ch/oauth/authorize',
                'token_endpoint' => 'https://db.scout.ch/oauth/token',
                'userinfo_endpoint' => 'https://db.scout.ch/oauth/userinfo',
                'jwks_uri' => 'https://db.scout.ch/oauth/discovery/keys',
                'scopes_supported' => ['openid', 'email', 'with_roles', 'groups', 'people'],
                'response_types_supported' => ['code'],
                'grant_types_supported' => ['authorization_code'],
                'token_endpoint_auth_methods_supported' => ['client_secret_post'],
                'id_token_signing_alg_values_supported' => ['RS256'],
                'code_challenge_methods_supported' => ['S256'],
            ]))),
            'https://app.ematchef.test',
            'https://db.scout.ch',
            'midata-client',
            'midata-secret',
            'https://app.ematchef.test/api/auth/midata/callback',
        );
        // OAuth-Firewall ohne Benutzer: die Sitzung kommt aus dem geprüften BEARER-Cookie (hier: wer eingeloggt ist).
        $resolver = $this->createMock(\App\Service\Auth\OAuthCallbackSessionResolver::class);
        $resolver->method('resolve')->willReturnCallback(function () use ($user): ?\App\Entity\UserSession {
            $session = $user === null
                ? null
                : ($user->getId() === 'user00000001' ? $this->currentSession->getAuthenticated() : new \App\Entity\UserSession($user, \App\Enum\AuthMethod::PASSWORD, 'UA'));
            $this->lastResolvedSessionId = $session?->getId() ?? '';

            return $session;
        });
        $controller = new MiDataOAuthController(
            $oauthClient,
            $this->state,
            $this->createMock(MiDataOAuthAccountService::class),
            $this->createMock(DepartmentJoinFlowService::class),
            $this->createMock(MiDataDepartmentOnboardingService::class),
            $this->createMock(\App\Service\Auth\MiDataGroupImportService::class),
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class),
            $this->createMock(AuthenticationSuccessHandler::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(\App\Service\Auth\MfaChallengeService::class),
            $this->currentSession,
            new \App\Service\Auth\OAuthStateReplayGuard(new \Symfony\Component\Cache\Adapter\ArrayAdapter()),
            $this->mfaGuard,
            $resolver,
            $this->linkResults,
        );

        $tokenStorage = new TokenStorage();
        if ($user !== null) {
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'api', ['ROLE_USER']));
        }
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $controller->setContainer($container);

        return $controller;
    }

    private function user(): User
    {
        return (new User())->setId('user00000001');
    }
}
