<?php

namespace App\Controller\Public;

use App\Service\Mail\MailTemplateContentStore;
use App\Service\Public\PublicFoundItemContactService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/public/contact', name: 'api_public_contact_')]
class PublicContactController extends AbstractController
{
    public function __construct(
        private PublicFoundItemContactService $foundItemContactService,
        private MailTemplateContentStore $mailTemplateContent
    ) {
    }

    #[Route('/found-item', name: 'found_item', methods: ['POST'])]
    public function foundItem(Request $request): JsonResponse
    {
        $loc = $this->mailTemplateContent->localeForApiRequest($request);
        $payload = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('pfd.json_invalid', $loc)], 400);
        }

        $result = $this->foundItemContactService->handle($payload, $loc);

        if (isset($result['ok'])) {
            return new JsonResponse(['ok' => true]);
        }

        return new JsonResponse(
            ['error' => $result['error'] ?? $this->mailTemplateContent->getApiString('pfd.generic', $loc)],
            (int) ($result['status'] ?? 500)
        );
    }
}
