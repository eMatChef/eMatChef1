<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\AuthMethod;
use App\Service\Auth\CurrentAuthSession;
use App\Service\Auth\ExternalIdentityException;
use App\Service\Auth\ExternalIdentityService;
use App\Service\Auth\AuthIntent;
use App\Service\Auth\LinkResultStore;
use App\Service\Auth\OAuthCallbackSessionResolver;
use App\Service\Auth\OAuthStateReplayGuard;
use App\Service\Auth\GoogleOAuthAccountService;
use App\Service\Auth\GoogleOAuthClient;
use App\Service\Auth\GoogleOAuthException;
use App\Service\Auth\GoogleOAuthState;
use App\Service\Auth\MfaChallengeService;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        private readonly MfaChallengeService $mfaChallenges,
        private readonly ExternalIdentityService $externalIdentities,
        private readonly CurrentAuthSession $currentSession,
        private readonly OAuthStateReplayGuard $replayGuard,
        private readonly OAuthCallbackSessionResolver $callbackSessions,
        private readonly LinkResultStore $linkResults,
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
        $response = new RedirectResponse($this->googleOAuthClient->buildAuthorizationUrl($issued['token'], $issued['nonce'], $issued['codeVerifier']));
        $response->headers->setCookie($this->stateCookie($issued['cookieValue'], time() + 600));

        return $response;
    }

    /**
     * Startet das Verbinden eines Google-Kontos für den angemeldeten User (POST: Step-up/Reauth greift zentral per
     * AdminMfaPolicy, bevor dieser Code läuft). Liefert die Google-URL; der State ist an User und Sitzung gebunden.
     * Body: {"redirect": "/interner/pfad"} (optional, nur interne Pfade).
     */
    #[Route('/link/google', name: 'google_link_start', methods: ['POST'])]
    public function linkStart(Request $request): Response
    {
        $user = $this->getUser();
        $session = $this->currentSession->getAuthenticated();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }
        if ($session === null) {
            return new JsonResponse(['error' => 'session_required'], Response::HTTP_CONFLICT);
        }
        if (!$this->googleOAuthClient->isConfigured()) {
            return new JsonResponse(['error' => 'not_configured'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $body = json_decode($request->getContent(), true);
        $redirect = $this->googleOAuthState->sanitizeRedirect(\is_array($body) && \is_string($body['redirect'] ?? null) ? $body['redirect'] : null);
        $issued = $this->googleOAuthState->issue($redirect, $user->getId(), $session->getId());
        $response = new JsonResponse([
            'authorization_url' => $this->googleOAuthClient->buildAuthorizationUrl($issued['token'], $issued['nonce'], $issued['codeVerifier']),
        ]);
        $response->headers->setCookie($this->stateCookie($issued['cookieValue'], time() + 600));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/google/callback', name: 'google_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $error = trim((string) $request->query->get('error', ''));
        $state = (string) $request->query->get('state', '');
        $code = (string) $request->query->get('code', '');
        $cookieValue = (string) $request->cookies->get(GoogleOAuthState::COOKIE_NAME, '');
        if ($error !== '') {
            // Abbruch beim Anbieter: ein Link-Flow kehrt ins Profil zurück, der normale Login zur Anmeldung.
            $pending = $this->googleOAuthState->verifyDetailed($cookieValue, $state);
            $reason = $error === 'access_denied' ? 'denied' : 'failed';
            if ($pending !== null && $pending['intent'] === AuthIntent::LINK_IDENTITY) {
                $session = $this->callbackSessions->resolve($request);

                return $session === null
                    ? $this->finishWithClearedState('error', 'session_expired')
                    : $this->linkResult($pending['redirect'], $session->getId(), 'error', $reason);
            }

            // Zurück zum ursprünglichen Einstieg (Einladung, Join-Code, geschützte Route), soweit der State gültig ist.
            return $this->finishWithClearedState('error', $reason, $pending['redirect'] ?? null);
        }

        $verified = $this->googleOAuthState->verifyDetailed($cookieValue, $state);
        // Einmaliger State: ein bereits eingelöster Callback-Link (Replay) wird abgelehnt.
        if ($verified === null || !$this->replayGuard->consume('google', $state)) {
            return $this->finishWithClearedState('error', 'invalid_state');
        }
        $internalRedirect = $verified['redirect'];
        if ($verified['link_user_id'] !== null) {
            return $this->completeLink($request, $code, $verified);
        }

        $mfa = null;
        try {
            $info = $this->googleOAuthClient->fetchUserInfo($code, $verified['code_verifier'], $verified['nonce']);
            $user = $this->googleOAuthAccountService->resolveOrCreate($info);
            // Google liefert für diesen Login keinen belastbaren MFA-Nachweis: aktives eMatChef-TOTP wird verlangt.
            $mfa = $this->mfaChallenges->issueIfRequired($user, AuthMethod::GOOGLE, false, $request);
            $authResponse = $mfa === null ? $this->authenticationSuccessHandler->handleAuthenticationSuccess($user) : null;
        } catch (GoogleOAuthException $e) {
            return $this->finishWithClearedState('error', $e->reason, $internalRedirect);
        } catch (\Throwable) {
            return $this->finishWithClearedState('error', 'failed', $internalRedirect);
        }

        if ($mfa !== null) {
            // Challenge im Fragment: erreicht weder Server-Logs noch Referer.
            $next = $internalRedirect !== '' && !str_starts_with($internalRedirect, '/login') ? '&next=' . rawurlencode($internalRedirect) : '';
            $trust = '&trust_days=' . $mfa['trust_days'];
            $response = new RedirectResponse($this->frontendUrl('/login?oauth=mfa&provider=google' . $next . '#challenge=' . $mfa['challenge'] . $trust));
            $response->headers->setCookie($this->stateCookie('', 1));
            $response->headers->set('Cache-Control', 'no-store');

            return $response;
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

    /**
     * Link-Callback: nur für den User und die Sitzung, die den Flow gestartet haben. Kein Login, kein neuer User,
     * keine neuen Tokens; die Sitzung des Users bleibt unverändert.
     *
     * @param array{redirect: string, nonce: string, code_verifier: string, link_user_id: ?string, session_id: ?string} $verified
     */
    private function completeLink(Request $request, string $code, array $verified): Response
    {
        // Die OAuth-Firewall kennt keinen Benutzer: das JWT-Cookie wird explizit geprüft (abgelaufen/ungültig → null).
        $session = $this->callbackSessions->resolve($request);
        if ($session === null) {
            // Keine Zuordnung ohne gültige Sitzung: sicher zurück zur Anmeldung.
            return $this->finishWithClearedState('error', 'session_expired');
        }
        $user = $session->getUser();
        if ($user->getId() !== $verified['link_user_id'] || $session->getId() !== $verified['session_id']) {
            return $this->linkResult($verified['redirect'], $session->getId(), 'error', 'session_mismatch');
        }

        try {
            $info = $this->googleOAuthClient->fetchUserInfo($code, $verified['code_verifier'], $verified['nonce']);
            $this->externalIdentities->attach(
                $user,
                'google',
                $info->googleId,
                $info->email,
                trim($info->firstName . ' ' . $info->lastName),
            );
        } catch (ExternalIdentityException $e) {
            return $this->linkResult($verified['redirect'], $session->getId(), 'error', $e->reason);
        } catch (GoogleOAuthException $e) {
            return $this->linkResult($verified['redirect'], $session->getId(), 'error', $e->reason);
        } catch (\Throwable) {
            return $this->linkResult($verified['redirect'], $session->getId(), 'error', 'failed');
        }

        return $this->linkResult($verified['redirect'], $session->getId(), 'linked', null);
    }

    /**
     * Zurück in die App. Das Ergebnis liegt serverseitig (einmalig, an die Sitzung gebunden); die URL trägt nur den
     * Hinweis «profile_security=1», keinen Erfolg/Fehler und keine Tokens.
     */
    private function linkResult(string $redirect, string $sessionId, string $status, ?string $reason): RedirectResponse
    {
        $this->linkResults->put($sessionId, 'google', $status, $reason);
        $path = $this->googleOAuthState->sanitizeRedirect($redirect) ?? '/';
        $path = preg_replace('/[?#].*$/', '', $path) ?? '/';
        $response = new RedirectResponse($this->frontendUrl($path . '?profile_security=1'));
        $response->headers->setCookie($this->stateCookie('', 1));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function finishWithClearedState(string $status, string $reason, ?string $redirect = null): RedirectResponse
    {
        $response = $this->frontendRedirect($status, $reason, $redirect);
        $response->headers->setCookie($this->stateCookie('', 1));

        return $response;
    }

    private function frontendRedirect(string $status, ?string $reason = null, ?string $redirect = null): RedirectResponse
    {
        $query = ['oauth' => $status];
        if ($reason !== null && $reason !== '') {
            $query['reason'] = $reason;
        }
        // Ursprünglicher Einstieg (bereinigter interner Pfad aus dem State) bleibt für den nächsten Versuch erhalten.
        $safeRedirect = $this->googleOAuthState->sanitizeRedirect($redirect);
        if ($safeRedirect !== null && AuthIntent::fromRedirect($safeRedirect) !== AuthIntent::LOGIN) {
            $query['redirect'] = $safeRedirect;
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
