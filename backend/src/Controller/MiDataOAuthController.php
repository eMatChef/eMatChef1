<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Auth\AdminMfaGuard;
use App\Service\Auth\AdminMfaPolicy;
use App\Service\Auth\CurrentAuthSession;
use App\Service\Auth\AuthIntent;
use App\Service\Auth\LinkResultStore;
use App\Service\Auth\OAuthCallbackSessionResolver;
use App\Service\Auth\OAuthStateReplayGuard;
use App\Service\Auth\DepartmentJoinFlowService;
use App\Service\Auth\DepartmentJoinOutcome;
use App\Service\Auth\DepartmentJoinOutcomeStatus;
use App\Service\Auth\HitobitoOAuthClient;
use App\Service\Auth\MiDataDepartmentOnboardingService;
use App\Service\Auth\MiDataGroupImportService;
use App\Service\Auth\MiDataOAuthAccountService;
use App\Service\Auth\MiDataOAuthException;
use App\Service\Auth\MiDataOAuthState;
use Doctrine\ORM\EntityManagerInterface;
use App\Enum\AuthMethod;
use App\Service\Auth\MfaChallengeService;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/auth', name: 'api_auth_')]
final class MiDataOAuthController extends AbstractController
{
    public function __construct(
        private readonly HitobitoOAuthClient $oauthClient,
        private readonly MiDataOAuthState $oauthState,
        private readonly MiDataOAuthAccountService $accountService,
        private readonly DepartmentJoinFlowService $departmentJoinFlow,
        private readonly MiDataDepartmentOnboardingService $departmentOnboarding,
        private readonly MiDataGroupImportService $groupImport,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_success')]
        private readonly AuthenticationSuccessHandler $authenticationSuccessHandler,
        private readonly LoggerInterface $logger,
        private readonly MfaChallengeService $mfaChallenges,
        private readonly CurrentAuthSession $currentSession,
        private readonly OAuthStateReplayGuard $replayGuard,
        private readonly AdminMfaGuard $mfaGuard,
        private readonly OAuthCallbackSessionResolver $callbackSessions,
        private readonly LinkResultStore $linkResults,
        #[Autowire('%env(bool:AUTH_COOKIE_SECURE)%')]
        private readonly bool $authCookieSecure = false,
        #[Autowire('%env(default::AUTH_COOKIE_DOMAIN)%')]
        private readonly string $authCookieDomain = '',
    ) {}

    #[Route('/midata', name: 'midata_start', methods: ['GET'])]
    public function start(Request $request): Response
    {
        return $this->startFlow($request, null);
    }

    #[Route('/midata/callback', name: 'midata_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $error = trim((string) $request->query->get('error', ''));
        $state = (string) $request->query->get('state', '');
        $code = (string) $request->query->get('code', '');
        $cookieValue = (string) $request->cookies->get(MiDataOAuthState::COOKIE_NAME, '');
        if ($error !== '') {
            // Abbruch beim Anbieter: das Verbinden aus dem Profil kehrt ins Profil zurück, alles andere wie bisher zur Anmeldung.
            $pending = $this->oauthState->verify($cookieValue, $state);
            $reason = $error === 'access_denied' ? 'denied' : 'failed';
            if ($pending !== null && $pending['profile_link'] && $pending['intent'] === AuthIntent::LINK_IDENTITY) {
                $session = $this->callbackSessions->resolve($request);

                return $session === null
                    ? $this->finishWithClearedState('error', 'session_expired')
                    : $this->profileLinkResult($pending, $session->getId(), 'error', $reason);
            }

            // Zurück zum ursprünglichen Einstieg (Einladung, Join-Code, Onboarding, geschützte Route), soweit der State gültig ist.
            return $this->finishWithClearedState('error', $reason, $pending['redirect'] ?? null);
        }

