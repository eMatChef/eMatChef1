<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GmailOAuthState;
use App\Service\Grossanlass\GrossanlassGmailAccountService;
use App\Service\Grossanlass\Mailbox\OutlookOAuthClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/api/auth/microsoft', name: 'api_auth_microsoft_outlook_')]
final class GrossanlassOutlookOAuthCallbackController extends AbstractController
{
    public function __construct(
        private readonly OutlookOAuthClient $frontend,
        private readonly GmailOAuthState $oauthState,
        private readonly GrossanlassGmailAccountService $mailbox,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%env(bool:AUTH_COOKIE_SECURE)%')]
        private readonly bool $authCookieSecure = false,
        #[Autowire('%env(default::AUTH_COOKIE_DOMAIN)%')]
        private readonly string $authCookieDomain = '',
    ) {}

    #[Route('/outlook/callback', name: 'callback', methods: ['GET'])]
    public function callback(Request $request): RedirectResponse
    {
        $state = (string) $request->query->get('state', '');
        $code = (string) $request->query->get('code', '');
        $cookieValue = (string) $request->cookies->get(GmailOAuthState::COOKIE_NAME, '');
        $error = trim((string) $request->query->get('error', ''));
        $verified = $this->oauthState->verify($cookieValue, $state);
        $departmentId = $verified['departmentId'] ?? '';

        if ($error !== '' || $verified === null || $code === '') {
            $reason = $error === 'access_denied' ? 'denied' : 'failed';

            return $this->finish($departmentId, 'error', $reason);
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        $user = $this->entityManager->getRepository(User::class)->find($verified['userId']);
        if (!$department instanceof Department || !$user instanceof User) {
            return $this->finish($departmentId, 'error', 'failed');
        }

        try {
            $this->mailbox->completeConnect($department, $user, $code, 'outlook');
        } catch (\Throwable) {
            return $this->finish($departmentId, 'error', 'failed');
        }

        return $this->finish($departmentId, 'ok');
    }

    private function finish(string $departmentId, string $status, ?string $reason = null): RedirectResponse
    {
        $path = $departmentId !== ''
            ? '/' . $departmentId . '/einstellungen/anfragen-email'
            : '/login';
        $query = ['outlook' => $status];
        if ($reason) {
            $query['reason'] = $reason;
        }
        $response = new RedirectResponse($this->frontend->getFrontendBaseUrl() . $path . '?' . http_build_query($query));
        $response->headers->setCookie($this->clearCookie());

        return $response;
    }

    private function clearCookie(): Cookie
    {
        $domain = trim($this->authCookieDomain);

        return Cookie::create(GmailOAuthState::COOKIE_NAME)
            ->withValue('')
            ->withExpires(1)
            ->withPath('/')
            ->withDomain($domain !== '' ? $domain : null)
            ->withSecure($this->authCookieSecure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }
}
