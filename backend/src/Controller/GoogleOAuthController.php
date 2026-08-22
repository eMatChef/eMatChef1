<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Auth\GoogleOAuthAccountService;
use App\Service\Auth\GoogleOAuthClient;
use App\Service\Auth\GoogleOAuthException;
use App\Service\Auth\GoogleOAuthState;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/auth', name: 'api_auth_')]
final class GoogleOAuthController extends AbstractController
{
    public function __construct(
        private readonly GoogleOAuthClient $googleOAuthClient,
        private readonly GoogleOAuthState $googleOAuthState,
        private readonly GoogleOAuthAccountService $googleOAuthAccountService,
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_success')]
        private readonly AuthenticationSuccessHandler $authenticationSuccessHandler,
        #[Autowire('%env(bool:AUTH_COOKIE_SECURE)%')]
        private readonly bool $authCookieSecure = false,
        #[Autowire('%env(default::AUTH_COOKIE_DOMAIN)%')]
        private readonly string $authCookieDomain = '',
    ) {}

    #[Route('/google', name: 'google_start', methods: ['GET'])]
    public function start(Request $request): Response
    {
        if (!$this->googleOAuthClient->isConfigured()) {
            return $this->frontendRedirect('error', 'not_configured');
        }

        $redirect = $this->googleOAuthState->sanitizeRedirect($request->query->get('redirect'));
        $issued = $this->googleOAuthState->issue($redirect);
        $response = new RedirectResponse($this->googleOAuthClient->buildAuthorizationUrl($issued['token']));
        $response->headers->setCookie($this->stateCookie($issued['cookieValue'], time() + 600));

        return $response;
    }

    #[Route('/google/callback', name: 'google_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $error = trim((string) $request->query->get('error', ''));
        if ($error === 'access_denied') {
            return $this->finishWithClearedState('error', 'denied');
        }
        if ($error !== '') {
            return $this->finishWithClearedState('error', 'failed');
        }

        $state = (string) $request->query->get('state', '');
        $code = (string) $request->query->get('code', '');
        $cookieValue = (string) $request->cookies->get(GoogleOAuthState::COOKIE_NAME, '');
        $internalRedirect = $this->googleOAuthState->verify($cookieValue, $state);
        if ($internalRedirect === null) {
            return $this->finishWithClearedState('error', 'invalid_state');
        }

        try {
            $info = $this->googleOAuthClient->fetchUserInfo($code);
            $user = $this->googleOAuthAccountService->resolveOrCreate($info);
            $authResponse = $this->authenticationSuccessHandler->handleAuthenticationSuccess($user);
        } catch (GoogleOAuthException $e) {
            return $this->finishWithClearedState('error', $e->reason);
        } catch (\Throwable) {
            return $this->finishWithClearedState('error', 'failed');
        }

        $frontendPath = $internalRedirect !== '' ? $internalRedirect : '/login';
        $separator = str_contains($frontendPath, '?') ? '&' : '?';
        if ($frontendPath === '/login' || str_starts_with($frontendPath, '/login?')) {
            $target = $this->frontendUrl($frontendPath . $separator . 'oauth=ok');
        } else {
            $target = $this->frontendUrl($frontendPath);
        }

        $response = new RedirectResponse($target);
        foreach ($authResponse->headers->getCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }
        $response->headers->setCookie($this->stateCookie('', 1));

        return $response;
    }

    private function finishWithClearedState(string $status, string $reason): RedirectResponse
    {
        $response = $this->frontendRedirect($status, $reason);
        $response->headers->setCookie($this->stateCookie('', 1));

        return $response;
    }

    private function frontendRedirect(string $status, ?string $reason = null): RedirectResponse
    {
        $query = ['oauth' => $status];
        if ($reason !== null && $reason !== '') {
            $query['reason'] = $reason;
        }

        return new RedirectResponse($this->frontendUrl('/login?' . http_build_query($query)));
    }

    private function frontendUrl(string $path): string
    {
        $base = $this->googleOAuthClient->getFrontendBaseUrl();
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return $base . $path;
    }

    private function stateCookie(string $value, int $expires): Cookie
    {
        $domain = trim($this->authCookieDomain);

        return Cookie::create(GoogleOAuthState::COOKIE_NAME)
            ->withValue($value)
            ->withExpires($expires)
            ->withPath('/')
            ->withDomain($domain !== '' ? $domain : null)
            ->withSecure($this->authCookieSecure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }
}
