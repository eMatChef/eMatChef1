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
use App\Entity\User;
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
        private EntityManagerInterface $entityManager
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

            // Ersteller
            $currentUser = $this->getUser();
            if ($currentUser instanceof User) {
                $ticket->setCreatedByUser($currentUser);
            }

            // Material-Zustand auf 'repair' setzen wenn type=repair und condition noch 'ok'
            if ($type === 'repair' && $material->getCondition() === 'ok') {
                $material->setCondition('repair');
                $material->updateTimestamps();
            }

            $this->entityManager->persist($ticket);

            // ── History-Eintrag: created ──
            $this->createHistoryEntry(
                $ticket,
                WorkshopTicketHistory::ACTION_CREATED,
                [],
                [
                    'title' => $ticket->getTitle(),
                    'type' => $ticket->getType(),
                    'priority' => $ticket->getPriority(),
                    'material_item_id' => $material->getId(),
                    'material_name' => $material->getName(),
                    'activity_id' => $ticket->getActivityId(),
                    'issue_report_id' => $ticket->getIssueReportId(),
                ]
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
                    } else {
                        $ticket->setAssignedToUser(null);
                    }
                    $changes['assigned_to_user_id'] = ['old' => $oldAssignedId, 'new' => $newAssignedId];
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

            // Ersatzteile
            if (array_key_exists('parts_used', $data)) {
                $ticket->setPartsUsed($data['parts_used']);
                $changes['parts_used'] = ['updated' => true];
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

            return new JsonResponse($this->serializeTicket($ticket));

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Aktualisieren des Tickets: ' . $e->getMessage()
            ], 500);
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

        try {
            $oldStatus = $ticket->getStatus();
            $ticket->setStatus($newStatus);
            $historyChanges = [
                'status' => ['old' => $oldStatus, 'new' => $newStatus],
            ];

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

                // Abschluss-Daten
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

                // Material-Zustand basierend auf resolution_action
                $material = $ticket->getMaterialItem();

                if ($resolutionAction === 'repaired' || $resolutionAction === 'ok') {
                    // Repariert → Material wieder OK
                    $oldCondition = $material->getCondition();
                    $material->setCondition('ok');
                    $material->updateTimestamps();
                    $historyChanges['material_condition'] = ['old' => $oldCondition, 'new' => 'ok'];

                    // MaterialHistory-Eintrag
                    $this->createMaterialHistoryEntry($material, 'condition_changed', [
                        'condition' => ['old' => $oldCondition, 'new' => 'ok'],
                        'reason' => 'Workshop-Ticket #' . $ticket->getId() . ' abgeschlossen (repariert)',
                    ]);

                } elseif ($resolutionAction === 'writeoff') {
                    // Abschreibung → Writeoff-Batch erstellen (Bestand -1)
                    $oldCondition = $material->getCondition();
                    $material->setCondition('defect');
                    $material->updateTimestamps();
                    $historyChanges['material_condition'] = ['old' => $oldCondition, 'new' => 'defect'];

                    // Writeoff-Batch: negative qty reduziert Bestand
                    $writeoffQty = (int)($data['writeoff_qty'] ?? 1);
                    $writeoffBatch = new MaterialBatch();
                    $writeoffBatch->setId(IdGenerator::generate13('ba'));
                    $writeoffBatch->setMaterialItem($material);
                    $writeoffBatch->setQty(-$writeoffQty); // NEGATIV!
                    $writeoffBatch->setBatchType('writeoff');
                    $writeoffBatch->setStatus('active');
                    $writeoffBatch->setLabel('Abschreibung');
                    $writeoffBatch->setNotes(
                        'Abgeschrieben via Workshop-Ticket #' . $ticket->getId()
                        . ($data['resolution_notes'] ? ' – ' . $data['resolution_notes'] : '')
                    );
                    $writeoffBatch->setAcquiredOn($now);

                    $this->entityManager->persist($writeoffBatch);

                    $historyChanges['writeoff_batch_id'] = $writeoffBatch->getId();
                    $historyChanges['writeoff_qty'] = $writeoffQty;

                    // MaterialHistory-Eintrag
                    $this->createMaterialHistoryEntry($material, 'writeoff', [
                        'condition' => ['old' => $oldCondition, 'new' => 'defect'],
                        'writeoff_qty' => $writeoffQty,
                        'batch_id' => $writeoffBatch->getId(),
                        'reason' => 'Workshop-Ticket #' . $ticket->getId() . ' – Abschreibung',
                    ]);
                }

                // Verknüpften IssueReport als resolved markieren
                $issueReport = $ticket->getIssueReport();
                if ($issueReport && !$issueReport->isResolved()) {
                    $issueReport->setResolved(true);
                    $issueReport->setResolvedAt($now);
                    $currentUser = $this->getUser();
                    if ($currentUser instanceof User) {
                        $issueReport->setResolvedByUser($currentUser);
                    }
                    $historyChanges['issue_report_resolved'] = true;
                }
            }

            if ($newStatus === WorkshopTicket::STATUS_CANCELLED) {
                $historyAction = WorkshopTicketHistory::ACTION_CANCELLED;
            }

            $ticket->updateTimestamps();

            // ── History-Eintrag ──
            $this->createHistoryEntry($ticket, $historyAction, [], $historyChanges);

            $this->entityManager->flush();

            return new JsonResponse($this->serializeTicket($ticket));

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

        // Tickets nach Status zählen
        $statusCounts = $conn->executeQuery(
            'SELECT status, COUNT(*) as count FROM workshop_ticket WHERE department_id = :deptId GROUP BY status',
            ['deptId' => $departmentId]
        )->fetchAllAssociative();

        $stats = [
            'open' => 0,
            'in_progress' => 0,
            'waiting_parts' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];
        foreach ($statusCounts as $row) {
            $stats[$row['status']] = (int)$row['count'];
        }

        // Diese Woche erledigt
        $weekStart = (new \DateTime())->modify('monday this week')->format('Y-m-d');
        $completedThisWeek = $conn->executeQuery(
            'SELECT COUNT(*) FROM workshop_ticket WHERE department_id = :deptId AND status = \'completed\' AND completed_at >= :weekStart',
            ['deptId' => $departmentId, 'weekStart' => $weekStart]
        )->fetchOne();

        // Tickets nach Typ zählen (nur aktive)
        $typeCounts = $conn->executeQuery(
            'SELECT type, COUNT(*) as count FROM workshop_ticket WHERE department_id = :deptId AND status NOT IN (\'completed\', \'cancelled\') GROUP BY type',
            ['deptId' => $departmentId]
        )->fetchAllAssociative();

        $types = [];
        foreach ($typeCounts as $row) {
            $types[$row['type']] = (int)$row['count'];
        }

        // Tickets nach Priorität zählen (nur aktive)
        $priorityCounts = $conn->executeQuery(
            'SELECT priority, COUNT(*) as count FROM workshop_ticket WHERE department_id = :deptId AND status NOT IN (\'completed\', \'cancelled\') GROUP BY priority',
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
               AND t.status = 'waiting_parts'
               AND a.type = 'external'",
            ['deptId' => $departmentId]
        )->fetchOne();

        $missingEstimatedCostCount = (int)$conn->executeQuery(
            "SELECT COUNT(*)
             FROM workshop_ticket t
             INNER JOIN activity a ON a.id = t.activity_id
             WHERE t.department_id = :deptId
               AND a.type = 'external'
               AND t.status IN ('open', 'in_progress', 'waiting_parts')
               AND t.type IN ('repair', 'writeoff')
               AND t.estimated_cost IS NULL",
            ['deptId' => $departmentId]
        )->fetchOne();

        return new JsonResponse([
            'status_counts' => $stats,
            'completed_this_week' => (int)$completedThisWeek,
            'type_counts' => $types,
            'priority_counts' => $priorities,
            'total_active' => $stats['open'] + $stats['in_progress'] + $stats['waiting_parts'],
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
                'barcode_tag' => $material->getBarcodeTag(),
                'sale_price' => $material->getSalePrice(),
                'category' => $material->getCategory() ? [
                    'id' => $material->getCategory()->getId(),
                    'name' => $material->getCategory()->getName(),
                ] : null,
            ],

            // Zuweisung
            'assigned_to' => $assignedUser ? [
                'id' => $assignedUser->getId(),
                'name' => $this->getUserDisplayName($assignedUser),
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

        if ($detailed) {
            $result['parts_used'] = $ticket->getPartsUsed();
            $result['photos'] = $ticket->getPhotos();

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
                $result['issue_report'] = [
                    'id' => $issueReport->getId(),
                    'type' => $issueReport->getType(),
                    'type_label' => $issueReport->getTypeLabel(),
                    'description' => $issueReport->getDescription(),
                    'quantity' => $issueReport->getQuantity(),
                    'photo_url' => $issueReport->getPhotoUrl(),
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

        // Priorität basierend auf Typ
        $priority = $issueReport->getType() === ActivityIssueReport::TYPE_DAMAGE
            ? WorkshopTicket::PRIORITY_HIGH
            : WorkshopTicket::PRIORITY_NORMAL;
        $ticket->setPriority($priority);

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

        // Ersteller = der Reporter
        if ($currentUser instanceof User) {
            $ticket->setCreatedByUser($currentUser);
        }

        // Material-Zustand: nur bei Verlust / Reparatur-Pfaden; not_taken & Verbrauch unverändert lassen
        if ($materialItem->getCondition() === 'ok') {
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

        // Priorität basierend auf condition
        $priority = $returnItem->getConditionIn() === 'defekt'
            ? WorkshopTicket::PRIORITY_HIGH
            : WorkshopTicket::PRIORITY_NORMAL;
        $ticket->setPriority($priority);

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
