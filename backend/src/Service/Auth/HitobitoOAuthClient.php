<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HitobitoOAuthClient
{
    private const SCOPES = ['openid', 'email', 'with_roles', 'groups', 'people'];

    private readonly string $issuer;
    private readonly string $clientId;
    private readonly string $clientSecret;
    private readonly string $redirectUri;
    private readonly string $frontendBaseUrl;

    /** @var array<string, mixed>|null */
    private ?array $discoveryDocument = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(APP_FRONTEND_URL)%')]
        string $frontendBaseUrl,
        #[Autowire('%env(MIDATA_OAUTH_ISSUER)%')]
        string $issuer,
        #[Autowire('%env(MIDATA_OAUTH_CLIENT_ID)%')]
        string $clientId = '',
        #[Autowire('%env(MIDATA_OAUTH_CLIENT_SECRET)%')]
        string $clientSecret = '',
        #[Autowire('%env(MIDATA_OAUTH_REDIRECT_URI)%')]
        string $redirectUri = '',
    ) {
        $this->frontendBaseUrl = rtrim(trim($frontendBaseUrl), '/');
        $this->issuer = rtrim(trim($issuer), '/');
        $this->clientId = trim($clientId);
        $this->clientSecret = trim($clientSecret);
        $configuredRedirect = trim($redirectUri);
        $this->redirectUri = $configuredRedirect !== ''
            ? $configuredRedirect
            : $this->frontendBaseUrl . '/api/auth/midata/callback';
    }

    public function isConfigured(): bool
    {
        return $this->frontendBaseUrl !== ''
            && $this->isHttpsUrl($this->issuer)
            && $this->clientId !== ''
            && $this->clientSecret !== '';
    }

    public function getFrontendBaseUrl(): string
    {
        return $this->frontendBaseUrl;
    }

    /**
     * @param array{token: string, nonce: string, codeVerifier: string} $state
     */
    public function buildAuthorizationUrl(array $state): string
    {
        if (!$this->isConfigured()) {
            throw new MiDataOAuthException('not_configured', 'MiData OAuth is not configured');
        }

        $discovery = $this->getDiscoveryDocument();
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $state['codeVerifier'], true)), '+/', '-_'), '=');

        return $this->requiredEndpoint($discovery, 'authorization_endpoint') . '?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'state' => $state['token'],
            'nonce' => $state['nonce'],
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function fetchUserInfo(string $code, string $codeVerifier, string $expectedNonce): MiDataOAuthUserInfo
    {
        if (!$this->isConfigured()) {
            throw new MiDataOAuthException('not_configured', 'MiData OAuth is not configured');
        }
        if ($code === '') {
            throw new MiDataOAuthException('failed', 'Missing MiData authorization code');
        }

        $discovery = $this->getDiscoveryDocument();
        try {
            $tokenResponse = $this->httpClient->request('POST', $this->requiredEndpoint($discovery, 'token_endpoint'), [
                'body' => [
                    'code' => $code,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'redirect_uri' => $this->redirectUri,
                    'grant_type' => 'authorization_code',
                    'code_verifier' => $codeVerifier,
                ],
                'max_redirects' => 0,
            ]);
            $tokenData = $tokenResponse->toArray(false);
        } catch (\Throwable $exception) {
            throw new MiDataOAuthException('failed', 'MiData token exchange failed', previous: $exception);
        }

        $accessToken = $tokenData['access_token'] ?? null;
        $idToken = $tokenData['id_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '' || !is_string($idToken) || $idToken === '') {
            throw new MiDataOAuthException('failed', 'MiData token response is missing required tokens');
        }

        $idClaims = $this->verifyIdToken(
            $idToken,
            $expectedNonce,
            $discovery,
        );

        try {
            $userinfoResponse = $this->httpClient->request('GET', $this->requiredEndpoint($discovery, 'userinfo_endpoint'), [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
                'max_redirects' => 0,
            ]);
            $userinfo = $userinfoResponse->toArray(false);
        } catch (\Throwable $exception) {
            throw new MiDataOAuthException('failed', 'MiData userinfo request failed', previous: $exception);
        }

        $subject = $userinfo['sub'] ?? null;
        if (!is_string($subject) || $subject === '' || !hash_equals($idClaims['sub'], $subject)) {
            throw new MiDataOAuthException('failed', 'MiData userinfo subject does not match the ID token');
        }

        $email = strtolower(trim((string) ($userinfo['email'] ?? $userinfo['with_roles_email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = null;
        }
        $firstName = trim((string) ($userinfo['given_name'] ?? $userinfo['first_name'] ?? $userinfo['with_roles_first_name'] ?? ''));
        $lastName = trim((string) ($userinfo['family_name'] ?? $userinfo['last_name'] ?? $userinfo['with_roles_last_name'] ?? ''));

        return new MiDataOAuthUserInfo(
            $subject,
            $email,
            filter_var($userinfo['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            $firstName !== '' ? $firstName : null,
            $lastName !== '' ? $lastName : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getDiscoveryDocument(): array
    {
        if ($this->discoveryDocument !== null) {
            return $this->discoveryDocument;
        }

        try {
            $response = $this->httpClient->request('GET', $this->issuer . '/.well-known/openid-configuration', [
                'max_redirects' => 0,
            ]);
            $document = $response->toArray(false);
        } catch (\Throwable $exception) {
            throw new MiDataOAuthException('failed', 'MiData OIDC discovery failed', previous: $exception);
        }

        if (
            !is_array($document)
            || !is_string($document['issuer'] ?? null)
            || rtrim($document['issuer'], '/') !== $this->issuer
        ) {
            throw new MiDataOAuthException('failed', 'MiData OIDC discovery issuer is invalid');
        }
        foreach (['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint', 'jwks_uri'] as $endpointName) {
            $endpoint = $document[$endpointName] ?? null;
            if (!is_string($endpoint) || !$this->isProviderUrl($endpoint)) {
                throw new MiDataOAuthException('failed', 'MiData OIDC discovery endpoint is invalid');
            }
        }
        $supportedScopes = $document['scopes_supported'] ?? [];
        if (!is_array($supportedScopes) || array_diff(self::SCOPES, $supportedScopes) !== []) {
            throw new MiDataOAuthException('failed', 'MiData OIDC discovery does not support required scopes');
        }
        $supportedAlgorithms = $document['id_token_signing_alg_values_supported'] ?? [];
        if (!is_array($supportedAlgorithms) || !in_array('RS256', $supportedAlgorithms, true)) {
            throw new MiDataOAuthException('failed', 'MiData OIDC discovery does not support RS256 ID tokens');
        }
        $supportedResponseTypes = $document['response_types_supported'] ?? [];
        if (!is_array($supportedResponseTypes) || !in_array('code', $supportedResponseTypes, true)) {
            throw new MiDataOAuthException('failed', 'MiData OIDC discovery does not support authorization code flow');
        }
        $supportedGrantTypes = $document['grant_types_supported'] ?? [];
        if (!is_array($supportedGrantTypes) || !in_array('authorization_code', $supportedGrantTypes, true)) {
            throw new MiDataOAuthException('failed', 'MiData OIDC discovery does not support authorization code grant');
        }
        $supportedChallengeMethods = $document['code_challenge_methods_supported'] ?? [];
        if (!is_array($supportedChallengeMethods) || !in_array('S256', $supportedChallengeMethods, true)) {
            throw new MiDataOAuthException('failed', 'MiData OIDC discovery does not support PKCE S256');
        }
        $tokenAuthMethods = $document['token_endpoint_auth_methods_supported'] ?? [];
        if (!is_array($tokenAuthMethods) || !in_array('client_secret_post', $tokenAuthMethods, true)) {
            throw new MiDataOAuthException('failed', 'MiData OIDC discovery does not support client_secret_post');
        }

        return $this->discoveryDocument = $document;
    }

    /**
     * @param array<string, mixed> $discovery
     */
    private function requiredEndpoint(array $discovery, string $name): string
    {
        $endpoint = $discovery[$name] ?? null;
        if (!is_string($endpoint) || !$this->isProviderUrl($endpoint)) {
            throw new MiDataOAuthException('failed', 'MiData OIDC endpoint is invalid');
        }

        return $endpoint;
    }

    /**
     * @param array<string, mixed> $discovery
     * @return array{sub: string}
     */
    private function verifyIdToken(string $idToken, string $expectedNonce, array $discovery): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new MiDataOAuthException('failed', 'MiData ID token is malformed');
        }

        $header = $this->decodeJwtSegment($parts[0]);
        $claims = $this->decodeJwtSegment($parts[1]);
        $signature = $this->base64UrlDecode($parts[2]);
        if (
            !is_array($header)
            || !is_array($claims)
            || $signature === null
            || ($header['alg'] ?? null) !== 'RS256'
            || !is_string($header['kid'] ?? null)
        ) {
            throw new MiDataOAuthException('failed', 'MiData ID token header or claims are invalid');
        }

        try {
            $jwksResponse = $this->httpClient->request('GET', $this->requiredEndpoint($discovery, 'jwks_uri'), [
                'max_redirects' => 0,
            ]);
            $jwks = $jwksResponse->toArray(false);
        } catch (\Throwable $exception) {
            throw new MiDataOAuthException('failed', 'MiData signing keys could not be loaded', previous: $exception);
        }

        $key = null;
        foreach (($jwks['keys'] ?? []) as $candidate) {
            if (
                is_array($candidate)
                && ($candidate['kid'] ?? null) === $header['kid']
                && ($candidate['kty'] ?? null) === 'RSA'
                && (($candidate['use'] ?? 'sig') === 'sig')
                && (($candidate['alg'] ?? 'RS256') === 'RS256')
                && is_string($candidate['n'] ?? null)
                && is_string($candidate['e'] ?? null)
            ) {
                $key = $candidate;
                break;
            }
        }
        if ($key === null) {
            throw new MiDataOAuthException('failed', 'MiData ID token signing key was not found');
        }

        $publicKey = openssl_pkey_get_public($this->rsaJwkToPem($key['n'], $key['e']));
        $keyDetails = $publicKey !== false ? openssl_pkey_get_details($publicKey) : false;
        if (
            $publicKey === false
            || !is_array($keyDetails)
            || ($keyDetails['bits'] ?? 0) < 2048
            || openssl_verify($parts[0] . '.' . $parts[1], $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1
        ) {
            throw new MiDataOAuthException('failed', 'MiData ID token signature is invalid');
        }

        $issuer = $discovery['issuer'];
        $audience = $claims['aud'] ?? null;
        $audiences = is_string($audience) ? [$audience] : $audience;
        $hasMultipleAudiences = is_array($audiences) && count($audiences) > 1;
        $now = time();
        if (
            ($claims['iss'] ?? null) !== $issuer
            || !is_array($audiences)
            || !in_array($this->clientId, $audiences, true)
            || ($hasMultipleAudiences && !is_string($claims['azp'] ?? null))
            || (isset($claims['azp']) && $claims['azp'] !== $this->clientId)
            || !is_numeric($claims['exp'] ?? null)
            || (int) $claims['exp'] <= $now
            || !is_numeric($claims['iat'] ?? null)
            || (int) $claims['iat'] > $now + 60
            || (isset($claims['nbf']) && (!is_numeric($claims['nbf']) || (int) $claims['nbf'] > $now + 60))
            || !is_string($claims['nonce'] ?? null)
            || !hash_equals($expectedNonce, $claims['nonce'])
            || !is_string($claims['sub'] ?? null)
            || $claims['sub'] === ''
        ) {
            throw new MiDataOAuthException('failed', 'MiData ID token claims are invalid');
        }

        return ['sub' => $claims['sub']];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJwtSegment(string $segment): ?array
    {
        $json = $this->base64UrlDecode($segment);
        if ($json === null) {
            return null;
        }

        try {
            $value = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($value) ? $value : null;
    }

    private function base64UrlDecode(string $value): ?string
    {
        $base64 = strtr($value, '-_', '+/');
        $remainder = strlen($base64) % 4;
        if ($remainder !== 0) {
            $base64 .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode($base64, true);

        return $decoded === false ? null : $decoded;
    }

    private function rsaJwkToPem(string $modulus, string $exponent): string
    {
        $modulusBytes = $this->base64UrlDecode($modulus);
        $exponentBytes = $this->base64UrlDecode($exponent);
        if ($modulusBytes === null || $exponentBytes === null || $modulusBytes === '' || $exponentBytes === '') {
            throw new MiDataOAuthException('failed', 'MiData RSA key is invalid');
        }

        $rsaKey = $this->derSequence($this->derInteger($modulusBytes) . $this->derInteger($exponentBytes));
        $algorithm = hex2bin('300d06092a864886f70d0101010500');
        if ($algorithm === false) {
            throw new MiDataOAuthException('failed', 'Unable to encode MiData RSA key');
        }
        $subjectPublicKey = "\x03" . $this->derLength(strlen($rsaKey) + 1) . "\x00" . $rsaKey;
        $der = $this->derSequence($algorithm . $subjectPublicKey);

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    private function derInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') {
            $bytes = "\x00";
        } elseif ((ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02" . $this->derLength(strlen($bytes)) . $bytes;
    }

    private function derSequence(string $bytes): string
    {
        return "\x30" . $this->derLength(strlen($bytes)) . $bytes;
    }

    private function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $encoded = '';
        while ($length > 0) {
            $encoded = chr($length & 0xff) . $encoded;
            $length >>= 8;
        }

        return chr(0x80 | strlen($encoded)) . $encoded;
    }

    private function isHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && is_string($parts['host'] ?? null)
            && $parts['host'] !== '';
    }

    private function isProviderUrl(string $url): bool
    {
        if (!$this->isHttpsUrl($url)) {
            return false;
        }

        $urlHost = parse_url($url, PHP_URL_HOST);
        $issuerHost = parse_url($this->issuer, PHP_URL_HOST);

        return is_string($urlHost)
            && is_string($issuerHost)
            && strcasecmp($urlHost, $issuerHost) === 0;
    }
}
