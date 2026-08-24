<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassInquiry;
use App\Entity\User;
use App\Service\Auth\GoogleOAuthException;
use App\Service\Grossanlass\GmailOAuthClient;
use App\Service\Grossanlass\GmailOAuthState;
use App\Service\Grossanlass\GrossanlassGmailAccountService;
use App\Service\Grossanlass\GrossanlassMailMergeService;
use App\Service\GroupAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/grossanlass/gmail', name: 'api_grossanlass_gmail_')]
class GrossanlassGmailController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassGmailAccountService $gmail,
        private GrossanlassMailMergeService $merge,
        private GmailOAuthClient $oauth,
        private GmailOAuthState $oauthState,
        private GroupAccessService $groupAccess,
        #[Autowire('%env(bool:AUTH_COOKIE_SECURE)%')]
        private readonly bool $authCookieSecure = false,
        #[Autowire('%env(default::AUTH_COOKIE_DOMAIN)%')]
        private readonly string $authCookieDomain = '',
    ) {}

    #[Route('/status', name: 'status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function status(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $department, User $user) => $this->gmail->status($department, $user));
    }

    #[Route('/connect', name: 'connect', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function connect(string $departmentId): Response
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        $user = $this->getUser();
        if (!$department instanceof Department || !$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht gefunden'], 404);
        }
        try {
            $this->gmail->status($department, $user);
            if (!$this->oauth->isConfigured()) {
                return new JsonResponse(['error' => 'Google OAuth ist nicht konfiguriert'], 400);
            }
            $issued = $this->oauthState->issue($department->getId(), $user->getId());
            $response = new RedirectResponse($this->oauth->buildAuthorizationUrl($issued['token']));
            $response->headers->setCookie($this->stateCookie($issued['cookieValue'], time() + 600));

            return $response;
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[Route('/disconnect', name: 'disconnect', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function disconnect(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, fn (Department $department, User $user) => $this->gmail->disconnect($department, $user));
    }

    #[Route('/templates', name: 'templates', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function templates(string $departmentId): JsonResponse
    {
        return $this->handle($departmentId, function (Department $department, User $user) {
            $this->gmail->status($department, $user);

            return $this->merge->listTemplates($department);
        });
    }

    #[Route('/templates', name: 'templates_save', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function saveTemplates(string $departmentId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $templates = is_array($data['templates'] ?? null) ? $data['templates'] : $data;
        $custom = is_array($data['custom_placeholders'] ?? null) ? $data['custom_placeholders'] : [];

        return $this->handle($departmentId, function (Department $department, User $user) use ($templates, $custom) {
            $this->gmail->status($department, $user);

            return $this->merge->saveTemplates($department, is_array($templates) ? $templates : [], is_array($custom) ? $custom : []);
        });
    }

    #[Route('/preview', name: 'preview', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function preview(string $departmentId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handle($departmentId, function (Department $department, User $user) use ($data) {
            $this->gmail->status($department, $user);
            $inquiry = null;
            $inquiryId = trim((string) ($data['inquiry_id'] ?? ''));
            if ($inquiryId !== '') {
                $inquiry = $this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)->find($inquiryId);
                if (!$inquiry instanceof DepartmentGrossanlassInquiry || $inquiry->getDepartmentId() !== $department->getId()) {
                    throw new \InvalidArgumentException('Anfrage nicht gefunden');
                }
            }

            return $this->merge->preview($department, $inquiry, (string) ($data['kind'] ?? 'anfrage'));
        });
    }

    /**
     * @param callable(Department, User): mixed $fn
     */
    private function handle(string $departmentId, callable $fn, int $okStatus = 200): JsonResponse
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if ($department === null) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }
        if (!$department->isGrossanlass()) {
            return new JsonResponse(['error' => 'Kein Grossanlass-Department'], 400);
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->groupAccess->userHasDepartmentMembership($user->getId(), $departmentId)) {
            return new JsonResponse(['error' => 'Kein Zugriff auf diese Abteilung'], 403);
        }
        try {
            return new JsonResponse($fn($department, $user), $okStatus);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (GoogleOAuthException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'reason' => $e->reason], 400);
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
