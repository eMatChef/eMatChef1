<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Service\Auth\HitobitoOAuthClient;
use App\Service\Auth\MiDataOAuthException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HitobitoOAuthClientTest extends TestCase
{
    public function testAuthorizationUrlUsesDiscoveryScopesAndPkce(): void
    {
        $http = new MockHttpClient([
            new MockResponse($this->json($this->discoveryDocument())),
        ]);
        $client = $this->client($http);

        $url = $client->buildAuthorizationUrl([
            'token' => 'state-value',
            'nonce' => 'oidc-nonce',
            'codeVerifier' => 'test-code-verifier',
        ]);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('https://db.scout.ch/oauth/authorize', strtok($url, '?'));
        self::assertSame('openid email with_roles groups people', $query['scope']);
        self::assertSame('S256', $query['code_challenge_method']);
        self::assertSame(
            rtrim(strtr(base64_encode(hash('sha256', 'test-code-verifier', true)), '+/', '-_'), '='),
            $query['code_challenge']
        );
        self::assertArrayNotHasKey('api', array_flip(explode(' ', $query['scope'])));
        self::assertArrayNotHasKey('profile', array_flip(explode(' ', $query['scope'])));
    }

    public function testAuthorizationUrlHasNoPromptByDefault(): void
    {
        $url = $this->client(new MockHttpClient([new MockResponse($this->json($this->discoveryDocument()))]))
            ->buildAuthorizationUrl(['token' => 'state-value', 'nonce' => 'oidc-nonce', 'codeVerifier' => 'verifier']);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertArrayNotHasKey('prompt', $query);
    }

    public function testPromptLoginKeepsStateNoncePkceAndRedirect(): void
    {
        $url = $this->client(new MockHttpClient([new MockResponse($this->json($this->discoveryDocument()))]))
            ->buildAuthorizationUrl(
                ['token' => 'state-value', 'nonce' => 'oidc-nonce', 'codeVerifier' => 'test-code-verifier'],
                HitobitoOAuthClient::PROMPT_LOGIN,
            );

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('login', $query['prompt']);
        self::assertSame('state-value', $query['state']);
        self::assertSame('oidc-nonce', $query['nonce']);
        self::assertSame('code', $query['response_type']);
        self::assertSame('midata-client', $query['client_id']);
        self::assertSame('https://app.ematchef.test/api/auth/midata/callback', $query['redirect_uri']);
        self::assertSame('S256', $query['code_challenge_method']);
        self::assertSame(
            rtrim(strtr(base64_encode(hash('sha256', 'test-code-verifier', true)), '+/', '-_'), '='),
            $query['code_challenge']
        );
    }

    public function testUnsupportedPromptValueIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->client(new MockHttpClient([new MockResponse($this->json($this->discoveryDocument()))]))
            ->buildAuthorizationUrl(
                ['token' => 'state-value', 'nonce' => 'oidc-nonce', 'codeVerifier' => 'verifier'],
                'select_account',
            );
    }

    public function testFetchUserInfoValidatesIdTokenAndMatchesUserinfoSubject(): void
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        self::assertNotFalse($privateKey);
        $keyDetails = openssl_pkey_get_details($privateKey);
        self::assertIsArray($keyDetails);
        self::assertIsArray($keyDetails['rsa'] ?? null);

        $nonce = 'expected-nonce';
        $idToken = $this->signedIdToken($privateKey, [
            'iss' => 'https://db.scout.ch',
            'sub' => 'midata-sub-42',
            'aud' => 'midata-client',
            'exp' => time() + 300,
            'iat' => time(),
            'nonce' => $nonce,
        ]);

        $requests = [];
        $responses = [
            new MockResponse($this->json($this->discoveryDocument())),
            new MockResponse($this->json(['access_token' => 'transient-access-token', 'id_token' => $idToken])),
            new MockResponse($this->json([
                'keys' => [[
                    'kid' => 'test-key',
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'n' => $this->base64UrlEncode($keyDetails['rsa']['n']),
                    'e' => $this->base64UrlEncode($keyDetails['rsa']['e']),
                ]],
            ])),
            new MockResponse($this->json([
                'sub' => 'midata-sub-42',
                'email' => 'person@example.com',
                'email_verified' => true,
                'given_name' => 'Ada',
                'family_name' => 'Lovelace',
                'nickname' => 'Enchantress',
                'primary_group_id' => 376803389,
                'roles' => [[
                    'group_id' => 376803389,
                    'group_name' => 'Bottom One',
                    'role' => 'Group::BottomLayer::Member',
                    'role_class' => 'Group::BottomLayer::Member',
                    'role_name' => 'Member',
                    'permissions' => ['layer_and_below_read', 'finance'],
                ]],
            ])),
        ];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $requests[] = [$method, $url, $options];

            return array_shift($responses);
        });

        $session = $this->client($http)->fetchUserInfo('authorization-code', 'pkce-verifier', $nonce);
        $info = $session->userInfo;

        self::assertSame('midata', $session->provider);
        self::assertSame('transient-access-token', $session->accessToken);
        self::assertSame('midata-sub-42', $info->subject);
        self::assertSame('person@example.com', $info->email);
        self::assertTrue($info->emailVerified);
        self::assertSame('Ada', $info->firstName);
        self::assertSame('Lovelace', $info->lastName);
        self::assertSame('Enchantress', $info->nickname);
        self::assertSame('376803389', $info->primaryGroupId);
        self::assertCount(1, $info->roles);
        self::assertSame('midata-sub-42', $info->roles[0]->personId);
        self::assertSame('376803389', $info->roles[0]->groupId);
        self::assertSame('Group::BottomLayer::Member', $info->roles[0]->type);
        self::assertSame('Group::BottomLayer::Member', $info->roles[0]->roleClass);
        self::assertSame('Group::BottomLayer::Member', $info->roles[0]->role);
        self::assertSame('Bottom One', $info->roles[0]->groupName);
        self::assertSame('Member', $info->roles[0]->roleName);
        self::assertSame(['layer_and_below_read', 'finance'], $info->roles[0]->permissions);
        self::assertNull($info->roles[0]->startOn);
        self::assertNull($info->roles[0]->endOn);
        self::assertCount(4, $requests);
        self::assertSame('GET', $requests[0][0]);
        self::assertSame('POST', $requests[1][0]);
        parse_str((string) $requests[1][2]['body'], $tokenRequestBody);
        self::assertSame('pkce-verifier', $tokenRequestBody['code_verifier'] ?? null);
        self::assertSame('GET', $requests[2][0]);
        self::assertSame('GET', $requests[3][0]);
    }

    public function testRejectsIdTokenWhenUserinfoSubjectDoesNotMatch(): void
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        self::assertNotFalse($privateKey);
        $keyDetails = openssl_pkey_get_details($privateKey);
        self::assertIsArray($keyDetails);
        $idToken = $this->signedIdToken($privateKey, [
            'iss' => 'https://db.scout.ch',
            'sub' => 'token-subject',
            'aud' => 'midata-client',
            'exp' => time() + 300,
            'iat' => time(),
            'nonce' => 'expected-nonce',
        ]);
        $http = new MockHttpClient([
            new MockResponse($this->json($this->discoveryDocument())),
            new MockResponse($this->json(['access_token' => 'access', 'id_token' => $idToken])),
            new MockResponse($this->json([
                'keys' => [[
                    'kid' => 'test-key',
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'n' => $this->base64UrlEncode($keyDetails['rsa']['n']),
                    'e' => $this->base64UrlEncode($keyDetails['rsa']['e']),
                ]],
            ])),
            new MockResponse($this->json(['sub' => 'different-subject', 'email' => 'person@example.com'])),
        ]);

        try {
            $this->client($http)->fetchUserInfo('code', 'verifier', 'expected-nonce');
            self::fail('Expected MiDataOAuthException for mismatched subject');
        } catch (MiDataOAuthException $exception) {
            self::assertSame('failed', $exception->reason);
        }
    }

    private function client(MockHttpClient $http): HitobitoOAuthClient
    {
        return new HitobitoOAuthClient(
            $http,
            'https://app.ematchef.test',
            'https://db.scout.ch',
            'midata-client',
            'midata-secret',
            'https://app.ematchef.test/api/auth/midata/callback',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function discoveryDocument(): array
    {
        return [
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
        ];
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function signedIdToken(\OpenSSLAsymmetricKey $privateKey, array $claims): string
    {
        $header = $this->base64UrlEncode($this->json(['alg' => 'RS256', 'kid' => 'test-key', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode($this->json($claims));
        $signedContent = $header . '.' . $payload;
        $signature = '';
        self::assertTrue(openssl_sign($signedContent, $signature, $privateKey, OPENSSL_ALGO_SHA256));

        return $signedContent . '.' . $this->base64UrlEncode($signature);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
