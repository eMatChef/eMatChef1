<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\VerificationEmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/mail-templates', name: 'api_mail_templates_')]
class MailTemplateController extends AbstractController
{
    public function __construct(
        private VerificationEmailService $verificationEmailService
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        if (!$this->canViewMailTemplates()) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        return new JsonResponse($this->verificationEmailService->getMailTemplateCatalog());
    }

    private function canViewMailTemplates(): bool
    {
        return $this->isGranted('ROLE_SUPERADMIN');
    }
}
