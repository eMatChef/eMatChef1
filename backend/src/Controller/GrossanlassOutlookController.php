<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GmailOAuthState;
use App\Service\Grossanlass\GrossanlassGmailAccountService;
use App\Service\Grossanlass\Mailbox\GrossanlassMailboxRegistry;
use App\Service\GroupAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/grossanlass/outlook', name: 'api_grossanlass_outlook_')]
final class GrossanlassOutlookController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassGmailAccountService $mailbox,
        private GrossanlassMailboxRegistry $providers,
        private GmailOAuthState $oauthState,
        private GroupAccessService $groupAccess,
        #[Autowire('%env(bool:AUTH_COOKIE_SECURE)%')]
        private readonly bool $authCookieSecure = false,
        #[Autowire('%env(default::AUTH_COOKIE_DOMAIN)%')]
        private readonly string $authCookieDomain = '',
    ) {}

    #[Route('/connect', name: 'connect', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function connect(string $departmentId): Response
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        $user = $this->getUser();
        if (!$department instanceof Department || !$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht gefunden'], 404);
        }
        if (!$department->isGrossanlass() || !$this->groupAccess->userHasDepartmentMembership($user->getId(), $departmentId)) {
            return new JsonResponse(['error' => 'Kein Zugriff auf diese Abteilung'], 403);
        }
        $outlook = $this->providers->get('outlook');
        if (!$outlook->isConfigured()) {
            return new JsonResponse(['error' => 'Outlook 365 ist nicht eingerichtet'], 400);
        }
        try {
            $this->mailbox->status($department, $user);
            $issued = $this->oauthState->issue($department->getId(), $user->getId());
            $response = new RedirectResponse($outlook->authorizationUrl($issued['token']));
            $response->headers->setCookie($this->stateCookie($issued['cookieValue'], time() + 600));

            return $response;
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    private function stateCookie(string $value, int $expires): Cookie
    {
        $domain = trim($this->authCookieDomain);

        return Cookie::create(GmailOAuthState::COOKIE_NAME)
            ->withValue($value)
            ->withExpires($expires)
            ->withPath('/')
            ->withDomain($domain !== '' ? $domain : null)
            ->withSecure($this->authCookieSecure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }
}
