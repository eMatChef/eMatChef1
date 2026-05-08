<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityHistory;
use App\Entity\ActivityItem;
use App\Entity\ActivityPackContainer;
use App\Entity\ActivityPackItem;
use App\Entity\ActivityReturnItem;
use App\Entity\ActivityIssueReport;
use App\Entity\Department;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\Membership;
use App\Entity\MaterialItem;
use App\Entity\WorkshopTicket;
use App\Entity\Address;
use App\Entity\User;
use App\Service\ActivityAccessService;
use App\Service\ActivityKisteMaterialLinker;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/activities', name: 'api_activities_')]
class ActivityController extends AbstractController
{
    private const DEPARTMENT_MANAGER_ROLES = ['mw', 'dc', 'org', 'sub', 'sa'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityAccessService $activityAccess,
        private ActivityKisteMaterialLinker $kisteMaterialLinker,
    ) {}

    private function normalizeInvitedDepartmentsPayload(array $incoming, array $existing = []): array
    {
        $existingById = [];
        foreach ($existing as $entry) {
            $id = isset($entry['id']) ? (string) $entry['id'] : '';
            if ($id !== '') {
                $existingById[$id] = $entry;
            }
        }

        $normalized = [];
        foreach ($incoming as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $id = trim((string) ($entry['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $name = trim((string) ($entry['name'] ?? ''));
            $orgName = trim((string) ($entry['organisation_name'] ?? ''));

            $existingEntry = $existingById[$id] ?? null;
            $status = (string) ($existingEntry['status'] ?? 'pending');
            if (!in_array($status, ['pending', 'accepted', 'rejected'], true)) {
                $status = 'pending';
            }

            $row = [
                'id' => $id,
                'name' => $name !== '' ? $name : ($existingEntry['name'] ?? ''),
                'organisation_name' => $orgName !== '' ? $orgName : ($existingEntry['organisation_name'] ?? ''),
                'status' => $status,
                'invited_at' => $existingEntry['invited_at'] ?? (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'decided_at' => $existingEntry['decided_at'] ?? null,
                'decided_by_user_id' => $existingEntry['decided_by_user_id'] ?? null,
            ];

            $groupId = '';
            if (array_key_exists('group_id', $entry)) {
                $groupId = trim((string) ($entry['group_id'] ?? ''));
            } else {
                $groupId = trim((string) ($existingEntry['group_id'] ?? ''));
            }
            if ($groupId !== '') {
                $group = $this->entityManager->getRepository(Group::class)->find($groupId);
                if ($group && $group->getDepartmentId() === $id) {
                    $row['group_id'] = $groupId;
                    $row['group_name'] = $group->getName();
                }
            }

            $normalized[] = $row;
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>|mixed>|null $rows
     * @return array<int, array<string, mixed>>
     */
    private function enrichInvitedDepartmentsForApi(?array $rows): array
    {
        if ($rows === null || $rows === []) {
            return [];
        }
        $out = [];
        foreach ($rows as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $gid = trim((string) ($entry['group_id'] ?? ''));
            if ($gid !== '' && ($entry['group_name'] ?? '') === '') {
                $g = $this->entityManager->getRepository(Group::class)->find($gid);
                if ($g) {
                    $entry['group_name'] = $g->getName();
                }
            }
            $out[] = $entry;
        }

        return $out;
    }

    private function assertDepartmentManager(User $user, string $departmentId): void
    {
        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$membership || !in_array($membership->getRole(), self::DEPARTMENT_MANAGER_ROLES, true)) {
            throw new AccessDeniedException('Keine Berechtigung für dieses Department');
        }
    }

    /**
     * Liste aller Aktivitäten für ein Department
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $departmentId = $request->query->get('department_id');

        if (!$departmentId) {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $currentUser->getId(), 'departmentId' => $departmentId]);
        if (!$membership) {
            return new JsonResponse(['error' => 'Keine Berechtigung fuer dieses Department'], 403);
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(Activity::class, 'a')
            ->where('a.departmentId = :departmentId')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('a.usageStart', 'DESC');

        // Statusfilter
        $status = $request->query->get('status');
        if ($status) {
            $statuses = explode(',', $status);
            $qb->andWhere('a.status IN (:statuses)')
                ->setParameter('statuses', $statuses);
        }

        // Typfilter
        $type = $request->query->get('type');
        if ($type) {
            $qb->andWhere('a.type = :type')
                ->setParameter('type', $type);
        }

        // Suchfilter
        $search = $request->query->get('search');
        if ($search) {
            $qb->andWhere('a.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Tab-basierter Filter
        $tab = $request->query->get('tab');
        if ($tab === 'upcoming') {
            // Alle aktiven Workflows (nicht abgeschlossen, nicht storniert)
            $qb->andWhere('a.status IN (:upcomingStatuses)')
                ->setParameter('upcomingStatuses', [
                    Activity::STATUS_DRAFT,
                    Activity::STATUS_SUBMITTED,
                    Activity::STATUS_APPROVED,
                    Activity::STATUS_PACKING,
                    Activity::STATUS_PACKED,
                    Activity::STATUS_ISSUED,
                    Activity::STATUS_RETURNED,
                ]);
        } elseif ($tab === 'past') {
            $qb->andWhere('a.status = :completedStatus')
                ->setParameter('completedStatus', Activity::STATUS_COMPLETED);
        } elseif ($tab === 'cancelled') {
            $qb->andWhere('a.status = :cancelledStatus')
                ->setParameter('cancelledStatus', Activity::STATUS_CANCELLED);
        }

        // Sichtbarkeit: nur Manager sehen Department-weit alles.
        // Normale User/Leader sehen nur eigene/verantwortete oder Gruppen-Aktivitaeten.
        $managerRoles = ['sa', 'org', 'sub', 'mw', 'dc'];
        if (!in_array($membership->getRole(), $managerRoles, true)) {
            $groupMemberships = $this->entityManager->getRepository(GroupMembership::class)
                ->findBy(['userId' => $currentUser->getId()]);
            $groupIds = array_values(array_unique(array_filter(array_map(
                static fn(GroupMembership $gm) => $gm->getGroupId(),
                $groupMemberships
            ))));

            $expr = $qb->expr()->orX(
                'a.createdByUserId = :currentUserId',
                'a.responsibleUserId = :currentUserId'
            );
            $qb->setParameter('currentUserId', $currentUser->getId());

            if (!empty($groupIds)) {
                $expr->add('a.groupId IN (:visibleGroupIds)');
                $qb->setParameter('visibleGroupIds', $groupIds);
            }

            $qb->andWhere($expr);
        }

        $activities = $qb->getQuery()->getResult();

        // Zusätzlich: Aktivitäten anderer Departments, die dieses Department angenommen hat
        $invitedQb = $this->entityManager->createQueryBuilder();
        $invitedQb->select('a')
            ->from(Activity::class, 'a')
            ->where('a.departmentId != :departmentId')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('a.type IN (:invitedTypes)')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('invitedTypes', ['camp', 'event']);

        if ($status) {
            $invitedQb->andWhere('a.status IN (:statuses)')
                ->setParameter('statuses', $statuses);
        }

        if ($type) {
            $invitedQb->andWhere('a.type = :type')
                ->setParameter('type', $type);
        }

        if ($search) {
            $invitedQb->andWhere('a.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($tab === 'upcoming') {
            $invitedQb->andWhere('a.status IN (:upcomingStatuses)')
                ->setParameter('upcomingStatuses', [
                    Activity::STATUS_DRAFT,
                    Activity::STATUS_SUBMITTED,
                    Activity::STATUS_APPROVED,
                    Activity::STATUS_PACKING,
                    Activity::STATUS_PACKED,
                    Activity::STATUS_ISSUED,
                    Activity::STATUS_RETURNED,
                ]);
        } elseif ($tab === 'past') {
            $invitedQb->andWhere('a.status = :completedStatus')
                ->setParameter('completedStatus', Activity::STATUS_COMPLETED);
        } elseif ($tab === 'cancelled') {
            $invitedQb->andWhere('a.status = :cancelledStatus')
                ->setParameter('cancelledStatus', Activity::STATUS_CANCELLED);
        }

        $invitedCandidates = $invitedQb->getQuery()->getResult();
        foreach ($invitedCandidates as $candidate) {
            if ($this->activityAccess->isDepartmentInviteAccepted($candidate, (string) $departmentId)) {
                $activities[] = $candidate;
            }
        }

        $result = [];
        foreach ($activities as $activity) {
            $result[] = $this->serializeActivity($activity);
        }

        usort($result, static function (array $a, array $b): int {
            $at = isset($a['usage_start']) && $a['usage_start'] ? strtotime((string) $a['usage_start']) : 0;
            $bt = isset($b['usage_start']) && $b['usage_start'] ? strtotime((string) $b['usage_start']) : 0;
            return $bt <=> $at;
        });

        return new JsonResponse($result);
    }

    /**
     * Material-Vorschläge basierend auf Nutzungshistorie
     *
     * Muss VOR Route /{id} stehen – sonst wird "material-suggestions" als Aktivitäts-ID aufgelöst.
     *
     * Drei Ebenen (absteigend nach Priorität):
     * 1. Gruppe + Wochentag (z.B. "Samstags üblich für Pfadi")
     * 2. Gruppe allgemein  (z.B. "Häufig für Pfadi")
     * 3. Persönlich        (z.B. "Zuletzt von dir verwendet")
     */
    #[Route('/material-suggestions', name: 'material_suggestions', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function materialSuggestions(Request $request): JsonResponse
    {
        $departmentId = $request->query->get('department_id');
        if (!$departmentId) {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $currentUser->getId(), 'departmentId' => $departmentId]);
        if (!$membership) {
            return new JsonResponse(['error' => 'Keine Berechtigung fuer dieses Department'], 403);
        }

        $groupId    = $request->query->get('group_id');
        $dayOfWeek  = $request->query->getInt('day_of_week', 0); // 1=Mo..7=So (ISO-8601)
        $type       = $request->query->get('type', 'activity');
        $limit      = min($request->query->getInt('limit', 10), 20);
        $minUsage   = $request->query->getInt('min_usage', 2);

        $user = $this->getUser();
        $userId = $user instanceof User ? $user->getId() : null;

        $suggestions = [];
        $seenMaterials = [];

        // ─── Ebene 1: Gruppe + Wochentag ───
        if ($groupId && $dayOfWeek >= 1 && $dayOfWeek <= 7) {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select(
                'ai.materialItemId',
                'mi.name AS materialName',
                'COUNT(ai.id) AS usageCount',
                'ROUND(AVG(ai.quantity)) AS avgQuantity',
                'MAX(a.usageStart) AS lastUsed'
            )
            ->from(ActivityItem::class, 'ai')
            ->join('ai.activity', 'a')
            ->join('ai.materialItem', 'mi')
            ->where('a.groupId = :groupId')
            ->andWhere('a.departmentId = :departmentId')
            ->andWhere('DAYOFWEEK(a.usageStart) = :dow')
            ->andWhere('a.type = :type')
            ->andWhere('a.status NOT IN (:excludeStatus)')
            ->andWhere('a.deletedAt IS NULL')
            ->groupBy('ai.materialItemId, mi.name')
            ->having('COUNT(ai.id) >= :minUsage')
            ->orderBy('usageCount', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('groupId', $groupId)
            ->setParameter('departmentId', $departmentId)
            ->setParameter('dow', $dayOfWeek)
            ->setParameter('type', $type)
            ->setParameter('excludeStatus', ['draft', 'cancelled'])
            ->setParameter('minUsage', $minUsage);

            $rows = $qb->getQuery()->getArrayResult();

            foreach ($rows as $row) {
                $matId = $row['materialItemId'];
                $seenMaterials[$matId] = true;
                $suggestions[] = [
                    'material_item_id' => $matId,
                    'name'             => $row['materialName'],
                    'usage_count'      => (int)$row['usageCount'],
                    'avg_quantity'     => max(1, (int)$row['avgQuantity']),
                    'last_used'        => $row['lastUsed'] instanceof \DateTimeInterface ? $row['lastUsed']->format('Y-m-d') : null,
                    'source'           => 'group_weekday',
                ];
            }
        }

        // ─── Ebene 2: Gruppe allgemein ───
        if ($groupId && count($suggestions) < $limit) {
            $remaining = $limit - count($suggestions);

            $qb2 = $this->entityManager->createQueryBuilder();
            $qb2->select(
                'ai.materialItemId',
                'mi.name AS materialName',
                'COUNT(ai.id) AS usageCount',
                'ROUND(AVG(ai.quantity)) AS avgQuantity',
                'MAX(a.usageStart) AS lastUsed'
            )
            ->from(ActivityItem::class, 'ai')
            ->join('ai.activity', 'a')
            ->join('ai.materialItem', 'mi')
            ->where('a.groupId = :groupId')
            ->andWhere('a.departmentId = :departmentId')
            ->andWhere('a.status NOT IN (:excludeStatus)')
            ->andWhere('a.deletedAt IS NULL')
            ->groupBy('ai.materialItemId, mi.name')
            ->having('COUNT(ai.id) >= :minUsage')
            ->orderBy('usageCount', 'DESC')
            ->setMaxResults($remaining + count($seenMaterials)) // Fetch extra to compensate for duplicates
            ->setParameter('groupId', $groupId)
            ->setParameter('departmentId', $departmentId)
            ->setParameter('excludeStatus', ['draft', 'cancelled'])
            ->setParameter('minUsage', $minUsage);

            $rows2 = $qb2->getQuery()->getArrayResult();

            foreach ($rows2 as $row) {
                if (count($suggestions) >= $limit) {
                    break;
                }
                $matId = $row['materialItemId'];
                if (isset($seenMaterials[$matId])) {
                    continue;
                }
                $seenMaterials[$matId] = true;
                $suggestions[] = [
                    'material_item_id' => $matId,
                    'name'             => $row['materialName'],
                    'usage_count'      => (int)$row['usageCount'],
                    'avg_quantity'     => max(1, (int)$row['avgQuantity']),
                    'last_used'        => $row['lastUsed'] instanceof \DateTimeInterface ? $row['lastUsed']->format('Y-m-d') : null,
                    'source'           => 'group',
                ];
            }
        }

        // ─── Ebene 3: Persönliche Favoriten ───
        if ($userId && count($suggestions) < $limit) {
            $remaining = $limit - count($suggestions);

            $qb3 = $this->entityManager->createQueryBuilder();
            $qb3->select(
                'ai.materialItemId',
                'mi.name AS materialName',
                'COUNT(ai.id) AS usageCount',
                'ROUND(AVG(ai.quantity)) AS avgQuantity',
                'MAX(a.usageStart) AS lastUsed'
            )
            ->from(ActivityItem::class, 'ai')
            ->join('ai.activity', 'a')
            ->join('ai.materialItem', 'mi')
            ->where('a.createdByUserId = :userId')
            ->andWhere('a.departmentId = :departmentId')
            ->andWhere('a.status NOT IN (:excludeStatus)')
            ->andWhere('a.deletedAt IS NULL')
            ->groupBy('ai.materialItemId, mi.name')
            ->orderBy('lastUsed', 'DESC')
            ->setMaxResults($remaining + count($seenMaterials))
            ->setParameter('userId', $userId)
            ->setParameter('departmentId', $departmentId)
            ->setParameter('excludeStatus', ['draft', 'cancelled']);

            $rows3 = $qb3->getQuery()->getArrayResult();

            foreach ($rows3 as $row) {
                if (count($suggestions) >= $limit) {
                    break;
                }
                $matId = $row['materialItemId'];
                if (isset($seenMaterials[$matId])) {
                    continue;
                }
                $seenMaterials[$matId] = true;
                $suggestions[] = [
                    'material_item_id' => $matId,
                    'name'             => $row['materialName'],
                    'usage_count'      => (int)$row['usageCount'],
                    'avg_quantity'     => max(1, (int)$row['avgQuantity']),
                    'last_used'        => $row['lastUsed'] instanceof \DateTimeInterface ? $row['lastUsed']->format('Y-m-d') : null,
                    'source'           => 'personal',
                ];
            }
        }

        return new JsonResponse([
            'suggestions' => $suggestions,
            'meta' => [
                'department_id' => $departmentId,
                'group_id'      => $groupId,
                'day_of_week'   => $dayOfWeek,
                'type'          => $type,
                'count'         => count($suggestions),
            ],
        ]);
    }

    /**
     * Einzelne Aktivität laden
     */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get(string $id): JsonResponse
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

        $data = $this->serializeActivity($activity, true);
        $draftMat = $this->activityAccess->canUserEditDraftActivityMaterial($currentUser, $activity);
        $data['can_edit_draft_material'] = $draftMat;
        $data['can_edit_activity_material'] = $activity->isDraft()
            ? $draftMat
            : $this->activityAccess->canHostMwOrDcEditActivityMaterialAfterDraft($currentUser, $activity);
        $data['can_edit_submitted_activity_content'] = $activity->isDraft()
            ? false
            : $this->activityAccess->canUserEditSubmittedActivityDetails($currentUser, $activity);

        return new JsonResponse($data);
    }

    /**
     * Neue Aktivität erstellen
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['department_id']) || !isset($data['name'])) {
            return new JsonResponse(['error' => 'department_id und name sind erforderlich'], 400);
        }

        // Department prüfen
        $department = $this->entityManager->getRepository(Department::class)
            ->find($data['department_id']);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $currentUser->getId(), 'departmentId' => $data['department_id']]);
        if (!$membership) {
            return new JsonResponse(['error' => 'Keine Berechtigung fuer dieses Department'], 403);
        }

        try {
            $activity = new Activity();
            $activity->setId(IdGenerator::generate());
            $activity->setDepartment($department);
            $activity->setName($data['name']);
            $activity->setType($data['type'] ?? 'activity');
            $activity->setStatus($data['status'] ?? 'draft');
            if ($activity->getStatus() === Activity::STATUS_SUBMITTED) {
                $activity->applyStatusTimestamp(Activity::STATUS_SUBMITTED);
            }

            // Gruppe (Group-Entity)
            if (isset($data['group_id'])) {
                $group = $this->entityManager->getRepository(Group::class)->find($data['group_id']);
                if ($group) {
                    $activity->setGroup($group);
                }
            }

            // Optionale Felder
            if (isset($data['color'])) {
                $activity->setColor($data['color']);
            }

            if (isset($data['usage_start'])) {
                $activity->setUsageStart(new \DateTime($data['usage_start']));
            }
            if (isset($data['usage_end'])) {
                $activity->setUsageEnd(new \DateTime($data['usage_end']));
            }
            if (isset($data['planning_start'])) {
                $activity->setPlanningStart(new \DateTime($data['planning_start']));
            }
            if (isset($data['planning_end'])) {
                $activity->setPlanningEnd(new \DateTime($data['planning_end']));
            }

            // Adresse (Mieter / Kunde)
            if (isset($data['address_id'])) {
                $address = $this->entityManager->getRepository(Address::class)->find($data['address_id']);
                if ($address) {
                    $activity->setAddress($address);
                }
            }

            // Eventstandort
            if (isset($data['venue_address_id'])) {
                $venueAddress = $this->entityManager->getRepository(Address::class)->find($data['venue_address_id']);
                if ($venueAddress) {
                    $activity->setVenueAddress($venueAddress);
                }
            }

            // Preise
            if (isset($data['pricing_mode'])) {
                $activity->setPricingMode($data['pricing_mode']);
            }
            if (isset($data['total_price'])) {
                $activity->setTotalPrice($data['total_price']);
            }
            if (isset($data['deposit_amount'])) {
                $activity->setDepositAmount($data['deposit_amount']);
            }

            if (isset($data['notes'])) {
                $activity->setNotes($data['notes']);
            }
            if (array_key_exists('create_wizard_completed', $data)) {
                $activity->setCreateWizardCompleted((bool) $data['create_wizard_completed']);
            }
            if (array_key_exists('invited_departments', $data)) {
                $incoming = is_array($data['invited_departments']) ? $data['invited_departments'] : [];
                $activity->setInvitedDepartments($this->normalizeInvitedDepartmentsPayload($incoming));
            }

            // Ersteller setzen
            if ($currentUser instanceof User) {
                $activity->setCreatedByUser($currentUser);
                // Ersteller ist auch gleich verantwortlich, wenn nicht anders angegeben
                if (!isset($data['responsible_user_id'])) {
                    $activity->setResponsibleUser($currentUser);
                }
            }

            if (isset($data['responsible_user_id'])) {
                $responsibleUser = $this->entityManager->getRepository(User::class)->find($data['responsible_user_id']);
                if ($responsibleUser) {
                    $activity->setResponsibleUser($responsibleUser);
                }
            }

            // Laufende Nummer generieren
            $maxNo = $this->entityManager->createQueryBuilder()
                ->select('MAX(a.no)')
                ->from(Activity::class, 'a')
                ->where('a.departmentId = :departmentId')
                ->setParameter('departmentId', $data['department_id'])
                ->getQuery()
                ->getSingleScalarResult();
            $activity->setNo(($maxNo ?? 0) + 1);

            $this->entityManager->persist($activity);
            $this->entityManager->flush();

            // History-Eintrag: Erstellung
            $this->createHistoryEntry($activity, 'created');
            $this->entityManager->flush();

            return new JsonResponse($this->serializeActivity($activity), 201);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler beim Erstellen: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Aktivität aktualisieren
     */
    #[Route('/{id}', name: 'update', methods: ['PATCH', 'PUT'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $id, Request $request): JsonResponse
    {
        $activity = $this->entityManager->getRepository(Activity::class)->find($id);

        if (!$activity || $activity->isDeleted()) {
            return new JsonResponse(['error' => 'Aktivität nicht gefunden'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if ($activity->isDraft()) {
            if (!$this->activityAccess->canUserEditActivity($currentUser, $activity)) {
                return new JsonResponse(['error' => 'Keine Berechtigung fuer diese Aktivitaet'], 403);
            }
        } elseif (!$this->activityAccess->canUserEditSubmittedActivityDetails($currentUser, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung fuer diese Aktivitaet'], 403);
        }

        $data = json_decode($request->getContent(), true);

        try {
            // Alten Zustand für History merken
            $oldSnapshot = $this->buildSnapshot($activity);

            if (isset($data['name'])) {
                $activity->setName($data['name']);
            }
            if (isset($data['type'])) {
                $activity->setType($data['type']);
            }
            if (array_key_exists('group_id', $data)) {
                if ($data['group_id']) {
                    $group = $this->entityManager->getRepository(Group::class)->find($data['group_id']);
                    $activity->setGroup($group);
                } else {
                    $activity->setGroup(null);
                    $activity->setGroupId(null);
                }
            }
            if (isset($data['status'])) {
                $activity->setStatus($data['status']);
            }
            if (isset($data['color'])) {
                $activity->setColor($data['color']);
            }
            if (array_key_exists('usage_start', $data)) {
                $activity->setUsageStart($data['usage_start'] ? new \DateTime($data['usage_start']) : null);
            }
            if (array_key_exists('usage_end', $data)) {
                $activity->setUsageEnd($data['usage_end'] ? new \DateTime($data['usage_end']) : null);
            }
            if (array_key_exists('planning_start', $data)) {
                $activity->setPlanningStart($data['planning_start'] ? new \DateTime($data['planning_start']) : null);
            }
            if (array_key_exists('planning_end', $data)) {
                $activity->setPlanningEnd($data['planning_end'] ? new \DateTime($data['planning_end']) : null);
            }
            if (array_key_exists('address_id', $data)) {
                if ($data['address_id']) {
                    $address = $this->entityManager->getRepository(Address::class)->find($data['address_id']);
                    $activity->setAddress($address);
                } else {
                    $activity->setAddress(null);
                    $activity->setAddressId(null);
                }
            }
            if (array_key_exists('venue_address_id', $data)) {
                if ($data['venue_address_id']) {
                    $venueAddress = $this->entityManager->getRepository(Address::class)->find($data['venue_address_id']);
                    $activity->setVenueAddress($venueAddress);
                } else {
                    $activity->setVenueAddress(null);
                    $activity->setVenueAddressId(null);
                }
            }
            if (array_key_exists('responsible_user_id', $data)) {
                if ($data['responsible_user_id']) {
                    $user = $this->entityManager->getRepository(User::class)->find($data['responsible_user_id']);
                    $activity->setResponsibleUser($user);
                } else {
                    $activity->setResponsibleUser(null);
                    $activity->setResponsibleUserId(null);
                }
            }
            if (array_key_exists('pricing_mode', $data)) {
                $activity->setPricingMode($data['pricing_mode']);
            }
            if (array_key_exists('total_price', $data)) {
                $activity->setTotalPrice($data['total_price']);
            }
            if (array_key_exists('deposit_amount', $data)) {
                $activity->setDepositAmount($data['deposit_amount']);
            }
            if (isset($data['deposit_paid'])) {
                $activity->setDepositPaid((bool)$data['deposit_paid']);
            }
            if (isset($data['is_paid'])) {
                $activity->setIsPaid((bool)$data['is_paid']);
            }
            if (array_key_exists('notes', $data)) {
                $activity->setNotes($data['notes']);
            }
            if (array_key_exists('create_wizard_completed', $data)) {
                $activity->setCreateWizardCompleted((bool) $data['create_wizard_completed']);
            }
            if (array_key_exists('invited_departments', $data)) {
                $incoming = is_array($data['invited_departments']) ? $data['invited_departments'] : [];
                $existingInvites = $activity->getInvitedDepartments() ?? [];
                $activity->setInvitedDepartments($this->normalizeInvitedDepartmentsPayload($incoming, $existingInvites));
            }

            $activity->setUpdatedAt(new \DateTime());

            // History-Eintrag: Änderungen berechnen und speichern
            $newSnapshot = $this->buildSnapshot($activity);
            $changes = $this->computeChanges($oldSnapshot, $newSnapshot);
            if (!empty($changes)) {
                $this->createHistoryEntry($activity, 'updated', $changes);
            }

            $this->entityManager->flush();

            return new JsonResponse($this->serializeActivity($activity));
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler beim Aktualisieren: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/department-invites/pending', name: 'department_invites_pending', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listPendingDepartmentInvites(Request $request): JsonResponse
    {
        $departmentId = trim((string) $request->query->get('department_id', ''));
        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        try {
            $this->assertDepartmentManager($currentUser, $departmentId);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $activities = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Activity::class, 'a')
            ->where('a.deletedAt IS NULL')
            ->andWhere('a.type IN (:types)')
            ->andWhere('a.status != :cancelled')
            ->andWhere('a.departmentId != :departmentId')
            ->andWhere('a.invitedDepartments IS NOT NULL')
            ->setParameter('types', ['camp', 'event'])
            ->setParameter('cancelled', Activity::STATUS_CANCELLED)
            ->setParameter('departmentId', $departmentId)
            ->orderBy('a.updatedAt', 'DESC')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();

        $pending = [];
        foreach ($activities as $activity) {
            $invites = $activity->getInvitedDepartments() ?? [];
            foreach ($invites as $invite) {
                if (!is_array($invite)) {
                    continue;
                }
                if (($invite['id'] ?? null) !== $departmentId) {
                    continue;
                }
                if (($invite['status'] ?? 'pending') !== 'pending') {
                    continue;
                }
                $pending[] = [
                    'activity_id' => $activity->getId(),
                    'activity_name' => $activity->getName(),
                    'activity_type' => $activity->getType(),
                    'usage_start' => $activity->getUsageStart()?->format(\DateTimeInterface::ATOM),
                    'usage_end' => $activity->getUsageEnd()?->format(\DateTimeInterface::ATOM),
                    'source_department_id' => $activity->getDepartmentId(),
                    'source_department_name' => $activity->getDepartment()->getName(),
                    'invited_at' => $invite['invited_at'] ?? null,
                ];
                break;
            }
        }

        return new JsonResponse([
            'count' => count($pending),
            'items' => $pending,
        ]);
    }

    #[Route('/{id}/department-invites/decision', name: 'department_invites_decision', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function decideDepartmentInvite(string $id, Request $request): JsonResponse
    {
        $activity = $this->entityManager->getRepository(Activity::class)->find($id);
        if (!$activity || $activity->isDeleted()) {
            return new JsonResponse(['error' => 'Aktivität nicht gefunden'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $departmentId = trim((string) ($data['department_id'] ?? ''));
        $decision = trim((string) ($data['decision'] ?? ''));
        if ($departmentId === '' || !in_array($decision, ['accepted', 'rejected'], true)) {
            return new JsonResponse(['error' => 'department_id und decision (accepted|rejected) sind erforderlich'], 400);
        }

        try {
            $this->assertDepartmentManager($currentUser, $departmentId);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $invites = $activity->getInvitedDepartments() ?? [];
        $updated = false;
        foreach ($invites as &$invite) {
            if (!is_array($invite)) {
                continue;
            }
            if (($invite['id'] ?? null) !== $departmentId) {
                continue;
            }
            $invite['status'] = $decision;
            $invite['decided_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
            $invite['decided_by_user_id'] = $currentUser->getId();
            $updated = true;
            break;
        }
        unset($invite);

        if (!$updated) {
            return new JsonResponse(['error' => 'Keine passende Einladung gefunden'], 404);
        }

        $activity->setInvitedDepartments($invites);
        $activity->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'activity_id' => $activity->getId(),
            'department_id' => $departmentId,
            'decision' => $decision,
        ]);
    }

    /**
     * Aktivität löschen (Soft Delete)
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $id): JsonResponse
    {
        $activity = $this->entityManager->getRepository(Activity::class)->find($id);

        if (!$activity || $activity->isDeleted()) {
            return new JsonResponse(['error' => 'Aktivität nicht gefunden'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->activityAccess->canUserAccessActivity($currentUser, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung fuer diese Aktivitaet'], 403);
        }

        $activity->setDeletedAt(new \DateTime());
        $activity->setUpdatedAt(new \DateTime());

        // History-Eintrag: Löschung
        $this->createHistoryEntry($activity, 'deleted');
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Aktivität gelöscht']);
    }

    /**
     * Status einer Aktivität ändern (mit Transitions-Logik + Berechtigungs-Check)
     * 
     * Body: { "status": "submitted", "comment": "optional" }
     */
    #[Route('/{id}/status', name: 'change_status', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function changeStatus(string $id, Request $request): JsonResponse
    {
        $activity = $this->entityManager->getRepository(Activity::class)->find($id);

        if (!$activity || $activity->isDeleted()) {
            return new JsonResponse(['error' => 'Aktivität nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;
        $comment = $data['comment'] ?? null;

        // 1. Gültiger Status?
        if (!$newStatus || !in_array($newStatus, Activity::ALL_STATUSES)) {
            return new JsonResponse([
                'error' => 'Ungültiger Status. Erlaubt: ' . implode(', ', Activity::ALL_STATUSES)
            ], 400);
        }

        $oldStatus = $activity->getStatus();

        // 2. Transition erlaubt?
        if (!$activity->canTransitionTo($newStatus)) {
            $allowed = Activity::STATUS_TRANSITIONS[$oldStatus] ?? [];
            return new JsonResponse([
                'error' => sprintf(
                    'Übergang von "%s" nach "%s" ist nicht erlaubt. Erlaubte Übergänge: %s',
                    $oldStatus,
                    $newStatus,
                    implode(', ', $allowed) ?: 'keine'
                )
            ], 422);
        }

        // 3. Berechtigung prüfen
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $permissionCheck = $this->checkTransitionPermission($user, $activity, $oldStatus, $newStatus);
        if ($permissionCheck !== true) {
            return new JsonResponse(['error' => $permissionCheck], 403);
        }

        // 3b. Abschluss-Blocker prüfen:
        // Aktivität darf erst abgeschlossen werden, wenn offene Issues/Tickets geklärt sind.
        if ($newStatus === Activity::STATUS_COMPLETED) {
            $blockers = $this->getCompletionBlockers($activity);
            if (($blockers['open_workshop_tickets_count'] ?? 0) > 0 || ($blockers['open_issue_reports_count'] ?? 0) > 0) {
                return new JsonResponse([
                    'error' => 'Aktivität kann nicht abgeschlossen werden: offene Werkstatt-/Verlustfälle vorhanden.',
                    'code' => 'activity_completion_blocked',
                    'blockers' => $blockers,
                ], 422);
            }
        }

        // 4. Status setzen + Timestamp
        $activity->setStatus($newStatus);
        $activity->setUpdatedAt(new \DateTime());
        $activity->applyStatusTimestamp($newStatus);

        // 5. Spezialfall: Zurückweisung (approved → submitted) mit Kommentar
        if ($oldStatus === Activity::STATUS_APPROVED && $newStatus === Activity::STATUS_SUBMITTED) {
            $activity->setRejectionComment($comment);
        }

        // 6. Auto-Initialisierungen bei bestimmten Übergängen
        if ($newStatus === Activity::STATUS_PACKING) {
            $this->autoInitPackList($activity);
        }
        if ($newStatus === Activity::STATUS_RETURNED) {
            $this->autoInitReturnList($activity);
        }
        if ($newStatus === Activity::STATUS_COMPLETED) {
            $this->applyStockAdjustments($activity);
        }

        // 7. History-Eintrag
        $changes = [
            'status' => ['old' => $oldStatus, 'new' => $newStatus],
        ];
        if ($comment) {
            $changes['comment'] = $comment;
        }
        $this->createHistoryEntry($activity, 'status_changed', $changes);

        $this->entityManager->flush();

        return new JsonResponse($this->serializeActivity($activity));
    }

    /**
     * Ermittelt Blocker für den Abschluss einer Aktivität.
     *
     * Blockiert wird bei:
     * - offenen Werkstatt-Tickets zur Aktivität
     * - offenen IssueReports (loss/repair/damage) zur Aktivität
     */
    private function getCompletionBlockers(Activity $activity): array
    {
        $activityId = $activity->getId();

        $openWorkshopTickets = $this->entityManager->getRepository(WorkshopTicket::class)
            ->createQueryBuilder('t')
            ->where('t.activityId = :activityId')
            ->andWhere('t.status IN (:openStatuses)')
            ->setParameter('activityId', $activityId)
            ->setParameter('openStatuses', [
                WorkshopTicket::STATUS_OPEN,
                WorkshopTicket::STATUS_IN_PROGRESS,
                WorkshopTicket::STATUS_WAITING_PARTS,
            ])
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        $openIssueReports = $this->entityManager->getRepository(ActivityIssueReport::class)
            ->createQueryBuilder('ir')
            ->leftJoin('ir.materialItem', 'm')
            ->addSelect('m')
            ->where('ir.activityId = :activityId')
            ->andWhere('ir.resolved = false')
            ->andWhere('ir.type IN (:blockingTypes)')
            ->setParameter('activityId', $activityId)
            ->setParameter('blockingTypes', [
                ActivityIssueReport::TYPE_LOSS,
                ActivityIssueReport::TYPE_REPAIR,
                ActivityIssueReport::TYPE_DAMAGE,
            ])
            ->orderBy('ir.reportedAt', 'DESC')
            ->setMaxResults(12)
            ->getQuery()
            ->getResult();

        return [
            'open_workshop_tickets_count' => count($openWorkshopTickets),
            'open_issue_reports_count' => count($openIssueReports),
            'open_workshop_tickets' => array_map(static fn(WorkshopTicket $t) => [
                'id' => $t->getId(),
                'title' => $t->getTitle(),
                'status' => $t->getStatus(),
                'type' => $t->getType(),
            ], $openWorkshopTickets),
            'open_issue_reports' => array_map(static fn(ActivityIssueReport $ir) => [
                'id' => $ir->getId(),
                'type' => $ir->getType(),
                'quantity' => $ir->getQuantity(),
                'material_name' => $ir->getMaterialItem()?->getName(),
                'reported_at' => $ir->getReportedAt()->format('c'),
            ], $openIssueReports),
        ];
    }

    /**
     * Gibt die erlaubten nächsten Status-Übergänge für eine Aktivität zurück
     * (inkl. Berechtigungs-Check für den aktuellen User)
     */
    #[Route('/{id}/transitions', name: 'transitions', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getTransitions(string $id): JsonResponse
    {
        $activity = $this->entityManager->getRepository(Activity::class)->find($id);

        if (!$activity || $activity->isDeleted()) {
            return new JsonResponse(['error' => 'Aktivität nicht gefunden'], 404);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $currentStatus = $activity->getStatus();
        $possibleTransitions = Activity::STATUS_TRANSITIONS[$currentStatus] ?? [];

        $transitions = [];
        foreach ($possibleTransitions as $targetStatus) {
            $allowed = $this->checkTransitionPermission($user, $activity, $currentStatus, $targetStatus);
            $transitions[] = [
                'status' => $targetStatus,
                'label' => $this->getTransitionActionLabel($currentStatus, $targetStatus),
                'allowed' => $allowed === true,
                'reason' => $allowed === true ? null : $allowed,
            ];
        }

        return new JsonResponse([
            'current_status' => $currentStatus,
            'current_label' => $this->getStatusLabel($currentStatus),
            'transitions' => $transitions,
        ]);
    }

    /**
     * Prüft ob der User die Berechtigung für einen Status-Übergang hat
     * 
     * @return true|string true wenn erlaubt, sonst Fehlermeldung
     */
    private function checkTransitionPermission(User $user, Activity $activity, string $fromStatus, string $toStatus): true|string
    {
        $requiredRoles = Activity::getTransitionPermissions($fromStatus, $toStatus);
        if (empty($requiredRoles)) {
            return 'Keine Berechtigung für diesen Übergang definiert';
        }

        $userId = $user->getId();
        $departmentId = $activity->getDepartmentId();
        $groupId = $activity->getGroupId();

        // Department-Rolle des Users prüfen
        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $userId, 'departmentId' => $departmentId]);
        $departmentRole = $membership?->getRole();

        // sa und org haben immer Zugriff wenn in requiredRoles
        if ($departmentRole && in_array($departmentRole, $requiredRoles)) {
            return true;
        }

        // Materialwart-Check (Department-Rolle 'mw')
        if (in_array('mw', $requiredRoles) && $departmentRole === 'mw') {
            return true;
        }

        // Ersteller der Aktivität (nur wenn in TRANSITION_PERMISSIONS vorgesehen, z. B. submitted→approved)
        if (in_array('creator', $requiredRoles, true) && $activity->getCreatedByUserId() === $userId) {
            return true;
        }

        // Gruppen-Rollen prüfen (leader/member)
        if ($groupId) {
            $groupMembership = $this->entityManager->getRepository(GroupMembership::class)
                ->findOneBy(['userId' => $userId, 'groupId' => $groupId]);

            if ($groupMembership) {
                $groupRole = $groupMembership->getRole();
                if (in_array($groupRole, $requiredRoles)) {
                    return true;
                }
            }
        }

        // Eingeladene Departments: Gruppenrolle (leader/member) in der pro Einladung festgelegten Gruppe
        foreach ($activity->getInvitedDepartments() ?? [] as $inv) {
            if (!is_array($inv) || ($inv['status'] ?? '') !== 'accepted') {
                continue;
            }
            $inviteDeptId = trim((string) ($inv['id'] ?? $inv['department_id'] ?? ''));
            $inviteGroupId = trim((string) ($inv['group_id'] ?? ''));
            if ($inviteDeptId === '' || $inviteGroupId === '') {
                continue;
            }
            $deptMem = $this->entityManager->getRepository(Membership::class)
                ->findOneBy(['userId' => $userId, 'departmentId' => $inviteDeptId]);
            if (!$deptMem) {
                continue;
            }
            $gMem = $this->entityManager->getRepository(GroupMembership::class)
                ->findOneBy(['userId' => $userId, 'groupId' => $inviteGroupId]);
            if ($gMem) {
                $gr = $gMem->getRole();
                if (in_array($gr, $requiredRoles, true)) {
                    return true;
                }
            }
        }

        // Ersteller darf eigene Drafts bearbeiten (member-Berechtigung)
        if (in_array('member', $requiredRoles) && $activity->getCreatedByUserId() === $userId) {
            return true;
        }

        // Eingeladenes Department (angenommen): Department-Rolle im Gast-Department — inkl. MW/DC auch wenn sonst nur Gruppenleiter der Gast-Gruppe Zugriff hätten
        if ($this->activityAccess->isInvitedAcceptedMember($user, $activity)
            || $this->activityAccess->isInvitedDepartmentMwOrDc($user, $activity)) {
            $guestMemberships = $this->entityManager->getRepository(Membership::class)
                ->findBy(['userId' => $userId]);
            foreach ($guestMemberships as $gm) {
                if (!$this->activityAccess->isDepartmentInviteAccepted($activity, $gm->getDepartmentId())) {
                    continue;
                }
                $r = $gm->getRole();
                if ($r && in_array($r, $requiredRoles, true)) {
                    return true;
                }
            }
        }

        $roleLabels = array_map(fn($r) => match($r) {
            'leader' => 'Gruppenleiter',
            'member' => 'Gruppenmitglied',
            'mw' => 'Materialwart',
            'dc' => 'Abteilungskoordination',
            'creator' => 'Ersteller der Aktivität',
            'sa' => 'Super-Admin',
            'org' => 'Organisations-Admin',
            'sub' => 'Sub-Organisation',
            default => $r,
        }, $requiredRoles);

        return 'Berechtigung fehlt. Benötigt: ' . implode(' oder ', $roleLabels);
    }

    /**
     * Status-Label für die API-Response (Zustandsbeschreibung)
     */
    private function getStatusLabel(string $status): string
    {
        return match($status) {
            Activity::STATUS_DRAFT     => 'Entwurf',
            Activity::STATUS_SUBMITTED => 'Eingereicht',
            Activity::STATUS_APPROVED  => 'Bestätigt',
            Activity::STATUS_PACKING   => 'Wird gepackt',
            Activity::STATUS_PACKED    => 'Gepackt',
            Activity::STATUS_ISSUED    => 'Ausgegeben',
            Activity::STATUS_RETURNED  => 'Retour',
            Activity::STATUS_COMPLETED => 'Abgeschlossen',
            Activity::STATUS_CANCELLED => 'Storniert',
            default => $status,
        };
    }

    /**
     * Aktions-Label für Transition-Buttons (Verb/Imperativ)
     */
    private function getTransitionActionLabel(string $fromStatus, string $targetStatus): string
    {
        // Spezialfall: submitted → packing (Annehmen & direkt Packen)
        if ($fromStatus === Activity::STATUS_SUBMITTED && $targetStatus === Activity::STATUS_PACKING) {
            return 'Annehmen & Packen';
        }

        // Spezialfall: approved → submitted = Zurückweisung (nicht Einreichen)
        if ($fromStatus === Activity::STATUS_APPROVED && $targetStatus === Activity::STATUS_SUBMITTED) {
            return 'Zurückweisen';
        }

        // Korrektur nach «Gepackt» — nicht «Packen starten» (sonst wie erster Start)
        if ($fromStatus === Activity::STATUS_PACKED && $targetStatus === Activity::STATUS_PACKING) {
            return 'Zur Packliste (Korrektur)';
        }

        return match($targetStatus) {
            Activity::STATUS_SUBMITTED => 'Einreichen',
            Activity::STATUS_APPROVED  => 'Bestätigen',
            Activity::STATUS_PACKING   => 'Packen starten',
            Activity::STATUS_PACKED    => 'Gepackt markieren',
            Activity::STATUS_ISSUED    => 'Ausgeben',
            Activity::STATUS_RETURNED  => 'Retour erfassen',
            Activity::STATUS_COMPLETED => 'Abschliessen',
            Activity::STATUS_CANCELLED => 'Stornieren',
            default => $this->getStatusLabel($targetStatus),
        };
    }

    /**
     * Material-Items einer Aktivität abrufen
     */
    #[Route('/{id}/items', name: 'items_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listItems(string $id): JsonResponse
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

        if ($this->activityAccess->canHostMwOrDcEditActivityMaterialAfterDraft($currentUser, $activity)) {
            $this->kisteMaterialLinker->syncMissingActivityLinesFromPackContainers($activity, $currentUser);
        }

        $items = $this->entityManager->getRepository(ActivityItem::class)
            ->createQueryBuilder('ai')
            ->leftJoin('ai.materialItem', 'mi')
            ->addSelect('mi')
            ->leftJoin('mi.linkedContainerBatch', 'linkCb')
            ->addSelect('linkCb')
            ->where('ai.activityId = :activityId')
            ->setParameter('activityId', $id)
            ->orderBy('mi.name', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var array<string, true> Material-IDs mit mindestens einem Pack-Behälter-Batch (SN oder Kisten-Instanz) */
        $serializedPackInstanceByMaterialId = [];
        /** @var array<string, true> Material-IDs mit Pack-Behälter-Batch (is_container auf Charge) */
        $containerPackBatchByMaterialId = [];
        $packContainers = $this->entityManager->getRepository(ActivityPackContainer::class)
            ->createQueryBuilder('pc')
            ->leftJoin('pc.containerBatch', 'pcb')
            ->addSelect('pcb')
            ->where('pc.activityId = :activityId')
            ->setParameter('activityId', $id)
            ->getQuery()
            ->getResult();
        foreach ($packContainers as $pc) {
            if (!$pc instanceof ActivityPackContainer) {
                continue;
            }
            $batch = $pc->getContainerBatch();
            if ($batch === null) {
                continue;
            }
            $mid = $batch->getMaterialItemId();
            if ($batch->getIsContainer()) {
                $containerPackBatchByMaterialId[$mid] = true;
            }
            $sn = $batch->getSerialNumber();
            if ($sn !== null && trim((string) $sn) !== '') {
                $serializedPackInstanceByMaterialId[$mid] = true;
                continue;
            }
            if ($batch->getIsContainer()) {
                $serializedPackInstanceByMaterialId[$mid] = true;
            }
        }

        $result = [];
        foreach ($items as $item) {
            $mi = $item->getMaterialItem();
            $sourceDepartment = $mi->getDepartment();
            $linkCb = $mi->getLinkedContainerBatch();
            $linkedContainerLabel = null;
            if ($linkCb !== null) {
                $lb = $linkCb->getLabel();
                $sn = $linkCb->getSerialNumber();
                $linkedContainerLabel = ($lb !== null && $lb !== '') ? $lb : (($sn !== null && $sn !== '') ? $sn : null);
            }
            $rawTracking = $mi->getTrackingType();
            $trackingType = ($rawTracking === 'serialized')
                ? 'serialized'
                : (isset($serializedPackInstanceByMaterialId[$mi->getId()]) ? 'serialized' : ($rawTracking ?? 'bulk'));
            $isContainerPosition = $mi->getIsContainer()
                || isset($containerPackBatchByMaterialId[$mi->getId()])
                || ($linkCb !== null && $linkCb->getIsContainer())
                || ($mi->getMaterialType() === 'physical_combo' && $linkCb !== null);
            $result[] = [
                'id' => $item->getId(),
                'material_item_id' => $item->getMaterialItemId(),
                'material_name' => $mi->getName(),
                'material_type' => $mi->getMaterialType(),
                'tracking_type' => $trackingType,
                'is_container' => $isContainerPosition,
                'linked_container_label' => $linkedContainerLabel,
                'source_department_id' => $sourceDepartment->getId(),
                'source_department_name' => $sourceDepartment->getName(),
                'quantity' => $item->getQuantity(),
                'priority' => $item->getPriority(),
                'status' => $item->getStatus(),
                'notes' => $item->getNotes(),
                'unit_price' => $item->getUnitPrice(),
                'line_total' => $item->getLineTotal(),
                'price_type' => $item->getPriceType(),
                'is_consumable' => $item->getIsConsumable(),
                'sale_price' => $mi->getSalePrice(),
                'pack_size' => $mi->getPackSize(),
                'pack_unit' => $mi->getPackUnit(),
                'is_js_material' => $mi->getIsJsMaterial(),
                'external_source' => $mi->getExternalSource(),
            ];
        }

        return new JsonResponse($result);
    }

    /**
     * Material-Items einer Aktivität setzen (Batch: alle auf einmal ersetzen)
     * 
     * Body: { "items": [{ "material_item_id": "...", "quantity": 3, "priority": "normal" }, ...] }
     */
    #[Route('/{id}/items', name: 'items_sync', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function syncItems(string $id, Request $request): JsonResponse
    {
        $activity = $this->entityManager->getRepository(Activity::class)->find($id);
        if (!$activity || $activity->isDeleted()) {
            return new JsonResponse(['error' => 'Aktivität nicht gefunden'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        $deny = $this->assertCanModifyActivityMaterialItems($currentUser, $activity);
        if ($deny !== null) {
            return $deny;
        }

        $data = json_decode($request->getContent(), true);
        $items = $data['items'] ?? [];

        try {
            // Alle bestehenden Items löschen
            $existingItems = $this->entityManager->getRepository(ActivityItem::class)
                ->findBy(['activityId' => $id]);
            foreach ($existingItems as $existing) {
                $this->entityManager->remove($existing);
            }

            // Neue Items erstellen
            $count = 0;
            $totalPrice = '0.00';
            foreach ($items as $itemData) {
                if (empty($itemData['material_item_id'])) continue;

                $materialItem = $this->entityManager->getRepository(MaterialItem::class)
                    ->find($itemData['material_item_id']);
                if (!$materialItem) continue;

                $activityItem = new ActivityItem();
                $activityItem->setId(IdGenerator::generate13('ai'));
                $activityItem->setActivity($activity);
                $activityItem->setMaterialItem($materialItem);
                $activityItem->setQuantity(max(1, (int)($itemData['quantity'] ?? 1)));
                $activityItem->setPriority($itemData['priority'] ?? 'normal');
                $activityItem->setNotes($itemData['notes'] ?? null);

                // Verbrauchsmaterial + Preisfelder
                $activityItem->setIsConsumable($materialItem->getIsConsumable());
                if (isset($itemData['unit_price'])) {
                    $activityItem->setUnitPrice($itemData['unit_price']);
                }
                if (isset($itemData['line_total'])) {
                    $activityItem->setLineTotal($itemData['line_total']);
                }
                if (isset($itemData['price_type'])) {
                    $activityItem->setPriceType($itemData['price_type']);
                }

                // Total aufsummieren
                if ($activityItem->getLineTotal() !== null) {
                    $totalPrice = bcadd($totalPrice, $activityItem->getLineTotal(), 2);
                }

                $this->entityManager->persist($activityItem);
                $count++;
            }

            // Item-Count und total_price am Activity aktualisieren
            $activity->setItemCount($count);
            $activity->setTotalPrice($totalPrice !== '0.00' ? $totalPrice : null);
            $activity->setUpdatedAt(new \DateTime());

            $this->entityManager->flush();

            // Packliste an Materialliste anbinden (PUT ersetzt alle Zeilen — ohne dies fehlen neue Positionen)
            if (in_array($activity->getStatus(), [
                Activity::STATUS_PACKING,
                Activity::STATUS_PACKED,
                Activity::STATUS_ISSUED,
            ], true)) {
                $this->resyncPackListFromActivityItems($activity);
                $this->entityManager->flush();
            }

            return new JsonResponse([
                'message' => "$count Material-Positionen gespeichert",
                'item_count' => $count,
                'total_price' => $activity->getTotalPrice(),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Einzelnes Material zu einer Aktivität hinzufügen
     * 
     * Body: { "material_item_id": "...", "quantity": 2, "priority": "normal" }
     */
    #[Route('/{id}/items', name: 'items_add', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addItem(string $id, Request $request): JsonResponse
    {
        $activity = $this->entityManager->getRepository(Activity::class)->find($id);
        if (!$activity || $activity->isDeleted()) {
            return new JsonResponse(['error' => 'Aktivität nicht gefunden'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        $deny = $this->assertCanModifyActivityMaterialItems($currentUser, $activity);
        if ($deny !== null) {
            return $deny;
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data['material_item_id'])) {
            return new JsonResponse(['error' => 'material_item_id erforderlich'], 400);
        }

        $materialItem = $this->entityManager->getRepository(MaterialItem::class)
            ->find($data['material_item_id']);
        if (!$materialItem) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }

        try {
            // Prüfen ob Material schon in dieser Aktivität ist
            $existing = $this->entityManager->getRepository(ActivityItem::class)
                ->findOneBy(['activityId' => $id, 'materialItemId' => $data['material_item_id']]);

            if ($existing) {
                // Menge erhöhen
                $existing->setQuantity($existing->getQuantity() + max(1, (int)($data['quantity'] ?? 1)));
                $existing->setUpdatedAt(new \DateTime());
                // Preis-Felder aktualisieren
                if (isset($data['unit_price'])) $existing->setUnitPrice($data['unit_price']);
                if (isset($data['line_total'])) $existing->setLineTotal($data['line_total']);
                if (isset($data['price_type'])) $existing->setPriceType($data['price_type']);
            } else {
                // Neues Item
                $activityItem = new ActivityItem();
                $activityItem->setId(IdGenerator::generate13('ai'));
                $activityItem->setActivity($activity);
                $activityItem->setMaterialItem($materialItem);
                $activityItem->setQuantity(max(1, (int)($data['quantity'] ?? 1)));
                $activityItem->setPriority($data['priority'] ?? 'normal');
                $activityItem->setNotes($data['notes'] ?? null);
                // Verbrauchsmaterial + Preisfelder
                $activityItem->setIsConsumable($materialItem->getIsConsumable());
                if (isset($data['unit_price'])) $activityItem->setUnitPrice($data['unit_price']);
                if (isset($data['line_total'])) $activityItem->setLineTotal($data['line_total']);
                if (isset($data['price_type'])) $activityItem->setPriceType($data['price_type']);
                $this->entityManager->persist($activityItem);
            }

            // Item-Count aktualisieren
            $itemCount = $this->entityManager->getRepository(ActivityItem::class)
                ->count(['activityId' => $id]);
            $activity->setItemCount($existing ? $itemCount : $itemCount + 1);

            // total_price neu berechnen
            $this->recalculateTotalPrice($activity);
            $activity->setUpdatedAt(new \DateTime());

            // Wenn Packliste existiert (Status packing+), PackItem synchronisieren
            if (in_array($activity->getStatus(), [
                Activity::STATUS_PACKING, Activity::STATUS_PACKED, Activity::STATUS_ISSUED
            ])) {
                $this->syncPackItemForMaterial($activity, $materialItem, $existing
                    ? $existing->getQuantity()
                    : max(1, (int)($data['quantity'] ?? 1))
                );
            }

            $this->entityManager->flush();

            return new JsonResponse(['message' => 'Material hinzugefügt', 'total_price' => $activity->getTotalPrice()], 201);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Einzelnes Material-Item aus Aktivität entfernen
     */
    #[Route('/{id}/items/{itemId}', name: 'items_remove', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function removeItem(string $id, string $itemId): JsonResponse
    {
        $item = $this->entityManager->getRepository(ActivityItem::class)->find($itemId);
        if (!$item || $item->getActivityId() !== $id) {
            return new JsonResponse(['error' => 'Item nicht gefunden'], 404);
        }

        $activity = $item->getActivity();
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        $deny = $this->assertCanModifyActivityMaterialItems($currentUser, $activity);
        if ($deny !== null) {
            return $deny;
        }

        $this->entityManager->remove($item);

        // Item-Count aktualisieren
        $activity->setItemCount(max(0, $activity->getItemCount() - 1));
        $activity->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        // total_price neu berechnen (nach flush, damit removed item nicht mehr gezählt wird)
        $this->recalculateTotalPrice($activity);

        if (in_array($activity->getStatus(), [
            Activity::STATUS_PACKING,
            Activity::STATUS_PACKED,
            Activity::STATUS_ISSUED,
        ], true)) {
            $this->resyncPackListFromActivityItems($activity);
        }

        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Material entfernt', 'total_price' => $activity->getTotalPrice()]);
    }

    /**
     * History Log für eine Aktivität abrufen
     */
    #[Route('/{id}/history', name: 'history', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function history(string $id): JsonResponse
    {
        $activity = $this->entityManager->getRepository(Activity::class)->find($id);

        if (!$activity) {
            return new JsonResponse(['error' => 'Aktivität nicht gefunden'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->activityAccess->canUserViewActivity($currentUser, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung fuer diese Aktivitaet'], 403);
        }

        $entries = $this->entityManager->getRepository(ActivityHistory::class)
            ->createQueryBuilder('h')
            ->leftJoin('h.user', 'u')
            ->leftJoin('u.profile', 'p')
            ->addSelect('u', 'p')
            ->where('h.activityId = :activityId')
            ->setParameter('activityId', $id)
            ->orderBy('h.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($entries as $entry) {
            $user = $entry->getUser();
            $profile = $user?->getProfile();

            $result[] = [
                'id' => $entry->getId(),
                'action' => $entry->getAction(),
                'snapshot' => $entry->getSnapshot(),
                'changes' => $entry->getChanges(),
                'created_at' => $entry->getCreatedAt()->format('c'),
                'user' => $user ? [
                    'id' => $user->getId(),
                    'name' => $profile ? trim($profile->getFirstName() . ' ' . $profile->getLastName()) : 'Unbekannt',
                ] : null,
            ];
        }

        return new JsonResponse($result);
    }

    /**
     * Erstellt einen History-Eintrag für eine Aktivität
     */
    private function createHistoryEntry(Activity $activity, string $action, array $changes = []): void
    {
        $history = new ActivityHistory();
        $history->setId(IdGenerator::generate13('ah'));
        $history->setActivity($activity);
        $history->setAction($action);
        $history->setSnapshot($this->buildSnapshot($activity));
        $history->setChanges($changes);

        // User aus Security-Context
        $user = $this->getUser();
        if ($user instanceof User) {
            $history->setUser($user);
        }

        $this->entityManager->persist($history);
    }

    /**
     * Berechnet total_price aus allen ActivityItems und setzt es auf der Activity.
     * Bei pricing_mode = 'set_price' wird der manuell gesetzte Preis NICHT überschrieben.
     */
    /**
     * Synchronisiert das PackItem wenn Material während der Packphase hinzugefügt/geändert wird.
     * Erstellt ein neues PackItem oder aktualisiert die bestellte Menge.
     */
    /**
     * Packliste mit Materialpositionen abgleichen (Summe pro Material, Zeilen ohne Pack-Eintrag anlegen, entfernte Materialien löschen).
     */
    private function resyncPackListFromActivityItems(Activity $activity): void
    {
        $activityItems = $this->entityManager->getRepository(ActivityItem::class)
            ->findBy(['activityId' => $activity->getId()]);

        $qtyByMaterialId = [];
        foreach ($activityItems as $ai) {
            $mid = $ai->getMaterialItemId();
            $qtyByMaterialId[$mid] = ($qtyByMaterialId[$mid] ?? 0) + $ai->getQuantity();
        }

        foreach ($qtyByMaterialId as $mid => $qty) {
            $materialItem = $this->entityManager->getRepository(MaterialItem::class)->find($mid);
            if ($materialItem !== null) {
                $this->syncPackItemForMaterial($activity, $materialItem, $qty);
            }
        }

        $packItems = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findBy(['activityId' => $activity->getId()]);

        foreach ($packItems as $pi) {
            if (!isset($qtyByMaterialId[$pi->getMaterialItemId()])) {
                $this->entityManager->remove($pi);
            }
        }
    }

    private function syncPackItemForMaterial(Activity $activity, MaterialItem $materialItem, int $newQuantity): void
    {
        $existingPackItem = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findOneBy([
                'activityId' => $activity->getId(),
                'materialItemId' => $materialItem->getId(),
            ]);

        if ($existingPackItem) {
            // Bestellte Menge aktualisieren
            $existingPackItem->setQuantityOrdered($newQuantity);
            if ($existingPackItem->getQuantityPacked() > $newQuantity) {
                $existingPackItem->setQuantityPacked($newQuantity);
            }
            if ($existingPackItem->getQuantityIssued() > $newQuantity) {
                $existingPackItem->setQuantityIssued($newQuantity);
            }
            $existingPackItem->setUpdatedAt(new \DateTime());
        } else {
            // Neues PackItem erstellen
            $packItem = new ActivityPackItem();
            $packItem->setId(IdGenerator::generate13('pk'));
            $packItem->setActivity($activity);
            $packItem->setMaterialItem($materialItem);
            $packItem->setQuantityOrdered($newQuantity);
            $packItem->setQuantityPacked(0);
            $packItem->setConditionOut('ok');

            $user = $this->getUser();
            if ($user instanceof User) {
                $packItem->setPackedByUser($user);
            }

            $this->entityManager->persist($packItem);
        }
    }

    private function recalculateTotalPrice(Activity $activity): void
    {
        // Setpreis: manuell gesetzter Preis bleibt bestehen
        if ($activity->getPricingMode() === 'set_price') {
            return;
        }

        $items = $this->entityManager->getRepository(ActivityItem::class)
            ->findBy(['activityId' => $activity->getId()]);

        $total = '0.00';
        foreach ($items as $item) {
            if ($item->getLineTotal() !== null) {
                $total = bcadd($total, $item->getLineTotal(), 2);
            }
        }

        $activity->setTotalPrice($total !== '0.00' ? $total : null);
    }

    /**
     * Erstellt einen Snapshot des aktuellen Aktivitäts-Zustands
     */
    private function buildSnapshot(Activity $activity): array
    {
        return [
            'name' => $activity->getName(),
            'type' => $activity->getType(),
            'status' => $activity->getStatus(),
            'color' => $activity->getColor(),
            'group_id' => $activity->getGroupId(),
            'usage_start' => $activity->getUsageStart()?->format('Y-m-d'),
            'usage_end' => $activity->getUsageEnd()?->format('Y-m-d'),
            'planning_start' => $activity->getPlanningStart()?->format('Y-m-d'),
            'planning_end' => $activity->getPlanningEnd()?->format('Y-m-d'),
            'address_id' => $activity->getAddressId(),
            'venue_address_id' => $activity->getVenueAddressId(),
            'responsible_user_id' => $activity->getResponsibleUserId(),
            'pricing_mode' => $activity->getPricingMode(),
            'total_price' => $activity->getTotalPrice(),
            'deposit_amount' => $activity->getDepositAmount(),
            'notes' => $activity->getNotes(),
            'invited_departments' => $activity->getInvitedDepartments(),
            'rejection_comment' => $activity->getRejectionComment(),
        ];
    }

    /**
     * Berechnet die Änderungen zwischen dem alten und neuen Zustand
     */
    private function computeChanges(array $oldSnapshot, array $newSnapshot): array
    {
        $changes = [];
        foreach ($newSnapshot as $key => $newValue) {
            $oldValue = $oldSnapshot[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }
        return $changes;
    }

    /**
     * Entwurf: wie bisher (isMaterialEditable + breite Draft-Regeln).
     * Nach Einreichung: nur Host-MW/DC in den Status submitted…packed.
     */
    private function assertCanModifyActivityMaterialItems(User $user, Activity $activity): ?JsonResponse
    {
        if ($activity->isDraft()) {
            if (!$activity->isMaterialEditable()) {
                return new JsonResponse(['error' => 'Material kann nur im Entwurf bearbeitet werden'], 422);
            }
            if (!$this->activityAccess->canUserEditDraftActivityMaterial($user, $activity)) {
                return new JsonResponse(['error' => 'Keine Berechtigung zum Bearbeiten der Materialliste'], 403);
            }

            return null;
        }

        if (!$this->activityAccess->canHostMwOrDcEditActivityMaterialAfterDraft($user, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung zum Bearbeiten der Materialliste'], 403);
        }

        return null;
    }

    /**
     * Aktivität serialisieren
     */
    private function serializeActivity(Activity $activity, bool $detailed = false): array
    {
        // Gruppenname ggf. laden
        $groupName = null;
        if ($activity->getGroupId()) {
            try {
                $group = $activity->getGroup();
                $groupName = $group?->getName();
            } catch (\Exception $e) {
                // Lazy loading might fail, try explicit query
                $group = $this->entityManager->getRepository(Group::class)->find($activity->getGroupId());
                $groupName = $group?->getName();
            }
        }

        $data = [
            'id' => $activity->getId(),
            'department_id' => $activity->getDepartmentId(),
            'department_name' => $activity->getDepartment()->getName(),
            'group_id' => $activity->getGroupId(),
            'group_name' => $groupName,
            'created_by_user_id' => $activity->getCreatedByUserId(),
            'no' => $activity->getNo(),
            'name' => $activity->getName(),
            'color' => $activity->getColor(),
            'type' => $activity->getType(),
            'status' => $activity->getStatus(),
            'create_wizard_completed' => $activity->isCreateWizardCompleted(),
            'usage_start' => $activity->getUsageStart()?->format('c'),
            'usage_end' => $activity->getUsageEnd()?->format('c'),
            'item_count' => $activity->getItemCount(),
            'pricing_mode' => $activity->getPricingMode(),
            'total_price' => $activity->getTotalPrice() ? (float) $activity->getTotalPrice() : null,
            'invited_departments' => $this->enrichInvitedDepartmentsForApi($activity->getInvitedDepartments()),
            'created_at' => $activity->getCreatedAt()->format('c'),
            'updated_at' => $activity->getUpdatedAt()->format('c'),
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'planning_start' => $activity->getPlanningStart()?->format('c'),
                'planning_end' => $activity->getPlanningEnd()?->format('c'),
                'address_id' => $activity->getAddressId(),
                'venue_address_id' => $activity->getVenueAddressId(),
                'responsible_user_id' => $activity->getResponsibleUserId(),
                'created_by_user_id' => $activity->getCreatedByUserId(),
                'pricing_mode' => $activity->getPricingMode(),
                'deposit_amount' => $activity->getDepositAmount() ? (float) $activity->getDepositAmount() : null,
                'deposit_paid' => $activity->isDepositPaid(),
                'is_paid' => $activity->isPaid(),
                'notes' => $activity->getNotes(),
                'invited_departments' => $this->enrichInvitedDepartmentsForApi($activity->getInvitedDepartments()),
                'deleted_at' => $activity->getDeletedAt()?->format('c'),
                // Workflow-Timestamps
                'submitted_at' => $activity->getSubmittedAt()?->format('c'),
                'approved_at' => $activity->getApprovedAt()?->format('c'),
                'issued_at' => $activity->getIssuedAt()?->format('c'),
                'returned_at' => $activity->getReturnedAt()?->format('c'),
                'completed_at' => $activity->getCompletedAt()?->format('c'),
                'rejection_comment' => $activity->getRejectionComment(),
                // Workflow-Flags
                'is_material_editable' => $activity->isMaterialEditable(),
                'is_pack_list_editable' => $activity->isPackListEditable(),
                'can_report_issues' => $activity->canReportIssues(),
                'is_return_editable' => $activity->isReturnEditable(),
                'is_cancellable' => $activity->isCancellable(),
            ]);
        }

        return $data;
    }

    // ═══════════════════════════════════════════════
    // AUTO-INITIALISIERUNGEN BEI STATUS-ÜBERGÄNGEN
    // ═══════════════════════════════════════════════

    /**
     * Packliste automatisch aus den ActivityItems erstellen, wenn noch keine existiert
     */
    private function autoInitPackList(Activity $activity): void
    {
        $existingCount = $this->entityManager->getRepository(ActivityPackItem::class)
            ->count(['activityId' => $activity->getId()]);

        if ($existingCount > 0) return;

        $activityItems = $this->entityManager->getRepository(ActivityItem::class)
            ->findBy(['activityId' => $activity->getId()]);

        foreach ($activityItems as $ai) {
            $packItem = new ActivityPackItem();
            $packItem->setId(IdGenerator::generate13('pk'));
            $packItem->setActivity($activity);
            $packItem->setMaterialItem($ai->getMaterialItem());
            $packItem->setQuantityOrdered($ai->getQuantity());
            $packItem->setQuantityPacked(0);
            $packItem->setConditionOut('ok');

            $user = $this->getUser();
            if ($user instanceof User) {
                $packItem->setPackedByUser($user);
            }

            $this->entityManager->persist($packItem);
        }
    }

    /**
     * Rückgabeliste automatisch aus der Packliste erstellen
     */
    private function autoInitReturnList(Activity $activity): void
    {
        $existingCount = $this->entityManager->getRepository(ActivityReturnItem::class)
            ->count(['activityId' => $activity->getId()]);

        if ($existingCount > 0) return;

        $packItems = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findBy(['activityId' => $activity->getId()]);

        // Issue Reports berücksichtigen
        $issueReports = $this->entityManager->getRepository(ActivityIssueReport::class)
            ->findBy(['activityId' => $activity->getId()]);

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

        foreach ($packItems as $pi) {
            $returnItem = new ActivityReturnItem();
            $returnItem->setId(IdGenerator::generate13('ri'));
            $returnItem->setActivity($activity);
            $returnItem->setMaterialItem($pi->getMaterialItem());

            $mid = $pi->getMaterialItemId();
            $losses = ($reportedByMaterial[$mid]['loss'] ?? 0) + ($reportedByMaterial[$mid]['consumption'] ?? 0);
            $expectedReturn = max(0, $pi->getQuantityPacked() - $losses);

            $returnItem->setQuantityReturned($expectedReturn);
            $returnItem->setQuantityMissing($losses);
            $returnItem->setQuantityDamaged($reportedByMaterial[$mid]['damage'] ?? 0);

            $user = $this->getUser();
            if ($user instanceof User) {
                $returnItem->setReturnedByUser($user);
            }

            $this->entityManager->persist($returnItem);
        }
    }

    /**
     * Bestandsanpassung bei Abschluss:
     * Erstellt History-Einträge für Verluste/Beschädigungen.
     * Die tatsächliche Bestandsbuchung erfolgt über das Batch-System.
     * 
     * TODO: Integration mit MaterialBatch/MaterialStockLedger für
     * automatische Bestandsreduktion bei Verlust/Verbrauch.
     */
    private function applyStockAdjustments(Activity $activity): void
    {
        $returnItems = $this->entityManager->getRepository(ActivityReturnItem::class)
            ->findBy(['activityId' => $activity->getId()]);

        $adjustments = [];
        foreach ($returnItems as $ri) {
            $missingQty = $ri->getQuantityMissing();
            $damagedQty = $ri->getQuantityDamaged();

            if ($missingQty > 0 || $damagedQty > 0) {
                $adjustments[] = [
                    'material_item_id' => $ri->getMaterialItemId(),
                    'material_name' => $ri->getMaterialItem()->getName(),
                    'missing' => $missingQty,
                    'damaged' => $damagedQty,
                ];
            }
        }

        // Differenzen als History dokumentieren
        if (!empty($adjustments)) {
            $this->createHistoryEntry($activity, 'stock_adjustment', [
                'adjustments' => $adjustments,
                'total_missing' => array_sum(array_column($adjustments, 'missing')),
                'total_damaged' => array_sum(array_column($adjustments, 'damaged')),
            ]);
        }
    }

}
