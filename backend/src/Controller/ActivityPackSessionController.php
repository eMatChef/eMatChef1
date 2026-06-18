<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\User;
use App\Service\ActivityAccessService;
use App\Service\ActivityPackSessionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/activities/{activityId}/pack-session', name: 'api_activity_pack_session_')]
class ActivityPackSessionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityAccessService $activityAccess,
        private ActivityPackSessionService $sessionService,
    ) {}

    #[Route('/presence', name: 'presence_get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listPresence(string $activityId): JsonResponse
    {
        $activity = $this->findActivity($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $user = $this->getUser();
        $excludeId = $user instanceof User ? $user->getId() : null;

        return new JsonResponse([
            'viewers' => $this->sessionService->listActive($activityId, $excludeId),
        ]);
    }

    #[Route('/presence', name: 'presence_patch', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updatePresence(string $activityId, Request $request): JsonResponse
    {
        $activity = $this->findActivity($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        return new JsonResponse([
            'viewers' => $this->sessionService->upsertPresence($activity, $user, [
                'shelf' => $data['shelf'] ?? null,
                'container_id' => $data['containerId'] ?? $data['container_id'] ?? null,
                'journey_step' => $data['journeyStep'] ?? $data['journey_step'] ?? null,
            ]),
        ]);
    }

    private function findActivity(string $activityId): Activity|JsonResponse
    {
        $activity = $this->entityManager->getRepository(Activity::class)->find($activityId);
        if (!$activity) {
            return new JsonResponse(['error' => 'Aktivität nicht gefunden'], 404);
        }

        $user = $this->getUser();
        if (!$user instanceof User || !$this->activityAccess->canUserViewActivity($user, $activity)) {
            return new JsonResponse(['error' => 'Kein Zugriff'], 403);
        }

        return $activity;
    }
}
