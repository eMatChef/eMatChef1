<?php

namespace App\Controller;

use App\Entity\Membership;
use App\Entity\User;
use App\Service\VerificationEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/mail-templates', name: 'api_mail_templates_')]
class MailTemplateController extends AbstractController
{
    public function __construct(
        private VerificationEmailService $verificationEmailService,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        if (!$this->canViewMailTemplates($request, $user)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        return new JsonResponse($this->verificationEmailService->getMailTemplateCatalog());
    }

    private function canViewMailTemplates(Request $request, User $user): bool
    {
        if (
            $this->isGranted('ROLE_SUPERADMIN') ||
            $this->isGranted('ROLE_ORGANISATIONSCHEF') ||
            $this->isGranted('ROLE_SUBORGCHEF')
        ) {
            return true;
        }

        $departmentId = trim((string) $request->query->get('department_id', ''));
        if ($departmentId === '') {
            return false;
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$membership) {
            return false;
        }

        $role = strtolower(trim((string) $membership->getRole()));
        return in_array($role, ['sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'], true);
    }
}
