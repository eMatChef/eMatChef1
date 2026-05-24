<?php

namespace App\Controller;

use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\Activity;
use App\Entity\ActivityHistory;
use App\Entity\ActivityItem;
use App\Entity\ActivityPackContainer;
use App\Entity\ActivityPackContainerItem;
use App\Entity\ActivityPackItem;
use App\Entity\ActivityReturnItem;
use App\Entity\ActivityIssueReport;
use App\Entity\Department;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\Membership;
use App\Entity\MaterialBatch;
use App\Entity\MaterialItem;
use App\Entity\WorkshopTicket;
use App\Entity\Address;
use App\Entity\User;
use App\Service\ActivityAccessService;
use App\Service\ActivityAccountingCostService;
use App\Service\ActivityItemPipelineStatusService;
use App\Service\ActivityKisteMaterialLinker;
use App\Service\ActivityMwNotificationService;
use App\Service\ActivityUserNotificationService;
use App\Service\InboxMessageService;
use App\Service\Public\PublicCodeService;
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
        private ActivityAccountingCostService $activityAccountingCost,
        private ActivityMwNotificationService $activityMwNotifications,
        private ActivityUserNotificationService $activityUserNotifications,
        private InboxMessageService $inboxMessageService,
        private PublicCodeService $publicCodeService,
        private ActivityItemPipelineStatusService $activityItemPipelineStatus,
    ) {}

    private function getActorUserId(): ?string
    {
        $user = $this->getUser();

        return $user instanceof User ? $user->getId() : null;
    }

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
                    Activity::STATUS_AT_EVENT,
                    Activity::STATUS_RETURNED,
                ]);
        } elseif ($tab === 'past') {
            $qb->andWhere('a.status = :completedStatus')
                ->setParameter('completedStatus', Activity::STATUS_COMPLETED);
        } elseif ($tab === 'cancelled') {
            $qb->andWhere('a.status = :cancelledStatus')
                ->setParameter('cancelledStatus', Activity::STATUS_CANCELLED);
        }

        // Sichtbarkeit: MW/DC/SA/Org sehen alles; User/Leader nur eigene/verantwortete oder Gruppen-Hierarchie.
        // Externe Ausleihen nur für MW/DC.
        $membershipRole = (string) $membership->getRole();
        $isRestrictedMember = $this->activityAccess->isRestrictedGroupMember($currentUser, (string) $departmentId);

        if ($isRestrictedMember) {
            $visibleGroupIds = $this->activityAccess->getExpandedVisibleGroupIds($currentUser, (string) $departmentId);
            $qb->setParameter('currentUserId', $currentUser->getId());

            if ($visibleGroupIds === []) {
                $qb->andWhere('a.createdByUserId = :currentUserId');
            } else {
                $expr = $qb->expr()->orX(
                    'a.createdByUserId = :currentUserId',
                    'a.groupId IN (:visibleGroupIds)'
                );
                $qb->setParameter('visibleGroupIds', $visibleGroupIds);
                $qb->andWhere($expr);
            }

            $qb->andWhere('a.type != :externalType')
                ->setParameter('externalType', 'external');
        } elseif (!$this->activityAccess->isDepartmentWideManager($membershipRole)) {
            $visibleGroupIds = $this->activityAccess->getExpandedVisibleGroupIds($currentUser, (string) $departmentId);

            $expr = $qb->expr()->orX(
                'a.createdByUserId = :currentUserId',
                'a.responsibleUserId = :currentUserId'
            );
            $qb->setParameter('currentUserId', $currentUser->getId());

            if (!empty($visibleGroupIds)) {
                $expr->add('a.groupId IN (:visibleGroupIds)');
                $qb->setParameter('visibleGroupIds', $visibleGroupIds);
            }

            $qb->andWhere($expr);
            $qb->andWhere('a.type != :externalType')
                ->setParameter('externalType', 'external');
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
                    Activity::STATUS_AT_EVENT,
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
        if (!$isRestrictedMember) {
            foreach ($invitedCandidates as $candidate) {
                if (!$this->activityAccess->isDepartmentInviteAccepted($candidate, (string) $departmentId)) {
                    continue;
                }
                if (!$this->activityAccess->isDepartmentWideManager($membershipRole)
                    && $candidate->isExternal()) {
                    continue;
                }
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
     * Benachrichtigungen für MW/DC: neue eingereichte Aktivitäten.
     * Route muss vor /{id} stehen, sonst wird „mw-notifications“ als Aktivitäts-ID interpretiert.
     */
    #[Route('/mw-notifications', name: 'mw_notifications_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listMwNotifications(Request $request): JsonResponse
    {
        $departmentId = trim((string) $request->query->get('department_id', ''));
        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id Parameter erforderlich'], 400);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        if (!$this->canAccessMwNotifications($user, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $bucket = strtolower(trim((string) $request->query->get('bucket', 'unread')));
        if (!in_array($bucket, ['unread', 'read', 'all'], true)) {
            $bucket = 'unread';
        }
        $limit = min(200, max(1, (int) $request->query->get('limit', 100)));

        $items = $this->activityMwNotifications->listInbox($departmentId, $bucket, $limit);
        $unreadCount = $this->activityMwNotifications->countUnread($departmentId);

        return new JsonResponse([
            'unread_count' => $unreadCount,
            'items' => $items,
        ]);
    }

    #[Route('/mw-notifications/{notificationId}/read', name: 'mw_notification_read', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function markMwNotificationRead(string $notificationId, Request $request): JsonResponse
    {
        $departmentId = trim((string) $request->query->get('department_id', ''));
        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id Parameter erforderlich'], 400);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        if (!$this->canAccessMwNotifications($user, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        if (!$this->activityMwNotifications->markRead($departmentId, $notificationId)) {
            return new JsonResponse(['error' => 'Benachrichtigung nicht gefunden'], 404);
        }

        return new JsonResponse(['ok' => true]);
    }

    private function canAccessMwNotifications(User $user, string $departmentId): bool
    {
        if (count(array_intersect(['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'], $user->getRoles())) > 0) {
            return true;
        }

        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $departmentId]);
        if (!$membership) {
            return false;
        }

        return in_array((string) ($membership->getRole() ?? ''), ['mw', 'dc'], true);
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

        $data = $this->serializeActivity($activity, true, $currentUser);
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
     * Erzeugt (Backfill) einen öffentlichen QR-Code für eine Aktivität, falls noch keiner vorhanden ist.
     */
    #[Route('/{id}/public-code', name: 'ensure_public_code', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function ensurePublicCode(string $id): JsonResponse
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
        if (!$this->canUserManageActivityPublicCode($currentUser, $activity)) {
            return new JsonResponse(['error' => 'Keine Berechtigung fuer oeffentliche QR-Codes'], 403);
        }

        $this->publicCodeService->ensureActivityPublicCode($activity, $this->getActorUserId());
        $this->entityManager->flush();

        return new JsonResponse($this->serializeActivity($activity, true, $currentUser));
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

        if ($this->activityAccess->isRestrictedGroupMember($currentUser, (string) $data['department_id'])) {
            $requestedType = (string) ($data['type'] ?? 'activity');
            if ($requestedType !== 'activity') {
                return new JsonResponse([
                    'error' => 'Als Gruppenmitglied dürfen Sie nur Aktivitäten vom Typ «Aktivität» anlegen.',
                ], 403);
            }
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
                    if ($this->activityAccess->isRestrictedGroupMember($currentUser, (string) $data['department_id'])) {
                        $visibleGroupIds = $this->activityAccess->getExpandedVisibleGroupIds(
                            $currentUser,
                            (string) $data['department_id']
                        );
                        if ($visibleGroupIds === [] || !in_array($group->getId(), $visibleGroupIds, true)) {
                            return new JsonResponse([
                                'error' => 'Die gewählte Gruppe gehört nicht zu Ihren Gruppen.',
                            ], 403);
                        }
                    }
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

            // Öffentlicher QR-Code sofort beim Anlegen (nicht erst manuell erzeugen)
            $this->publicCodeService->ensureActivityPublicCode($activity, $currentUser->getId());
            $this->entityManager->flush();

            if ($activity->getStatus() === Activity::STATUS_SUBMITTED && $currentUser instanceof User) {
                $this->activityMwNotifications->notifyActivitySubmitted($activity, $currentUser);
            }

            if (array_key_exists('invited_departments', $data)) {
                $this->inboxMessageService->syncActivityDepartmentInvites($activity);
            }

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

            if (array_key_exists('invited_departments', $data)) {
                $this->inboxMessageService->syncActivityDepartmentInvites($activity);
            }

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

        $pending = $this->inboxMessageService->listPendingActivityDepartmentInvites($departmentId);

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

        $this->inboxMessageService->removeActivityDepartmentInvite($activity->getId(), $departmentId);

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
        if (!$newStatus || !in_array($newStatus, Activity::ALL_STATUSES, true)) {
            return new JsonResponse([
                'error' => 'Ungültiger Status. Erlaubt: ' . implode(', ', Activity::ALL_STATUSES)
            ], 400);
        }

        $oldStatus = $activity->getStatus();

        // Quick-Modus (Typ «activity»): Einreichen überspringt «eingereicht», landet direkt bei «bestätigt»
        $isQuickAutoApprove = $newStatus === Activity::STATUS_SUBMITTED
            && $activity->getType() === 'activity'
            && $oldStatus === Activity::STATUS_DRAFT;
        if ($isQuickAutoApprove) {
            $newStatus = Activity::STATUS_APPROVED;
        }

        // 2. Transition erlaubt?
        if (!$isQuickAutoApprove && !$activity->canTransitionTo($newStatus)) {
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

        $permissionCheck = $this->checkTransitionPermission(
            $user,
            $activity,
            $oldStatus,
            $isQuickAutoApprove ? Activity::STATUS_SUBMITTED : $newStatus,
        );
        if ($permissionCheck !== true) {
            return new JsonResponse(['error' => $permissionCheck], 403);
        }

        // 3b. Abschluss-Blocker prüfen:
        // Aktivität darf erst abgeschlossen werden, wenn offene Issues/Tickets geklärt sind.
        if ($newStatus === Activity::STATUS_COMPLETED) {
            $blockers = $this->getCompletionBlockers($activity);
            if ($this->hasCompletionBlockers($blockers)) {
                return new JsonResponse([
                    'error' => $this->completionBlockerMessage($blockers),
                    'code' => 'activity_completion_blocked',
                    'blockers' => $blockers,
                ], 422);
            }
        }

        // 4. Status setzen + Timestamp
        if ($isQuickAutoApprove) {
            $activity->setSubmittedAt(new \DateTime());
        }
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
        if ($newStatus === Activity::STATUS_CANCELLED) {
            $this->resetPackPipelineOnCancel($activity, $oldStatus);
        }

        if (in_array($newStatus, [
            Activity::STATUS_PACKING,
            Activity::STATUS_PACKED,
            Activity::STATUS_AT_EVENT,
            Activity::STATUS_RETURNED,
            Activity::STATUS_CANCELLED,
        ], true)) {
            $this->activityItemPipelineStatus->syncForActivity($activity);
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

        if ($newStatus === Activity::STATUS_COMPLETED && $oldStatus !== Activity::STATUS_COMPLETED) {
            // Fallback: Verbrauch, der nie eingelagert wurde (vollständig verbraucht)
            $this->activityAccountingCost->finalizeConsumptionAccountingForActivity($activity);
        }

        if ($isQuickAutoApprove) {
            $this->activityMwNotifications->notifyActivitySubmitted($activity, $user);
        } elseif (
            $newStatus === Activity::STATUS_SUBMITTED
            && $oldStatus !== Activity::STATUS_SUBMITTED
        ) {
            $this->activityMwNotifications->notifyActivitySubmitted($activity, $user);
        }

        if (
            $newStatus === Activity::STATUS_APPROVED
            && $oldStatus !== Activity::STATUS_APPROVED
            && !$isQuickAutoApprove
        ) {
            $this->activityUserNotifications->notifyStatus($activity, $user, 'activity_approved');
        }

        if ($newStatus === Activity::STATUS_PACKED && $oldStatus !== Activity::STATUS_PACKED) {
            $this->activityUserNotifications->notifyPacked($activity, $user);
        }

        if ($newStatus === Activity::STATUS_RETURNED && $oldStatus !== Activity::STATUS_RETURNED) {
            $this->activityUserNotifications->notifyStatus($activity, $user, 'activity_returned');
            $this->activityMwNotifications->notifyActivityReturned($activity, $user);
        }

        if ($oldStatus === Activity::STATUS_APPROVED && $newStatus === Activity::STATUS_SUBMITTED) {
            $this->activityUserNotifications->notifyStatus($activity, $user, 'activity_rejected');
        }

        if ($newStatus === Activity::STATUS_CANCELLED && $oldStatus !== Activity::STATUS_CANCELLED) {
            $this->activityUserNotifications->notifyCancelled($activity, $user);
        }

        if (
            in_array($newStatus, [Activity::STATUS_COMPLETED, Activity::STATUS_CANCELLED], true)
            && !in_array($oldStatus, [Activity::STATUS_COMPLETED, Activity::STATUS_CANCELLED], true)
        ) {
            $this->inboxMessageService->purgeByActivity($activity->getDepartment(), $activity->getId());
        }

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

        $pendingAccountingFollowups = [];
        $pendingAccountingCount = 0;
        try {
            $pendingAccountingFollowups = $this->entityManager->createQueryBuilder()
                ->select('f')
                ->from(AccountingAcquisitionFollowUp::class, 'f')
                ->where('f.activity = :activityId')
                ->andWhere('f.status = :pending')
                ->setParameter('activityId', $activityId)
                ->setParameter('pending', AccountingAcquisitionFollowUp::STATUS_PENDING)
                ->orderBy('f.createdAt', 'ASC')
                ->setMaxResults(12)
                ->getQuery()
                ->getResult();
            $pendingAccountingCount = count($pendingAccountingFollowups);
        } catch (\Throwable) {
            // Migration noch nicht ausgeführt
        }

        $unstoredPackItems = $this->entityManager->getRepository(ActivityPackItem::class)
            ->createQueryBuilder('pi')
            ->innerJoin('pi.materialItem', 'm')
            ->addSelect('m')
            ->where('pi.activityId = :activityId')
            ->andWhere('pi.quantityReturned > pi.quantityStored')
            ->andWhere('pi.quantityReturned > 0')
            ->setParameter('activityId', $activityId)
            ->orderBy('m.name', 'ASC')
            ->setMaxResults(12)
            ->getQuery()
            ->getResult();

        $unstoredPackItemsCount = (int) $this->entityManager->getRepository(ActivityPackItem::class)
            ->createQueryBuilder('pi')
            ->select('COUNT(pi.id)')
            ->where('pi.activityId = :activityId')
            ->andWhere('pi.quantityReturned > pi.quantityStored')
            ->andWhere('pi.quantityReturned > 0')
            ->setParameter('activityId', $activityId)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'open_workshop_tickets_count' => count($openWorkshopTickets),
            'open_issue_reports_count' => count($openIssueReports),
            'pending_accounting_followups_count' => $pendingAccountingCount,
            'unstored_pack_items_count' => $unstoredPackItemsCount,
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
            'pending_accounting_followups' => array_map(static fn(AccountingAcquisitionFollowUp $f) => [
                'id' => $f->getId(),
                'amount' => $f->getAmount(),
                'receipt_label' => $f->getReceiptLabel(),
                'source_kind' => $f->getSourceKind(),
            ], $pendingAccountingFollowups),
            'unstored_pack_items' => array_map(static fn(ActivityPackItem $pi) => [
                'id' => $pi->getId(),
                'material_name' => $pi->getMaterialItem()?->getName(),
                'quantity_returned' => $pi->getQuantityReturned(),
                'quantity_stored' => $pi->getQuantityStored(),
                'pending_store' => $pi->getQuantityReturned() - $pi->getQuantityStored(),
            ], $unstoredPackItems),
        ];
    }

    /**
     * @param array<string, mixed> $blockers
     */
    private function hasCompletionBlockers(array $blockers): bool
    {
        return ($blockers['open_workshop_tickets_count'] ?? 0) > 0
            || ($blockers['open_issue_reports_count'] ?? 0) > 0
            || ($blockers['pending_accounting_followups_count'] ?? 0) > 0
            || ($blockers['unstored_pack_items_count'] ?? 0) > 0;
    }

    /**
     * @param array<string, mixed> $blockers
     */
    private function completionBlockerMessage(array $blockers): string
    {
        $parts = [];
        if (($blockers['open_workshop_tickets_count'] ?? 0) > 0) {
            $parts[] = 'offene Werkstatt-Tickets';
        }
        if (($blockers['open_issue_reports_count'] ?? 0) > 0) {
            $parts[] = 'offene Verlust-/Schadenmeldungen';
        }
        if (($blockers['pending_accounting_followups_count'] ?? 0) > 0) {
            $parts[] = 'offene Buchhaltungs-Aufträge (Kosten zuordnen)';
        }
        if (($blockers['unstored_pack_items_count'] ?? 0) > 0) {
            $parts[] = 'Material noch nicht eingelagert (Retour → Ausgepackt)';
        }

        return 'Aktivität kann nicht abgeschlossen werden: ' . implode(', ', $parts) . '.';
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

        $completionBlockers = $currentStatus !== Activity::STATUS_COMPLETED
            ? $this->getCompletionBlockers($activity)
            : [];

        $transitions = [];
        foreach ($possibleTransitions as $targetStatus) {
            // Quick-Modus: «Bestätigen» nur für Lager/Event — Gruppe hat Material bereits final eingereicht
            if (
                $activity->getType() === 'activity'
                && $currentStatus === Activity::STATUS_SUBMITTED
                && $targetStatus === Activity::STATUS_APPROVED
            ) {
                continue;
            }

            $allowed = $this->checkTransitionPermission($user, $activity, $currentStatus, $targetStatus);
            if ($allowed === true && $targetStatus === Activity::STATUS_COMPLETED && $this->hasCompletionBlockers($completionBlockers)) {
                $allowed = $this->completionBlockerMessage($completionBlockers);
            }
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

        // Gruppenmitglied (u, kein Gruppenchef): nur Typ «activity» einreichen — nicht Lager/extern
        if ($fromStatus === Activity::STATUS_DRAFT && $toStatus === Activity::STATUS_SUBMITTED) {
            if ($this->activityAccess->isRestrictedGroupMember($user, (string) $departmentId)) {
                $actType = (string) ($activity->getType() ?? 'activity');
                if ($actType === 'camp' || $actType === 'external') {
                    return 'Als Gruppenmitglied dürfen Sie Lager und externe Ausleihen nicht einreichen. '
                        . 'Bitte wende dich an Gruppenchef, MW oder DC.';
                }
            }
        }

        // Typ «activity»: Ersteller/Gruppenmitglied nur «gepackt → am Event» und «am Event → Retour»
        $handoffKey = $fromStatus . '->' . $toStatus;
        if (\in_array($handoffKey, ['packed->at_event', 'at_event->returned'], true)) {
            if ($activity->getType() === 'activity'
                && $this->activityAccess->canUserOperateActivityPackHandoff($user, $activity)) {
                return true;
            }
        }

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
            Activity::STATUS_AT_EVENT  => 'Am Event',
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

        if ($fromStatus === Activity::STATUS_AT_EVENT && $targetStatus === Activity::STATUS_PACKED) {
            return 'Zurück zu «Gepackt»';
        }
        if ($fromStatus === Activity::STATUS_RETURNED && $targetStatus === Activity::STATUS_AT_EVENT) {
            return 'Zurück zu «Am Event»';
        }

        return match($targetStatus) {
            Activity::STATUS_SUBMITTED => 'Einreichen',
            Activity::STATUS_APPROVED  => 'Bestätigen',
            Activity::STATUS_PACKING   => 'Packen starten',
            Activity::STATUS_PACKED    => 'Gepackt markieren',
            Activity::STATUS_AT_EVENT  => 'Alles mitgenommen ans Event',
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
                'linked_container_batch_id' => $linkCb !== null ? $linkCb->getId() : null,
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
                'is_replenishment' => $item->getIsReplenishment(),
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
                $activityItem->setIsReplenishment(false);
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
                Activity::STATUS_AT_EVENT,
                Activity::STATUS_RETURNED,
                Activity::STATUS_COMPLETED,
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
     * Body: { "material_item_id": "...", "quantity": 2, "priority": "normal", "replenishment": true }
     * — replenishment: Verbrauchsmaterial-Nachbuchung (eigene Zeile + Packliste bleibt in aktueller Workflow-Stufe).
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
            $replenishment = filter_var($data['replenishment'] ?? false, FILTER_VALIDATE_BOOLEAN);

            // Erste Zeile pro Material (Hauptbuchung vor Nachbuchungs-Zeilen) — es können mehrere activity_item-Zeilen existieren
            $existing = $this->primaryActivityItemForMaterial($id, (string) $data['material_item_id']);

            $isConsumableMat = $materialItem->getIsConsumable();
            $createReplenishmentLine = $existing && $replenishment && $isConsumableMat;
            $replenishmentItem = null;

            if ($createReplenishmentLine) {
                $purchaseLineTotal = $this->parsePositiveMoney($data['line_total'] ?? null);
                if ($purchaseLineTotal === null) {
                    return new JsonResponse([
                        'error' => 'Bei Nachlieferungen ist der Einkaufsbetrag (line_total) erforderlich und muss grösser als 0 sein',
                    ], 400);
                }
                // Eigene Zeile: Packliste kann Ursprung (Materiallager) vs. Nachbuchung ausweisen
                $addQty = max(1, (int) ($data['quantity'] ?? 1));
                $activityItem = new ActivityItem();
                $activityItem->setId(IdGenerator::generate13('ai'));
                $activityItem->setActivity($activity);
                $activityItem->setMaterialItem($materialItem);
                $activityItem->setQuantity($addQty);
                $activityItem->setPriority($data['priority'] ?? $existing->getPriority());
                $activityItem->setNotes($data['notes'] ?? null);
                $activityItem->setIsConsumable(true);
                $activityItem->setIsReplenishment(true);
                $activityItem->setLineTotal(number_format($purchaseLineTotal, 2, '.', ''));
                $unitFromTotal = $addQty > 0 ? round($purchaseLineTotal / $addQty, 2) : $purchaseLineTotal;
                if (isset($data['unit_price'])) {
                    $activityItem->setUnitPrice($data['unit_price']);
                } else {
                    $activityItem->setUnitPrice(number_format($unitFromTotal, 2, '.', ''));
                }
                if (isset($data['price_type'])) {
                    $activityItem->setPriceType($data['price_type']);
                } else {
                    $activityItem->setPriceType('sale');
                }
                $this->entityManager->persist($activityItem);
                $replenishmentItem = $activityItem;
            } elseif ($existing) {
                // Menge erhöhen
                $existing->setQuantity($existing->getQuantity() + max(1, (int)($data['quantity'] ?? 1)));
                $existing->setUpdatedAt(new \DateTime());
                // Preis-Felder aktualisieren
                if (isset($data['unit_price'])) {
                    $existing->setUnitPrice($data['unit_price']);
                }
                if (isset($data['line_total'])) {
                    $existing->setLineTotal($data['line_total']);
                }
                if (isset($data['price_type'])) {
                    $existing->setPriceType($data['price_type']);
                }
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
                $activityItem->setIsReplenishment(false);
                if (isset($data['unit_price'])) {
                    $activityItem->setUnitPrice($data['unit_price']);
                }
                if (isset($data['line_total'])) {
                    $activityItem->setLineTotal($data['line_total']);
                }
                if (isset($data['price_type'])) {
                    $activityItem->setPriceType($data['price_type']);
                }
                $this->entityManager->persist($activityItem);
            }

            // ActivityItem zuerst schreiben: COUNT/SUM/recalculate lesen aus der DB —
            // vor flush() sieht die DB neue/geänderte Zeilen nicht → Packliste quantity_ordered=0, Preis falsch.
            $this->entityManager->flush();

            $activity->setItemCount(
                (int) $this->entityManager->getRepository(ActivityItem::class)
                    ->count(['activityId' => $id])
            );

            // total_price neu berechnen
            $this->recalculateTotalPrice($activity);
            $activity->setUpdatedAt(new \DateTime());

            // PackItem synchronisieren (Summe aller ActivityItem-Zeilen pro Material)
            if (in_array($activity->getStatus(), [
                Activity::STATUS_PACKING,
                Activity::STATUS_PACKED,
                Activity::STATUS_AT_EVENT,
                Activity::STATUS_RETURNED,
                Activity::STATUS_COMPLETED,
            ], true)) {
                $sumQty = (int) $this->entityManager->createQueryBuilder()
                    ->select('COALESCE(SUM(ai.quantity), 0)')
                    ->from(ActivityItem::class, 'ai')
                    ->where('ai.activityId = :aid')
                    ->andWhere('ai.materialItemId = :mid')
                    ->setParameter('aid', $id)
                    ->setParameter('mid', $materialItem->getId())
                    ->getQuery()
                    ->getSingleScalarResult();
                $this->syncPackItemForMaterial($activity, $materialItem, $sumQty);
            }

            $this->entityManager->flush();

            return new JsonResponse(['message' => 'Material hinzugefügt', 'total_price' => $activity->getTotalPrice()], 201);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Verbrauchsmaterial: gebuchte Menge reduzieren (Überschuss / Reste entlasten), zuerst Nachbuchungs-Zeilen.
     * Body: { "material_item_id": "...", "quantity": 3 }
     */
    #[Route('/{id}/items/release-surplus', name: 'items_release_surplus', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function releaseConsumableSurplus(string $id, Request $request): JsonResponse
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
        $materialItemId = isset($data['material_item_id']) ? (string) $data['material_item_id'] : '';
        $releaseQty = max(0, (int) ($data['quantity'] ?? 0));
        if ($materialItemId === '' || $releaseQty < 1) {
            return new JsonResponse(['error' => 'material_item_id und quantity (≥1) erforderlich'], 400);
        }

        $booked = (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(ai.quantity), 0)')
            ->from(ActivityItem::class, 'ai')
            ->where('ai.activityId = :aid')
            ->andWhere('ai.materialItemId = :mid')
            ->setParameter('aid', $id)
            ->setParameter('mid', $materialItemId)
            ->getQuery()
            ->getSingleScalarResult();

        $consumed = $this->consumedQtyForMaterial($id, $materialItemId);
        $maxRelease = max(0, $booked - $consumed);
        if ($releaseQty > $maxRelease) {
            return new JsonResponse([
                'error' => "Höchstens {$maxRelease} Stk. entlastbar (gebucht {$booked}, verbraucht {$consumed}).",
            ], 422);
        }

        $remaining = $releaseQty;
        /** @var ActivityItem[] $replenishmentLines */
        $replenishmentLines = $this->entityManager->createQueryBuilder()
            ->select('ai')
            ->from(ActivityItem::class, 'ai')
            ->where('ai.activityId = :aid')
            ->andWhere('ai.materialItemId = :mid')
            ->andWhere('ai.isReplenishment = true')
            ->orderBy('ai.createdAt', 'DESC')
            ->setParameter('aid', $id)
            ->setParameter('mid', $materialItemId)
            ->getQuery()
            ->getResult();

        foreach ($replenishmentLines as $line) {
            if ($remaining <= 0) {
                break;
            }
            $lineQty = $line->getQuantity();
            if ($lineQty <= 0) {
                continue;
            }
            $take = min($remaining, $lineQty);
            $remaining -= $take;
            if ($take >= $lineQty) {
                $this->entityManager->remove($line);
                $activity->setItemCount(max(0, $activity->getItemCount() - 1));
            } else {
                $line->setQuantity($lineQty - $take);
                $oldTotal = $line->getLineTotal();
                if ($oldTotal !== null && $oldTotal !== '') {
                    $ratio = ($lineQty - $take) / $lineQty;
                    $line->setLineTotal(number_format((float) $oldTotal * $ratio, 2, '.', ''));
                }
                $oldUnit = $line->getUnitPrice();
                if ($oldUnit !== null && $oldUnit !== '' && $line->getLineTotal() === null) {
                    $line->setUnitPrice($oldUnit);
                }
                $line->setUpdatedAt(new \DateTime());
            }
        }

        if ($remaining > 0) {
            $primary = $this->primaryActivityItemForMaterial($id, $materialItemId);
            if ($primary !== null && !$primary->getIsReplenishment()) {
                $replenishmentBookedNow = (int) $this->entityManager->createQueryBuilder()
                    ->select('COALESCE(SUM(ai.quantity), 0)')
                    ->from(ActivityItem::class, 'ai')
                    ->where('ai.activityId = :aid')
                    ->andWhere('ai.materialItemId = :mid')
                    ->andWhere('ai.isReplenishment = true')
                    ->setParameter('aid', $id)
                    ->setParameter('mid', $materialItemId)
                    ->getQuery()
                    ->getSingleScalarResult();
                $minPrimary = max(0, $consumed - $replenishmentBookedNow);
                $lineQty = $primary->getQuantity();
                $maxFromPrimary = max(0, $lineQty - $minPrimary);
                $take = min($remaining, $maxFromPrimary);
                if ($take > 0) {
                    $primary->setQuantity($lineQty - $take);
                    $primary->setUpdatedAt(new \DateTime());
                    $remaining -= $take;
                }
            }
        }

        if ($remaining > 0) {
            return new JsonResponse(['error' => 'Menge konnte nicht vollständig entlastet werden.'], 422);
        }

        $this->entityManager->flush();
        $this->recalculateTotalPrice($activity);
        $activity->setUpdatedAt(new \DateTime());

        $materialItem = $this->entityManager->getRepository(MaterialItem::class)->find($materialItemId);
        if ($materialItem && in_array($activity->getStatus(), [
            Activity::STATUS_PACKING,
            Activity::STATUS_PACKED,
            Activity::STATUS_AT_EVENT,
            Activity::STATUS_RETURNED,
            Activity::STATUS_COMPLETED,
        ], true)) {
            $sumQty = (int) $this->entityManager->createQueryBuilder()
                ->select('COALESCE(SUM(ai.quantity), 0)')
                ->from(ActivityItem::class, 'ai')
                ->where('ai.activityId = :aid')
                ->andWhere('ai.materialItemId = :mid')
                ->setParameter('aid', $id)
                ->setParameter('mid', $materialItemId)
                ->getQuery()
                ->getSingleScalarResult();
            $this->syncPackItemForMaterial($activity, $materialItem, $sumQty);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Überschuss entlastet',
            'released' => $releaseQty,
            'total_price' => $activity->getTotalPrice(),
        ]);
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

        $removedMaterial = $item->getMaterialItem();
        $removedMaterialId = $item->getMaterialItemId();

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
            Activity::STATUS_AT_EVENT,
            Activity::STATUS_RETURNED,
            Activity::STATUS_COMPLETED,
        ], true)) {
            if ($removedMaterial->getMaterialType() === 'physical_combo') {
                $this->dissolvePackContainersForRemovedMaterial($activity, $removedMaterialId);
                $this->removeOrphanKisteActivityLinesAfterComboDissolve($activity, $removedMaterial);
            }
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
                    'nickname' => $profile?->getNickname(),
                    'first_name' => $profile?->getFirstName(),
                    'last_name' => $profile?->getLastName(),
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

        $packItemsBefore = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findBy(['activityId' => $activity->getId()]);
        foreach ($packItemsBefore as $pi) {
            if (!isset($qtyByMaterialId[$pi->getMaterialItemId()])) {
                $this->dissolvePackContainersForRemovedMaterial($activity, $pi->getMaterialItemId());
            }
        }

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

    /**
     * Phys.-Kombi / Kisten-Shell aus Materialliste entfernt: Pack-Behälter auflösen, Inhalt als lose Positionen.
     */
    private function dissolvePackContainersForRemovedMaterial(Activity $activity, string $removedMaterialItemId): void
    {
        $material = $this->entityManager->getRepository(MaterialItem::class)->find($removedMaterialItemId);
        if ($material === null) {
            return;
        }

        $linkedBatchId = $material->getLinkedContainerBatchId();
        $activityId = $activity->getId();

        $qb = $this->entityManager->createQueryBuilder()
            ->select('pc')
            ->from(ActivityPackContainer::class, 'pc')
            ->leftJoin('pc.containerBatch', 'b')
            ->where('pc.activityId = :aid')
            ->setParameter('aid', $activityId);

        $orExpr = $qb->expr()->orX('b.materialItemId = :rmid');
        $qb->setParameter('rmid', $removedMaterialItemId);
        if ($linkedBatchId !== null && $linkedBatchId !== '') {
            $orExpr->add('pc.containerBatchId = :lbid');
            $qb->setParameter('lbid', $linkedBatchId);
        }
        $qb->andWhere($orExpr);

        /** @var ActivityPackContainer[] $toDissolve */
        $toDissolve = $qb->getQuery()->getResult();

        foreach ($toDissolve as $container) {
            $this->releasePackContainerContentsToLooseActivityLines($activity, $container, $removedMaterialItemId);
            $this->entityManager->remove($container);
        }
    }

    /**
     * Durch linkKisteOnContainerBatchAssigned angelegte Kisten-Zeile entfernen, wenn die Phys.-Kombi gelöscht wurde.
     */
    private function removeOrphanKisteActivityLinesAfterComboDissolve(Activity $activity, MaterialItem $comboMaterial): void
    {
        $linkedBatchId = $comboMaterial->getLinkedContainerBatchId();
        if ($linkedBatchId === null || $linkedBatchId === '') {
            return;
        }

        $batch = $this->entityManager->getRepository(MaterialBatch::class)->find($linkedBatchId);
        if ($batch === null) {
            return;
        }

        $kisteMaterialId = $batch->getMaterialItemId();
        if ($kisteMaterialId === $comboMaterial->getId()) {
            return;
        }

        $stillLinked = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(pc.id)')
            ->from(ActivityPackContainer::class, 'pc')
            ->where('pc.activityId = :aid')
            ->andWhere('pc.containerBatchId = :bid')
            ->setParameter('aid', $activity->getId())
            ->setParameter('bid', $linkedBatchId)
            ->getQuery()
            ->getSingleScalarResult();

        if ($stillLinked > 0) {
            return;
        }

        $orphanItems = $this->entityManager->getRepository(ActivityItem::class)->findBy([
            'activityId' => $activity->getId(),
            'materialItemId' => $kisteMaterialId,
        ]);
        foreach ($orphanItems as $ai) {
            $this->entityManager->remove($ai);
            $activity->setItemCount(max(0, $activity->getItemCount() - 1));
        }
    }

    /**
     * Pack-Behälterzeilen in Materialliste + Packliste überführen (vor Löschen des Behälters).
     */
    private function releasePackContainerContentsToLooseActivityLines(
        Activity $activity,
        ActivityPackContainer $container,
        string $excludeShellMaterialId,
    ): void {
        $items = $this->entityManager->getRepository(ActivityPackContainerItem::class)
            ->findBy(['packContainerId' => $container->getId()]);

        foreach ($items as $ci) {
            if (!$ci instanceof ActivityPackContainerItem) {
                continue;
            }
            $mid = $ci->getMaterialItemId();
            if ($mid === $excludeShellMaterialId) {
                $this->entityManager->remove($ci);
                continue;
            }

            $qty = max(0, $ci->getQuantityPacked());
            if ($qty < 1) {
                $this->entityManager->remove($ci);
                continue;
            }

            $materialItem = $ci->getMaterialItem();
            $this->addActivityItemQuantity($activity, $materialItem, $qty);

            $sumQty = (int) $this->entityManager->createQueryBuilder()
                ->select('COALESCE(SUM(ai.quantity), 0)')
                ->from(ActivityItem::class, 'ai')
                ->where('ai.activityId = :aid')
                ->andWhere('ai.materialItemId = :mid')
                ->setParameter('aid', $activity->getId())
                ->setParameter('mid', $mid)
                ->getQuery()
                ->getSingleScalarResult();

            $this->syncPackItemForMaterial($activity, $materialItem, max(1, $sumQty));

            $packItem = $this->entityManager->getRepository(ActivityPackItem::class)->findOneBy([
                'activityId' => $activity->getId(),
                'materialItemId' => $mid,
            ]);
            if ($packItem !== null) {
                $packItem->setQuantityPacked(max($packItem->getQuantityPacked(), $ci->getQuantityPacked()));
                $packItem->setQuantityIssued(max($packItem->getQuantityIssued(), $ci->getQuantityIssued()));
                $packItem->setQuantityReturned(max($packItem->getQuantityReturned(), $ci->getQuantityReturned()));
                $packItem->setUpdatedAt(new \DateTime());
            }

            $this->entityManager->remove($ci);
        }
    }

    private function addActivityItemQuantity(Activity $activity, MaterialItem $materialItem, int $addQty): void
    {
        if ($addQty < 1) {
            return;
        }

        $existingList = $this->entityManager->getRepository(ActivityItem::class)->findBy(
            ['activityId' => $activity->getId(), 'materialItemId' => $materialItem->getId()],
            ['isReplenishment' => 'ASC', 'createdAt' => 'ASC'],
        );
        $existing = $existingList[0] ?? null;
        if ($existing !== null) {
            $existing->setQuantity($existing->getQuantity() + $addQty);
            $existing->setUpdatedAt(new \DateTime());
            return;
        }

        $activityItem = new ActivityItem();
        $activityItem->setId(IdGenerator::generate13('ai'));
        $activityItem->setActivity($activity);
        $activityItem->setMaterialItem($materialItem);
        $activityItem->setQuantity($addQty);
        $activityItem->setPriority('normal');
        $activityItem->setIsConsumable($materialItem->getIsConsumable());
        $activityItem->setIsReplenishment(false);
        $this->entityManager->persist($activityItem);
        $activity->setItemCount($activity->getItemCount() + 1);
        $activity->setUpdatedAt(new \DateTime());
    }

    /**
     * Erste ActivityItem-Zeile für dieses Material (is_replenishment = false zuerst, dann älteste).
     */
    private function consumedQtyForMaterial(string $activityId, string $materialItemId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(ir.quantity), 0)')
            ->from(ActivityIssueReport::class, 'ir')
            ->where('ir.activityId = :aid')
            ->andWhere('ir.materialItemId = :mid')
            ->andWhere('ir.type = :t')
            ->setParameter('aid', $activityId)
            ->setParameter('mid', $materialItemId)
            ->setParameter('t', ActivityIssueReport::TYPE_CONSUMPTION)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function primaryActivityItemForMaterial(string $activityId, string $materialItemId): ?ActivityItem
    {
        return $this->entityManager->createQueryBuilder()
            ->select('ai')
            ->from(ActivityItem::class, 'ai')
            ->where('ai.activityId = :aid')
            ->andWhere('ai.materialItemId = :mid')
            ->orderBy('ai.isReplenishment', 'ASC')
            ->addOrderBy('ai.createdAt', 'ASC')
            ->setParameter('aid', $activityId)
            ->setParameter('mid', $materialItemId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function syncPackItemForMaterial(Activity $activity, MaterialItem $materialItem, int $newQuantity): void
    {
        $existingPackItem = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findOneBy([
                'activityId' => $activity->getId(),
                'materialItemId' => $materialItem->getId(),
            ]);

        if ($existingPackItem) {
            $oldOrdered = $existingPackItem->getQuantityOrdered();
            $delta = max(0, $newQuantity - $oldOrdered);

            // Bestellte Menge aktualisieren
            $existingPackItem->setQuantityOrdered($newQuantity);
            if ($existingPackItem->getQuantityPacked() > $newQuantity) {
                $existingPackItem->setQuantityPacked($newQuantity);
            }
            if ($existingPackItem->getQuantityIssued() > $newQuantity) {
                $existingPackItem->setQuantityIssued($newQuantity);
            }

            // Verbrauchsmaterial: Mengenzuwachs während gepackt/ausgegeben/retour nicht in «Bestätigt→Gepackt» parken,
            // sondern dort buchen, wo das Material faktisch steht (gepackt bzw. am Event).
            if ($materialItem->getIsConsumable() && $delta > 0) {
                $st = $activity->getStatus();
                if ($st === Activity::STATUS_PACKED) {
                    $existingPackItem->setQuantityPacked(
                        min($newQuantity, $existingPackItem->getQuantityPacked() + $delta)
                    );
                } elseif (in_array($st, [
                    Activity::STATUS_AT_EVENT,
                    Activity::STATUS_RETURNED,
                    Activity::STATUS_COMPLETED,
                ], true)) {
                    $existingPackItem->setQuantityPacked(
                        min($newQuantity, $existingPackItem->getQuantityPacked() + $delta)
                    );
                    $existingPackItem->setQuantityIssued(
                        min($newQuantity, $existingPackItem->getQuantityIssued() + $delta)
                    );
                }
            }

            // Leihmaterial: Mengenzuwachs wie physisch gepackt bzw. am Event (sonst Packliste-UI 0/0).
            if (!$materialItem->getIsConsumable() && $delta > 0) {
                $st = $activity->getStatus();
                if ($st === Activity::STATUS_PACKED) {
                    $existingPackItem->setQuantityPacked(
                        min($newQuantity, $existingPackItem->getQuantityPacked() + $delta)
                    );
                } elseif (in_array($st, [
                    Activity::STATUS_AT_EVENT,
                    Activity::STATUS_RETURNED,
                    Activity::STATUS_COMPLETED,
                ], true)) {
                    $existingPackItem->setQuantityPacked(
                        min($newQuantity, $existingPackItem->getQuantityPacked() + $delta)
                    );
                    $existingPackItem->setQuantityIssued(
                        min($newQuantity, $existingPackItem->getQuantityIssued() + $delta)
                    );
                }
            }

            $existingPackItem->setUpdatedAt(new \DateTime());
        } else {
            // Neues PackItem erstellen
            $packItem = new ActivityPackItem();
            $packItem->setId(IdGenerator::generate13('pk'));
            $packItem->setActivity($activity);
            $packItem->setMaterialItem($materialItem);
            $packItem->setQuantityOrdered($newQuantity);
            $stNew = $activity->getStatus();
            if ($stNew === Activity::STATUS_PACKED) {
                $packItem->setQuantityPacked($newQuantity);
            } elseif (in_array($stNew, [
                Activity::STATUS_AT_EVENT,
                Activity::STATUS_RETURNED,
                Activity::STATUS_COMPLETED,
            ], true)) {
                $packItem->setQuantityPacked($newQuantity);
                $packItem->setQuantityIssued($newQuantity);
            } else {
                $packItem->setQuantityPacked(0);
            }
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
    private function canUserManageActivityPublicCode(User $user, Activity $activity): bool
    {
        if (count(array_intersect(['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'], $user->getRoles())) > 0) {
            return true;
        }

        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $user->getId(), 'departmentId' => $activity->getDepartmentId()]);
        if (!$membership) {
            return false;
        }

        return in_array((string) ($membership->getRole() ?? ''), ['mw', 'dc'], true);
    }

    private function serializeActivity(Activity $activity, bool $detailed = false, ?User $viewer = null): array
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

        $activityPublicEntry = $this->publicCodeService->getActiveActivityPublicCode((string) $activity->getId());
        $activityPublicCode = $activityPublicEntry?->getPublicCode();
        $data['public_code'] = $activityPublicCode;
        $data['public_url'] = $activityPublicCode
            ? $this->publicCodeService->buildActivityPublicUrl($activityPublicCode)
            : null;

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
                'is_pack_list_editable' => $viewer instanceof User
                    ? $this->activityAccess->canUserEditPackList($viewer, $activity)
                    : $activity->isPackListEditable(),
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
     * Storno ab «Wird gepackt»: Pack-Pipeline zurücksetzen — gebuchte Mengen gelten wieder als im Lager.
     */
    private function resetPackPipelineOnCancel(Activity $activity, string $oldStatus): void
    {
        if (!in_array($oldStatus, [Activity::STATUS_PACKING, Activity::STATUS_PACKED], true)) {
            return;
        }

        $activityId = $activity->getId();
        if (!$activityId) {
            return;
        }

        $packItems = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findBy(['activityId' => $activityId]);
        foreach ($packItems as $packItem) {
            $packItem->setQuantityPacked(0);
            $packItem->setQuantityTransportTo(0);
            $packItem->setQuantityIssued(0);
            $packItem->setQuantityTransportBack(0);
            $packItem->setQuantityReturned(0);
            $packItem->setUpdatedAt(new \DateTime());
        }

        $containerIds = $this->entityManager->getRepository(ActivityPackContainer::class)
            ->createQueryBuilder('c')
            ->select('c.id')
            ->where('c.activityId = :activityId')
            ->setParameter('activityId', $activityId)
            ->getQuery()
            ->getSingleColumnResult();

        if ($containerIds !== []) {
            $containerItems = $this->entityManager->getRepository(ActivityPackContainerItem::class)
                ->createQueryBuilder('ci')
                ->where('ci.packContainerId IN (:ids)')
                ->setParameter('ids', $containerIds)
                ->getQuery()
                ->getResult();

            foreach ($containerItems as $ci) {
                if (!$ci instanceof ActivityPackContainerItem) {
                    continue;
                }
                $ci->setQuantityPacked(0);
                $ci->setQuantityTransportTo(0);
                $ci->setQuantityIssued(0);
                $ci->setQuantityTransportBack(0);
                $ci->setQuantityReturned(0);
            }
        }

        $this->createHistoryEntry($activity, 'pack_pipeline_reset_on_cancel', [
            'previous_status' => $oldStatus,
        ]);
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

    /** @param mixed $value */
    private function parsePositiveMoney($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }
        if (!is_numeric($value)) {
            return null;
        }
        $n = (float) $value;

        return $n > 0 ? $n : null;
    }

}
