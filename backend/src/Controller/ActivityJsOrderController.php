<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityJsOrder;
use App\Entity\ActivityJsOrderItem;
use App\Entity\MaterialItem;
use App\Entity\User;
use App\Service\Activity\ActivityJsOrderPdfService;
use App\Service\Activity\ActivityJsOrderPdfStorageService;
use App\Service\ActivityAccessService;
use App\Service\JsDotationRulesService;
use App\Service\JsOrderPrefillService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/activities/{activityId}', name: 'api_activity_js_order_')]
class ActivityJsOrderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityAccessService $activityAccess,
        private JsOrderPrefillService $prefillService,
        private JsDotationRulesService $dotationRules,
        private ActivityJsOrderPdfService $pdfService,
        private ActivityJsOrderPdfStorageService $pdfStorage,
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

        if (\array_key_exists('items', $data)) {
            $syncError = $this->syncOrderItems($order, \is_array($data['items']) ? $data['items'] : [], $activity);
            if ($syncError !== null) {
                return $syncError;
            }
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

    #[Route('/js-order/apply-dotation', name: 'apply_dotation', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function applyDotation(string $activityId, Request $request): JsonResponse
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

        $order = $this->findOrderForActivity($activityId);
        if (!$order instanceof ActivityJsOrder) {
            return new JsonResponse(['error' => 'J+S-Bestellung existiert noch nicht — Formular zuerst öffnen'], 422);
        }

        if ($order->getStatus() === ActivityJsOrder::STATUS_ORDERED) {
            return new JsonResponse(['error' => 'Bestellung ist bereits als bestellt markiert'], 422);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (\array_key_exists('participant_count', $data)) {
            $order->setParticipantCount($this->normalizeParticipantCount($data['participant_count']));
        }

        $participantCount = $order->getParticipantCount()
            ?? $activity->getParticipantCount()
            ?? 0;
        if ($participantCount < 1) {
            return new JsonResponse(['error' => 'Teilnehmerzahl fehlt — zuerst in Block 2 erfassen'], 422);
        }

        /** @var MaterialItem[] $materials */
        $materials = $this->entityManager->getRepository(MaterialItem::class)->createQueryBuilder('m')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.isJsMaterial = true')
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();

        $suggestions = $this->dotationRules->buildDotationSuggestions($materials, $participantCount);
        $existingByMaterial = [];
        foreach ($order->getItems() as $item) {
            $existingByMaterial[$item->getMaterialItemId()] = $item;
        }

        $sort = 0;
        foreach ($suggestions as $row) {
            $materialId = (string) ($row['material_item_id'] ?? '');
            if ($materialId === '') {
                continue;
            }
            $suggestedQty = (int) ($row['dotation_suggested'] ?? $row['quantity_ordered'] ?? 0);
            if ($suggestedQty < 1) {
                continue;
            }

            $material = $this->entityManager->getRepository(MaterialItem::class)->find($materialId);
            if (!$material instanceof MaterialItem || !$material->getIsJsMaterial()) {
                continue;
            }

            $item = $existingByMaterial[$materialId] ?? null;
            if (!$item instanceof ActivityJsOrderItem) {
                $item = new ActivityJsOrderItem();
                $item->setId(IdGenerator::generate13Unique($this->entityManager, ActivityJsOrderItem::class, 'ji'));
                $item->setJsOrder($order);
                $item->setMaterialItem($material);
                $item->setQuantityOrdered($suggestedQty);
                $item->setDotationSuggested($suggestedQty);
                $item->setSortOrder($sort++);
                $order->addItem($item);
                $this->entityManager->persist($item);
                $existingByMaterial[$materialId] = $item;
                continue;
            }

            $item->setDotationSuggested($suggestedQty);
            if ($item->getQuantityOrdered() < 1) {
                $item->setQuantityOrdered($suggestedQty);
            }
            $item->touchUpdatedAt();
        }

        $order->touchUpdatedAt();
        $this->entityManager->flush();

        return new JsonResponse(['order' => $this->serializeOrder($order)]);
    }

    #[Route('/js-order/generate-pdf', name: 'generate_pdf', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function generatePdf(string $activityId): JsonResponse
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

        $order = $this->findOrderForActivity($activityId);
        if (!$order instanceof ActivityJsOrder) {
            return new JsonResponse(['error' => 'J+S-Bestellung existiert noch nicht — Formular zuerst speichern'], 422);
        }

        try {
            $pdfBinary = $this->pdfService->renderPdf($order);
            $stored = $this->pdfStorage->store($order, $user, $pdfBinary);

            $previousMediaId = $order->getGeneratedPdfMediaId();
            if ($previousMediaId !== null && $previousMediaId !== '' && $previousMediaId !== $stored['id']) {
                $this->pdfStorage->deleteByMediaId($order, $previousMediaId);
            }

            $order->setGeneratedPdfMediaId($stored['id']);
            if ($order->getStatus() === ActivityJsOrder::STATUS_DRAFT && $order->getItems()->count() > 0) {
                $order->setStatus(ActivityJsOrder::STATUS_READY);
            }
            $order->touchUpdatedAt();
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'PDF konnte nicht erzeugt werden: ' . $e->getMessage()], 500);
        }

        return new JsonResponse([
            'order' => $this->serializeOrder($order),
            'pdf_url' => $stored['url'],
        ]);
    }

    #[Route('/js-order/pdf/{filename}', name: 'pdf', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function showPdf(string $activityId, string $filename): Response
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
            return new JsonResponse(['error' => 'J+S-Bestellung nicht gefunden'], 404);
        }

        $mediaId = $order->getGeneratedPdfMediaId();
        if ($mediaId === null || $mediaId === '') {
            return new JsonResponse(['error' => 'PDF noch nicht erzeugt'], 404);
        }

        $expectedFilename = $mediaId . '.pdf';
        if ($filename !== $expectedFilename) {
            return new JsonResponse(['error' => 'PDF nicht gefunden'], 404);
        }

        try {
            $path = $this->pdfStorage->resolveFilePath(
                $activityId,
                (string) $order->getId(),
                $activity->getDepartmentId(),
                $filename,
            );
            $response = new BinaryFileResponse($path);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);

            return $response;
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }
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

    /**
     * @param list<mixed> $itemsPayload
     */
    private function syncOrderItems(ActivityJsOrder $order, array $itemsPayload, Activity $activity): ?JsonResponse
    {
        $participantCount = $order->getParticipantCount()
            ?? $activity->getParticipantCount()
            ?? 0;

        $normalizedRows = [];
        $seenMaterialIds = [];

        foreach ($itemsPayload as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $materialId = trim((string) ($row['material_item_id'] ?? ''));
            if ($materialId === '' || isset($seenMaterialIds[$materialId])) {
                continue;
            }
            $seenMaterialIds[$materialId] = true;

            $material = $this->entityManager->getRepository(MaterialItem::class)->find($materialId);
            if (!$material instanceof MaterialItem || !$material->getIsJsMaterial()) {
                return new JsonResponse(['error' => 'Ungültiges J+S-Material: ' . $materialId], 400);
            }

            $qty = (int) ($row['quantity_ordered'] ?? 0);
            if ($qty < 0) {
                return new JsonResponse(['error' => 'Menge darf nicht negativ sein'], 400);
            }

            $normalizedRows[] = [
                'material_item_id' => $materialId,
                'material_name' => $material->getName(),
                'quantity_ordered' => $qty,
                'dotation_suggested' => isset($row['dotation_suggested']) ? (int) $row['dotation_suggested'] : null,
                'notes' => isset($row['notes']) ? trim((string) $row['notes']) : null,
                'material' => $material,
            ];
        }

        $validationErrors = $this->dotationRules->validateOrderItems($participantCount, $normalizedRows);
        if ($validationErrors !== []) {
            return new JsonResponse(['error' => 'Dotations-Validierung fehlgeschlagen', 'validation_errors' => $validationErrors], 422);
        }

        $payloadIds = array_column($normalizedRows, 'material_item_id');
        foreach ($order->getItems()->toArray() as $existing) {
            if (!\in_array($existing->getMaterialItemId(), $payloadIds, true)) {
                $order->removeItem($existing);
                $this->entityManager->remove($existing);
            }
        }

        $existingByMaterial = [];
        foreach ($order->getItems() as $existing) {
            $existingByMaterial[$existing->getMaterialItemId()] = $existing;
        }

        foreach ($normalizedRows as $index => $row) {
            $materialId = $row['material_item_id'];
            $item = $existingByMaterial[$materialId] ?? null;
            if (!$item instanceof ActivityJsOrderItem) {
                $item = new ActivityJsOrderItem();
                $item->setId(IdGenerator::generate13Unique($this->entityManager, ActivityJsOrderItem::class, 'ji'));
                $item->setJsOrder($order);
                $item->setMaterialItem($row['material']);
                $order->addItem($item);
                $this->entityManager->persist($item);
            }

            $item->setQuantityOrdered((int) $row['quantity_ordered']);
            $dotation = $row['dotation_suggested'];
            $item->setDotationSuggested($dotation !== null && $dotation >= 0 ? $dotation : null);
            $item->setNotes($row['notes'] !== '' ? $row['notes'] : null);
            $item->setSortOrder($index);
            $item->touchUpdatedAt();
        }

        return null;
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
            'generated_pdf_url' => $this->buildGeneratedPdfUrl($order),
            'items' => $items,
            'dotation_warnings' => $this->buildDotationWarnings($order),
            'created_at' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $order->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return list<string> */
    private function buildDotationWarnings(ActivityJsOrder $order): array
    {
        $participantCount = $order->getParticipantCount()
            ?? $order->getActivity()->getParticipantCount()
            ?? 0;

        $rows = [];
        foreach ($order->getItems() as $item) {
            $rows[] = [
                'material_item_id' => $item->getMaterialItemId(),
                'material_name' => $item->getMaterialItem()->getName(),
                'quantity_ordered' => $item->getQuantityOrdered(),
            ];
        }

        $formData = $order->getFormData() ?? [];
        $courseType = \is_array($formData['block2'] ?? null)
            ? (string) (($formData['block2']['course_type'] ?? '') ?: '')
            : '';

        return $this->dotationRules->collectOrderWarnings($participantCount, $rows, $courseType !== '' ? $courseType : null);
    }

    private function buildGeneratedPdfUrl(ActivityJsOrder $order): ?string
    {
        $mediaId = $order->getGeneratedPdfMediaId();
        if ($mediaId === null || $mediaId === '') {
            return null;
        }

        return $this->pdfStorage->buildPdfUrl($order->getActivityId(), $mediaId . '.pdf');
    }
}
