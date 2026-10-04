<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class MiDataOAuthState
{
    public const COOKIE_NAME = 'emat_midata_oauth_state';
    private const TTL_SECONDS = 600;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
    ) {}

    /**
     * @return array{token: string, cookieValue: string, nonce: string, codeVerifier: string}
     */
    public function issue(?string $redirectPath, ?string $linkUserId = null): array
    {
        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));
        $codeVerifier = $this->base64UrlEncode(random_bytes(32));
        $payload = json_encode([
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $codeVerifier,
            'redirect' => $redirectPath,
            'link_user_id' => $linkUserId,
            'exp' => time() + self::TTL_SECONDS,
        ], JSON_THROW_ON_ERROR);

        return [
            'token' => $state,
            'cookieValue' => $this->base64UrlEncode($payload) . '.' . $this->sign($payload),
            'nonce' => $nonce,
            'codeVerifier' => $codeVerifier,
        ];
    }

    /**
     * @return array{nonce: string, code_verifier: string, redirect: string, link_user_id: ?string}|null
     */
    public function verify(string $cookieValue, string $returnedState): ?array
    {
        $parts = explode('.', $cookieValue, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$payloadB64, $signature] = $parts;
        $payload = $this->base64UrlDecode($payloadB64);
        if ($payload === null || !hash_equals($this->sign($payload), $signature)) {
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

        $state = $data['state'] ?? null;
        $nonce = $data['nonce'] ?? null;
        $verifier = $data['verifier'] ?? null;
        $expiresAt = $data['exp'] ?? null;
        $redirect = $data['redirect'] ?? null;
        $linkUserId = $data['link_user_id'] ?? null;

        if (
            !is_string($state)
            || !hash_equals($state, $returnedState)
            || !is_string($nonce)
            || !is_string($verifier)
            || (!is_int($expiresAt) && !is_numeric($expiresAt))
            || (int) $expiresAt < time()
            || ($redirect !== null && !is_string($redirect))
            || ($linkUserId !== null && !is_string($linkUserId))
        ) {
            return null;
        }

        return [
            'nonce' => $nonce,
            'code_verifier' => $verifier,
            'redirect' => $redirect ?? '',
            'link_user_id' => $linkUserId,
        ];
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

    public function extractDepartmentJoinCodeIntent(string $redirect): ?string
    {
        $parts = parse_url($redirect);
        if (!is_array($parts) || ($parts['path'] ?? null) !== '/pending-assignment') {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        if (array_key_exists('invite_id', $query) || !is_string($query['join_code'] ?? null)) {
            return null;
        }

        $joinCode = trim($query['join_code']);

        return $joinCode !== '' ? $joinCode : null;
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->appSecret);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
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
}
