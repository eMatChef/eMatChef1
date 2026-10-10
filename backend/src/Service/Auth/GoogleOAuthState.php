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
     * Link-Flow: $linkUserId und $sessionId binden den Callback an den eingeloggten User und dessen Sitzung.
     *
     * @return array{token: string, cookieValue: string, nonce: string, codeVerifier: string}
     */
    public function issue(?string $redirectPath, ?string $linkUserId = null, ?string $sessionId = null): array
    {
        $nonce = bin2hex(random_bytes(16));
        $oidcNonce = bin2hex(random_bytes(32));
        $codeVerifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $intent = $linkUserId !== null ? AuthIntent::LINK_IDENTITY : AuthIntent::fromRedirect($redirectPath);
        $payload = json_encode([
            'n' => $nonce,
            'r' => $redirectPath,
            'intent' => $intent->value,
            'oidc' => $oidcNonce,
            'v' => $codeVerifier,
            'link' => $linkUserId,
            'sid' => $sessionId,
            'exp' => time() + self::TTL_SECONDS,
        ], JSON_THROW_ON_ERROR);
        $cookieValue = $this->encode($payload);

        return ['token' => $nonce, 'cookieValue' => $cookieValue, 'nonce' => $oidcNonce, 'codeVerifier' => $codeVerifier];
    }

    public function verify(string $cookieValue, string $returnedState): ?string
    {
        return $this->verifyDetailed($cookieValue, $returnedState)['redirect'] ?? null;
    }

    /**
     * @return array{redirect: string, nonce: string, code_verifier: string, link_user_id: ?string, session_id: ?string, intent: AuthIntent}|null
     */
    public function verifyDetailed(string $cookieValue, string $returnedState): ?array
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
        $link = $data['link'] ?? null;
        $sid = $data['sid'] ?? null;

        return [
            'redirect' => is_string($redirect) && $redirect !== '' ? $redirect : '',
            'nonce' => is_string($data['oidc'] ?? null) ? $data['oidc'] : '',
            'code_verifier' => is_string($data['v'] ?? null) ? $data['v'] : '',
            'link_user_id' => is_string($link) && $link !== '' ? $link : null,
            'session_id' => is_string($sid) && $sid !== '' ? $sid : null,
            'intent' => AuthIntent::tryFrom((string) ($data['intent'] ?? '')) ?? AuthIntent::fromRedirect(is_string($redirect) ? $redirect : null),
        ];
    }

    public function sanitizeRedirect(?string $path): ?string
    {
        return AuthIntent::sanitizeRedirect($path);
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
