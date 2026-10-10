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
     * Link-Flow: $linkUserId und $sessionId binden den Callback an den eingeloggten User und dessen Sitzung;
     * $profileLink kennzeichnet das reine Verbinden aus Profil → Sicherheit (kein Onboarding/Import im Callback).
     *
     * @return array{token: string, cookieValue: string, nonce: string, codeVerifier: string}
     */
    public function issue(?string $redirectPath, ?string $linkUserId = null, ?string $sessionId = null, bool $profileLink = false): array
    {
        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));
        $codeVerifier = $this->base64UrlEncode(random_bytes(32));
        $intent = $profileLink ? AuthIntent::LINK_IDENTITY : AuthIntent::fromRedirect($redirectPath);
        $payload = json_encode([
            'intent' => $intent->value,
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $codeVerifier,
            'redirect' => $redirectPath,
            'link_user_id' => $linkUserId,
            'sid' => $sessionId,
            'profile_link' => $profileLink,
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
     * @return array{nonce: string, code_verifier: string, redirect: string, link_user_id: ?string, session_id: ?string, profile_link: bool, intent: AuthIntent}|null
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
        $sessionId = $data['sid'] ?? null;

        if (
            !is_string($state)
            || !hash_equals($state, $returnedState)
            || !is_string($nonce)
            || !is_string($verifier)
            || (!is_int($expiresAt) && !is_numeric($expiresAt))
            || (int) $expiresAt < time()
            || ($redirect !== null && !is_string($redirect))
            || ($linkUserId !== null && !is_string($linkUserId))
            || ($sessionId !== null && !is_string($sessionId))
        ) {
            return null;
        }

        return [
            'nonce' => $nonce,
            'code_verifier' => $verifier,
            'redirect' => $redirect ?? '',
            'link_user_id' => $linkUserId,
            'session_id' => $sessionId,
            'profile_link' => ($data['profile_link'] ?? false) === true,
            'intent' => AuthIntent::tryFrom((string) ($data['intent'] ?? '')) ?? AuthIntent::fromRedirect($redirect),
        ];
    }

    public function sanitizeRedirect(?string $path): ?string
    {
        return AuthIntent::sanitizeRedirect($path);
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

    /**
     * Returns the onboarding offer the user wants to complete; it is only a reference, never authorization.
     */
    public function extractDepartmentOnboardingIntent(string $redirect): ?string
    {
        return $this->extractPendingAssignmentId($redirect, 'midata_onboarding');
    }

    /**
     * Returns the search-mode candidate the user selected; it is only a reference, never authorization.
     */
    public function extractMembershipCandidateIntent(string $redirect): ?string
    {
        return $this->extractPendingAssignmentId($redirect, 'midata_candidate');
    }

    /**
     * Returns the department whose MiData group import the user started; only a reference, never authorization.
     */
    public function extractGroupImportDepartmentIntent(string $redirect): ?string
    {
        $parts = parse_url($redirect);
        if (!is_array($parts) || !self::isMyDepartmentPath($parts['path'] ?? null)) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $id = $query['midata_group_import'] ?? null;

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{1,32}$/', $id) === 1 ? $id : null;
    }

    /** The department-scoped settings page, with its optional single-segment department prefix. */
    public static function isMyDepartmentPath(mixed $path): bool
    {
        return is_string($path) && preg_match('#^(/[A-Za-z0-9_-]+)?/dept/settings/my-department$#', $path) === 1;
    }

    private function extractPendingAssignmentId(string $redirect, string $parameter): ?string
    {
        $parts = parse_url($redirect);
        if (!is_array($parts) || ($parts['path'] ?? null) !== '/pending-assignment') {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $id = $query[$parameter] ?? null;
        if (!is_string($id)) {
            return null;
        }

        $id = trim($id);

        return preg_match('/^[a-f0-9]{12}$/', $id) === 1 ? $id : null;
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
