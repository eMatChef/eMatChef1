<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\MiDataOAuthController;
use App\Entity\User;
use App\Repository\UserRepository;
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

    protected function setUp(): void
    {
        $this->state = new MiDataOAuthState('test-secret');
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
     * @return array{nonce: string, code_verifier: string, redirect: string, link_user_id: ?string}
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
        $controller = new MiDataOAuthController(
            $oauthClient,
            $this->state,
            $this->createMock(MiDataOAuthAccountService::class),
            $this->createMock(DepartmentJoinFlowService::class),
            $this->createMock(MiDataDepartmentOnboardingService::class),
            $this->createMock(UserRepository::class),
            $this->createMock(\App\Service\Auth\MiDataGroupImportService::class),
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class),
            $this->createMock(AuthenticationSuccessHandler::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(\App\Service\Auth\MfaChallengeService::class),
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
