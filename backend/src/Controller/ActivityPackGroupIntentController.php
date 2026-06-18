<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityPackContainer;
use App\Entity\ActivityPackGroupIntent;
use App\Entity\User;
use App\Service\ActivityAccessService;
use App\Service\ActivityPackGroupIntentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/activities/{activityId}/pack-group-intents', name: 'api_activity_pack_group_intents_')]
class ActivityPackGroupIntentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityAccessService $activityAccess,
        private ActivityPackGroupIntentService $intentService,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $activityId): JsonResponse
    {
        $activity = $this->findActivity($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $intents = $this->intentService->listOpenIntents($activityId);

        return new JsonResponse(array_map(
            fn (ActivityPackGroupIntent $i) => $this->intentService->serializeIntent($i),
            $intents,
        ));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(string $activityId, Request $request): JsonResponse
    {
        $activity = $this->findActivity($activityId, true);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $packItemIds = $data['pack_item_ids'] ?? [];
        if (!is_array($packItemIds)) {
            return new JsonResponse(['error' => 'pack_item_ids erforderlich'], 400);
        }

        try {
            $intent = $this->intentService->createIntent(
                $activity,
                $user,
                $packItemIds,
                isset($data['label']) ? (string) $data['label'] : null,
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse($this->intentService->serializeIntent($intent), 201);
    }

    #[Route('/{intentId}/resolve', name: 'resolve', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function resolve(string $activityId, string $intentId, Request $request): JsonResponse
    {
        $activity = $this->findActivity($activityId, true);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $intent = $this->entityManager->getRepository(ActivityPackGroupIntent::class)->find($intentId);
        if (!$intent || $intent->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Intent nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $containerId = trim((string) ($data['container_id'] ?? ''));
        if ($containerId === '') {
            return new JsonResponse(['error' => 'container_id erforderlich'], 400);
        }

        $container = $this->entityManager->getRepository(ActivityPackContainer::class)->find($containerId);
        if (!$container || $container->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Container nicht gefunden'], 404);
        }

        try {
            $this->intentService->resolveIntent($activity, $intent, $container);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse($this->intentService->serializeIntent($intent));
    }

    private function findActivity(string $activityId, bool $requireEdit = false): Activity|JsonResponse
    {
        $activity = $this->entityManager->getRepository(Activity::class)->find($activityId);
        if (!$activity) {
            return new JsonResponse(['error' => 'Aktivität nicht gefunden'], 404);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        if ($requireEdit) {
            if (!$this->activityAccess->canUserEditPackList($user, $activity)) {
                return new JsonResponse(['error' => 'Kein Zugriff'], 403);
            }
        } elseif (!$this->activityAccess->canUserViewActivity($user, $activity)) {
            return new JsonResponse(['error' => 'Kein Zugriff'], 403);
        }

        return $activity;
    }
}
