<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Auth\CurrentAuthSession;
use App\Service\Auth\StepUpService;
use App\Service\Auth\TotpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Step-up innerhalb der bestehenden Sitzung. Erzeugt keine neue Sitzung und keine Tokens.
 */
#[Route('/api/auth/step-up', name: 'api_auth_step_up_')]
#[IsGranted('ROLE_USER')]
final class StepUpController extends AbstractController
{
    public function __construct(
        private readonly StepUpService $stepUp,
        private readonly CurrentAuthSession $currentSession,
    ) {
    }

    /** Body: {"code": "<TOTP- oder Recovery Code>"} */
    #[Route('', name: 'confirm', methods: ['POST'])]
    public function confirm(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $session = $this->currentSession->getAuthenticated();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        if ($session === null) {
            // Legacy-Token ohne Sitzung kann keinen Step-up tragen: neu anmelden.
            return $this->noStore(new JsonResponse(['error' => 'session_required', 'message' => 'Bitte melde dich erneut an.'], 409));
        }

        $data = json_decode($request->getContent(), true);
        $code = \is_array($data) && \is_string($data['code'] ?? null) ? $data['code'] : '';

        try {
            $this->stepUp->confirm($user, $session, $code);
        } catch (TotpException $e) {
            return $this->noStore(new JsonResponse(['error' => $e->reason === TotpException::NOT_ACTIVE ? 'mfa_setup_required' : $e->reason, 'message' => $e->getMessage()], match ($e->reason) {
                TotpException::LOCKED => 429,
                TotpException::NOT_ACTIVE => 403,
                default => 400,
            }));
        }

        return $this->noStore(new JsonResponse([
            'step_up' => true,
            'valid_for' => StepUpService::FRESHNESS_SECONDS,
        ]));
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