        $verifiedState = $this->oauthState->verify($cookieValue, $state);
        // Einmaliger State: ein bereits eingelöster Callback-Link (Replay) wird abgelehnt.
        if ($verifiedState === null || !$this->replayGuard->consume('midata', $state)) {
            return $this->finishWithClearedState('error', 'invalid_state');
        }
        $boundUser = null;
        $boundSession = null;
        if ($verifiedState['link_user_id'] !== null) {
            // Die OAuth-Firewall kennt keinen Benutzer: das JWT-Cookie wird explizit geprüft (abgelaufen/ungültig → null).
            $boundSession = $this->callbackSessions->resolve($request);
            if ($boundSession === null) {
                return $this->finishWithClearedState('error', 'session_expired');
            }
            if ($boundSession->getUser()->getId() !== $verifiedState['link_user_id'] || $boundSession->getId() !== $verifiedState['session_id']) {
                return $verifiedState['profile_link']
                    ? $this->profileLinkResult($verifiedState, $boundSession->getId(), 'error', 'session_mismatch')
                    : $this->finishWithClearedState('error', 'session_mismatch', $verifiedState['redirect']);
            }
            $boundUser = $boundSession->getUser();
        }
        if ($verifiedState['profile_link'] && $boundUser instanceof User && $boundSession !== null) {
            return $this->completeProfileLink($code, $verifiedState, $boundSession);
        }

        $joinResult = null;
        $joinDepartmentId = null;
        $onboardingResult = null;
        $onboardingDepartmentId = null;
        $groupImportResult = null;
        $mfa = null;
        try {
            $session = $this->oauthClient->fetchUserInfo(
                $code,
                $verifiedState['code_verifier'],
                $verifiedState['nonce'],
            );
            $linkUser = $boundUser;

            // Onboarding/Gruppenimport bestätigen nur ein bereits verbundenes Konto; weitere Konten nur über Profil → Sicherheit.
            $user = $this->accountService->resolveOrCreate($session->userInfo, $linkUser, false);
            // Verknüpfen ist kein Login: die bestehende Sitzung des eingeloggten Users bleibt, keine neuen Tokens.
            // MiData liefert keinen belastbaren MFA-Nachweis (kein amr/acr/auth_time): aktives eMatChef-TOTP wird verlangt.
            $mfa = $linkUser instanceof User ? null : $this->mfaChallenges->issueIfRequired($user, AuthMethod::MIDATA, false, $request);
            $authResponse = $linkUser instanceof User || $mfa !== null
                ? null
                : $this->authenticationSuccessHandler->handleAuthenticationSuccess($user);
            $onboardingId = $this->oauthState->extractDepartmentOnboardingIntent($verifiedState['redirect']);
            $candidateId = $this->oauthState->extractMembershipCandidateIntent($verifiedState['redirect']);
            $joinCode = $this->oauthState->extractDepartmentJoinCodeIntent($verifiedState['redirect']);
            $groupImportDepartmentId = $linkUser instanceof User
                ? $this->oauthState->extractGroupImportDepartmentIntent($verifiedState['redirect'])
                : null;
            if ($groupImportDepartmentId !== null) {
                // Only loads a snapshot of importable sub-groups; the actual import is a separate, authorized API call.
                try {
                    $department = $this->entityManager->getRepository(Department::class)->find($groupImportDepartmentId);
                    $groupImportResult = $department instanceof Department
                        ? $this->groupImport->loadSnapshotFromOAuthCallback($user, $department, $session)
                        : 'denied';
                } catch (\Throwable $exception) {
                    $this->logger->error('MiData group import snapshot failed', ['exception' => $exception]);
                    $groupImportResult = 'failed';
                }
            } elseif ($onboardingId !== null || $candidateId !== null) {
                try {
                    $onboardingOutcome = $onboardingId !== null
                        ? $this->departmentOnboarding->completeFromOAuthCallback($user, $onboardingId, $session)
                        : $this->departmentOnboarding->completeCandidateFromOAuthCallback($user, (string) $candidateId, $session);
                    $onboardingResult = $onboardingOutcome->status->value;
                    $onboardingDepartmentId = $onboardingOutcome->department?->getId();
                } catch (\Throwable $exception) {
                    $this->logger->error('MiData department onboarding failed', ['exception' => $exception]);
                    $onboardingResult = 'failed';
                }
            } elseif ($joinCode !== null) {
                try {
                    $joinOutcome = $this->departmentJoinFlow->verifyJoinCodeForOAuthCallback(
                        $user,
                        $joinCode,
                        $session,
                    );
                    $joinResult = $this->joinResultCode($joinOutcome);
                    if ($joinOutcome->status === DepartmentJoinOutcomeStatus::MANUAL_REQUEST_REQUIRED) {
                        $joinDepartmentId = $joinOutcome->department?->getId();
                    }
                } catch (\Throwable $exception) {
                    $this->logger->error('MiData department join processing failed', ['exception' => $exception]);
                    $joinResult = 'failed';
                }
            } else {
                try {
                    $this->departmentOnboarding->offerFromOAuthCallback($user, $session);
                } catch (\Throwable $exception) {
                    $this->logger->warning('MiData department onboarding offer failed', ['exception' => $exception]);
                }
            }
        } catch (MiDataOAuthException $exception) {
            return $this->finishWithClearedState('error', $exception->reason, $verifiedState['redirect']);
        } catch (\Throwable $exception) {
            $this->logger->error('MiData OAuth callback failed', ['exception' => $exception]);

            return $this->finishWithClearedState('error', 'failed', $verifiedState['redirect']);
        }

