<?php

namespace App\Service\Auth;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * HttpOnly JWT/Refresh-Cookies und lesbares Logout-Signal für alle *.ematchef.ch-Frontends.
 */
final class CrossSubdomainAuthCookies
{
    public const LOGOUT_MARKER_COOKIE = 'emat_logged_out';

    public function __construct(
        #[Autowire('%env(default::AUTH_COOKIE_DOMAIN)%')]
        private string $authCookieDomain = '',
        #[Autowire('%env(bool:AUTH_COOKIE_SECURE)%')]
        private bool $authCookieSecure = false,
    ) {}

    public function clearAuthCookies(Response $response): void
    {
        $domain = $this->resolvedDomain();
        foreach (['BEARER', 'refresh_token'] as $cookieName) {
            $response->headers->clearCookie(
                $cookieName,
                '/',
                $domain,
                $this->authCookieSecure,
                true,
                Cookie::SAMESITE_LAX
            );
        }
    }

    /** Signal für andere Subdomains: localStorage-Session verwerfen. */
    public function setLogoutMarker(Response $response): void
    {
        $domain = $this->resolvedDomain();
        $response->headers->setCookie(Cookie::create(
            self::LOGOUT_MARKER_COOKIE,
            (string) time(),
            time() + 86400,
            '/',
            $domain,
            $this->authCookieSecure,
            false,
            false,
            Cookie::SAMESITE_LAX
        ));
    }

    public function clearLogoutMarker(Response $response): void
    {
        $domain = $this->resolvedDomain();
        $response->headers->clearCookie(
            self::LOGOUT_MARKER_COOKIE,
            '/',
            $domain,
            $this->authCookieSecure,
            false,
            Cookie::SAMESITE_LAX
        );
    }

    private function resolvedDomain(): ?string
    {
        $domain = trim($this->authCookieDomain);

        return '' !== $domain ? $domain : null;
    }
}
