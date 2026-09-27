<?php

declare(strict_types=1);

namespace App\Service\Grossanlass\Mailbox;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OutlookOAuthClient
{
    /** Entwurf im Ordner eMatChef, Posteingang dieses Ordners, Antworten lesen. */
    private const SCOPES = [
        'offline_access',
        'openid',
        'email',
        'User.Read',
        'Mail.ReadWrite',
    ];

    private readonly string $clientId;
    private readonly string $clientSecret;
    private readonly string $tenant;
    private readonly string $redirectUri;
    private readonly string $frontendBaseUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(APP_FRONTEND_URL)%')]
        string $frontendBaseUrl,
        #[Autowire('%env(MICROSOFT_OAUTH_CLIENT_ID)%')]
        string $clientId = '',
        #[Autowire('%env(MICROSOFT_OAUTH_CLIENT_SECRET)%')]
        string $clientSecret = '',
        #[Autowire('%env(MICROSOFT_OAUTH_TENANT)%')]
        string $tenant = 'common',
        #[Autowire('%env(MICROSOFT_OUTLOOK_REDIRECT_URI)%')]
        string $redirectUri = '',
    ) {
        $this->frontendBaseUrl = rtrim(trim($frontendBaseUrl), '/');
        $this->clientId = trim($clientId);
        $this->clientSecret = trim($clientSecret);
        $tenant = trim($tenant);
        $this->tenant = $tenant !== '' ? $tenant : 'common';
        $configured = trim($redirectUri);
        $this->redirectUri = $configured !== '' ? $configured : $this->defaultRedirectUri();
    }

    /**
     * Entra erlaubt für lokale Hosts Loopback. *.test landet wie bei Gmail auf 127.0.0.1.
     */
    private function defaultRedirectUri(): string
    {
        $host = strtolower((string) parse_url($this->frontendBaseUrl, PHP_URL_HOST));
        $private = $host === 'localhost'
            || $host === '127.0.0.1'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test');
        if ($private) {
            return 'http://127.0.0.1:8081/api/auth/microsoft/outlook/callback';
        }

        return $this->frontendBaseUrl . '/api/auth/microsoft/outlook/callback';
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->frontendBaseUrl !== '';
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    public function getFrontendBaseUrl(): string
    {
        return $this->frontendBaseUrl;
    }

    public function buildAuthorizationUrl(string $state): string
    {
        if (!$this->isConfigured()) {
            throw new MailboxProviderException('Outlook 365 ist nicht eingerichtet.');
        }

        return $this->authorizeUrl() . '?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'response_mode' => 'query',
            'scope' => implode(' ', self::SCOPES),
            'state' => $state,
            'prompt' => 'select_account',
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
            'scope' => implode(' ', self::SCOPES),
        ]);
        $accessToken = $this->accessTokenFrom($tokenData);
        $refresh = $tokenData['refresh_token'] ?? null;

        return [
            'access_token' => $accessToken,
            'refresh_token' => is_string($refresh) && $refresh !== '' ? $refresh : null,
            'expires_in' => (int) ($tokenData['expires_in'] ?? 3600),
            'email' => $this->fetchEmail($accessToken),
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
            'scope' => implode(' ', self::SCOPES),
        ]);

        return [
            'access_token' => $this->accessTokenFrom($tokenData),
            'expires_in' => (int) ($tokenData['expires_in'] ?? 3600),
        ];
    }

    public function fetchEmail(string $accessToken): string
    {
        try {
            $info = $this->httpClient->request('GET', 'https://graph.microsoft.com/v1.0/me', [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            ])->toArray(false);
        } catch (\Throwable $e) {
            throw new MailboxProviderException('Outlook-Profil konnte nicht gelesen werden.');
        }
        $email = strtolower(trim((string) ($info['mail'] ?? $info['userPrincipalName'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new MailboxProviderException('Das Microsoft-Konto hat keine E-Mail-Adresse.');
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
            throw new MailboxProviderException('Outlook 365 ist nicht eingerichtet.');
        }
        try {
            $tokenData = $this->httpClient->request('POST', $this->tokenUrl(), [
                'body' => $body,
            ])->toArray(false);
        } catch (\Throwable $e) {
            throw new MailboxProviderException('Microsoft-Anmeldung ist fehlgeschlagen.');
        }
        if (isset($tokenData['error'])) {
            $detail = (string) ($tokenData['error_description'] ?? $tokenData['error']);
            throw new MailboxProviderException($detail !== '' ? $detail : 'Microsoft-Anmeldung ist fehlgeschlagen.');
        }

        return $tokenData;
    }

    /**
     * @param array<string, mixed> $tokenData
     */
    private function accessTokenFrom(array $tokenData): string
    {
        $accessToken = $tokenData['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new MailboxProviderException('Microsoft hat kein Zugriffstoken geliefert.');
        }

        return $accessToken;
    }

    private function authorizeUrl(): string
    {
        return 'https://login.microsoftonline.com/' . rawurlencode($this->tenant) . '/oauth2/v2.0/authorize';
    }

    private function tokenUrl(): string
    {
        return 'https://login.microsoftonline.com/' . rawurlencode($this->tenant) . '/oauth2/v2.0/token';
    }
}
