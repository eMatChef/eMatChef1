<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class GmailOAuthState
{
    public const COOKIE_NAME = 'emat_gmail_oauth_state';
    private const TTL_SECONDS = 600;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
    ) {}

    /**
     * Signierter Blob als Google-`state` — ohne Cookie, damit der Callback
     * auf 127.0.0.1 landen kann (Google akzeptiert kein .test).
     *
     * @return array{token: string, cookieValue: string}
     */
    public function issue(string $departmentId, string $userId): array
    {
        $payload = json_encode([
            'd' => $departmentId,
            'u' => $userId,
            'exp' => time() + self::TTL_SECONDS,
        ], JSON_THROW_ON_ERROR);
        $token = $this->encode($payload);

        return ['token' => $token, 'cookieValue' => $token];
    }

    /**
     * @return array{departmentId: string, userId: string}|null
     */
    public function verify(string $cookieValue, string $returnedState): ?array
    {
        $blob = $returnedState !== '' ? $returnedState : $cookieValue;
        $parts = explode('.', $blob, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$payloadB64, $sig] = $parts;
        $payload = $this->b64decode($payloadB64);
        if ($payload === null || !hash_equals($this->sign($payload), $sig)) {
            return null;
        }
        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($data)) {
            return null;
        }
        $exp = $data['exp'] ?? null;
        $departmentId = $data['d'] ?? null;
        $userId = $data['u'] ?? null;
        if (!is_string($departmentId) || $departmentId === '' || !is_string($userId) || $userId === '') {
            return null;
        }
        if (!is_int($exp) && !is_numeric($exp)) {
            return null;
        }
        if ((int) $exp < time()) {
            return null;
        }

        return ['departmentId' => $departmentId, 'userId' => $userId];
    }

    private function encode(string $payload): string
    {
        return $this->b64encode($payload) . '.' . $this->sign($payload);
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->appSecret);
    }

    private function b64encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function b64decode(string $b64): ?string
    {
        $padded = strtr($b64, '-_', '+/');
        $remainder = strlen($padded) % 4;
        if ($remainder > 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }
}
