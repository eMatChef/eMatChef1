<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Signiertes OAuth-state-Cookie (CSRF + optionale interne Redirect-URL).
 */
final class GoogleOAuthState
{
    public const COOKIE_NAME = 'emat_google_oauth_state';
    private const TTL_SECONDS = 600;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
    ) {}

    /**
     * @return array{token: string, cookieValue: string}
     */
    public function issue(?string $redirectPath): array
    {
        $nonce = bin2hex(random_bytes(16));
        $payload = json_encode([
            'n' => $nonce,
            'r' => $redirectPath,
            'exp' => time() + self::TTL_SECONDS,
        ], JSON_THROW_ON_ERROR);
        $cookieValue = $this->encode($payload);

        return ['token' => $nonce, 'cookieValue' => $cookieValue];
    }

    public function verify(string $cookieValue, string $returnedState): ?string
    {
        $parts = explode('.', $cookieValue, 2);
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
        $nonce = $data['n'] ?? null;
        $exp = $data['exp'] ?? null;
        if (!is_string($nonce) || $nonce === '' || !hash_equals($nonce, $returnedState)) {
            return null;
        }
        if (!is_int($exp) && !is_numeric($exp)) {
            return null;
        }
        if ((int) $exp < time()) {
            return null;
        }
        $redirect = $data['r'] ?? null;

        return is_string($redirect) && $redirect !== '' ? $redirect : '';
    }

    public function sanitizeRedirect(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }
        $path = trim($path);
        if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }
        if (str_contains($path, '\\') || str_contains($path, "\n") || str_contains($path, "\r")) {
            return null;
        }
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $path) === 1) {
            return null;
        }

        return $path;
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
