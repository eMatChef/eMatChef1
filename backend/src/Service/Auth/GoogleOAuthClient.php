<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleOAuthClient
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    private readonly string $clientId;
    private readonly string $clientSecret;
    private readonly string $redirectUri;
    private readonly string $frontendBaseUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(APP_FRONTEND_URL)%')]
        string $frontendBaseUrl,
        #[Autowire('%env(GOOGLE_OAUTH_CLIENT_ID)%')]
        string $clientId = '',
        #[Autowire('%env(GOOGLE_OAUTH_CLIENT_SECRET)%')]
        string $clientSecret = '',
        #[Autowire('%env(GOOGLE_OAUTH_REDIRECT_URI)%')]
        string $redirectUri = '',
    ) {
        $this->frontendBaseUrl = rtrim(trim($frontendBaseUrl), '/');
        $this->clientId = trim($clientId);
        $this->clientSecret = trim($clientSecret);
        $configuredRedirect = trim($redirectUri);
        $this->redirectUri = $configuredRedirect !== ''
            ? $configuredRedirect
            : $this->frontendBaseUrl . '/api/auth/google/callback';
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->frontendBaseUrl !== '';
    }

    public function getFrontendBaseUrl(): string
    {
        return $this->frontendBaseUrl;
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    public function buildAuthorizationUrl(string $state, ?string $nonce = null, ?string $codeVerifier = null): string
    {
        if (!$this->isConfigured()) {
            throw new GoogleOAuthException('not_configured', 'Google OAuth is not configured');
        }

        $query = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];
        if ($nonce !== null && $nonce !== '') {
            $query['nonce'] = $nonce;
        }
        if ($codeVerifier !== null && $codeVerifier !== '') {
            $query['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
            $query['code_challenge_method'] = 'S256';
        }

        return self::AUTH_URL . '?' . http_build_query($query);
    }

    /**
     * @param string|null $codeVerifier PKCE-Verifier des Flows (wird mitgeschickt)
     * @param string|null $expectedNonce OIDC-Nonce des Flows; wird gegen den id_token-Claim geprüft
     */
    public function fetchUserInfo(string $code, ?string $codeVerifier = null, ?string $expectedNonce = null): GoogleOAuthUserInfo
    {
        if (!$this->isConfigured()) {
            throw new GoogleOAuthException('not_configured', 'Google OAuth is not configured');
        }
        if ($code === '') {
            throw new GoogleOAuthException('token', 'Missing authorization code');
        }

        try {
            $body = [
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri,
                'grant_type' => 'authorization_code',
            ];
            if ($codeVerifier !== null && $codeVerifier !== '') {
                $body['code_verifier'] = $codeVerifier;
            }
            $tokenResponse = $this->httpClient->request('POST', self::TOKEN_URL, ['body' => $body]);
            $tokenData = $tokenResponse->toArray(false);
        } catch (\Throwable $e) {
            throw new GoogleOAuthException('token', 'Google token exchange failed: ' . $e->getMessage());
        }

        $accessToken = $tokenData['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new GoogleOAuthException('token', 'Google token response missing access_token');
        }

        $idTokenSubject = null;
        if ($expectedNonce !== null && $expectedNonce !== '') {
            // Direkt vom Token-Endpoint (TLS) erhalten: Claims ohne Signaturprüfung lesbar (OIDC Core 3.1.3.7).
            $claims = self::decodeIdTokenClaims($tokenData['id_token'] ?? null);
            if ($claims === null || !hash_equals($expectedNonce, (string) ($claims['nonce'] ?? ''))) {
                throw new GoogleOAuthException('invalid_state', 'Google id_token nonce mismatch');
            }
            $idTokenSubject = (string) ($claims['sub'] ?? '');
        }

        try {
            $infoResponse = $this->httpClient->request('GET', self::USERINFO_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);
            $info = $infoResponse->toArray(false);
        } catch (\Throwable $e) {
            throw new GoogleOAuthException('token', 'Google userinfo failed: ' . $e->getMessage());
        }

        $googleId = trim((string) ($info['sub'] ?? ''));
        if ($idTokenSubject !== null && !hash_equals($idTokenSubject, $googleId)) {
            throw new GoogleOAuthException('failed', 'Google id_token subject mismatch');
        }
        $email = strtolower(trim((string) ($info['email'] ?? '')));
        $emailVerified = filter_var($info['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($googleId === '') {
            throw new GoogleOAuthException('failed', 'Google userinfo missing sub');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new GoogleOAuthException('no_email', 'Google account has no email');
        }
        if (!$emailVerified) {
            throw new GoogleOAuthException('unverified_email', 'Google email is not verified');
        }

        $firstName = trim((string) ($info['given_name'] ?? ''));
        $lastName = trim((string) ($info['family_name'] ?? ''));
        if ($firstName === '' && $lastName === '') {
            $full = trim((string) ($info['name'] ?? ''));
            if ($full !== '') {
                $parts = preg_split('/\s+/', $full) ?: [];
                $firstName = (string) array_shift($parts);
                $lastName = implode(' ', $parts);
            }
        }

        return new GoogleOAuthUserInfo(
            $googleId,
            $email,
            true,
            $firstName !== '' ? $firstName : 'Google',
            $lastName !== '' ? $lastName : 'User',
        );
    }

    /** @return array<string, mixed>|null */
    private static function decodeIdTokenClaims(mixed $idToken): ?array
    {
        if (!\is_string($idToken)) {
            return null;
        }
        $parts = explode('.', $idToken);
        if (\count($parts) !== 3) {
            return null;
        }
        $json = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $claims = $json === false ? null : json_decode($json, true);

        return \is_array($claims) ? $claims : null;
    }
}
