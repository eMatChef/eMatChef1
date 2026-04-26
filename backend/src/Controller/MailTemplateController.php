<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\Mail\MailTemplateContentStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/mail-templates', name: 'api_mail_templates_')]
class MailTemplateController extends AbstractController
{
    public function __construct(
        private MailTemplateContentStore $mailTemplateContent
    ) {
    }

    private function loc(Request $request): string
    {
        return $this->mailTemplateContent->localeForApiRequest($request);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('mt.unauth', $this->loc($request))], 403);
        }

        if (!$this->canViewMailTemplates()) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('mt.forbidden', $this->loc($request))], 403);
        }

        $locale = $this->mailTemplateContent->normalizeLocaleParam((string) $request->query->get('locale', 'de'));

        return new JsonResponse($this->mailTemplateContent->getCatalogForLocale($locale));
    }

    #[Route('/messages', name: 'messages_get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function messagesGet(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('mt.unauth', $this->loc($request))], 403);
        }

        if (!$this->canViewMailTemplates()) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('mt.forbidden', $this->loc($request))], 403);
        }

        $locale = $this->mailTemplateContent->normalizeLocaleParam((string) $request->query->get('locale', 'de'));

        return new JsonResponse([
            'locale' => $locale,
            'messages' => $this->mailTemplateContent->getMessagesForLocale($locale),
        ]);
    }

    #[Route('/messages', name: 'messages_put', methods: ['PUT'])]
    #[IsGranted('ROLE_SUPERADMIN')]
    public function messagesPut(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('mt.unauth', $this->loc($request))], 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('mt.json_body', $this->loc($request))], Response::HTTP_BAD_REQUEST);
        }

        $locale = $this->mailTemplateContent->normalizeLocaleParam((string) ($payload['locale'] ?? 'de'));
        $messages = $payload['messages'] ?? null;
        if (!is_array($messages)) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('mt.messages_shape', $this->loc($request))], Response::HTTP_BAD_REQUEST);
        }

        unset($messages['_catalog']);
        $allowed = array_keys($this->mailTemplateContent->getMessagesForLocale('de'));
        $filtered = [];
        foreach ($messages as $key => $patch) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                continue;
            }
            if (!is_array($patch)) {
                continue;
            }
            $filtered[$key] = $patch;
        }

        if (count($filtered) === 0) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('mt.no_valid_template_keys', $this->loc($request))], Response::HTTP_BAD_REQUEST);
        }

        $this->mailTemplateContent->mergeOverrides($locale, $filtered);

        return new JsonResponse(['ok' => true, 'locale' => $locale]);
    }

    private function canViewMailTemplates(): bool
    {
        return $this->isGranted('ROLE_SUPERADMIN');
    }
}
