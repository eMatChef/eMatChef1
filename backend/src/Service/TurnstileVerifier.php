<?php

namespace App\Service;

/**
 * Cloudflare Turnstile (https://developers.cloudflare.com/turnstile/).
 * Wenn kein Secret gesetzt ist, ist die Verifikation deaktiviert (lokale Entwicklung).
 *
 * Nutzt native HTTP (stream), damit kein Symfony HttpClient nötig ist (robust in Docker/vendor).
 */
final class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private string $secretKey;

    public function __construct(
        string $secretKey,
        private readonly bool $skipVerify = false,
    ) {
        $this->secretKey = trim($secretKey);
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '';
    }

    /**
     * Wenn true, muss ein gueltiges Turnstile-Token geprueft werden.
     * Bei skipVerify (nur Test/Lokal) oder ohne Secret: false.
     */
    public function mustValidateCaptcha(): bool
    {
        return $this->isConfigured() && !$this->skipVerify;
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

        $data = $this->postForm(self::VERIFY_URL, $body);
        if ($data === null) {
            return false;
        }

        return ($data['success'] ?? false) === true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function postForm(string $url, array $body): ?array
    {
        $payload = http_build_query($body);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $payload,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
