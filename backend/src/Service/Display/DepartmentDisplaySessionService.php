<?php

namespace App\Service\Display;

use App\Entity\DepartmentDisplayScreen;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Signierte HttpOnly-Cookie-Session für Infoscreens (kein User-JWT).
 *
 * Pro Screen ein eigenes, hostgebundenes Cookie (Name mit publicId, Pfad auf die Screen-API
 * begrenzt), damit mehrere Displays im selben Browser sich nicht überschreiben und das Cookie
 * nie an andere Subdomains oder an die normale App-API gesendet wird.
 */
final class DepartmentDisplaySessionService
{
    /** Altes, domainweites Sammel-Cookie (nur noch lesend akzeptiert, bis es abläuft). */
    public const LEGACY_COOKIE_NAME = 'EMC_DISPLAY_SESSION';
    public const COOKIE_PREFIX = 'EMC_DISPLAY_';

    private const TTL_SECONDS = 7776000; // 90 Tage

    public function __construct(
        #[Autowire('%env(APP_SECRET)%')] private string $appSecret,
        #[Autowire('%env(bool:AUTH_COOKIE_SECURE)%')] private bool $cookieSecure,
    ) {
    }

    public function createCookie(DepartmentDisplayScreen $screen): Cookie
    {
        $value = $this->signPayload([
            'sid' => $screen->getId(),
            'pid' => $screen->getPublicId(),
            'v' => $screen->getCodeVersion(),
            'exp' => time() + self::TTL_SECONDS,
        ]);

        return Cookie::create(self::cookieName($screen->getPublicId()))
            ->withValue($value)
            ->withExpires(new \DateTimeImmutable('+' . self::TTL_SECONDS . ' seconds'))
            ->withPath(self::cookiePath($screen->getPublicId()))
            ->withSecure($this->cookieSecure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    public function createClearCookie(string $publicId): Cookie
    {
        return Cookie::create(self::cookieName($publicId))
            ->withValue('')
            ->withExpires(new \DateTimeImmutable('-1 day'))
            ->withPath(self::cookiePath($publicId))
            ->withSecure($this->cookieSecure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    public static function cookieName(string $publicId): string
    {
        return self::COOKIE_PREFIX . strtolower($publicId);
    }

    public static function cookiePath(string $publicId): string
    {
        return '/api/public/display/' . rawurlencode($publicId);
    }

    /**
     * @return array{screen: DepartmentDisplayScreen}|null
     */
    public function resolveScreenFromRequest(
        Request $request,
        string $publicId,
        ?DepartmentDisplayScreen $screen,
    ): ?array {
        if ($screen === null || $screen->isRevoked()) {
            return null;
        }

        if (strcasecmp($screen->getPublicId(), $publicId) !== 0) {
            return null;
        }

        $raw = (string) $request->cookies->get(self::cookieName($publicId), '');
        if ($raw === '') {
            $raw = (string) $request->cookies->get(self::LEGACY_COOKIE_NAME, '');
        }
        $payload = $this->verifySignedValue($raw);
        if ($payload === null) {
            return null;
        }

        if (($payload['sid'] ?? '') !== $screen->getId()) {
            return null;
        }
        if (strcasecmp((string) ($payload['pid'] ?? ''), $screen->getPublicId()) !== 0) {
            return null;
        }
        if ((int) ($payload['v'] ?? 0) !== $screen->getCodeVersion()) {
            return null;
        }
        if ((int) ($payload['exp'] ?? 0) < time()) {
            return null;
        }

        return ['screen' => $screen];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function signPayload(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $body = $this->base64UrlEncode($json);
        $sig = hash_hmac('sha256', $body, $this->appSecret, true);

        return $body . '.' . $this->base64UrlEncode($sig);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function verifySignedValue(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '' || !str_contains($raw, '.')) {
            return null;
        }

        [$body, $sig] = explode('.', $raw, 2);
        $expected = hash_hmac('sha256', $body, $this->appSecret, true);
        $given = $this->base64UrlDecode($sig);
        if ($given === false || !hash_equals($expected, $given)) {
            return null;
        }

        $json = $this->base64UrlDecode($body);
        if ($json === false) {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    private function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $raw): string|false
    {
        $pad = strlen($raw) % 4;
        if ($pad > 0) {
            $raw .= str_repeat('=', 4 - $pad);
        }

        return base64_decode(strtr($raw, '-_', '+/'), true);
    }
}
