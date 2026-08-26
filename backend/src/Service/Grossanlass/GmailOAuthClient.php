<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Service\Auth\GoogleOAuthException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GmailOAuthClient
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    /** Drafts anlegen, Labels, Inbox lesen (Antwort-Sync). */
    private const SCOPES = [
        'openid',
        'email',
        'https://www.googleapis.com/auth/gmail.compose',
        'https://www.googleapis.com/auth/gmail.modify',
    ];

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
        #[Autowire('%env(GOOGLE_GMAIL_REDIRECT_URI)%')]
        string $gmailRedirectUri = '',
        #[Autowire('%env(GOOGLE_OAUTH_REDIRECT_URI)%')]
        string $loginRedirectUri = '',
    ) {
        $this->frontendBaseUrl = rtrim(trim($frontendBaseUrl), '/');
        $this->clientId = trim($clientId);
        $this->clientSecret = trim($clientSecret);
        $configured = trim($gmailRedirectUri);
        $this->redirectUri = $configured !== ''
            ? $configured
            : $this->defaultRedirectUri();
        unset($loginRedirectUri);
    }

    /**
     * Google Cloud erlaubt kein *.test — lokal Callback auf Loopback (Port wie docker-compose backend).
     */
    private function defaultRedirectUri(): string
    {
        $host = strtolower((string) parse_url($this->frontendBaseUrl, PHP_URL_HOST));
        $private = $host === 'localhost'
            || $host === '127.0.0.1'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test');
        if ($private) {
            return 'http://127.0.0.1:8081/api/auth/google/gmail/callback';
        }

        return $this->frontendBaseUrl . '/api/auth/google/gmail/callback';
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

    public function buildAuthorizationUrl(string $state): string
    {
        if (!$this->isConfigured()) {
            throw new GoogleOAuthException('not_configured', 'Google OAuth is not configured');
        }

        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'state' => $state,
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent select_account',
        ]);
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, email: string}
     */
    public function exchangeCode(string $code): array
    {
        $tokenData = $this->requestToken([
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        $accessToken = $tokenData['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new GoogleOAuthException('token', 'Google token response missing access_token');
        }
        $refresh = $tokenData['refresh_token'] ?? null;
        $email = $this->fetchEmail($accessToken);

        return [
            'access_token' => $accessToken,
            'refresh_token' => is_string($refresh) && $refresh !== '' ? $refresh : null,
            'expires_in' => (int) ($tokenData['expires_in'] ?? 3600),
            'email' => $email,
        ];
    }

    /**
     * @return array{access_token: string, expires_in: int}
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $tokenData = $this->requestToken([
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
        ]);
        $accessToken = $tokenData['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new GoogleOAuthException('token', 'Google refresh missing access_token');
        }

        return [
            'access_token' => $accessToken,
            'expires_in' => (int) ($tokenData['expires_in'] ?? 3600),
        ];
    }

    public function revokeToken(string $token): void
    {
        $token = trim($token);
        if ($token === '' || !$this->isConfigured()) {
            return;
        }
        try {
            $this->httpClient->request('POST', self::REVOKE_URL, [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => ['token' => $token],
            ])->getStatusCode();
        } catch (\Throwable) {
        }
    }

    public function fetchEmail(string $accessToken): string
    {
        try {
            $info = $this->httpClient->request('GET', self::USERINFO_URL, [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            ])->toArray(false);
        } catch (\Throwable $e) {
            throw new GoogleOAuthException('token', 'Google userinfo failed: ' . $e->getMessage());
        }
        $email = strtolower(trim((string) ($info['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new GoogleOAuthException('no_email', 'Google account has no email');
        }

        return $email;
    }

    /**
     * @param array<string, string> $body
     * @return array<string, mixed>
     */
    private function requestToken(array $body): array
    {
        if (!$this->isConfigured()) {
            throw new GoogleOAuthException('not_configured', 'Google OAuth is not configured');
        }
        try {
            $tokenData = $this->httpClient->request('POST', self::TOKEN_URL, ['body' => $body])->toArray(false);
        } catch (\Throwable $e) {
            throw new GoogleOAuthException('token', 'Google token exchange failed: ' . $e->getMessage());
        }
        if (isset($tokenData['error'])) {
            throw new GoogleOAuthException('token', (string) ($tokenData['error_description'] ?? $tokenData['error']));
        }

        return $tokenData;
    }
}
