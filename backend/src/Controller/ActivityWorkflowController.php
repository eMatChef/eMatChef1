<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityItem;
use App\Entity\ActivityPackItem;
use App\Entity\ActivityReturnItem;
use App\Entity\ActivityIssueReport;
use App\Entity\ActivityHistory;
use App\Entity\MaterialItem;
use App\Entity\User;
use App\Controller\WorkshopController;
use App\Service\ActivityAccessService;
use App\Service\ActivityAccountingCostService;
use App\Service\ActivityItemPipelineStatusService;
use App\Service\ActivityKisteMaterialLinker;
use App\Service\ActivityPackCrateCheckService;
use App\Service\InboxMessageService;
use App\Service\PackPipelineService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller für den Aktivitäts-Workflow:
 * - Packliste (Materialwart packt Material)
 * - Issue Reports (Meldungen während Ausleihe)
 * - Rückgabe (Materialwart erfasst Retour)
 */
#[Route('/api/activities/{activityId}', name: 'api_activity_workflow_')]
class ActivityWorkflowController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityAccessService $activityAccess,
        private ActivityKisteMaterialLinker $kisteMaterialLinker,
        private ActivityPackCrateCheckService $packCrateCheckService,
        private PackPipelineService $packPipeline,
        private ActivityItemPipelineStatusService $activityItemPipelineStatus,
        private ActivityAccountingCostService $activityAccountingCost,
        private InboxMessageService $inboxMessageService,
    ) {}

    // ═══════════════════════════════════════════════
    // PACKLISTE
    // ═══════════════════════════════════════════════

    /**
     * Packliste laden (wird beim Wechsel zu "packing" aus ActivityItems erzeugt)
     */
    #[Route('/pack-items', name: 'pack_items_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listPackItems(string $activityId): JsonResponse
    {
        $activity = $this->findActivityForUser($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $this->kisteMaterialLinker->reconcileOrphanPackItemsWithoutMaterialLine($activity);

        $items = $this->entityManager->getRepository(ActivityPackItem::class)
            ->createQueryBuilder('pi')
            ->leftJoin('pi.materialItem', 'mi')
            ->addSelect('mi')
            ->leftJoin('mi.linkedContainerBatch', 'linkCb')
            ->addSelect('linkCb')
            ->leftJoin('mi.storageAddress', 'storAddr')
            ->addSelect('storAddr')
            ->leftJoin('pi.packedByUser', 'u')
            ->addSelect('u')
            ->where('pi.activityId = :activityId')
            ->setParameter('activityId', $activityId)
            ->orderBy('mi.name', 'ASC')
            ->getQuery()
            ->getResult();

        $materialIds = [];
        foreach ($items as $item) {
            $materialIds[] = $item->getMaterialItemId();
        }
        $rackSlotByMid = $this->loadPrimaryStorageRackSlotByMaterialIds($materialIds);

        $result = [];
        foreach ($items as $item) {
            $mid = $item->getMaterialItemId();
            $rs = $rackSlotByMid[$mid] ?? ['rack_name' => null, 'slot_name' => null];
            $mi = $item->getMaterialItem();
            $storAddr = $mi->getStorageAddress();
            $addrName = $storAddr ? $storAddr->getName() : null;
            $result[] = $this->serializePackItem(
                $item,
                $rs['rack_name'] ?? null,
                $rs['slot_name'] ?? null,
                $addrName
            );
        }

        return new JsonResponse($result);
    }

    /**
     * Packliste initialisieren (aus den bestellten ActivityItems)
     * Wird automatisch beim Übergang zu "packing" aufgerufen, kann aber auch manuell ausgelöst werden.
     */
    #[Route('/pack-items/init', name: 'pack_items_init', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function initPackItems(string $activityId): JsonResponse
    {
        $activity = $this->findActivityForUser($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        // Nur wenn noch keine Packliste existiert
        $existingCount = $this->entityManager->getRepository(ActivityPackItem::class)
            ->count(['activityId' => $activityId]);

        if ($existingCount > 0) {
            return new JsonResponse(['message' => 'Packliste existiert bereits', 'count' => $existingCount]);
        }

        // Aus den ActivityItems erzeugen
        $activityItems = $this->entityManager->getRepository(ActivityItem::class)
            ->findBy(['activityId' => $activityId]);

        $user = $this->getUser();
        $count = 0;

        foreach ($activityItems as $ai) {
            $packItem = new ActivityPackItem();
            $packItem->setId(IdGenerator::generate13('pk'));
            $packItem->setActivity($activity);
            $packItem->setMaterialItem($ai->getMaterialItem());
            $packItem->setQuantityOrdered($ai->getQuantity());
            $packItem->setQuantityPacked(0);
            $packItem->setConditionOut('ok');

            if ($user instanceof User) {
                $packItem->setPackedByUser($user);
            }

            $this->entityManager->persist($packItem);
            $count++;
        }

        $this->activityItemPipelineStatus->syncForActivity($activity);
        $this->entityManager->flush();

        return new JsonResponse(['message' => "$count Pack-Positionen erstellt", 'count' => $count], 201);
    }

    /**
     * Einzelne Pack-Position aktualisieren
     * Body: { "quantity_packed": 5, "condition_out": "ok", "batch_numbers": "SN123", "notes": "..." }
     */
    #[Route('/pack-items/{packItemId}', name: 'pack_items_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updatePackItem(string $activityId, string $packItemId, Request $request): JsonResponse
    {
        $activity = $this->findActivityForUser($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        if (!$activity->isPackListEditable()) {
            return new JsonResponse(['error' => 'Packliste kann in Status "' . $activity->getStatus() . '" nicht bearbeitet werden'], 422);
        }

        $packItem = $this->entityManager->getRepository(ActivityPackItem::class)->find($packItemId);
        if (!$packItem || $packItem->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Pack-Position nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['quantity_packed'])) {
            $packItem->setQuantityPacked(max(0, (int)$data['quantity_packed']));
        }
        if (isset($data['quantity_issued'])) {
            $packItem->setQuantityIssued(max(0, (int)$data['quantity_issued']));
        }
        if (isset($data['quantity_returned'])) {
            $packItem->setQuantityReturned(max(0, (int)$data['quantity_returned']));
        }
        if (isset($data['condition_out'])) {
            $packItem->setConditionOut($data['condition_out']);
        }
        if (array_key_exists('batch_numbers', $data)) {
            $packItem->setBatchNumbers($data['batch_numbers']);
        }
        if (array_key_exists('notes', $data)) {
            $packItem->setNotes($data['notes']);
        }

        $packItem->setPackedAt(new \DateTime());
        $packItem->setUpdatedAt(new \DateTime());

        $user = $this->getUser();
        if ($user instanceof User) {
            $packItem->setPackedByUser($user);
        }

        $this->activityItemPipelineStatus->syncForActivity($activity);
        $this->entityManager->flush();

        $d = $this->storageDisplayForPackItem($packItem);

        return new JsonResponse($this->serializePackItem($packItem, $d['rack'], $d['slot'], $d['address']));
    }

    /**
     * Alle Pack-Positionen auf einmal aktualisieren (Batch-Update)
     * Body: { "items": [{ "id": "...", "quantity_packed": 5, ... }, ...] }
     */
    #[Route('/pack-items/batch', name: 'pack_items_batch', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function batchUpdatePackItems(string $activityId, Request $request): JsonResponse
    {
        $activity = $this->findActivityForUser($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        if (!$activity->isPackListEditable()) {
            return new JsonResponse(['error' => 'Packliste kann in Status "' . $activity->getStatus() . '" nicht bearbeitet werden'], 422);
        }

        $data = json_decode($request->getContent(), true);
        $items = $data['items'] ?? [];
        $user = $this->getUser();
        $updated = 0;

        foreach ($items as $itemData) {
            if (empty($itemData['id'])) continue;

            $packItem = $this->entityManager->getRepository(ActivityPackItem::class)->find($itemData['id']);
            if (!$packItem || $packItem->getActivityId() !== $activityId) continue;

            if (isset($itemData['quantity_packed'])) {
                $packItem->setQuantityPacked(max(0, (int)$itemData['quantity_packed']));
            }
            if (isset($itemData['condition_out'])) {
                $packItem->setConditionOut($itemData['condition_out']);
            }
            if (array_key_exists('batch_numbers', $itemData)) {
                $packItem->setBatchNumbers($itemData['batch_numbers']);
            }
            if (array_key_exists('notes', $itemData)) {
                $packItem->setNotes($itemData['notes']);
            }

            $packItem->setPackedAt(new \DateTime());
            $packItem->setUpdatedAt(new \DateTime());

            if ($user instanceof User) {
                $packItem->setPackedByUser($user);
            }

            $updated++;
        }

        $this->activityItemPipelineStatus->syncForActivity($activity);
        $this->entityManager->flush();

        return new JsonResponse(['message' => "$updated Positionen aktualisiert", 'updated' => $updated]);
    }

    /**
     * Pack-Fortschritt abfragen (Statistik)
     */
    #[Route('/pack-progress', name: 'pack_progress', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function packProgress(string $activityId): JsonResponse
    {
        $activity = $this->findActivityForUser($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $items = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findBy(['activityId' => $activityId]);

        $totalItems = count($items);
        $packedItems = 0;
        $totalOrdered = 0;
        $totalPacked = 0;

        foreach ($items as $item) {
            $totalOrdered += $item->getQuantityOrdered();
            $totalPacked += $item->getQuantityPacked();
            if ($item->isFullyPacked()) {
                $packedItems++;
            }
        }

        return new JsonResponse([
            'total_items' => $totalItems,
            'packed_items' => $packedItems,
            'total_ordered' => $totalOrdered,
            'total_packed' => $totalPacked,
            'progress_percent' => $totalItems > 0 ? round(($packedItems / $totalItems) * 100) : 0,
            'is_complete' => $packedItems === $totalItems && $totalItems > 0,
        ]);
    }

    /**
     * Pack-Position zur nächsten Stufe verschieben (Teilmenge möglich)
     * Body: { "stage": "packed"|"transport_to"|"at_event"|"issued"|"transport_back"|"returned", "quantity": 5 }
     */
    #[Route('/pack-items/{packItemId}/move', name: 'pack_items_move', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function movePackItem(string $activityId, string $packItemId, Request $request): JsonResponse
    {
        $activity = $this->findActivityForUser($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $packItem = $this->entityManager->getRepository(ActivityPackItem::class)->find($packItemId);
        if (!$packItem || $packItem->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Pack-Position nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $stage = $this->packPipeline->normalizeStage((string) ($data['stage'] ?? ''));
        $qty = max(0, (int)($data['quantity'] ?? 0));
        $profile = $this->packPipeline->profileForActivityType($activity->getType());

        if ($qty === 0) {
            return new JsonResponse(['error' => 'Menge muss grösser als 0 sein'], 400);
        }

        if (!in_array($stage, PackPipelineService::allForwardStages(), true)) {
            return new JsonResponse(['error' => 'Ungültige Stufe'], 400);
        }

        $user = $this->getUser();
        if ($user instanceof User) {
            $allowedStages = $this->activityAccess->allowedPackMoveStagesForUser($user, $activity);
            if ($allowedStages !== null && !\in_array($stage, $allowedStages, true)) {
                return new JsonResponse(['error' => 'Keine Berechtigung für diese Pack-Stufe'], 403);
            }
        }

        $maxAllowed = $this->packPipeline->maxForwardQty($packItem, $stage, $profile);
        if ($qty > $maxAllowed) {
            return new JsonResponse(['error' => "Maximal $maxAllowed verfügbar"], 422);
        }

        $this->packPipeline->applyForward($packItem, $stage, $qty, $profile);

        if ($stage === PackPipelineService::STAGE_PACKED) {
            $packItem->setPackedAt(new \DateTime());
            $user = $this->getUser();
            if ($user instanceof User) {
                $packItem->setPackedByUser($user);
            }
        }

        $packItem->setUpdatedAt(new \DateTime());
        $this->activityItemPipelineStatus->syncForActivity($activity);
        $this->entityManager->flush();

        $d = $this->storageDisplayForPackItem($packItem);

        return new JsonResponse($this->serializePackItem($packItem, $d['rack'], $d['slot'], $d['address']));
    }

    /**
     * Pack-Position zur vorherigen Stufe zurückverschieben (Teilmenge möglich)
     * Body: { "stage": "packed"|"transport_to"|"at_event"|"issued"|"transport_back"|"returned", "quantity": 5 }
     */
    #[Route('/pack-items/{packItemId}/moveback', name: 'pack_items_moveback', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function moveBackPackItem(string $activityId, string $packItemId, Request $request): JsonResponse
    {
        $activity = $this->findActivityForUser($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $packItem = $this->entityManager->getRepository(ActivityPackItem::class)->find($packItemId);
        if (!$packItem || $packItem->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Pack-Position nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $stage = $this->packPipeline->normalizeStage((string) ($data['stage'] ?? ''));
        $qty = max(0, (int)($data['quantity'] ?? 0));

        if ($qty === 0) {
            return new JsonResponse(['error' => 'Menge muss grösser als 0 sein'], 400);
        }

        if (!in_array($stage, PackPipelineService::allForwardStages(), true)) {
            return new JsonResponse(['error' => 'Ungültige Stufe'], 400);
        }

        $canRemove = $this->packPipeline->maxBackwardQty($packItem, $stage);
        if ($qty > $canRemove) {
            return new JsonResponse(['error' => "Maximal $canRemove können zurückgenommen werden"], 422);
        }

        $this->packPipeline->applyBackward($packItem, $stage, $qty);

        $packItem->setUpdatedAt(new \DateTime());
        $this->activityItemPipelineStatus->syncForActivity($activity);
        $this->entityManager->flush();

        $d = $this->storageDisplayForPackItem($packItem);

        return new JsonResponse($this->serializePackItem($packItem, $d['rack'], $d['slot'], $d['address']));
    }

    /**
     * Lose Bestände für Kistencheck (Nachlegen) — mehrere Material-IDs.
     * Query: material_item_ids=id1,id2,…
     */
    #[Route('/pack-items/{packItemId}/crate-check-stock', name: 'pack_items_crate_check_stock', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function packItemCrateCheckStock(string $activityId, string $packItemId, Request $request): JsonResponse
    {
        $activity = $this->findActivityForUser($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $packItem = $this->entityManager->getRepository(ActivityPackItem::class)->find($packItemId);
        if (!$packItem || $packItem->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Pack-Position nicht gefunden'], 404);
        }

        $raw = (string) $request->query->get('material_item_ids', '');
        $ids = array_values(array_filter(array_map('trim', explode(',', $raw))));
        $stock = $this->packCrateCheckService->looseStockByMaterialIds($activity->getDepartmentId(), $ids);

        return new JsonResponse(['loose_stock_by_material_id' => $stock]);
    }

    /**
     * Sichtkontrolle Phys.-Kombi-Kiste vor Weiterbuchen (History, Nachlegen, Meldungen).
     * Body: siehe ActivityPackCrateCheckService::apply
     */
    #[Route('/pack-items/{packItemId}/crate-check', name: 'pack_items_crate_check', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function packItemCrateCheck(string $activityId, string $packItemId, Request $request): JsonResponse
    {
        $activity = $this->findActivityForUser($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        if (!$activity->isPackListEditable()) {
            return new JsonResponse(['error' => 'Packliste kann in Status "' . $activity->getStatus() . '" nicht bearbeitet werden'], 422);
        }

        $packItem = $this->entityManager->getRepository(ActivityPackItem::class)->find($packItemId);
        if (!$packItem || $packItem->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Pack-Position nicht gefunden'], 404);
        }

        $mi = $packItem->getMaterialItem();
        if ($mi === null || $mi->getMaterialType() !== 'physical_combo') {
            return new JsonResponse(['error' => 'Kistencheck nur für physische Kombi-Packzeilen'], 422);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiger JSON-Body'], 400);
        }

        $user = $this->getUser();
        $result = $this->packCrateCheckService->apply(
            $activity,
            $packItem,
            $data,
            $user instanceof User ? $user : null,
        );

        return new JsonResponse($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    /**
     * Alle Pack-Positionen zur nächsten Stufe verschieben (Batch)
     * Body: { "stage": "packed"|"issued"|"returned" }
     */
    #[Route('/pack-items/move-all', name: 'pack_items_move_all', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function moveAllPackItems(string $activityId, Request $request): JsonResponse
    {
        $activity = $this->findActivityForUser($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $data = json_decode($request->getContent(), true);
        $stage = $this->packPipeline->normalizeStage((string) ($data['stage'] ?? ''));
        $profile = $this->packPipeline->profileForActivityType($activity->getType());

        if (!in_array($stage, PackPipelineService::allForwardStages(), true)) {
            return new JsonResponse(['error' => 'Ungültige Stufe'], 400);
        }

        $user = $this->getUser();
        if ($user instanceof User) {
            $allowedStages = $this->activityAccess->allowedPackMoveStagesForUser($user, $activity);
            if ($allowedStages !== null && !\in_array($stage, $allowedStages, true)) {
                return new JsonResponse(['error' => 'Keine Berechtigung für diese Pack-Stufe'], 403);
            }
        }

        $packItems = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findBy(['activityId' => $activityId]);

        $moved = 0;

        foreach ($packItems as $packItem) {
            $remaining = $this->packPipeline->maxForwardQty($packItem, $stage, $profile);
            if ($remaining <= 0) {
                continue;
            }

            $this->packPipeline->applyForward($packItem, $stage, $remaining, $profile);

            if ($stage === PackPipelineService::STAGE_PACKED) {
                $packItem->setPackedAt(new \DateTime());
                if ($user instanceof User) {
                    $packItem->setPackedByUser($user);
                }
            }

            $packItem->setUpdatedAt(new \DateTime());
            $moved++;
        }

        $this->activityItemPipelineStatus->syncForActivity($activity);
        $this->entityManager->flush();

        return new JsonResponse(['message' => "$moved Positionen verschoben", 'moved' => $moved]);
    }

    // ═══════════════════════════════════════════════
    // MELDUNGEN (Issue Reports)
    // ═══════════════════════════════════════════════

    /**
     * Meldungen für eine Aktivität laden
     */
    #[Route('/issues', name: 'issues_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listIssues(string $activityId): JsonResponse
    {
        $activity = $this->findActivityForUser($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $reports = $this->entityManager->getRepository(ActivityIssueReport::class)
            ->createQueryBuilder('ir')
            ->leftJoin('ir.materialItem', 'mi')
            ->addSelect('mi')
            ->leftJoin('ir.reportedByUser', 'ru')
            ->addSelect('ru')
            ->where('ir.activityId = :activityId')
            ->setParameter('activityId', $activityId)
            ->orderBy('ir.reportedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($reports as $report) {
            $result[] = $this->serializeIssueReport($report);
        }

        return new JsonResponse($result);
    }

    /**
     * Neue Meldung erstellen
     * Body: { "material_item_id": "...", "type": "damage", "quantity": 1, "description": "..." }
     */
    #[Route('/issues', name: 'issues_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createIssue(string $activityId, Request $request): JsonResponse
    {
        $activity = $this->findActivityForUser($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        if (!$activity->canReportIssues()) {
            return new JsonResponse(['error' => 'Meldungen sind in diesem Aktivitätsstatus nicht möglich'], 422);
        }

        $data = json_decode($request->getContent(), true);

        $type = $data['type'] ?? 'damage';
        if (!in_array($type, ActivityIssueReport::ALL_TYPES)) {
            return new JsonResponse(['error' => 'Ungültiger Typ. Erlaubt: ' . implode(', ', ActivityIssueReport::ALL_TYPES)], 400);
        }

        $quantityRequested = max(1, (int) ($data['quantity'] ?? 1));

        if ($type === ActivityIssueReport::TYPE_CONSUMPTION && !empty($data['material_item_id'])) {
            $consumptionErr = $this->validateConsumptionWithinBooked(
                $activityId,
                (string) $data['material_item_id'],
                $quantityRequested
            );
            if ($consumptionErr !== null) {
                return new JsonResponse(['error' => $consumptionErr], 422);
            }
        }

        try {
            $report = new ActivityIssueReport();
            $report->setId(IdGenerator::generate13('ir'));
            $report->setActivity($activity);
            $report->setType($type);
            $report->setQuantity($quantityRequested);
            $report->setDescription($data['description'] ?? null);
            $report->setPhotoUrl($data['photo_url'] ?? null);
            $report->setNotes($data['notes'] ?? null);

            if (!empty($data['material_item_id'])) {
                $materialItem = $this->entityManager->getRepository(MaterialItem::class)
                    ->find($data['material_item_id']);
                if ($materialItem) {
                    $report->setMaterialItem($materialItem);
                }
            }

            $user = $this->getUser();
            if ($user instanceof User) {
                $report->setReportedByUser($user);
            }

            $this->entityManager->persist($report);

            // Bei Verlust oder Verbrauch: quantityIssued im PackItem reduzieren
            // Damit weniger Rückgabe erwartet wird
            if (in_array($type, [ActivityIssueReport::TYPE_LOSS, ActivityIssueReport::TYPE_CONSUMPTION])
                && !empty($data['material_item_id'])
            ) {
                $packItem = $this->entityManager->getRepository(ActivityPackItem::class)->findOneBy([
                    'activityId' => $activity->getId(),
                    'materialItemId' => $data['material_item_id'],
                ]);
                if ($packItem) {
                    $newIssued = max(0, $packItem->getQuantityIssued() - $report->getQuantity());
                    $packItem->setQuantityIssued($newIssued);
                    // Returned darf nicht grösser als issued sein
                    if ($packItem->getQuantityReturned() > $newIssued) {
                        $packItem->setQuantityReturned($newIssued);
                    }
                }
            }

            // History-Eintrag
            $this->createHistoryEntry($activity, 'issue_reported', [
                'type' => $type,
                'material_item_id' => $data['material_item_id'] ?? null,
                'quantity' => $report->getQuantity(),
                'description' => $report->getDescription(),
            ]);

            // ── Auto-Erstellung Workshop-Ticket bei repair/damage/loss ──
            $workshopTicket = null;
            if (in_array($type, [ActivityIssueReport::TYPE_REPAIR, ActivityIssueReport::TYPE_DAMAGE, ActivityIssueReport::TYPE_LOSS])) {
                $currentUser = $this->getUser();
                $workshopTicket = WorkshopController::autoCreateFromIssueReport(
                    $this->entityManager,
                    $report,
                    $activity,
                    $currentUser instanceof User ? $currentUser : null
                );
            }

            $this->entityManager->flush();

            if ($user instanceof User) {
                $this->inboxMessageService->notifyActivityIssueReported($activity, $user, $report);
            }

            // Verbrauch → Buchhaltung erst beim Aktivitäts-Abschluss (finalizeConsumptionAccountingForActivity)

            $response = $this->serializeIssueReport($report);
            if ($workshopTicket) {
                $response['workshop_ticket_id'] = $workshopTicket->getId();
                $response['workshop_ticket_created'] = true;
            }

            return new JsonResponse($response, 201);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Meldung als erledigt markieren
     */
    #[Route('/issues/{issueId}/resolve', name: 'issues_resolve', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function resolveIssue(string $activityId, string $issueId, Request $request): JsonResponse
    {
        $report = $this->entityManager->getRepository(ActivityIssueReport::class)->find($issueId);
        if (!$report || $report->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Meldung nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);

        $report->setResolved(true);
        $report->setResolvedAt(new \DateTime());

        if (isset($data['notes'])) {
            $report->setNotes($data['notes']);
        }

        $user = $this->getUser();
        if ($user instanceof User) {
            $report->setResolvedByUser($user);
        }

        $this->entityManager->flush();

        return new JsonResponse($this->serializeIssueReport($report));
    }

    // ═══════════════════════════════════════════════
    // RÜCKGABE
    // ═══════════════════════════════════════════════

    /**
     * Rückgabeliste laden
     */
    #[Route('/return-items', name: 'return_items_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listReturnItems(string $activityId): JsonResponse
    {
        $activity = $this->findActivityForUser($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        $items = $this->entityManager->getRepository(ActivityReturnItem::class)
            ->createQueryBuilder('ri')
            ->leftJoin('ri.materialItem', 'mi')
            ->addSelect('mi')
            ->leftJoin('ri.returnedByUser', 'u')
            ->addSelect('u')
            ->where('ri.activityId = :activityId')
            ->setParameter('activityId', $activityId)
            ->orderBy('mi.name', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($items as $item) {
            $result[] = $this->serializeReturnItem($item);
        }

        return new JsonResponse($result);
    }

    /**
     * Rückgabeliste initialisieren (aus der Packliste)
     * Wird beim Übergang zu "returned" aufgerufen
     */
    #[Route('/return-items/init', name: 'return_items_init', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function initReturnItems(string $activityId): JsonResponse
    {
        $activity = $this->findActivityForUser($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        // Nur wenn noch keine Rückgabeliste existiert
        $existingCount = $this->entityManager->getRepository(ActivityReturnItem::class)
            ->count(['activityId' => $activityId]);

        if ($existingCount > 0) {
            return new JsonResponse(['message' => 'Rückgabeliste existiert bereits', 'count' => $existingCount]);
        }

        // Aus den PackItems erzeugen (was gepackt wurde)
        $packItems = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findBy(['activityId' => $activityId]);

        // Issue Reports berücksichtigen (Verluste/Verbrauch)
        $issueReports = $this->entityManager->getRepository(ActivityIssueReport::class)
            ->findBy(['activityId' => $activityId]);

        // Gemeldete Mengen pro Material summieren
        $reportedByMaterial = [];
        foreach ($issueReports as $report) {
            $mid = $report->getMaterialItemId();
            if (!$mid) continue;
            if (!isset($reportedByMaterial[$mid])) {
                $reportedByMaterial[$mid] = ['loss' => 0, 'consumption' => 0, 'damage' => 0];
            }
            match ($report->getType()) {
                'loss' => $reportedByMaterial[$mid]['loss'] += $report->getQuantity(),
                'consumption' => $reportedByMaterial[$mid]['consumption'] += $report->getQuantity(),
                'damage' => $reportedByMaterial[$mid]['damage'] += $report->getQuantity(),
                default => null,
            };
        }

        $user = $this->getUser();
        $count = 0;

        foreach ($packItems as $pi) {
            $returnItem = new ActivityReturnItem();
            $returnItem->setId(IdGenerator::generate13('ri'));
            $returnItem->setActivity($activity);
            $returnItem->setMaterialItem($pi->getMaterialItem());

            // Erwartete Rückgabemenge = gepackt - gemeldete Verluste/Verbrauch
            $mid = $pi->getMaterialItemId();
            $losses = ($reportedByMaterial[$mid]['loss'] ?? 0) + ($reportedByMaterial[$mid]['consumption'] ?? 0);
            $expectedReturn = max(0, $pi->getQuantityPacked() - $losses);

            // Vorab mit erwarteter Menge befüllen (Materialwart korrigiert ggf.)
            $returnItem->setQuantityReturned($expectedReturn);
            $returnItem->setQuantityMissing($losses);
            $returnItem->setQuantityDamaged($reportedByMaterial[$mid]['damage'] ?? 0);

            if ($user instanceof User) {
                $returnItem->setReturnedByUser($user);
            }

            $this->entityManager->persist($returnItem);
            $count++;
        }

        $this->entityManager->flush();

        return new JsonResponse(['message' => "$count Rückgabe-Positionen erstellt", 'count' => $count], 201);
    }

    /**
     * Einzelne Rückgabe-Position aktualisieren
     */
    #[Route('/return-items/{returnItemId}', name: 'return_items_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateReturnItem(string $activityId, string $returnItemId, Request $request): JsonResponse
    {
        $activity = $this->findActivityForUser($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        if (!$activity->isReturnEditable()) {
            return new JsonResponse(['error' => 'Rückgabe kann in Status "' . $activity->getStatus() . '" nicht bearbeitet werden'], 422);
        }

        $returnItem = $this->entityManager->getRepository(ActivityReturnItem::class)->find($returnItemId);
        if (!$returnItem || $returnItem->getActivityId() !== $activityId) {
            return new JsonResponse(['error' => 'Rückgabe-Position nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['quantity_returned'])) {
            $returnItem->setQuantityReturned(max(0, (int)$data['quantity_returned']));
        }
        if (isset($data['quantity_damaged'])) {
            $returnItem->setQuantityDamaged(max(0, (int)$data['quantity_damaged']));
        }
        if (isset($data['quantity_missing'])) {
            $returnItem->setQuantityMissing(max(0, (int)$data['quantity_missing']));
        }
        if (isset($data['condition_in'])) {
            $returnItem->setConditionIn($data['condition_in']);
        }
        if (array_key_exists('notes', $data)) {
            $returnItem->setNotes($data['notes']);
        }

        $returnItem->setReturnedAt(new \DateTime());
        $returnItem->setUpdatedAt(new \DateTime());

        $user = $this->getUser();
        if ($user instanceof User) {
            $returnItem->setReturnedByUser($user);
        }

        // ── Auto-Erstellung Workshop-Ticket bei defekter/beschädigter Rückgabe ──
        $workshopTicket = null;
        $conditionIn = $data['condition_in'] ?? null;
        if ($conditionIn && in_array($conditionIn, ['defekt', 'beschaedigt'])) {
            $workshopTicket = WorkshopController::autoCreateFromReturnItem(
                $this->entityManager,
                $returnItem,
                $activity,
                $user instanceof User ? $user : null
            );
        }

        $this->entityManager->flush();

        $response = $this->serializeReturnItem($returnItem);
        if ($workshopTicket) {
            $response['workshop_ticket_id'] = $workshopTicket->getId();
            $response['workshop_ticket_created'] = true;
        }

        return new JsonResponse($response);
    }

    /**
     * Rückgabe-Zusammenfassung
     */
    #[Route('/return-summary', name: 'return_summary', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function returnSummary(string $activityId): JsonResponse
    {
        $activity = $this->findActivityForUser($activityId);
        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        // Packliste
        $packItems = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findBy(['activityId' => $activityId]);

        // Rückgabe
        $returnItems = $this->entityManager->getRepository(ActivityReturnItem::class)
            ->findBy(['activityId' => $activityId]);

        // Issue Reports
        $issueReports = $this->entityManager->getRepository(ActivityIssueReport::class)
            ->findBy(['activityId' => $activityId]);

        $totalPacked = 0;
        $totalReturned = 0;
        $totalDamaged = 0;
        $totalMissing = 0;
        $totalIssues = count($issueReports);
        $unresolvedIssues = 0;

        foreach ($packItems as $pi) {
            $totalPacked += $pi->getQuantityPacked();
        }

        foreach ($returnItems as $ri) {
            $totalReturned += $ri->getQuantityReturned();
            $totalDamaged += $ri->getQuantityDamaged();
            $totalMissing += $ri->getQuantityMissing();
        }

        foreach ($issueReports as $ir) {
            if (!$ir->isResolved()) {
                $unresolvedIssues++;
            }
        }

        return new JsonResponse([
            'total_packed' => $totalPacked,
            'total_returned' => $totalReturned,
            'total_damaged' => $totalDamaged,
            'total_missing' => $totalMissing,
            'total_issues' => $totalIssues,
            'unresolved_issues' => $unresolvedIssues,
            'has_differences' => $totalDamaged > 0 || $totalMissing > 0,
        ]);
    }

    // ═══════════════════════════════════════════════
    // HELPER
    // ═══════════════════════════════════════════════

    /**
     * Verbrauch nur bis zur Summe der Aktivitäts-Positionen (gebuchte Menge), abzüglich bereits gebuchtem Verbrauch.
     *
     * @return null|string Fehlertext oder null wenn ok
     */
    private function validateConsumptionWithinBooked(string $activityId, string $materialItemId, int $requestedQty): ?string
    {
        $booked = (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(ai.quantity), 0)')
            ->from(ActivityItem::class, 'ai')
            ->where('ai.activityId = :aid')
            ->andWhere('ai.materialItemId = :mid')
            ->setParameter('aid', $activityId)
            ->setParameter('mid', $materialItemId)
            ->getQuery()
            ->getSingleScalarResult();

        if ($booked < 1) {
            return 'Für dieses Material ist keine Menge auf der Aktivität gebucht.';
        }

        $consumed = (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(r.quantity), 0)')
            ->from(ActivityIssueReport::class, 'r')
            ->where('r.activityId = :aid')
            ->andWhere('r.materialItemId = :mid')
            ->andWhere('r.type = :ctype')
            ->setParameter('aid', $activityId)
            ->setParameter('mid', $materialItemId)
            ->setParameter('ctype', ActivityIssueReport::TYPE_CONSUMPTION)
            ->getQuery()
            ->getSingleScalarResult();

        $remaining = $booked - $consumed;
        if ($requestedQty > $remaining) {
            return sprintf(
                'Verbrauch höchstens %d Stk. möglich (%d für diese Aktivität gebucht, %d bereits verbraucht).',
                max(0, $remaining),
                $booked,
                $consumed
            );
        }

        return null;
    }

    private function findActivityForUser(string $id): Activity|JsonResponse
    {
        $activity = $this->entityManager->getRepository(Activity::class)->find($id);
        if (!$activity || $activity->isDeleted()) {
            return new JsonResponse(['error' => 'Aktivität nicht gefunden'], 404);
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

    private function serializePackItem(
        ActivityPackItem $item,
        ?string $storageRackName = null,
        ?string $storageSlotName = null,
        ?string $storageAddressName = null,
    ): array {
        $mi = $item->getMaterialItem();
        $user = $item->getPackedByUser();
        $cat = $mi->getCategory();

        $rackDisplay = $storageRackName;
        if ($rackDisplay === null || trim((string) $rackDisplay) === '') {
            $loc = $mi->getLocation();
            $rackDisplay = ($loc !== null && trim($loc) !== '') ? trim($loc) : null;
        }

        $linkCb = $mi->getLinkedContainerBatch();
        $linkedContainerLabel = null;
        if ($linkCb !== null) {
            $lb = $linkCb->getLabel();
            $sn = $linkCb->getSerialNumber();
            $linkedContainerLabel = ($lb !== null && $lb !== '') ? $lb : (($sn !== null && $sn !== '') ? $sn : null);
        }

        return [
            'id' => $item->getId(),
            'activity_id' => $item->getActivityId(),
            'material_item_id' => $item->getMaterialItemId(),
            'material_name' => $mi->getName(),
            'material_type' => $mi->getMaterialType(),
            'linked_container_label' => $linkedContainerLabel,
            'linked_container_batch_id' => $linkCb?->getId(),
            'category_name' => $cat ? $cat->getName() : null,
            'category_id' => $cat ? $cat->getId() : null,
            'pack_size' => $mi->getPackSize(),
            'pack_unit' => $mi->getPackUnit(),
            'quantity_ordered' => $item->getQuantityOrdered(),
            'quantity_packed' => $item->getQuantityPacked(),
            'quantity_transport_to' => $item->getQuantityTransportTo(),
            'quantity_issued' => $item->getQuantityIssued(),
            'quantity_transport_back' => $item->getQuantityTransportBack(),
            'quantity_returned' => $item->getQuantityReturned(),
            'quantity_stored' => $item->getQuantityStored(),
            'condition_out' => $item->getConditionOut(),
            'batch_numbers' => $item->getBatchNumbers(),
            'notes' => $item->getNotes(),
            'is_fully_packed' => $item->isFullyPacked(),
            'is_fully_issued' => $item->isFullyIssued(),
            'is_fully_returned' => $item->isFullyReturned(),
            'is_fully_stored' => $item->isFullyStored(),
            'store_difference' => $item->getStoreDifference(),
            'pack_difference' => $item->getPackDifference(),
            'issue_difference' => $item->getIssueDifference(),
            'return_difference' => $item->getReturnDifference(),
            'packed_by' => $user ? $user->getId() : null,
            'packed_at' => $item->getPackedAt()?->format('c'),
            'is_consumable' => $mi->getIsConsumable(),
            'is_js_material' => $mi->getIsJsMaterial(),
            'external_source' => $mi->getExternalSource(),
            'storage_rack_name' => $rackDisplay,
            'storage_slot_name' => $storageSlotName,
            'storage_address_name' => $storageAddressName,
        ];
    }

    /**
     * @return array{rack: ?string, slot: ?string, address: ?string}
     */
    private function storageDisplayForPackItem(ActivityPackItem $packItem): array
    {
        $mid = $packItem->getMaterialItemId();
        $map = $this->loadPrimaryStorageRackSlotByMaterialIds([$mid]);
        $rs = $map[$mid] ?? ['rack_name' => null, 'slot_name' => null];
        $mi = $packItem->getMaterialItem();
        $storAddr = $mi->getStorageAddress();

        return [
            'rack' => $rs['rack_name'] ?? null,
            'slot' => $rs['slot_name'] ?? null,
            'address' => $storAddr ? $storAddr->getName() : null,
        ];
    }

    /**
     * Primärer Lagerplatz (Gestell/Fach) pro Material — gleiche Logik wie früher ActivityController::listItems.
     *
     * @param list<string> $materialIds
     *
     * @return array<string, array{rack_name: ?string, slot_name: ?string}>
     */
    private function loadPrimaryStorageRackSlotByMaterialIds(array $materialIds): array
    {
        $materialIds = array_values(array_unique(array_filter($materialIds)));
        if ($materialIds === []) {
            return [];
        }

        $conn = $this->entityManager->getConnection();
        $placeholders = implode(',', array_fill(0, count($materialIds), '?'));
        $out = [];

        $sql1 = "SELECT mb.material_item_id, r.name AS rack_name, s.name AS slot_name
FROM material_batch mb
LEFT JOIN storage_rack r ON r.id = mb.rack_id
LEFT JOIN storage_slot s ON s.id = mb.slot_id
WHERE mb.status = 'active'
AND mb.material_item_id IN ($placeholders)
AND (mb.rack_id IS NOT NULL OR mb.slot_id IS NOT NULL)
ORDER BY mb.material_item_id ASC, mb.id ASC";
        foreach ($conn->executeQuery($sql1, $materialIds)->fetchAllAssociative() as $row) {
            $mid = $row['material_item_id'];
            if (!isset($out[$mid])) {
                $out[$mid] = [
                    'rack_name' => $row['rack_name'],
                    'slot_name' => $row['slot_name'],
                ];
            }
        }

        $missing = array_values(array_diff($materialIds, array_keys($out)));
        if ($missing === []) {
            return $out;
        }

        $ph2 = implode(',', array_fill(0, count($missing), '?'));
        $sql2 = "SELECT DISTINCT ON (mb.material_item_id)
  mb.material_item_id,
  r.name AS rack_name,
  s.name AS slot_name
FROM material_batch mb
INNER JOIN batch_storage_allocation a ON a.batch_id = mb.id
LEFT JOIN material_batch cb ON cb.id = a.container_batch_id
LEFT JOIN storage_rack r ON r.id = COALESCE(cb.rack_id, a.rack_id)
LEFT JOIN storage_slot s ON s.id = COALESCE(cb.slot_id, a.slot_id)
WHERE mb.status = 'active'
AND mb.material_item_id IN ($ph2)
AND (COALESCE(cb.rack_id, a.rack_id) IS NOT NULL OR COALESCE(cb.slot_id, a.slot_id) IS NOT NULL)
ORDER BY mb.material_item_id ASC, mb.id ASC";

        foreach ($conn->executeQuery($sql2, $missing)->fetchAllAssociative() as $row) {
            $mid = $row['material_item_id'];
            if (!isset($out[$mid])) {
                $out[$mid] = [
                    'rack_name' => $row['rack_name'],
                    'slot_name' => $row['slot_name'],
                ];
            }
        }

        return $out;
    }

    private function serializeIssueReport(ActivityIssueReport $report): array
    {
        $mi = $report->getMaterialItem();
        $reporter = $report->getReportedByUser();
        $resolver = $report->getResolvedByUser();

        return [
            'id' => $report->getId(),
            'activity_id' => $report->getActivityId(),
            'material_item_id' => $report->getMaterialItemId(),
            'material_name' => $mi?->getName(),
            'type' => $report->getType(),
            'type_label' => $report->getTypeLabel(),
            'quantity' => $report->getQuantity(),
            'description' => $report->getDescription(),
            'photo_url' => $report->getPhotoUrl(),
            'notes' => $report->getNotes(),
            'resolved' => $report->isResolved(),
            'resolved_at' => $report->getResolvedAt()?->format('c'),
            'resolved_by' => $resolver?->getId(),
            'reported_by' => $reporter?->getId(),
            'reported_at' => $report->getReportedAt()->format('c'),
            'created_at' => $report->getCreatedAt()->format('c'),
            'is_js_material' => $mi?->getIsJsMaterial() ?? false,
            'external_source' => $mi?->getExternalSource(),
        ];
    }

    private function serializeReturnItem(ActivityReturnItem $item): array
    {
        $mi = $item->getMaterialItem();
        $user = $item->getReturnedByUser();

        // Pack-Info laden für Vergleich
        $packItem = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findOneBy([
                'activityId' => $item->getActivityId(),
                'materialItemId' => $item->getMaterialItemId(),
            ]);

        return [
            'id' => $item->getId(),
            'activity_id' => $item->getActivityId(),
            'material_item_id' => $item->getMaterialItemId(),
            'material_name' => $mi->getName(),
            'quantity_packed' => $packItem?->getQuantityPacked() ?? 0,
            'quantity_returned' => $item->getQuantityReturned(),
            'quantity_damaged' => $item->getQuantityDamaged(),
            'quantity_missing' => $item->getQuantityMissing(),
            'quantity_ok' => $item->getQuantityOk(),
            'condition_in' => $item->getConditionIn(),
            'notes' => $item->getNotes(),
            'has_differences' => $item->hasDifferences(),
            'returned_by' => $user?->getId(),
            'returned_at' => $item->getReturnedAt()?->format('c'),
            'is_js_material' => $mi->getIsJsMaterial(),
            'external_source' => $mi->getExternalSource(),
        ];
    }

    private function createHistoryEntry(Activity $activity, string $action, array $changes = []): void
    {
        $history = new ActivityHistory();
        $history->setId(IdGenerator::generate13('ah'));
        $history->setActivity($activity);
        $history->setAction($action);
        $history->setSnapshot([]);
        $history->setChanges($changes);

        $user = $this->getUser();
        if ($user instanceof User) {
            $history->setUser($user);
        }

        $this->entityManager->persist($history);
    }
}
