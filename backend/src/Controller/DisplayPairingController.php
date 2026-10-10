<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\Display\DepartmentDisplayScreenService;
use App\Service\Display\DisplayPairingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Smartphone-Seite der QR-Kopplung: angemeldeter, berechtigter User wählt einen Infoscreen und bestätigt.
 */
#[Route('/api/display-pairing', name: 'api_display_pairing_')]
#[IsGranted('ROLE_USER')]
class DisplayPairingController extends AbstractController
{
    public function __construct(
        private DisplayPairingService $pairingService,
        private DepartmentDisplayScreenService $screenService,
    ) {
    }

    /** Infoscreens (Departments und Grossanlässe), die der User koppeln darf. */
    #[Route('/screens', name: 'screens', methods: ['GET'])]
    public function screens(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $rows = [];
        foreach ($this->screenService->listManageableScreens($user) as $row) {
            $rows[] = [
                'id' => $row['screen']->getId(),
                'name' => $row['screen']->getName(),
                'department_id' => $row['department']->getId(),
                'department_name' => $row['department']->getName(),
                'is_grossanlass' => $row['department']->isGrossanlass(),
            ];
        }

        return new JsonResponse($rows);
    }

    #[Route('/requests/{token}', name: 'show', methods: ['GET'])]
    public function show(string $token): JsonResponse
    {
        $pairing = $this->pairingService->findOpenByToken($token);
        if ($pairing === null) {
            return new JsonResponse(['error' => 'Kopplungsanfrage abgelaufen oder bereits verwendet'], 410);
        }

        return new JsonResponse([
            'user_code' => $pairing->getUserCode(),
            'expires_at' => $pairing->getExpiresAt()->format('c'),
        ]);
    }

    #[Route('/requests/{token}/approve', name: 'approve', methods: ['POST'])]
    public function approve(string $token, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $screenId = is_array($data) ? trim((string) ($data['screen_id'] ?? '')) : '';
        if ($screenId === '') {
            return new JsonResponse(['error' => 'screen_id ist erforderlich'], 400);
        }

        return match ($this->pairingService->approve($token, $user, $screenId)) {
            DisplayPairingService::APPROVE_OK => new JsonResponse(['approved' => true]),
            DisplayPairingService::APPROVE_FORBIDDEN => new JsonResponse(['error' => 'Keine Berechtigung'], 403),
            DisplayPairingService::APPROVE_NOT_FOUND => new JsonResponse(['error' => 'Screen nicht gefunden'], 404),
            default => new JsonResponse(['error' => 'Kopplungsanfrage abgelaufen oder bereits verwendet'], 410),
        };
    }
}
