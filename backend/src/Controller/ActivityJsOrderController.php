<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityJsOrder;
use App\Entity\User;
use App\Service\ActivityAccessService;
use App\Service\JsOrderPrefillService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/activities/{activityId}', name: 'api_activity_js_order_')]
class ActivityJsOrderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityAccessService $activityAccess,
        private JsOrderPrefillService $prefillService,
    ) {}

    #[Route('/js-order', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getOrder(string $activityId): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $deny = $this->assertJsOrderContext($activity);
        if ($deny !== null) {
            return $deny;
        }

        $order = $this->findOrderForActivity($activityId);
        if (!$order instanceof ActivityJsOrder) {
            return new JsonResponse(['order' => null]);
        }

        return new JsonResponse(['order' => $this->serializeOrder($order)]);
    }

    #[Route('/js-order', name: 'put', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function putOrder(string $activityId, Request $request): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $deny = $this->assertJsOrderContext($activity);
        if ($deny !== null) {
            return $deny;
        }

        $editDeny = $this->assertCanEditJsOrder($user, $activity);
        if ($editDeny !== null) {
            return $editDeny;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $order = $this->findOrderForActivity($activityId);
        $created = false;
        if (!$order instanceof ActivityJsOrder) {
            $order = new ActivityJsOrder();
            $order->setId(IdGenerator::generate13Unique($this->entityManager, ActivityJsOrder::class, 'jo'));
            $order->setActivity($activity);
            $order->setStatus(ActivityJsOrder::STATUS_DRAFT);
            $order->setDeliveryType($this->prefillService->resolveDefaultDeliveryType($activity));
            $this->prefillService->applyPrefill($order, $activity, $user, true);
            $this->entityManager->persist($order);
            $created = true;
        }

        if ($order->getStatus() === ActivityJsOrder::STATUS_ORDERED) {
            return new JsonResponse(['error' => 'Bestellung ist bereits als bestellt markiert'], 422);
        }

        if (\array_key_exists('form_data', $data)) {
            $order->setFormData($this->prefillService->normalizeIncomingFormData(
                \is_array($data['form_data']) ? $data['form_data'] : null,
            ));
        }

        if (\array_key_exists('participant_count', $data)) {
            $order->setParticipantCount($this->normalizeParticipantCount($data['participant_count']));
        }

        if (\array_key_exists('delivery_type', $data)) {
            $order->setDeliveryType($this->normalizeDeliveryType($data['delivery_type']));
        }

        if (\array_key_exists('status', $data) && \in_array($data['status'], [ActivityJsOrder::STATUS_DRAFT, ActivityJsOrder::STATUS_READY], true)) {
            $order->setStatus((string) $data['status']);
        }

        $order->touchUpdatedAt();
        $this->entityManager->flush();

        return new JsonResponse(
            ['order' => $this->serializeOrder($order)],
            $created ? 201 : 200,
        );
    }

    #[Route('/js-order/prefill', name: 'prefill', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function prefillOrder(string $activityId, Request $request): JsonResponse
    {
        $activity = $this->findActivityWithAccess($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $deny = $this->assertJsOrderContext($activity);
        if ($deny !== null) {
            return $deny;
        }

        $editDeny = $this->assertCanEditJsOrder($user, $activity);
        if ($editDeny !== null) {
            return $editDeny;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $forceAll = !empty($data['force']);

        $order = $this->findOrderForActivity($activityId);
        $created = false;
        if (!$order instanceof ActivityJsOrder) {
            $order = new ActivityJsOrder();
            $order->setId(IdGenerator::generate13Unique($this->entityManager, ActivityJsOrder::class, 'jo'));
            $order->setActivity($activity);
            $order->setStatus(ActivityJsOrder::STATUS_DRAFT);
            $order->setDeliveryType($this->prefillService->resolveDefaultDeliveryType($activity));
            $this->entityManager->persist($order);
            $created = true;
        }

        if ($order->getStatus() === ActivityJsOrder::STATUS_ORDERED) {
            return new JsonResponse(['error' => 'Bestellung ist bereits als bestellt markiert'], 422);
        }

        $this->prefillService->applyPrefill($order, $activity, $user, !$forceAll);
        $order->touchUpdatedAt();
        $this->entityManager->flush();

        return new JsonResponse(
            ['order' => $this->serializeOrder($order)],
            $created ? 201 : 200,
        );
    }

    private function findOrderForActivity(string $activityId): ?ActivityJsOrder
    {
        return $this->entityManager->getRepository(ActivityJsOrder::class)->findOneBy([
            'activityId' => $activityId,
        ]);
    }

    private function findActivityWithAccess(string $activityId): Activity|JsonResponse
    {
        $activity = $this->entityManager->getRepository(Activity::class)->find($activityId);
        if (!$activity || $activity->isDeleted()) {
            return new JsonResponse(['error' => 'Aktivitaet nicht gefunden'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        if (!$this->activityAccess->canUserViewActivity($currentUser, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung fuer diese Aktivitaet'], 403);
        }

        return $activity;
    }

    private function assertJsOrderContext(Activity $activity): ?JsonResponse
    {
        if (!\in_array($activity->getType(), ['camp', 'event'], true)) {
            return new JsonResponse(['error' => 'J+S-Bestellung nur fuer Lager und Events'], 422);
        }

        if (!$activity->getWantsJsMaterial()) {
            return new JsonResponse(['error' => 'J+S-Leihmaterial ist fuer diese Aktivitaet nicht aktiviert'], 422);
        }

        return null;
    }

    private function assertCanEditJsOrder(User $user, Activity $activity): ?JsonResponse
    {
        if ($activity->isDraft()) {
            if (!$activity->isMaterialEditable()) {
                return new JsonResponse(['error' => 'Formular kann nur im Entwurf bearbeitet werden'], 422);
            }
            if (!$this->activityAccess->canUserEditDraftActivityMaterial($user, $activity)) {
                return new JsonResponse(['error' => 'Keine Berechtigung zum Bearbeiten der J+S-Bestellung'], 403);
            }

            return null;
        }

        if (!$this->activityAccess->canHostMwOrDcEditActivityMaterialAfterDraft($user, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung zum Bearbeiten der J+S-Bestellung'], 403);
        }

        return null;
    }

    private function normalizeParticipantCount(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = \is_int($value) ? $value : (int) $value;

        return $n >= 1 ? $n : null;
    }

    private function normalizeDeliveryType(mixed $value): string
    {
        $raw = trim((string) $value);

        return $raw === ActivityJsOrder::DELIVERY_PICKUP_THUN
            ? ActivityJsOrder::DELIVERY_PICKUP_THUN
            : ActivityJsOrder::DELIVERY_FRANKO;
    }

    /** @return array<string, mixed> */
    private function serializeOrder(ActivityJsOrder $order): array
    {
        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = [
                'id' => $item->getId(),
                'material_item_id' => $item->getMaterialItemId(),
                'material_name' => $item->getMaterialItem()->getName(),
                'quantity_ordered' => $item->getQuantityOrdered(),
                'dotation_suggested' => $item->getDotationSuggested(),
                'order_confirmed' => $item->isOrderConfirmed(),
                'quantity_received' => $item->getQuantityReceived(),
                'quantity_returned' => $item->getQuantityReturned(),
                'notes' => $item->getNotes(),
                'sort_order' => $item->getSortOrder(),
            ];
        }

        return [
            'id' => $order->getId(),
            'activity_id' => $order->getActivityId(),
            'status' => $order->getStatus(),
            'form_data' => $order->getFormData() ?? JsOrderPrefillService::emptyFormData(),
            'participant_count' => $order->getParticipantCount(),
            'delivery_type' => $order->getDeliveryType(),
            'ordered_at' => $order->getOrderedAt()?->format(\DateTimeInterface::ATOM),
            'ordered_by_user_id' => $order->getOrderedByUserId(),
            'generated_pdf_media_id' => $order->getGeneratedPdfMediaId(),
            'items' => $items,
            'created_at' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $order->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
