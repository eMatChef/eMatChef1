<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Service\Auth\GoogleOAuthClient;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleOAuthClientTest extends TestCase
{
    public function testAuthorizationUrlContainsRequiredParams(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $client = new GoogleOAuthClient(
            $http,
            'https://app.ematchef.test',
            'google-client-id',
            'google-client-secret',
            '',
        );

        $url = $client->buildAuthorizationUrl('nonce-1');
        self::assertStringContainsString('accounts.google.com/o/oauth2/v2/auth', $url);
        self::assertStringContainsString('client_id=google-client-id', $url);
        self::assertStringContainsString(urlencode('https://app.ematchef.test/api/auth/google/callback'), $url);
        self::assertStringContainsString('state=nonce-1', $url);
        self::assertStringContainsString('scope=', $url);
    }

    public function testAuthorizationUrlCarriesNonceAndPkceChallenge(): void
    {
        $client = new GoogleOAuthClient($this->createMock(HttpClientInterface::class), 'https://app.ematchef.test', 'id', 'secret', '');

        $url = $client->buildAuthorizationUrl('state-1', 'oidc-nonce', 'verifier-123');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        self::assertSame('oidc-nonce', $q['nonce']);
        self::assertSame('S256', $q['code_challenge_method']);
        self::assertSame(rtrim(strtr(base64_encode(hash('sha256', 'verifier-123', true)), '+/', '-_'), '='), $q['code_challenge']);
        self::assertStringNotContainsString('verifier-123', $url);
    }

    public function testTokenRequestSendsVerifierAndRejectsWrongNonce(): void
    {
        $requests = [];
        $idToken = fn (string $nonce, string $sub): string => 'h.' . rtrim(strtr(base64_encode((string) json_encode(['nonce' => $nonce, 'sub' => $sub])), '+/', '-_'), '=') . '.s';
        $http = new \Symfony\Component\HttpClient\MockHttpClient(function (string $method, string $url, array $options) use (&$requests, $idToken) {
            $requests[] = [$url, $options['body'] ?? ''];
            if (str_contains($url, 'token')) {
                return new \Symfony\Component\HttpClient\Response\MockResponse((string) json_encode(['access_token' => 'at', 'id_token' => $idToken($GLOBALS['__nonce'], 'sub-1')]));
            }

            return new \Symfony\Component\HttpClient\Response\MockResponse((string) json_encode(['sub' => 'sub-1', 'email' => 'a@b.test', 'email_verified' => true, 'given_name' => 'A', 'family_name' => 'B']));
        });
        $client = new GoogleOAuthClient($http, 'https://app.ematchef.test', 'id', 'secret', '');

        $GLOBALS['__nonce'] = 'n-good';
        $info = $client->fetchUserInfo('code-1', 'verifier-1', 'n-good');
        self::assertSame('sub-1', $info->googleId);
        self::assertStringContainsString('code_verifier=verifier-1', (string) $requests[0][1]);

        $GLOBALS['__nonce'] = 'n-replayed';
        try {
            $client->fetchUserInfo('code-2', 'verifier-1', 'n-good');
            self::fail('Falsche Nonce muss abgelehnt werden');
        } catch (\App\Service\Auth\GoogleOAuthException $e) {
            self::assertSame('invalid_state', $e->reason);
        } finally {
            unset($GLOBALS['__nonce']);
        }
    }

    public function testIsConfiguredRequiresSecrets(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $client = new GoogleOAuthClient($http, 'https://app.ematchef.test', '', '', '');
        self::assertFalse($client->isConfigured());
    }
}
