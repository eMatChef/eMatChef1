<?php

namespace App\Controller;

use App\Entity\WorkshopTicket;
use App\Entity\WorkshopTicketHistory;
use App\Entity\MaterialItem;
use App\Entity\MaterialBatch;
use App\Entity\MaterialHistory;
use App\Entity\Activity;
use App\Entity\ActivityIssueReport;
use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\User;
use App\Service\ActivityAccountingCostService;
use App\Service\Media\MediaPhotoNormalizer;
use App\Service\Public\PublicCodeService;
use App\Service\Workshop\WorkshopOrderReminderService;
use App\Service\Workshop\WorkshopPartsUsedValidator;
use App\Service\Workshop\WorkshopPurchaseLineService;
use App\Service\Workshop\WorkshopTicketCompletionException;
use App\Service\Workshop\WorkshopTicketCompletionService;
use App\Service\Inventory\InventoryTaskLinkService;
use App\Service\Workshop\WorkshopExternalCleaningService;
use App\Service\Workshop\WorkshopSendToSupplierService;
use App\Service\Workshop\WorkshopTicketPhaseService;
use App\Service\ActivityWetDryingService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/workshop', name: 'api_workshop_')]
class WorkshopController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityAccountingCostService $activityAccountingCost,
        private PublicCodeService $publicCodeService,
        private MediaPhotoNormalizer $photoNormalizer,
        private WorkshopPartsUsedValidator $partsUsedValidator,
        private WorkshopTicketCompletionService $ticketCompletionService,
        private WorkshopPurchaseLineService $purchaseLineService,
        private WorkshopTicketPhaseService $ticketPhaseService,
        private WorkshopOrderReminderService $orderReminderService,
        private WorkshopSendToSupplierService $sendToSupplierService,
        private WorkshopExternalCleaningService $externalCleaningService,
        private InventoryTaskLinkService $inventoryTaskLinkService,
        private ActivityWetDryingService $wetDrying,
    ) {}

    // ═══════════════════════════════════════════════
    // TICKETS CRUD
    // ═══════════════════════════════════════════════

    /**
     * Liste aller Workshop-Tickets für ein Department
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $departmentId = $request->query->get('department_id');

        if (!$departmentId) {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t', 'm', 'a', 'ir', 'au', 'cu')
            ->from(WorkshopTicket::class, 't')
            ->leftJoin('t.materialItem', 'm')
            ->leftJoin('t.activity', 'a')
            ->leftJoin('t.issueReport', 'ir')
            ->leftJoin('t.assignedToUser', 'au')
            ->leftJoin('t.createdByUser', 'cu')
            ->where('t.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('t.createdAt', 'DESC');

        // Status-Filter
        $status = $request->query->get('status');
        if ($status) {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $status);
        }

        // Type-Filter
        $type = $request->query->get('type');
        if ($type) {
            $qb->andWhere('t.type = :type')
                ->setParameter('type', $type);
        }

        // Priority-Filter
        $priority = $request->query->get('priority');
        if ($priority) {
            $qb->andWhere('t.priority = :priority')
                ->setParameter('priority', $priority);
        }

        // Activity-Filter
        $activityId = $request->query->get('activity_id');
        if ($activityId) {
            $qb->andWhere('t.activityId = :activityId')
                ->setParameter('activityId', $activityId);
        }

        // Material-Filter (z. B. Material-Detail → Werkstatt)
        $materialItemId = $request->query->get('material_item_id');
        if ($materialItemId) {
            $qb->andWhere('t.materialItemId = :materialItemId')
                ->setParameter('materialItemId', (string) $materialItemId);
        }

        // Suche
        $search = $request->query->get('search');
        if ($search) {
            $qb->andWhere('t.title LIKE :search OR t.description LIKE :search OR m.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Zuweisungs-Filter
        $assignedTo = $request->query->get('assigned_to');
        if ($assignedTo) {
            $qb->andWhere('t.assignedToUserId = :assignedTo')
                ->setParameter('assignedTo', $assignedTo);
        }

        $tickets = $qb->getQuery()->getResult();

        $result = [];
        foreach ($tickets as $ticket) {
            $result[] = $this->serializeTicket($ticket);
        }

        return new JsonResponse($result);
    }

    /**
     * Einzelnes Ticket laden (mit Details)
     */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get(string $id): JsonResponse
    {
        $ticket = $this->entityManager->getRepository(WorkshopTicket::class)
            ->createQueryBuilder('t')
            ->leftJoin('t.materialItem', 'm')
            ->leftJoin('t.activity', 'a')
            ->leftJoin('t.issueReport', 'ir')
            ->leftJoin('t.assignedToUser', 'au')
            ->leftJoin('au.profile', 'aup')
            ->leftJoin('t.createdByUser', 'cu')
            ->leftJoin('cu.profile', 'cup')
            ->addSelect('m', 'a', 'ir', 'au', 'aup', 'cu', 'cup')
            ->where('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$ticket) {
            return new JsonResponse(['error' => 'Ticket nicht gefunden'], 404);
        }

        return new JsonResponse($this->serializeTicket($ticket, true));
    }

    /**
     * Erzeugt (Backfill) einen öffentlichen QR-Code für ein Werkstatt-Ticket, falls noch keiner vorhanden ist.
     */
    #[Route('/{id}/public-code', name: 'ensure_public_code', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function ensurePublicCode(string $id): JsonResponse
    {
        $ticket = $this->entityManager->getRepository(WorkshopTicket::class)->find($id);

        if (!$ticket) {
            return new JsonResponse(['error' => 'Ticket nicht gefunden'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->canUserManageWorkshopPublicCode($currentUser, $ticket)) {
            return new JsonResponse(['error' => 'Keine Berechtigung fuer oeffentliche QR-Codes'], 403);
        }

        $actorId = $currentUser->getId();
        $this->publicCodeService->ensureWorkshopPublicCode($ticket, $actorId !== null ? (string) $actorId : null);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeTicket($ticket, true));
    }

    /**
     * Neues Workshop-Ticket erstellen
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validierung
        if (!isset($data['department_id']) || !isset($data['material_item_id']) || !isset($data['title'])) {
            return new JsonResponse(['error' => 'department_id, material_item_id und title sind erforderlich'], 400);
        }

        // Department prüfen
        $department = $this->entityManager->getRepository(Department::class)
            ->find($data['department_id']);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        // Material prüfen
        $material = $this->entityManager->getRepository(MaterialItem::class)
            ->find($data['material_item_id']);
        if (!$material) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }

        $materialBatch = null;
        $batchValidation = $this->resolveTicketMaterialBatch($material, $data['material_batch_id'] ?? null);
        if ($batchValidation instanceof JsonResponse) {
            return $batchValidation;
        }
        $materialBatch = $batchValidation;

        // Typ validieren
        $type = $data['type'] ?? 'repair';
        if (!in_array($type, WorkshopTicket::ALL_TYPES)) {
            return new JsonResponse(['error' => 'Ungültiger Typ. Erlaubt: ' . implode(', ', WorkshopTicket::ALL_TYPES)], 400);
        }

        // Priorität validieren
        $priority = $data['priority'] ?? 'normal';
        if (!in_array($priority, WorkshopTicket::ALL_PRIORITIES)) {
            return new JsonResponse(['error' => 'Ungültige Priorität. Erlaubt: ' . implode(', ', WorkshopTicket::ALL_PRIORITIES)], 400);
        }

        try {
            $ticket = new WorkshopTicket();
            $ticket->setId(IdGenerator::generate13('wt'));
            $ticket->setDepartment($department);
            $ticket->setMaterialItem($material);
            if ($materialBatch instanceof MaterialBatch) {
                $ticket->setMaterialBatch($materialBatch);
            }
            $ticket->setTitle($data['title']);
            $ticket->setType($type);
            $ticket->setPriority($priority);

            // Optionale Felder
            if (isset($data['description'])) {
                $ticket->setDescription($data['description']);
            }

            // Activity-Referenz
            if (isset($data['activity_id']) && $data['activity_id']) {
                $activity = $this->entityManager->getRepository(Activity::class)
                    ->find($data['activity_id']);
                if ($activity) {
                    $ticket->setActivity($activity);
                }
            }

            // IssueReport-Referenz
            if (isset($data['issue_report_id']) && $data['issue_report_id']) {
                $issueReport = $this->entityManager->getRepository(ActivityIssueReport::class)
                    ->find($data['issue_report_id']);
                if ($issueReport) {
                    $ticket->setIssueReport($issueReport);
                }
            }

            // Zuweisung
            if (isset($data['assigned_to_user_id']) && $data['assigned_to_user_id']) {
                $assignedUser = $this->entityManager->getRepository(User::class)
                    ->find($data['assigned_to_user_id']);
                if ($assignedUser) {
                    $ticket->setAssignedToUser($assignedUser);
                }
            }

            // Kosten
            if (isset($data['estimated_cost'])) {
                $ticket->setEstimatedCost($data['estimated_cost']);
            }

            $affectedQuantity = $this->resolveTicketAffectedQuantity($material, $materialBatch, $data['affected_quantity'] ?? null);
            if ($affectedQuantity instanceof JsonResponse) {
                return $affectedQuantity;
            }
            $ticket->setAffectedQuantity($affectedQuantity);

            // Ersteller
            $currentUser = $this->getUser();
            if ($currentUser instanceof User) {
                $ticket->setCreatedByUser($currentUser);
            }

            if (isset($data['repair_checklist']) && \is_array($data['repair_checklist'])) {
                $ticket->setRepairChecklist($data['repair_checklist']);
            }

            $this->applyRepairStateOnTicketCreate($ticket, $material, $materialBatch, $type);

            $this->entityManager->persist($ticket);

            // ── History-Eintrag: created ──
            $historyPayload = [
                'title' => $ticket->getTitle(),
                'type' => $ticket->getType(),
                'priority' => $ticket->getPriority(),
                'material_item_id' => $material->getId(),
                'material_name' => $material->getName(),
                'activity_id' => $ticket->getActivityId(),
                'issue_report_id' => $ticket->getIssueReportId(),
            ];
            if ($materialBatch instanceof MaterialBatch) {
                $historyPayload['material_batch_id'] = $materialBatch->getId();
                $historyPayload['material_batch_serial'] = $materialBatch->getSerialNumber();
            }
            if ($ticket->getAffectedQuantity() !== null) {
                $historyPayload['affected_quantity'] = $ticket->getAffectedQuantity();
            }
            $this->createHistoryEntry(
                $ticket,
                WorkshopTicketHistory::ACTION_CREATED,
                [],
                $historyPayload
            );

            $this->entityManager->flush();

            return new JsonResponse($this->serializeTicket($ticket), 201);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Erstellen des Tickets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ticket aktualisieren
     */
    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $id, Request $request): JsonResponse
    {
        $ticket = $this->entityManager->getRepository(WorkshopTicket::class)->find($id);

        if (!$ticket) {
            return new JsonResponse(['error' => 'Ticket nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);

        try {
            $changes = [];

            if (isset($data['title']) && $data['title'] !== $ticket->getTitle()) {
                $changes['title'] = ['old' => $ticket->getTitle(), 'new' => $data['title']];
                $ticket->setTitle($data['title']);
            }
            if (isset($data['description']) && $data['description'] !== $ticket->getDescription()) {
                $changes['description'] = ['old' => $ticket->getDescription(), 'new' => $data['description']];
                $ticket->setDescription($data['description']);
            }
            if (isset($data['priority'])) {
                if (!in_array($data['priority'], WorkshopTicket::ALL_PRIORITIES)) {
                    return new JsonResponse(['error' => 'Ungültige Priorität'], 400);
                }
                if ($data['priority'] !== $ticket->getPriority()) {
                    $changes['priority'] = ['old' => $ticket->getPriority(), 'new' => $data['priority']];
                    $ticket->setPriority($data['priority']);
                }
            }
            if (isset($data['type'])) {
                if (!in_array($data['type'], WorkshopTicket::ALL_TYPES)) {
                    return new JsonResponse(['error' => 'Ungültiger Typ'], 400);
                }
                if ($data['type'] !== $ticket->getType()) {
                    $changes['type'] = ['old' => $ticket->getType(), 'new' => $data['type']];
                    $ticket->setType($data['type']);
                }
            }

            // Zuweisung
            if (array_key_exists('assigned_to_user_id', $data)) {
                $oldAssignedId = $ticket->getAssignedToUserId();
                $newAssignedId = $data['assigned_to_user_id'] ?: null;

                if ($oldAssignedId !== $newAssignedId) {
                    if ($newAssignedId) {
                        $assignedUser = $this->entityManager->getRepository(User::class)
                            ->find($newAssignedId);
                        $ticket->setAssignedToUser($assignedUser);
                        $ticket->setAssignedToSupplierCompany(null);
                    } else {
                        $ticket->setAssignedToUser(null);
                    }
                    $changes['assigned_to_user_id'] = ['old' => $oldAssignedId, 'new' => $newAssignedId];
                }
            }

            if (array_key_exists('assigned_to_supplier_company_id', $data)) {
                $oldSupplierId = $ticket->getAssignedToSupplierCompanyId();
                $newSupplierId = $data['assigned_to_supplier_company_id'] ?: null;

                if ($oldSupplierId !== $newSupplierId) {
                    if ($newSupplierId) {
                        $supplierCompany = $this->entityManager->getRepository(\App\Entity\SupplierCompany::class)
                            ->find($newSupplierId);
                        if (!$supplierCompany instanceof \App\Entity\SupplierCompany) {
                            return new JsonResponse(['error' => 'Lieferanten-Firma nicht gefunden'], 404);
                        }
                        if (!\in_array(\App\Entity\SupplierCompany::CAPABILITY_REPAIRS, $supplierCompany->getCapabilities(), true)) {
                            return new JsonResponse(['error' => 'Firma hat keine Repairs-Capability'], 400);
                        }
                        $ticket->setAssignedToSupplierCompany($supplierCompany);
                        $ticket->setAssignedToUser(null);
                    } else {
                        $ticket->setAssignedToSupplierCompany(null);
                    }
                    $changes['assigned_to_supplier_company_id'] = ['old' => $oldSupplierId, 'new' => $newSupplierId];
                }
            }

            // Kosten
            if (array_key_exists('estimated_cost', $data) && $data['estimated_cost'] !== $ticket->getEstimatedCost()) {
                $changes['estimated_cost'] = ['old' => $ticket->getEstimatedCost(), 'new' => $data['estimated_cost']];
                $ticket->setEstimatedCost($data['estimated_cost']);
            }
            if (array_key_exists('actual_cost', $data) && $data['actual_cost'] !== $ticket->getActualCost()) {
                $changes['actual_cost'] = ['old' => $ticket->getActualCost(), 'new' => $data['actual_cost']];
                $ticket->setActualCost($data['actual_cost']);
            }

            // Ersatzteile (Stückliste Nicht-Zelt)
            if (array_key_exists('parts_used', $data)) {
                if ($ticket->getStrategy() === WorkshopTicket::STRATEGY_WRITEOFF) {
                    return new JsonResponse(['error' => 'Stückliste ist bei Abschreibungs-Tickets nicht erlaubt'], 400);
                }
                $partsErrors = $this->partsUsedValidator->validate($data['parts_used'], $ticket->getDepartmentId());
                if ($partsErrors !== []) {
                    return new JsonResponse(['error' => $partsErrors[0]], 400);
                }
                $normalizedParts = $this->partsUsedValidator->normalize($data['parts_used']);
                $ticket->setPartsUsed($normalizedParts);
                $this->ticketPhaseService->syncFromPartsUsed($ticket);
                $this->orderReminderService->syncForTicket($ticket);
                $changes['parts_used'] = ['updated' => true];
            }

            if (array_key_exists('repair_checklist', $data)) {
                $ticket->setRepairChecklist($data['repair_checklist']);
                $changes['repair_checklist'] = ['updated' => true];
            }

            // Fotos
            if (array_key_exists('photos', $data)) {
                $ticket->setPhotos($data['photos']);
                $changes['photos'] = ['updated' => true];
            }

            // Abschluss-Notizen
            if (isset($data['resolution_notes'])) {
                $changes['resolution_notes'] = ['old' => $ticket->getResolutionNotes(), 'new' => $data['resolution_notes']];
                $ticket->setResolutionNotes($data['resolution_notes']);
            }

            $ticket->updateTimestamps();

            // ── History-Eintrag: updated (nur wenn es Änderungen gab) ──
            if (!empty($changes)) {
                // Spezifische Actions je nach Änderung
                $action = WorkshopTicketHistory::ACTION_UPDATED;
                if (isset($changes['assigned_to_user_id'])) {
                    $action = WorkshopTicketHistory::ACTION_ASSIGNED;
                }

                $this->createHistoryEntry($ticket, $action, [], $changes);
            }

            $this->entityManager->flush();

            return new JsonResponse($this->serializeTicket($ticket, true));

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Aktualisieren des Tickets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Workflow-Phase manuell weiterschalten (interne Reparatur).
     */
    #[Route('/{id}/phase', name: 'set_phase', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function setPhase(string $id, Request $request): JsonResponse
    {
        $ticket = $this->loadTicketForMutation($id);
        if ($ticket instanceof JsonResponse) {
            return $ticket;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $targetPhase = $data['phase'] ?? null;

        if (!$targetPhase || !\is_string($targetPhase)) {
            return new JsonResponse(['error' => 'phase ist erforderlich'], 400);
        }

        if (!\in_array($targetPhase, [
            WorkshopTicket::PHASE_READY,
            WorkshopTicket::PHASE_IN_PROGRESS,
        ], true)) {
            return new JsonResponse(['error' => 'Ungültige Ziel-Phase'], 400);
        }

        $validationError = $this->ticketPhaseService->validateAdvanceTo($ticket, $targetPhase);
        if ($validationError !== null) {
            return new JsonResponse(['error' => $validationError, 'code' => 'phase_advance_blocked'], 422);
        }

        $oldPhase = $ticket->getPhase();
        $this->ticketPhaseService->advanceTo($ticket, $targetPhase);

        $changes = [
            'phase' => ['old' => $oldPhase, 'new' => $ticket->getPhase()],
        ];
        if ($ticket->getStartedAt() && $targetPhase === WorkshopTicket::PHASE_IN_PROGRESS) {
            $changes['started_at'] = ['new' => $ticket->getStartedAt()->format('c')];
        }

        $this->createHistoryEntry($ticket, WorkshopTicketHistory::ACTION_STATUS_CHANGED, [], $changes);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeTicket($ticket, true));
    }

    /**
     * Triage-Entscheidung: setzt strategy und initial phase (Workflow 2026).
     */
    #[Route('/{id}/triage', name: 'triage', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function triage(string $id, Request $request): JsonResponse
    {
        $ticket = $this->entityManager->getRepository(WorkshopTicket::class)
            ->createQueryBuilder('t')
            ->leftJoin('t.materialItem', 'm')
            ->leftJoin('t.issueReport', 'ir')
            ->addSelect('m', 'ir')
            ->where('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$ticket) {
            return new JsonResponse(['error' => 'Ticket nicht gefunden'], 404);
        }

        if (!$ticket->isInTriage()) {
            return new JsonResponse([
                'error' => 'Triage ist nur möglich, solange strategy=triage ist.',
                'code' => 'not_in_triage',
            ], 409);
        }

        $data = json_decode($request->getContent(), true);
        $strategy = $data['strategy'] ?? null;

        if (!$strategy || !in_array($strategy, WorkshopTicket::ALL_STRATEGIES, true)) {
            return new JsonResponse([
                'error' => 'strategy ist erforderlich. Erlaubt: ' . implode(', ', WorkshopTicket::ALL_STRATEGIES),
            ], 400);
        }

        if ($strategy === WorkshopTicket::STRATEGY_TRIAGE) {
            return new JsonResponse(['error' => 'strategy darf nicht triage sein'], 400);
        }

        $requiresSupplier = in_array($strategy, [
            WorkshopTicket::STRATEGY_EXTERNAL_REPAIR,
            WorkshopTicket::STRATEGY_EXTERNAL_CLEANING,
        ], true);

        if ($requiresSupplier) {
            $supplierId = $data['assigned_to_supplier_company_id'] ?? $ticket->getAssignedToSupplierCompanyId();
            if (!$supplierId) {
                return new JsonResponse([
                    'error' => 'Für externe Strategien ist assigned_to_supplier_company_id erforderlich.',
                    'code' => 'supplier_required',
                ], 422);
            }

            $supplierCompany = $this->entityManager->getRepository(\App\Entity\SupplierCompany::class)
                ->find($supplierId);
            if (!$supplierCompany instanceof \App\Entity\SupplierCompany) {
                return new JsonResponse(['error' => 'Lieferanten-Firma nicht gefunden'], 404);
            }
            if (!\in_array(\App\Entity\SupplierCompany::CAPABILITY_REPAIRS, $supplierCompany->getCapabilities(), true)) {
                return new JsonResponse(['error' => 'Firma hat keine Repairs-Capability'], 400);
            }
            $ticket->setAssignedToSupplierCompany($supplierCompany);
            $ticket->setAssignedToUser(null);
        }

        try {
            $oldStrategy = $ticket->getStrategy();
            $oldPhase = $ticket->getPhase();
            $newPhase = WorkshopTicket::getInitialPhaseForStrategy($strategy);

            $ticket->setStrategy($strategy);
            $ticket->setPhase($newPhase);
            $ticket->syncStatusFromPhase();

            $historyChanges = [
                'strategy' => ['old' => $oldStrategy, 'new' => $strategy],
                'phase' => ['old' => $oldPhase, 'new' => $newPhase],
            ];

            if (isset($data['priority'])) {
                $newPriority = $data['priority'];
                if (!\in_array($newPriority, WorkshopTicket::ALL_PRIORITIES, true)) {
                    return new JsonResponse(['error' => 'Ungültige Priorität'], 400);
                }
                if ($newPriority !== $ticket->getPriority()) {
                    $historyChanges['priority'] = ['old' => $ticket->getPriority(), 'new' => $newPriority];
                    $ticket->setPriority($newPriority);
                }
            }
            if ($requiresSupplier) {
                $historyChanges['assigned_to_supplier_company_id'] = $ticket->getAssignedToSupplierCompanyId();
            }

            if ($strategy === WorkshopTicket::STRATEGY_EXTERNAL_CLEANING) {
                $historyChanges = array_merge(
                    $historyChanges,
                    $this->externalCleaningService->applyTriage($ticket, $data),
                );
            }

            $ticket->updateTimestamps();

            $this->createHistoryEntry($ticket, WorkshopTicketHistory::ACTION_UPDATED, [], $historyChanges);
            $this->entityManager->flush();

            return new JsonResponse($this->serializeTicket($ticket, true));
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler bei der Triage: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{id}/send-to-supplier', name: 'send_to_supplier', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function sendToSupplier(string $id, Request $request): JsonResponse
    {
        $ticket = $this->loadTicketForMutation($id);
        if ($ticket instanceof JsonResponse) {
            return $ticket;
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $changes = $this->sendToSupplierService->send($ticket, $data);
            $filtered = array_filter($changes, static fn ($v) => $v !== null);
            $this->createHistoryEntry($ticket, WorkshopTicketHistory::ACTION_UPDATED, [], $filtered);
            $this->entityManager->flush();

            return new JsonResponse($this->serializeTicket($ticket, true));
        } catch (WorkshopTicketCompletionException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => $e->errorCode], 422);
        }
    }

    #[Route('/{id}/parts-used/{lineId}/order', name: 'parts_order', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function orderPurchaseLine(string $id, string $lineId, Request $request): JsonResponse
    {
        $ticket = $this->loadTicketForMutation($id);
        if ($ticket instanceof JsonResponse) {
            return $ticket;
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $this->purchaseLineService->markOrdered($ticket, $lineId, $data);
            $this->entityManager->flush();

            return new JsonResponse($this->serializeTicket($ticket, true));
        } catch (WorkshopTicketCompletionException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => $e->errorCode], 422);
        }
    }

    #[Route('/{id}/parts-used/{lineId}/receive', name: 'parts_receive', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function receivePurchaseLine(string $id, string $lineId, Request $request): JsonResponse
    {
        $ticket = $this->loadTicketForMutation($id);
        if ($ticket instanceof JsonResponse) {
            return $ticket;
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $this->purchaseLineService->receivePurchase($ticket, $lineId, $data);
            $this->entityManager->flush();

            return new JsonResponse($this->serializeTicket($ticket, true));
        } catch (WorkshopTicketCompletionException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => $e->errorCode], 422);
        }
    }

    /**
     * Status-Übergang für ein Ticket
     * 
     * Bei Status "completed" zusätzlich:
     * - resolution_action: 'repaired' | 'writeoff' | 'ok'
     * - resolution_notes: Abschluss-Notiz
     * - actual_cost: Tatsächliche Kosten
     * 
     * Bei "writeoff" wird automatisch ein Writeoff-Batch erstellt (Bestand -1)
     */
    #[Route('/{id}/transition', name: 'transition', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function transition(string $id, Request $request): JsonResponse
    {
        $ticket = $this->entityManager->getRepository(WorkshopTicket::class)
            ->createQueryBuilder('t')
            ->leftJoin('t.materialItem', 'm')
            ->leftJoin('t.issueReport', 'ir')
            ->addSelect('m', 'ir')
            ->where('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$ticket) {
            return new JsonResponse(['error' => 'Ticket nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;

        if (!$newStatus) {
            return new JsonResponse(['error' => 'status ist erforderlich'], 400);
        }

        if (!in_array($newStatus, WorkshopTicket::ALL_STATUSES)) {
            return new JsonResponse(['error' => 'Ungültiger Status'], 400);
        }

        if (!$ticket->canTransitionTo($newStatus)) {
            return new JsonResponse([
                'error' => sprintf(
                    'Übergang von "%s" nach "%s" ist nicht erlaubt. Erlaubt: %s',
                    $ticket->getStatus(),
                    $newStatus,
                    implode(', ', WorkshopTicket::STATUS_TRANSITIONS[$ticket->getStatus()] ?? [])
                )
            ], 400);
        }

        $isExternalActivity = $ticket->getActivity()?->getType() === 'external';

        if ($newStatus === WorkshopTicket::STATUS_WAITING_PARTS && $isExternalActivity) {
            $effectiveEstimatedCost = array_key_exists('estimated_cost', $data)
                ? $data['estimated_cost']
                : $ticket->getEstimatedCost();
            if ($effectiveEstimatedCost === null || $effectiveEstimatedCost === '') {
                return new JsonResponse([
                    'error' => 'Für externe Fälle ist ein geschätzter Preis (estimated_cost) erforderlich, bevor auf "Wartet auf Teile" gesetzt wird.',
                    'code' => 'estimated_cost_required',
                ], 422);
            }
        }

        if ($newStatus === WorkshopTicket::STATUS_COMPLETED && $isExternalActivity) {
            $resolutionAction = $data['resolution_action'] ?? 'repaired';
            if (in_array($resolutionAction, ['repaired', 'writeoff'], true)) {
                $effectiveActualCost = array_key_exists('actual_cost', $data)
                    ? $data['actual_cost']
                    : $ticket->getActualCost();
                if ($effectiveActualCost === null || $effectiveActualCost === '') {
                    return new JsonResponse([
                        'error' => 'Für externe Fälle ist ein Ist-Preis (actual_cost) erforderlich, bevor das Ticket abgeschlossen wird.',
                        'code' => 'actual_cost_required',
                    ], 422);
                }
            }
        }

        if ($newStatus === WorkshopTicket::STATUS_COMPLETED) {
            $wetBlock = $this->wetDrying->assertCleaningTicketCompletable($ticket);
            if ($wetBlock !== null) {
                return new JsonResponse(['error' => $wetBlock, 'code' => 'wet_not_stored'], 422);
            }
            $resolutionAction = $data['resolution_action'] ?? 'repaired';
            $validationError = $this->ticketCompletionService->validateBeforeComplete($ticket, $resolutionAction);
            if ($validationError !== null) {
                return new JsonResponse(['error' => $validationError, 'code' => 'insufficient_stock'], 422);
            }
        }

        try {
            $oldStatus = $ticket->getStatus();
            $oldPhase = $ticket->getPhase();
            $ticket->setStatus($newStatus);
            $ticket->syncPhaseFromStatus($newStatus);
            $historyChanges = [
                'status' => ['old' => $oldStatus, 'new' => $newStatus],
            ];
            if ($oldPhase !== $ticket->getPhase()) {
                $historyChanges['phase'] = ['old' => $oldPhase, 'new' => $ticket->getPhase()];
            }

            // Timestamps basierend auf Status setzen
            $now = new \DateTime();
            if ($newStatus === WorkshopTicket::STATUS_IN_PROGRESS && !$ticket->getStartedAt()) {
                $ticket->setStartedAt($now);
                $historyChanges['started_at'] = ['new' => $now->format('c')];
            }

            if ($newStatus === WorkshopTicket::STATUS_WAITING_PARTS && array_key_exists('estimated_cost', $data)) {
                $ticket->setEstimatedCost($data['estimated_cost']);
                $historyChanges['estimated_cost'] = $data['estimated_cost'];
            }

            $historyAction = WorkshopTicketHistory::ACTION_STATUS_CHANGED;

            if ($newStatus === WorkshopTicket::STATUS_COMPLETED) {
                $ticket->setCompletedAt($now);
                $historyChanges['completed_at'] = ['new' => $now->format('c')];
                $historyAction = WorkshopTicketHistory::ACTION_COMPLETED;

                $resolutionAction = $data['resolution_action'] ?? 'repaired';
                $ticket->setResolutionAction($resolutionAction);
                $historyChanges['resolution_action'] = $resolutionAction;

                if (isset($data['resolution_notes'])) {
                    $ticket->setResolutionNotes($data['resolution_notes']);
                    $historyChanges['resolution_notes'] = $data['resolution_notes'];
                }
                if (isset($data['actual_cost'])) {
                    $ticket->setActualCost($data['actual_cost']);
                    $historyChanges['actual_cost'] = $data['actual_cost'];
                }
                if (isset($data['cost_breakdown']) && \is_array($data['cost_breakdown'])) {
                    $historyChanges['cost_breakdown'] = $data['cost_breakdown'];
                }

                $actor = $this->getUser();
                $completionChanges = $this->ticketCompletionService->applyCompletion(
                    $ticket,
                    $resolutionAction,
                    $data,
                    $now,
                    $actor instanceof User ? $actor : null,
                );
                $historyChanges = array_merge($historyChanges, $completionChanges);

                if (
                    $ticket->getStrategy() === WorkshopTicket::STRATEGY_INSPECTION
                    && !empty($data['inventory_task_id'])
                ) {
                    try {
                        $linkChanges = $this->inventoryTaskLinkService->linkOnInspectionComplete(
                            $ticket,
                            (string) $data['inventory_task_id'],
                        );
                        $historyChanges = array_merge($historyChanges, $linkChanges);
                    } catch (\InvalidArgumentException $e) {
                        return new JsonResponse(['error' => $e->getMessage(), 'code' => 'inventory_task_link_failed'], 422);
                    }
                }
            }

            if ($newStatus === WorkshopTicket::STATUS_CANCELLED) {
                $historyAction = WorkshopTicketHistory::ACTION_CANCELLED;
            }

            $ticket->updateTimestamps();

            // ── History-Eintrag ──
            $this->createHistoryEntry($ticket, $historyAction, [], $historyChanges);

            $this->entityManager->flush();

            if ($newStatus === WorkshopTicket::STATUS_COMPLETED) {
                $this->activityAccountingCost->enqueueFromWorkshopTicket($ticket);
            }

            return new JsonResponse($this->serializeTicket($ticket));

        } catch (WorkshopTicketCompletionException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => $e->errorCode], 422);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Status-Übergang: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ticket löschen (nur offene oder abgebrochene Tickets)
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $id): JsonResponse
    {
        $ticket = $this->entityManager->getRepository(WorkshopTicket::class)->find($id);

        if (!$ticket) {
            return new JsonResponse(['error' => 'Ticket nicht gefunden'], 404);
        }

        if (!in_array($ticket->getStatus(), ['open', 'cancelled'])) {
            return new JsonResponse(['error' => 'Nur offene oder abgebrochene Tickets können gelöscht werden'], 400);
        }

        $this->entityManager->remove($ticket);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    // ═══════════════════════════════════════════════
    // HISTORY
    // ═══════════════════════════════════════════════

    /**
     * History eines Tickets laden (chronologisch)
     */
    #[Route('/{id}/history', name: 'history', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function history(string $id): JsonResponse
    {
        $ticket = $this->entityManager->getRepository(WorkshopTicket::class)->find($id);
        if (!$ticket) {
            return new JsonResponse(['error' => 'Ticket nicht gefunden'], 404);
        }

        $entries = $this->entityManager->getRepository(WorkshopTicketHistory::class)
            ->createQueryBuilder('h')
            ->leftJoin('h.user', 'u')
            ->leftJoin('u.profile', 'p')
            ->addSelect('u', 'p')
            ->where('h.workshopTicketId = :ticketId')
            ->setParameter('ticketId', $id)
            ->orderBy('h.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($entries as $entry) {
            $user = $entry->getUser();
            $result[] = [
                'id' => $entry->getId(),
                'action' => $entry->getAction(),
                'action_label' => $this->getActionLabel($entry->getAction()),
                'changes' => $entry->getChanges(),
                'user' => $user ? [
                    'id' => $user->getId(),
                    'name' => $this->getUserDisplayName($user),
                ] : null,
                'created_at' => $entry->getCreatedAt()->format('c'),
            ];
        }

        return new JsonResponse($result);
    }

    // ═══════════════════════════════════════════════
    // STATS
    // ═══════════════════════════════════════════════

    /**
     * Statistiken für Workshop-Dashboard
     */
    #[Route('/stats', name: 'stats', methods: ['GET'], priority: 10)]
    #[IsGranted('ROLE_USER')]
    public function stats(Request $request): JsonResponse
    {
        $departmentId = $request->query->get('department_id');

        if (!$departmentId) {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        $conn = $this->entityManager->getConnection();

        $phaseCounts = [
            'triage' => 0,
            'planning' => 0,
            'ordered' => 0,
            'ready' => 0,
            'in_progress' => 0,
            'awaiting_quote' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];

        $phaseRows = $conn->executeQuery(
            "SELECT
                CASE
                    WHEN strategy = 'triage' AND (phase IS NULL OR phase NOT IN ('completed', 'cancelled')) THEN 'triage'
                    WHEN phase IS NULL THEN 'triage'
                    ELSE phase
                END AS display_phase,
                COUNT(*) AS count
             FROM workshop_ticket
             WHERE department_id = :deptId
             GROUP BY display_phase",
            ['deptId' => $departmentId]
        )->fetchAllAssociative();

        foreach ($phaseRows as $row) {
            $key = (string) $row['display_phase'];
            if (\array_key_exists($key, $phaseCounts)) {
                $phaseCounts[$key] = (int) $row['count'];
            }
        }

        // Legacy status_counts (computed aus phase für API-Kompatibilität)
        $statusCounts = [
            'open' => $phaseCounts['triage'],
            'in_progress' => $phaseCounts['planning'] + $phaseCounts['ordered'] + $phaseCounts['ready'] + $phaseCounts['in_progress'],
            'waiting_parts' => $phaseCounts['awaiting_quote'],
            'completed' => $phaseCounts['completed'],
            'cancelled' => $phaseCounts['cancelled'],
        ];

        // Diese Woche erledigt
        $weekStart = (new \DateTime())->modify('monday this week')->format('Y-m-d');
        $completedThisWeek = $conn->executeQuery(
            'SELECT COUNT(*) FROM workshop_ticket WHERE department_id = :deptId AND phase = \'completed\' AND completed_at >= :weekStart',
            ['deptId' => $departmentId, 'weekStart' => $weekStart]
        )->fetchOne();

        // Tickets nach Typ zählen (nur aktive)
        $typeCounts = $conn->executeQuery(
            "SELECT type, COUNT(*) as count FROM workshop_ticket
             WHERE department_id = :deptId
               AND (phase IS NULL OR phase NOT IN ('completed', 'cancelled'))
             GROUP BY type",
            ['deptId' => $departmentId]
        )->fetchAllAssociative();

        $types = [];
        foreach ($typeCounts as $row) {
            $types[$row['type']] = (int)$row['count'];
        }

        // Tickets nach Priorität zählen (nur aktive)
        $priorityCounts = $conn->executeQuery(
            "SELECT priority, COUNT(*) as count FROM workshop_ticket
             WHERE department_id = :deptId
               AND (phase IS NULL OR phase NOT IN ('completed', 'cancelled'))
             GROUP BY priority",
            ['deptId' => $departmentId]
        )->fetchAllAssociative();

        $priorities = [];
        foreach ($priorityCounts as $row) {
            $priorities[$row['priority']] = (int)$row['count'];
        }

        $waitingQuoteCount = (int)$conn->executeQuery(
            "SELECT COUNT(*)
             FROM workshop_ticket t
             INNER JOIN activity a ON a.id = t.activity_id
             WHERE t.department_id = :deptId
               AND t.phase = 'awaiting_quote'
               AND a.type = 'external'",
            ['deptId' => $departmentId]
        )->fetchOne();

        $missingEstimatedCostCount = (int)$conn->executeQuery(
            "SELECT COUNT(*)
             FROM workshop_ticket t
             INNER JOIN activity a ON a.id = t.activity_id
             WHERE t.department_id = :deptId
               AND a.type = 'external'
               AND (t.phase IS NULL OR t.phase NOT IN ('completed', 'cancelled'))
               AND t.type IN ('repair', 'writeoff')
               AND t.estimated_cost IS NULL",
            ['deptId' => $departmentId]
        )->fetchOne();

        $totalActive = $phaseCounts['triage'] + $phaseCounts['planning'] + $phaseCounts['ordered']
            + $phaseCounts['ready'] + $phaseCounts['in_progress'] + $phaseCounts['awaiting_quote'];

        return new JsonResponse([
            'phase_counts' => $phaseCounts,
            'status_counts' => $statusCounts,
            'completed_this_week' => (int)$completedThisWeek,
            'type_counts' => $types,
            'priority_counts' => $priorities,
            'total_active' => $totalActive,
            'pending_cost_tasks' => [
                'waiting_quote' => $waitingQuoteCount,
                'missing_estimated_cost' => $missingEstimatedCostCount,
            ],
        ]);
    }

    // ═══════════════════════════════════════════════
    // HELPER: History
    // ═══════════════════════════════════════════════

    /**
     * Erstellt einen History-Eintrag für ein Workshop-Ticket
     */
    private function createHistoryEntry(WorkshopTicket $ticket, string $action, array $snapshot = [], array $changes = []): void
    {
        $history = new WorkshopTicketHistory();
        $history->setId(IdGenerator::generate13('wh'));
        $history->setWorkshopTicket($ticket);
        $history->setAction($action);

        // Snapshot: Aktueller Zustand des Tickets
        if (empty($snapshot)) {
            $snapshot = [
                'status' => $ticket->getStatus(),
                'type' => $ticket->getType(),
                'priority' => $ticket->getPriority(),
                'title' => $ticket->getTitle(),
                'assigned_to_user_id' => $ticket->getAssignedToUserId(),
                'estimated_cost' => $ticket->getEstimatedCost(),
                'actual_cost' => $ticket->getActualCost(),
                'resolution_action' => $ticket->getResolutionAction(),
            ];
        }
        $history->setSnapshot($snapshot);
        $history->setChanges($changes);

        $user = $this->getUser();
        if ($user instanceof User) {
            $history->setUser($user);
        }

        $this->entityManager->persist($history);
    }

    /**
     * Erstellt einen MaterialHistory-Eintrag (für Material-Zustandsänderungen)
     */
    private function createMaterialHistoryEntry(MaterialItem $material, string $action, array $changes): void
    {
        $history = new MaterialHistory();
        $history->setId(IdGenerator::generate13('hi'));
        $history->setMaterialItem($material);
        $history->setAction($action);
        $history->setSnapshot([
            'condition' => $material->getCondition(),
            'name' => $material->getName(),
        ]);
        $history->setChanges($changes);

        $user = $this->getUser();
        if ($user instanceof User) {
            $history->setUser($user);
        }

        $this->entityManager->persist($history);
    }

    /**
     * @return MaterialBatch|null|JsonResponse null = kein Batch nötig; JsonResponse = Validierungsfehler
     */
    private function resolveTicketMaterialBatch(MaterialItem $material, mixed $batchId): MaterialBatch|null|JsonResponse
    {
        $isSerialized = $material->getTrackingType() === 'serialized';
        $batchId = is_string($batchId) ? trim($batchId) : '';

        if ($isSerialized && $batchId === '') {
            return new JsonResponse(['error' => 'material_batch_id ist für serialisierte Artikel erforderlich'], 400);
        }
        if (!$isSerialized) {
            if ($batchId !== '') {
                return new JsonResponse(['error' => 'material_batch_id ist nur für serialisierte Artikel erlaubt'], 400);
            }
            return null;
        }

        $batch = $this->entityManager->getRepository(MaterialBatch::class)->find($batchId);
        if (!$batch || $batch->getMaterialItemId() !== $material->getId()) {
            return new JsonResponse(['error' => 'Seriennummer/Charge gehört nicht zum gewählten Material'], 400);
        }
        if ($batch->getStatus() !== 'active') {
            return new JsonResponse(['error' => 'Die gewählte Seriennummer ist nicht aktiv'], 400);
        }

        return $batch;
    }

    private function applyRepairStateOnTicketCreate(
        WorkshopTicket $ticket,
        MaterialItem $material,
        ?MaterialBatch $materialBatch,
        string $type
    ): void {
        if ($type !== WorkshopTicket::TYPE_REPAIR) {
            return;
        }

        if ($materialBatch instanceof MaterialBatch) {
            $materialBatch->setStatus('defect');
            return;
        }

        $affectedQty = max(1, $ticket->getAffectedQuantity() ?? 1);
        $totalStock = $material->getTotalStock();
        // Stamm nur markieren, wenn der gesamte Bestand betroffen ist
        if ($totalStock > 0 && $affectedQty >= $totalStock && $material->getCondition() === 'ok') {
            $material->setCondition('repair');
            $material->updateTimestamps();
        }
    }

    /** @return int|JsonResponse */
    private function resolveTicketAffectedQuantity(
        MaterialItem $material,
        ?MaterialBatch $materialBatch,
        mixed $rawQuantity
    ): int|JsonResponse {
        if ($materialBatch instanceof MaterialBatch) {
            return 1;
        }

        $qty = $rawQuantity !== null && $rawQuantity !== '' ? (int) $rawQuantity : 1;
        if ($qty < 1) {
            return new JsonResponse(['error' => 'affected_quantity muss mindestens 1 sein'], 400);
        }

        $totalStock = $material->getTotalStock();
        if ($totalStock > 0 && $qty > $totalStock) {
            return new JsonResponse([
                'error' => sprintf('affected_quantity darf den Bestand (%d) nicht überschreiten', $totalStock),
            ], 400);
        }

        return $qty;
    }

    private function loadTicketForMutation(string $id): WorkshopTicket|JsonResponse
    {
        $ticket = $this->entityManager->getRepository(WorkshopTicket::class)
            ->createQueryBuilder('t')
            ->leftJoin('t.materialItem', 'm')
            ->addSelect('m')
            ->where('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$ticket instanceof WorkshopTicket) {
            return new JsonResponse(['error' => 'Ticket nicht gefunden'], 404);
        }

        return $ticket;
    }

    /** @return array<string, mixed>|null */
    private function serializeTicketMaterialBatch(WorkshopTicket $ticket): ?array
    {
        $batch = $ticket->getMaterialBatch();
        if (!$batch instanceof MaterialBatch) {
            return null;
        }

        return [
            'id' => $batch->getId(),
            'serial_number' => $batch->getSerialNumber(),
            'label' => $batch->getLabel(),
            'status' => $batch->getStatus(),
            'ean' => $batch->getEan(),
            'barcode_tag' => $batch->getBarcodeTag(),
        ];
    }

    /**
     * Action-Label für die History-Anzeige
     */
    private function getActionLabel(string $action): string
    {
        return match ($action) {
            WorkshopTicketHistory::ACTION_CREATED => 'Ticket erstellt',
            WorkshopTicketHistory::ACTION_UPDATED => 'Ticket aktualisiert',
            WorkshopTicketHistory::ACTION_STATUS_CHANGED => 'Status geändert',
            WorkshopTicketHistory::ACTION_ASSIGNED => 'Zuweisung geändert',
            WorkshopTicketHistory::ACTION_COMPLETED => 'Ticket abgeschlossen',
            WorkshopTicketHistory::ACTION_CANCELLED => 'Ticket abgebrochen',
            WorkshopTicketHistory::ACTION_AUTO_CREATED_ISSUE => 'Automatisch erstellt (Schadensmeldung)',
            WorkshopTicketHistory::ACTION_AUTO_CREATED_RETURN => 'Automatisch erstellt (Rückgabe)',
            default => $action,
        };
    }

    // ═══════════════════════════════════════════════
    // HELPER: Serialization
    // ═══════════════════════════════════════════════

    /**
     * Serialisiert ein Ticket für die API-Response
     */
    private function serializeTicket(WorkshopTicket $ticket, bool $detailed = false): array
    {
        $material = $ticket->getMaterialItem();
        $assignedUser = $ticket->getAssignedToUser();
        $createdByUser = $ticket->getCreatedByUser();

        $result = [
            'id' => $ticket->getId(),
            'department_id' => $ticket->getDepartmentId(),
            'type' => $ticket->getType(),
            'type_label' => $ticket->getTypeLabel(),
            'priority' => $ticket->getPriority(),
            'priority_label' => $ticket->getPriorityLabel(),
            'status' => $ticket->getStatus(),
            'status_label' => $ticket->getStatusLabel(),
            'strategy' => $ticket->getStrategy(),
            'strategy_label' => $ticket->getStrategyLabel(),
            'phase' => $ticket->getPhase(),
            'phase_label' => $ticket->getPhaseLabel(),
            'title' => $ticket->getTitle(),
            'description' => $ticket->getDescription(),
            'estimated_cost' => $ticket->getEstimatedCost(),
            'actual_cost' => $ticket->getActualCost(),
            'resolution_action' => $ticket->getResolutionAction(),
            'resolution_notes' => $ticket->getResolutionNotes(),
            'started_at' => $ticket->getStartedAt()?->format('c'),
            'completed_at' => $ticket->getCompletedAt()?->format('c'),
            'created_at' => $ticket->getCreatedAt()->format('c'),
            'updated_at' => $ticket->getUpdatedAt()->format('c'),

            // Material (immer)
            'material_item' => [
                'id' => $material->getId(),
                'name' => $material->getName(),
                'condition' => $material->getCondition(),
                'tracking_type' => $material->getTrackingType(),
                'pack_unit' => $material->getPackUnit(),
                'total_stock' => $material->getTotalStock(),
                'barcode_tag' => $ticket->getMaterialBatch()?->getBarcodeTag(),
                'sale_price' => $material->getSalePrice(),
                'reference_purchase_unit_chf' => $material->getReferencePurchaseUnitChf(),
                'repair_template_key' => $material->getRepairTemplateKey(),
                'category' => $material->getCategory() ? [
                    'id' => $material->getCategory()->getId(),
                    'name' => $material->getCategory()->getName(),
                ] : null,
            ],

            'material_batch' => $this->serializeTicketMaterialBatch($ticket),
            'affected_quantity' => $ticket->getAffectedQuantity(),

            // Zuweisung
            'assigned_to' => $assignedUser ? [
                'id' => $assignedUser->getId(),
                'name' => $this->getUserDisplayName($assignedUser),
            ] : null,

            'assigned_to_supplier_company' => $ticket->getAssignedToSupplierCompany() ? [
                'id' => $ticket->getAssignedToSupplierCompany()->getId(),
                'name' => $ticket->getAssignedToSupplierCompany()->getName(),
            ] : null,

            // Ersteller
            'created_by' => $createdByUser ? [
                'id' => $createdByUser->getId(),
                'name' => $this->getUserDisplayName($createdByUser),
            ] : null,

            // Referenzen
            'activity_id' => $ticket->getActivityId(),
            'activity_type' => $ticket->getActivity()?->getType(),
            'issue_report_id' => $ticket->getIssueReportId(),
            'origin_source' => $ticket->getIssueReportId() ? 'issue_report' : 'manual',
            'origin_issue_type' => $ticket->getIssueReport()?->getType(),
            'origin_issue_type_label' => $ticket->getIssueReport()?->getTypeLabel(),
        ];

        $workshopPublicEntry = $this->publicCodeService->getActiveWorkshopPublicCode((string) $ticket->getId());
        $workshopPublicCode = $workshopPublicEntry?->getPublicCode();
        $result['public_code'] = $workshopPublicCode;
        $result['public_url'] = $workshopPublicCode
            ? $this->publicCodeService->buildWorkshopPublicUrl($workshopPublicCode)
            : null;

        if ($detailed) {
            $result['parts_used'] = $ticket->getPartsUsed();
            $result['repair_checklist'] = $ticket->getRepairChecklist();
            $result['photos'] = $this->photoNormalizer->normalizeOutgoing($ticket->getPhotos());

            // Activity-Info
            $activity = $ticket->getActivity();
            if ($activity) {
                $result['activity'] = [
                    'id' => $activity->getId(),
                    'name' => $activity->getName(),
                    'type' => $activity->getType(),
                    'status' => $activity->getStatus(),
                ];
            }

            // IssueReport-Info (Herkunft: Wer hat's gemeldet, wann, was)
            $issueReport = $ticket->getIssueReport();
            if ($issueReport) {
                $reporter = $issueReport->getReportedByUser();
                $issuePhotos = $this->photoNormalizer->normalizeOutgoing($issueReport->getPhotos());
                $result['issue_report'] = [
                    'id' => $issueReport->getId(),
                    'type' => $issueReport->getType(),
                    'type_label' => $issueReport->getTypeLabel(),
                    'description' => $issueReport->getDescription(),
                    'quantity' => $issueReport->getQuantity(),
                    'photo_url' => $issueReport->getPrimaryPhotoUrl(),
                    'photos' => $issuePhotos,
                    'reported_at' => $issueReport->getReportedAt()->format('c'),
                    'reported_by' => $reporter ? [
                        'id' => $reporter->getId(),
                        'name' => $this->getUserDisplayName($reporter),
                    ] : null,
                    'resolved' => $issueReport->isResolved(),
                    'resolved_at' => $issueReport->getResolvedAt()?->format('c'),
                ];
            }

            // Erlaubte Übergänge
            $result['allowed_transitions'] = WorkshopTicket::STATUS_TRANSITIONS[$ticket->getStatus()] ?? [];
        }

        return $result;
    }

    private function canUserManageWorkshopPublicCode(User $user, WorkshopTicket $ticket): bool
    {
        if (count(array_intersect(['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'], $user->getRoles())) > 0) {
            return true;
        }

        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $ticket->getDepartmentId()]);
        if (!$membership) {
            return false;
        }

        return in_array((string) ($membership->getRole() ?? ''), ['mw', 'dc'], true);
    }

    /**
     * Gibt den Anzeigenamen eines Users zurück
     */
    private function getUserDisplayName(User $user): string
    {
        $profile = $user->getProfile();
        if (!$profile) return 'Unbekannt';

        $first = $profile->getFirstName() ?? '';
        $last = $profile->getLastName() ?? '';
        $name = trim($first . ' ' . $last);

        return $name ?: 'Unbekannt';
    }

    // ═══════════════════════════════════════════════
    // STATIC HELPER: Auto-Erstellung (für andere Controller)
    // ═══════════════════════════════════════════════

    /**
     * Erstellt automatisch ein Workshop-Ticket aus einem IssueReport
     * 
     * Wird aufgerufen von ActivityWorkflowController::createIssue()
     * sowie ActivityPackCrateCheckService bei repair, damage, loss und not_taken (letzteres: Inspektion, kein Lagerverlust).
     */
    public static function autoCreateFromIssueReport(
        EntityManagerInterface $em,
        ActivityIssueReport $issueReport,
        Activity $activity,
        ?User $currentUser
    ): ?WorkshopTicket {
        $materialItem = $issueReport->getMaterialItem();
        if (!$materialItem) {
            return null; // Kein Material → kein Ticket
        }

        // Prüfen ob bereits ein Ticket für diesen IssueReport existiert
        $existing = $em->getRepository(WorkshopTicket::class)
            ->findOneBy(['issueReportId' => $issueReport->getId()]);
        if ($existing) {
            return $existing; // Bereits vorhanden
        }

        $ticket = new WorkshopTicket();
        $ticket->setId(IdGenerator::generate13('wt'));
        $ticket->setDepartment($activity->getDepartment());
        $ticket->setMaterialItem($materialItem);
        $ticket->setActivity($activity);
        $ticket->setIssueReport($issueReport);

        // Typ basierend auf IssueReport-Typ
        $type = match ($issueReport->getType()) {
            ActivityIssueReport::TYPE_REPAIR => WorkshopTicket::TYPE_REPAIR,
            ActivityIssueReport::TYPE_DAMAGE => WorkshopTicket::TYPE_REPAIR,
            ActivityIssueReport::TYPE_LOSS => WorkshopTicket::TYPE_WRITEOFF,
            ActivityIssueReport::TYPE_NOT_TAKEN => WorkshopTicket::TYPE_INSPECTION,
            default => WorkshopTicket::TYPE_INSPECTION,
        };
        $ticket->setType($type);

        // Priorität: Vorschlag normal — Materialwart setzt sie in der Triage
        $ticket->setPriority(WorkshopTicket::PRIORITY_NORMAL);

        // Titel auto-generieren
        $title = sprintf(
            '%s: %s',
            $issueReport->getTypeLabel(),
            $materialItem->getName()
        );
        $ticket->setTitle($title);

        // Beschreibung vom IssueReport übernehmen
        $description = $issueReport->getDescription();
        if ($description) {
            $ticket->setDescription($description);
        }

        $checklist = $issueReport->getRepairChecklist();
        if (\is_array($checklist) && $checklist !== []) {
            $ticket->setRepairChecklist($checklist);
        }

        // Ersteller = der Reporter
        if ($currentUser instanceof User) {
            $ticket->setCreatedByUser($currentUser);
        }

        $affectedQty = max(1, $issueReport->getQuantity());
        $ticket->setAffectedQuantity($affectedQty);

        // Material-Stamm nur bei voller Betroffenheit anpassen
        $totalStock = $materialItem->getTotalStock();
        if ($materialItem->getCondition() === 'ok' && $totalStock > 0 && $affectedQty >= $totalStock) {
            if ($issueReport->getType() === ActivityIssueReport::TYPE_LOSS) {
                $materialItem->setCondition('lost');
                $materialItem->updateTimestamps();
            } elseif (in_array($issueReport->getType(), [ActivityIssueReport::TYPE_REPAIR, ActivityIssueReport::TYPE_DAMAGE], true)) {
                $materialItem->setCondition('repair');
                $materialItem->updateTimestamps();
            }
        }

        $em->persist($ticket);

        // History-Eintrag: auto_created_issue
        $history = new WorkshopTicketHistory();
        $history->setId(IdGenerator::generate13('wh'));
        $history->setWorkshopTicket($ticket);
        $history->setAction(WorkshopTicketHistory::ACTION_AUTO_CREATED_ISSUE);
        $history->setSnapshot([
            'status' => $ticket->getStatus(),
            'type' => $ticket->getType(),
            'priority' => $ticket->getPriority(),
        ]);
        $history->setChanges([
            'source' => 'issue_report',
            'issue_report_id' => $issueReport->getId(),
            'issue_report_type' => $issueReport->getType(),
            'activity_id' => $activity->getId(),
            'activity_name' => $activity->getName(),
            'material_id' => $materialItem->getId(),
            'material_name' => $materialItem->getName(),
            'reported_by_user_id' => $issueReport->getReportedByUserId(),
            'affected_quantity' => $affectedQty,
        ]);
        if ($currentUser instanceof User) {
            $history->setUser($currentUser);
        }
        $em->persist($history);

        return $ticket;
    }

    /**
     * Inspektions-Aufgabe nach Kistencheck-Überschuss (Lager-Kontrolle).
     */
    public static function autoCreateInspectionForCrateCheckSurplus(
        EntityManagerInterface $em,
        Activity $activity,
        MaterialItem $materialItem,
        int $qty,
        string $shellLabel,
        ?User $currentUser,
    ): ?WorkshopTicket {
        $ticket = new WorkshopTicket();
        $ticket->setId(IdGenerator::generate13('wt'));
        $ticket->setDepartment($activity->getDepartment());
        $ticket->setMaterialItem($materialItem);
        $ticket->setActivity($activity);
        $ticket->setType(WorkshopTicket::TYPE_INSPECTION);
        $ticket->setPriority(WorkshopTicket::PRIORITY_NORMAL);
        $ticket->setTitle(sprintf('Inventur/Kontrolle: %s', $materialItem->getName()));
        $ticket->setDescription(sprintf(
            'Kistencheck «%s» (Aktivität «%s»): %d Stk. Überschuss — Lagerstand und Einlagerung prüfen.',
            $shellLabel,
            $activity->getName(),
            max(1, $qty),
        ));
        if ($currentUser instanceof User) {
            $ticket->setCreatedByUser($currentUser);
        }
        $em->persist($ticket);

        $history = new WorkshopTicketHistory();
        $history->setId(IdGenerator::generate13('wh'));
        $history->setWorkshopTicket($ticket);
        $history->setAction(WorkshopTicketHistory::ACTION_AUTO_CREATED_ISSUE);
        $history->setSnapshot([
            'status' => $ticket->getStatus(),
            'type' => $ticket->getType(),
            'priority' => $ticket->getPriority(),
        ]);
        $history->setChanges([
            'source' => 'pack_crate_check_surplus',
            'activity_id' => $activity->getId(),
            'activity_name' => $activity->getName(),
            'material_id' => $materialItem->getId(),
            'material_name' => $materialItem->getName(),
            'surplus_qty' => max(1, $qty),
            'shell_label' => $shellLabel,
        ]);
        if ($currentUser instanceof User) {
            $history->setUser($currentUser);
        }
        $em->persist($history);

        return $ticket;
    }

    /**
     * Erstellt automatisch ein Workshop-Ticket aus einer Rückgabe-Position
     * 
     * Wird aufgerufen von ActivityWorkflowController::updateReturnItem()
     * wenn condition_in = 'defekt' oder 'beschaedigt'
     */
    public static function autoCreateFromReturnItem(
        EntityManagerInterface $em,
        \App\Entity\ActivityReturnItem $returnItem,
        Activity $activity,
        ?User $currentUser
    ): ?WorkshopTicket {
        $materialItem = $returnItem->getMaterialItem();

        // Prüfen ob bereits ein Ticket für dieses Material + Activity existiert
        $existing = $em->getRepository(WorkshopTicket::class)
            ->createQueryBuilder('t')
            ->where('t.materialItemId = :materialId')
            ->andWhere('t.activityId = :activityId')
            ->andWhere('t.status NOT IN (:closedStatuses)')
            ->setParameter('materialId', $materialItem->getId())
            ->setParameter('activityId', $activity->getId())
            ->setParameter('closedStatuses', [WorkshopTicket::STATUS_COMPLETED, WorkshopTicket::STATUS_CANCELLED])
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing) {
            return $existing; // Bereits vorhanden
        }

        $ticket = new WorkshopTicket();
        $ticket->setId(IdGenerator::generate13('wt'));
        $ticket->setDepartment($activity->getDepartment());
        $ticket->setMaterialItem($materialItem);
        $ticket->setActivity($activity);
        $ticket->setType(WorkshopTicket::TYPE_REPAIR);

        // Priorität: Vorschlag normal — Materialwart setzt sie in der Triage
        $ticket->setPriority(WorkshopTicket::PRIORITY_NORMAL);

        // Titel auto-generieren
        $conditionLabel = match ($returnItem->getConditionIn()) {
            'defekt' => 'Defekt',
            'beschaedigt' => 'Beschädigt',
            default => 'Schaden',
        };
        $title = sprintf(
            'Rückgabe %s: %s',
            $conditionLabel,
            $materialItem->getName()
        );
        $ticket->setTitle($title);

        // Beschreibung
        $description = sprintf(
            "Bei Rückgabe von Aktivität \"%s\" als %s eingestuft.\n%s%s",
            $activity->getName(),
            strtolower($conditionLabel),
            $returnItem->getQuantityDamaged() > 0 ? "Beschädigt: " . $returnItem->getQuantityDamaged() . " Stk.\n" : '',
            $returnItem->getNotes() ? "Anmerkung: " . $returnItem->getNotes() : ''
        );
        $ticket->setDescription(trim($description));

        if ($currentUser instanceof User) {
            $ticket->setCreatedByUser($currentUser);
        }

        // Material-Zustand setzen
        $conditionMap = [
            'defekt' => 'defect',
            'beschaedigt' => 'repair',
        ];
        $newCondition = $conditionMap[$returnItem->getConditionIn()] ?? 'repair';
        if (in_array($materialItem->getCondition(), ['ok', 'repair'])) {
            $materialItem->setCondition($newCondition);
            $materialItem->updateTimestamps();
        }

        $em->persist($ticket);

        // History-Eintrag: auto_created_return
        $history = new WorkshopTicketHistory();
        $history->setId(IdGenerator::generate13('wh'));
        $history->setWorkshopTicket($ticket);
        $history->setAction(WorkshopTicketHistory::ACTION_AUTO_CREATED_RETURN);
        $history->setSnapshot([
            'status' => $ticket->getStatus(),
            'type' => $ticket->getType(),
            'priority' => $ticket->getPriority(),
        ]);
        $history->setChanges([
            'source' => 'return_item',
            'return_item_id' => $returnItem->getId(),
            'condition_in' => $returnItem->getConditionIn(),
            'quantity_damaged' => $returnItem->getQuantityDamaged(),
            'activity_id' => $activity->getId(),
            'activity_name' => $activity->getName(),
            'material_id' => $materialItem->getId(),
            'material_name' => $materialItem->getName(),
            'returned_by_user_id' => $returnItem->getReturnedByUserId(),
        ]);
        if ($currentUser instanceof User) {
            $history->setUser($currentUser);
        }
        $em->persist($history);

        return $ticket;
    }
}
