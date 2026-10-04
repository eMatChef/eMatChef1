<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Auth\HitobitoOAuthClient;
use App\Service\Auth\MiDataOAuthAccountService;
use App\Service\Auth\MiDataOAuthException;
use App\Service\Auth\MiDataOAuthState;
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
        private readonly UserRepository $userRepository,
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_success')]
        private readonly AuthenticationSuccessHandler $authenticationSuccessHandler,
        private readonly LoggerInterface $logger,
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
        if ($error === 'access_denied') {
            return $this->finishWithClearedState('error', 'denied');
        }
        if ($error !== '') {
            return $this->finishWithClearedState('error', 'failed');
        }

        $state = (string) $request->query->get('state', '');
        $code = (string) $request->query->get('code', '');
        $cookieValue = (string) $request->cookies->get(MiDataOAuthState::COOKIE_NAME, '');
        $verifiedState = $this->oauthState->verify($cookieValue, $state);
        if ($verifiedState === null) {
            return $this->finishWithClearedState('error', 'invalid_state');
        }

        try {
            $info = $this->oauthClient->fetchUserInfo(
                $code,
                $verifiedState['code_verifier'],
                $verifiedState['nonce'],
            );
            $linkUser = $verifiedState['link_user_id'] !== null
                ? $this->userRepository->find($verifiedState['link_user_id'])
                : null;
            if ($verifiedState['link_user_id'] !== null && !($linkUser instanceof User)) {
                throw new MiDataOAuthException('link_conflict', 'The account to link no longer exists');
            }

            $user = $this->accountService->resolveOrCreate($info, $linkUser);
            $authResponse = $this->authenticationSuccessHandler->handleAuthenticationSuccess($user);
        } catch (MiDataOAuthException $exception) {
            return $this->finishWithClearedState('error', $exception->reason);
        } catch (\Throwable $exception) {
            $this->logger->error('MiData OAuth callback failed', ['exception' => $exception]);

            return $this->finishWithClearedState('error', 'failed');
        }

        $isLinkFlow = $verifiedState['link_user_id'] !== null;
        $frontendPath = $verifiedState['redirect'] !== ''
            ? $verifiedState['redirect']
            : '/login';
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
        foreach ($authResponse->headers->getCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }
        $response->headers->setCookie($this->stateCookie('', 1));

        return $response;
    }

    #[Route('/link/midata', name: 'midata_link_start', methods: ['GET'])]
    public function linkStart(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->startFlow($request, $user);
    }

    private function startFlow(Request $request, ?User $linkToUser): Response
    {
        if (!$this->oauthClient->isConfigured()) {
            return $this->frontendRedirect('error', 'not_configured');
        }

        $redirect = $this->oauthState->sanitizeRedirect($request->query->get('redirect'));
        $issued = $this->oauthState->issue($redirect, $linkToUser?->getId());
        try {
            $url = $this->oauthClient->buildAuthorizationUrl($issued);
        } catch (MiDataOAuthException $exception) {
            $this->logger->error('MiData OAuth start failed', ['reason' => $exception->reason, 'exception' => $exception]);

            return $this->frontendRedirect('error', $exception->reason);
        }

        $response = new RedirectResponse($url);
        $response->headers->setCookie($this->stateCookie($issued['cookieValue'], time() + 600));

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
        $query = ['oauth' => $status, 'provider' => 'midata'];
        if ($reason !== null && $reason !== '') {
            $query['reason'] = $reason;
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
