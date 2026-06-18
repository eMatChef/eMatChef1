<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityReplenishmentWish;
use App\Entity\MaterialItem;
use App\Entity\User;
use App\Service\ActivityAccessService;
use App\Service\ActivityReplenishmentWishService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/activities/{activityId}/replenishment-wishes', name: 'api_activity_replenishment_wishes_')]
class ActivityReplenishmentWishController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityAccessService $activityAccess,
        private ActivityReplenishmentWishService $wishService,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $activityId, Request $request): JsonResponse
    {
        $activity = $this->findActivity($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $canManage = $this->activityAccess->canUserEditPackList($user, $activity);
        $status = trim((string) $request->query->get('status', ''));

        $qb = $this->entityManager->getRepository(ActivityReplenishmentWish::class)->createQueryBuilder('w')
            ->where('w.activityId = :activityId')
            ->setParameter('activityId', $activityId)
            ->orderBy('w.requestedAt', 'DESC');

        if (!$canManage) {
            $qb->andWhere('w.requestedByUserId = :userId')
                ->setParameter('userId', $user->getId());
        }
        if ($status !== '') {
            $qb->andWhere('w.status = :status')->setParameter('status', $status);
        }

        $wishes = $qb->getQuery()->getResult();

        return new JsonResponse(array_map(
            fn (ActivityReplenishmentWish $w) => $this->wishService->serializeWish($w),
            $wishes,
        ));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(string $activityId, Request $request): JsonResponse
    {
        $activity = $this->findActivity($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        if (!$this->activityAccess->canUserRequestConsumableReplenishment($user, $activity)
            && !$this->activityAccess->canUserViewActivity($user, $activity)) {
            return new JsonResponse(['error' => 'Kein Zugriff'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $materialId = trim((string) ($data['material_item_id'] ?? ''));
        if ($materialId === '') {
            return new JsonResponse(['error' => 'material_item_id erforderlich'], 400);
        }

        $material = $this->entityManager->getRepository(MaterialItem::class)->find($materialId);
        if (!$material) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }

        $qty = max(1, (int) ($data['quantity'] ?? 1));
        $notes = isset($data['notes']) ? (string) $data['notes'] : null;
        $snapshot = isset($data['availability_snapshot']) && is_array($data['availability_snapshot'])
            ? $data['availability_snapshot']
            : null;

        $wish = $this->wishService->createWish($activity, $user, $material, $qty, $notes, $snapshot);

        return new JsonResponse($this->wishService->serializeWish($wish), 201);
    }

    #[Route('/{wishId}', name: 'patch', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function patch(string $activityId, string $wishId, Request $request): JsonResponse
    {
        $activity = $this->findActivity($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $wish = $this->findWish($activityId, $wishId);
        if ($wish instanceof JsonResponse) {
            return $wish;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $action = trim((string) ($data['action'] ?? ''));

        if ($action === 'cancel') {
            if ($wish->getRequestedByUserId() !== $user->getId()) {
                return new JsonResponse(['error' => 'Kein Zugriff'], 403);
            }
            if ($wish->getStatus() !== ActivityReplenishmentWish::STATUS_PENDING) {
                return new JsonResponse(['error' => 'Wunsch nicht mehr offen'], 400);
            }
            $wish->setStatus(ActivityReplenishmentWish::STATUS_CANCELLED);
            $wish->touch();
            $this->entityManager->flush();
            return new JsonResponse($this->wishService->serializeWish($wish));
        }

        if (!$this->activityAccess->canUserEditPackList($user, $activity)) {
            return new JsonResponse(['error' => 'Kein Zugriff'], 403);
        }

        if ($action === 'reject') {
            if ($wish->getStatus() !== ActivityReplenishmentWish::STATUS_PENDING) {
                return new JsonResponse(['error' => 'Wunsch nicht mehr offen'], 400);
            }
            $this->wishService->rejectWish($wish, $user, isset($data['reason']) ? (string) $data['reason'] : null);
            return new JsonResponse($this->wishService->serializeWish($wish));
        }

        return new JsonResponse(['error' => 'Unbekannte Aktion'], 400);
    }

    #[Route('/{wishId}/fulfill', name: 'fulfill', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function fulfill(string $activityId, string $wishId): JsonResponse
    {
        $activity = $this->findActivity($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $user = $this->getUser();
        if (!$user instanceof User || !$this->activityAccess->canUserEditPackList($user, $activity)) {
            return new JsonResponse(['error' => 'Kein Zugriff'], 403);
        }

        $wish = $this->findWish($activityId, $wishId);
        if ($wish instanceof JsonResponse) {
            return $wish;
        }

        if ($wish->getStatus() !== ActivityReplenishmentWish::STATUS_PENDING) {
            return new JsonResponse(['error' => 'Wunsch nicht mehr offen'], 400);
        }

        $this->wishService->fulfillWish($wish, $user);

        return new JsonResponse($this->wishService->serializeWish($wish));
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

    private function findWish(string $activityId, string $wishId): ActivityReplenishmentWish|JsonResponse
    {
        $wish = $this->entityManager->getRepository(ActivityReplenishmentWish::class)->find($wishId);
        if (!$wish || $wish->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Wunsch nicht gefunden'], 404);
        }

        return $wish;
    }
}
