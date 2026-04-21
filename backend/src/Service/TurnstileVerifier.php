<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Cloudflare Turnstile (https://developers.cloudflare.com/turnstile/).
 * Wenn kein Secret gesetzt ist, ist die Verifikation deaktiviert (lokale Entwicklung).
 */
final class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private string $secretKey;

    public function __construct(
        private HttpClientInterface $httpClient,
        string $secretKey,
    ) {
        $this->secretKey = trim($secretKey);
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '';
    }

    public function verify(string $token, ?string $remoteIp): bool
    {
        if (!$this->isConfigured() || $token === '') {
            return false;
        }

        $body = [
            'secret' => $this->secretKey,
            'response' => $token,
        ];
        if ($remoteIp !== null && $remoteIp !== '') {
            $body['remoteip'] = $remoteIp;
        }

        try {
            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => $body,
            ]);
            $data = $response->toArray();
        } catch (\Throwable) {
            return false;
        }

        return ($data['success'] ?? false) === true;
    }
}
