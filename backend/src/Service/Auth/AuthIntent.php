<?php

declare(strict_types=1);

namespace App\Service\Auth;

/**
 * Wofür ein OAuth-Durchlauf gestartet wurde. Der Intent wird beim Start serverseitig festgelegt (aus dem
 * bereinigten internen Rückweg bzw. dem expliziten Link-Flow), im signierten State-Cookie gespeichert und beim
 * Callback gelesen. Er kommt nie als Parameter vom Client. Nur LINK_IDENTITY führt zurück in Profil → Sicherheit.
 */
enum AuthIntent: string
{
    case LOGIN = 'login';
    case LINK_IDENTITY = 'link_identity';
    case INVITATION = 'invitation';
    case JOIN_CODE = 'join_code';
    case PROTECTED_ROUTE = 'protected_route';
    case MIDATA_ONBOARDING = 'midata_onboarding';

    /** Parameter, die ausschliesslich der Server beim Rückweg setzt; in nutzergelieferten Rückwegen nie zulässig. */
    public const RESERVED_RETURN_PARAMS = ['profile_security', 'oauth', 'provider', 'reason'];

    /** Intent aus dem bereinigten internen Rückweg (kein Link-Flow). */
    public static function fromRedirect(?string $redirect): self
    {
        if ($redirect === null || $redirect === '') {
            return self::LOGIN;
        }
        $parts = parse_url($redirect);
        $path = \is_array($parts) ? (string) ($parts['path'] ?? '') : '';
        if ($path === '' || $path === '/' || $path === '/login') {
            return self::LOGIN;
        }
        parse_str(\is_array($parts) ? (string) ($parts['query'] ?? '') : '', $query);
        if ($path === '/pending-assignment') {
            if (\is_string($query['midata_onboarding'] ?? null) || \is_string($query['midata_candidate'] ?? null)) {
                return self::MIDATA_ONBOARDING;
            }
            if (\is_string($query['join_code'] ?? null)) {
                // E-Mail-Einladung trägt zusätzlich die Einladungs-ID; QR-Code/Join-Code nur den Code.
                return \is_string($query['invite_id'] ?? null) ? self::INVITATION : self::JOIN_CODE;
            }
            if (\is_string($query['invite_id'] ?? null)) {
                return self::INVITATION;
            }
        }

        return self::PROTECTED_ROUTE;
    }

    /**
     * Nur interne Pfade; keine absoluten/protokoll-relativen URLs, keine Steuerzeichen. Server-reservierte
     * Rückweg-Parameter werden entfernt, damit ein Login-Link nie Profil → Sicherheit öffnen kann.
     */
    public static function sanitizeRedirect(?string $path): ?string
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

        return self::stripReservedParams($path);
    }

    private static function stripReservedParams(string $path): string
    {
        $queryStart = strpos($path, '?');
        if ($queryStart === false) {
            return $path;
        }
        $fragmentStart = strpos($path, '#', $queryStart);
        $query = substr($path, $queryStart + 1, $fragmentStart === false ? null : $fragmentStart - $queryStart - 1);
        $fragment = $fragmentStart === false ? '' : substr($path, $fragmentStart);
        $kept = array_filter(
            explode('&', $query),
            static fn (string $pair): bool => $pair !== '' && !\in_array(urldecode(explode('=', $pair, 2)[0]), self::RESERVED_RETURN_PARAMS, true),
        );

        return substr($path, 0, $queryStart) . ($kept === [] ? '' : '?' . implode('&', $kept)) . $fragment;
    }
}