        $isLinkFlow = $verifiedState['link_user_id'] !== null;
        $frontendPath = $verifiedState['redirect'] !== ''
            ? $verifiedState['redirect']
            : '/login';
        if ($joinResult !== null) {
            $frontendPath = $this->withJoinResult($frontendPath, $joinResult, $joinDepartmentId);
        }
        if ($onboardingResult !== null) {
            $frontendPath = $this->withOnboardingResult($frontendPath, $onboardingResult, $onboardingDepartmentId);
        }
        if ($groupImportResult !== null) {
            $frontendPath = $this->withGroupImportResult($frontendPath, $groupImportResult);
        }
        if ($mfa !== null) {
            // Challenge im Fragment: erreicht weder Server-Logs noch Referer.
            $query = ['oauth' => 'mfa', 'provider' => 'midata'];
            if ($frontendPath !== '/login' && !str_starts_with($frontendPath, '/login?')) {
                $query['next'] = $frontendPath;
            }
            $response = new RedirectResponse($this->appendQuery($this->frontendUrl('/login'), $query) . '#challenge=' . $mfa['challenge'] . '&trust_days=' . $mfa['trust_days']);
            $response->headers->setCookie($this->stateCookie('', 1));
            $response->headers->set('Cache-Control', 'no-store');

            return $response;
        }

        $target = $this->frontendUrl($frontendPath);
        if (
            $isLinkFlow
            || $frontendPath === '/login'
            || str_starts_with($frontendPath, '/login?')
        ) {
            $target = $this->appendQuery($target, [
                'oauth' => $isLinkFlow ? 'linked' : 'ok',
                'provider' => 'midata',
            ]);
        }
        $response = new RedirectResponse($target);
        foreach ($authResponse?->headers->getCookies() ?? [] as $cookie) {
            $response->headers->setCookie($cookie);
        }
        $response->headers->setCookie($this->stateCookie('', 1));

        return $response;
    }

    /**
     * Link-Start für Onboarding und Gruppenimport (Browser-Navigation). Verbinden ist sicherheitskritisch:
     * ohne frisches Step-up (TOTP) bzw. kürzliche Anmeldung (ohne TOTP) kehrt der User mit Fehlergrund zurück.
     */
    #[Route('/link/midata', name: 'midata_link_start', methods: ['GET'])]
    public function linkStart(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }
        // Step-up/Reauth nur beim erstmaligen Verbinden von MiData (Onboarding). Wer schon ein MiData-Konto verbunden hat,
        // bestätigt hier nur dieses (Gruppenimport/Onboarding); ein weiteres Konto lehnt der Callback ab (additional_account).
        $firstLink = !$this->hasMiDataIdentity($user);
        $denial = $firstLink
            ? $this->mfaGuard->denialReason($user, $this->currentSession->getAuthenticated(), AdminMfaPolicy::LEVEL_SELF_SENSITIVE)
            : null;
        if ($denial !== null) {
            $redirect = $this->oauthState->sanitizeRedirect($request->query->get('redirect')) ?? '/';

            return new RedirectResponse($this->appendQuery(
                $this->frontendUrl(preg_replace('/[?#].*$/', '', $redirect) ?? '/'),
                ['oauth' => 'error', 'provider' => 'midata', 'reason' => $denial],
            ));
        }

        return $this->startFlow($request, $user);
    }

    /**
     * Startet das Verbinden eines (weiteren) MiData-Kontos aus Profil → Sicherheit. POST: Step-up/Reauth greift zentral
     * per AdminMfaPolicy. Der State ist an User und Sitzung gebunden; der Callback legt nur die Identität an
     * (kein Login, kein Onboarding, kein Import). Body: {"redirect": "/interner/pfad"}.
     */
    #[Route('/link/midata', name: 'midata_link_start_post', methods: ['POST'])]
    public function profileLinkStart(Request $request): Response
    {
        $user = $this->getUser();
        $session = $this->currentSession->getAuthenticated();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }
        if ($session === null) {
            return new JsonResponse(['error' => 'session_required'], Response::HTTP_CONFLICT);
        }
        if (!$this->oauthClient->isConfigured()) {
            return new JsonResponse(['error' => 'not_configured'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $body = json_decode($request->getContent(), true);
        $redirect = $this->oauthState->sanitizeRedirect(\is_array($body) && \is_string($body['redirect'] ?? null) ? $body['redirect'] : null);
        $issued = $this->oauthState->issue($redirect, $user->getId(), $session->getId(), true);
        try {
            // Immer Anmeldung erzwingen: ein weiteres Konto soll bewusst gewählt werden, nicht still die laufende MiData-Sitzung übernehmen.
            $url = $this->oauthClient->buildAuthorizationUrl($issued, HitobitoOAuthClient::PROMPT_LOGIN);
        } catch (MiDataOAuthException $exception) {
            $this->logger->error('MiData OAuth link start failed', ['reason' => $exception->reason, 'exception' => $exception]);

            return new JsonResponse(['error' => $exception->reason], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $response = new JsonResponse(['authorization_url' => $url]);
        $response->headers->setCookie($this->stateCookie($issued['cookieValue'], time() + 600));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function hasMiDataIdentity(User $user): bool
    {
        foreach ($user->getExternalIdentities() as $identity) {
            if ($identity->getProvider() === 'midata') {
                return true;
            }
        }

        return false;
    }

    /**
     * Nur die Identität verbinden: kein Login, keine neuen Tokens, kein Onboarding-Angebot, kein Join, kein Gruppenimport.
     *
     * @param array{nonce: string, code_verifier: string, redirect: string} $verified
     */
    private function completeProfileLink(string $code, array $verified, \App\Entity\UserSession $boundSession): Response
    {
        $user = $boundSession->getUser();
        try {
            $session = $this->oauthClient->fetchUserInfo($code, $verified['code_verifier'], $verified['nonce']);
            $this->accountService->resolveOrCreate($session->userInfo, $user);
        } catch (MiDataOAuthException $exception) {
            return $this->profileLinkResult($verified, $boundSession->getId(), 'error', $exception->reason);
        } catch (\Throwable $exception) {
            $this->logger->error('MiData profile link failed', ['exception' => $exception]);

            return $this->profileLinkResult($verified, $boundSession->getId(), 'error', 'failed');
        }

        return $this->profileLinkResult($verified, $boundSession->getId(), 'linked', null);
    }

    /**
     * Zurück in die App. Das Ergebnis liegt serverseitig (einmalig, an die Sitzung gebunden); die URL trägt nur den
     * Hinweis «profile_security=1», keinen Erfolg/Fehler und keine Tokens.
     *
     * @param array{redirect: string} $verified
     */
    private function profileLinkResult(array $verified, string $sessionId, string $status, ?string $reason): RedirectResponse
    {
        $this->linkResults->put($sessionId, 'midata', $status, $reason);
        $path = $this->oauthState->sanitizeRedirect($verified['redirect']) ?? '/';
        $response = new RedirectResponse($this->appendQuery($this->frontendUrl(preg_replace('/[?#].*$/', '', $path) ?? '/'), ['profile_security' => '1']));
        $response->headers->setCookie($this->stateCookie('', 1));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function startFlow(Request $request, ?User $linkToUser): Response
    {
        if (!$this->oauthClient->isConfigured()) {
            return $this->frontendRedirect('error', 'not_configured');
        }

        $redirect = $this->oauthState->sanitizeRedirect($request->query->get('redirect'));
        $issued = $this->oauthState->issue($redirect, $linkToUser?->getId(), $linkToUser !== null ? $this->currentSession->getAuthenticated()?->getId() : null);
        // A normal login must never silently take over an existing MiData browser session.
        // Link and onboarding flows verify the MiData subject against the linked identity instead.
        $prompt = $linkToUser === null ? HitobitoOAuthClient::PROMPT_LOGIN : null;
        try {
            $url = $this->oauthClient->buildAuthorizationUrl($issued, $prompt);
        } catch (MiDataOAuthException $exception) {
            $this->logger->error('MiData OAuth start failed', ['reason' => $exception->reason, 'exception' => $exception]);

            return $this->frontendRedirect('error', $exception->reason);
        }

        $response = new RedirectResponse($url);
        $response->headers->setCookie($this->stateCookie($issued['cookieValue'], time() + 600));

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
        $query = ['oauth' => $status, 'provider' => 'midata'];
        if ($reason !== null && $reason !== '') {
            $query['reason'] = $reason;
        }
        // Ursprünglicher Einstieg (bereinigter interner Pfad aus dem State) bleibt für den nächsten Versuch erhalten.
        $safeRedirect = $this->oauthState->sanitizeRedirect($redirect);
        if ($safeRedirect !== null && AuthIntent::fromRedirect($safeRedirect) !== AuthIntent::LOGIN) {
            $query['redirect'] = $safeRedirect;
        }

        return new RedirectResponse($this->frontendUrl('/login?' . http_build_query($query)));
    }

    private function frontendUrl(string $path): string
    {
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return $this->oauthClient->getFrontendBaseUrl() . $path;
    }

    /**
     * @param array<string, string> $query
     */
    private function appendQuery(string $url, array $query): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    private function joinResultCode(DepartmentJoinOutcome $outcome): string
    {
        return match ($outcome->status) {
            DepartmentJoinOutcomeStatus::MEMBERSHIP_CONFIRMED => 'joined',
            DepartmentJoinOutcomeStatus::ALREADY_MEMBER => 'already_member',
            DepartmentJoinOutcomeStatus::MANUAL_REQUEST_REQUIRED => 'request_required',
            DepartmentJoinOutcomeStatus::REQUEST_CREATED => 'request_created',
            DepartmentJoinOutcomeStatus::REQUEST_ALREADY_PENDING => 'request_pending',
            DepartmentJoinOutcomeStatus::VERIFICATION_UNAVAILABLE => 'unavailable',
            DepartmentJoinOutcomeStatus::DEPARTMENT_NOT_FOUND => 'invalid_code',
        };
    }

    private function withJoinResult(string $redirect, string $result, ?string $departmentId): string
    {
        $parts = parse_url($redirect);
        if (!is_array($parts) || ($parts['path'] ?? null) !== '/pending-assignment') {
            return $redirect;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        unset($query['auto_join']);
        $query['midata_join_result'] = $result;
        if ($result === 'request_required' && $departmentId !== null) {
            $query['midata_join_department_id'] = $departmentId;
        }

        return $parts['path'] . '?' . http_build_query($query);
    }

    private function withOnboardingResult(string $redirect, string $result, ?string $departmentId): string
    {
        $parts = parse_url($redirect);
        if (!is_array($parts) || ($parts['path'] ?? null) !== '/pending-assignment') {
            return $redirect;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        unset($query['midata_onboarding'], $query['midata_candidate']);
        $query['midata_onboarding_result'] = $result;
        if ($departmentId !== null) {
            $query['midata_onboarding_department_id'] = $departmentId;
        }

        return $parts['path'] . '?' . http_build_query($query);
    }

    private function withGroupImportResult(string $redirect, string $result): string
    {
        $parts = parse_url($redirect);
        if (!is_array($parts) || !MiDataOAuthState::isMyDepartmentPath($parts['path'] ?? null)) {
            return $redirect;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $query['midata_group_import_result'] = $result;

        return $parts['path'] . '?' . http_build_query($query);
    }

    private function stateCookie(string $value, int $expires): Cookie
    {
        $domain = trim($this->authCookieDomain);

        return Cookie::create(MiDataOAuthState::COOKIE_NAME)
            ->withValue($value)
            ->withExpires($expires)
            ->withPath('/')
            ->withDomain($domain !== '' ? $domain : null)
            ->withSecure($this->authCookieSecure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }
}
