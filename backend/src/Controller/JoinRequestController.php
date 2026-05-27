<?php

namespace App\Controller;

use App\Entity\AdminJoinRequest;
use App\Entity\AdminJoinRequestEvent;
use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\JoinRequest;
use App\Entity\Membership;
use App\Entity\Organisation;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\AuditLogger;
use App\Service\Mail\MailTemplateContentStore;
use App\Service\OrganisationUserPickerFilter;
use App\Service\InboxMessageService;
use App\Service\JoinRequestManagerNotificationService;
use App\Service\TurnstileVerifier;
use App\Service\UserDepartmentInviteNotificationService;
use App\Service\VerificationEmailService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/join-requests', name: 'api_join_requests_')]
class JoinRequestController extends AbstractController
{
    private const MANAGER_ROLES = ['mw', 'dc'];
    private const GLOBAL_ADMIN_ROLES = ['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'];
    private const INVITE_CODE_SETTING_KEY = 'join.invite_code';
    private const VALID_MEMBER_ROLES = ['mw', 'dc', 'l1', 'l2', 'l3', 'u'];
    private const PENDING_INVITES_SETTING_KEY = 'join.pending_invites';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuditLogger $auditLogger,
        private VerificationEmailService $verificationEmailService,
        private MailTemplateContentStore $mailTemplateContent,
        private UserDepartmentInviteNotificationService $userDepartmentInviteNotifications,
        private JoinRequestManagerNotificationService $joinRequestManagerNotifications,
        private TurnstileVerifier $turnstileVerifier,
        private InboxMessageService $inboxMessages,
        private AdminCapabilityChecker $adminCapabilityChecker,
        #[Autowire('%env(APP_FRONTEND_URL)%')] private string $frontendUrl
    )
    {
    }

    private function hasGlobalAdminRole(User $user): bool
    {
        return $this->adminCapabilityChecker->can($user, 'support_requests.assign');
    }

    /** @return list<string>|null null = alle Organisationen */
    private function getManagedOrganisationIds(User $user): ?array
    {
        if ($this->adminCapabilityChecker->isSuperAdmin($user)) {
            return null;
        }

        return $this->adminCapabilityChecker->getAccessibleOrganisationIds($user);
    }

    private function logAdminJoinRequestEvent(AdminJoinRequest $adminRequest, User $actor, string $action, ?array $payload = null): void
    {
        $event = new AdminJoinRequestEvent();
        $event->setId(IdGenerator::generateUnique($this->entityManager, AdminJoinRequestEvent::class));
        $event->setAdminJoinRequest($adminRequest);
        $event->setUser($actor);
        $event->setAction($action);
        $event->setPayload($payload);
        $this->entityManager->persist($event);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $turnstileError = $this->validateTurnstileToken($request, $data);
        if ($turnstileError !== null) {
            return $turnstileError;
        }

        $joinCode = trim((string) ($data['join_code'] ?? ''));
        $departmentId = trim((string) ($data['department_id'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        $requestedRole = strtolower(trim((string) ($data['requested_role'] ?? 'u')));
        if ($requestedRole === '') {
            $requestedRole = 'u';
        }
        if (!in_array($requestedRole, self::VALID_MEMBER_ROLES, true)) {
            return new JsonResponse(['error' => 'Ungueltige Rolle. Erlaubt: mw, dc, l1, l2, l3, u'], 400);
        }
        if ($joinCode === '' && $departmentId === '') {
            return new JsonResponse(['error' => 'Join-Code oder department_id ist erforderlich'], 400);
        }

        $department = null;
        if ($departmentId !== '') {
            /** @var Department|null $department */
            $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        } elseif ($joinCode !== '') {
            /** @var Department|null $department */
            $department = $this->entityManager->getRepository(Department::class)->find($joinCode);
            if (!$department) {
                $normalizedJoinCode = $this->normalizeJoinCode($joinCode);
                $inviteSetting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
                    'settingKey' => self::INVITE_CODE_SETTING_KEY,
                    'settingValue' => $normalizedJoinCode,
                ]);
                $department = $inviteSetting?->getDepartment();
            }
        }
        if (!$department) {
            return new JsonResponse(['error' => 'Kein Department fuer diesen Join-Code gefunden'], 404);
        }

        $existingMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $currentUser->getId(),
            'departmentId' => $department->getId(),
        ]);
        if ($existingMembership) {
            return new JsonResponse(['error' => 'Sie sind bereits Mitglied dieses Departments'], 409);
        }

        $existingPending = $this->entityManager->getRepository(JoinRequest::class)->findOneBy([
            'userId' => $currentUser->getId(),
            'departmentId' => $department->getId(),
            'status' => 'pending',
        ]);
        if ($existingPending) {
            return new JsonResponse(['error' => 'Es existiert bereits eine offene Anfrage fuer dieses Department'], 409);
        }

        $viaJoinCode = $departmentId === '' && $joinCode !== '';
        $autoJoined = false;
        $assignedRole = null;

        if ($viaJoinCode) {
            $this->createMembershipForUser($currentUser, $department, 'u', $currentUser);
            $autoJoined = true;
            $assignedRole = 'u';
        }

        $joinRequest = new JoinRequest();
        $joinRequest->setId(IdGenerator::generateUnique($this->entityManager, JoinRequest::class));
        $joinRequest->setUser($currentUser);
        $joinRequest->setDepartment($department);
        $joinRequest->setMessage($message !== '' ? $message : null);
        // Join-Code: sofort Mitglied. Abteilung per Suche / persoenliche Einladung: MW/DC-Freigabe.
        if ($autoJoined) {
            $joinRequest->setStatus('approved');
            $joinRequest->setReviewedBy($currentUser);
        } else {
            $joinRequest->setStatus('pending');
        }

        $this->entityManager->persist($joinRequest);
        $this->entityManager->flush();

        if (!$autoJoined) {
            try {
                $this->joinRequestManagerNotifications->notifyJoinRequestCreated($joinRequest);
            } catch (\Throwable) {
                // Anfrage bleibt gueltig auch wenn Mail fehlschlaegt
            }
        }

        return new JsonResponse([
            'id' => $joinRequest->getId(),
            'status' => $joinRequest->getStatus(),
            'department_id' => $department->getId(),
            'department_name' => $department->getName(),
            'assigned_role' => $assignedRole,
            'auto_joined' => $autoJoined,
            'created_at' => $joinRequest->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], 201);
    }

    #[Route('/admin-request', name: 'admin_request_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createAdminRequest(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $turnstileError = $this->validateTurnstileToken($request, $data);
        if ($turnstileError !== null) {
            return $turnstileError;
        }

        $requestedDepartmentName = trim((string) ($data['requested_department_name'] ?? ''));
        $requestedAffiliation = trim((string) ($data['requested_affiliation'] ?? ''));
        $requestedOrganisationId = trim((string) ($data['requested_organisation_id'] ?? ''));
        $requestedParentDepartmentName = trim((string) ($data['requested_parent_department_name'] ?? ''));
        $requestedParentDepartmentId = trim((string) ($data['requested_parent_department_id'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));

        if ($requestedDepartmentName === '') {
            return new JsonResponse(['error' => 'requested_department_name ist erforderlich'], 400);
        }

        if ($requestedOrganisationId !== '') {
            /** @var Organisation|null $pickedOrg */
            $pickedOrg = $this->entityManager->getRepository(Organisation::class)->find($requestedOrganisationId);
            if (!$pickedOrg || !OrganisationUserPickerFilter::isVisibleForUserPickers($pickedOrg)) {
                return new JsonResponse(['error' => 'Ungueltige Organisation'], 400);
            }
        }

        $existingPending = $this->entityManager->getRepository(AdminJoinRequest::class)->findOneBy([
            'userId' => $currentUser->getId(),
            'requestedDepartmentName' => $requestedDepartmentName,
            'status' => 'pending',
        ]);
        if ($existingPending) {
            return new JsonResponse(['error' => 'Es existiert bereits eine offene Admin-Anfrage fuer diese Abteilung'], 409);
        }

        $adminRequest = new AdminJoinRequest();
        $adminRequest->setId(IdGenerator::generateUnique($this->entityManager, AdminJoinRequest::class));
        $adminRequest->setUser($currentUser);
        $adminRequest->setRequestedDepartmentName($requestedDepartmentName);
        $adminRequest->setRequestedAffiliation($requestedAffiliation !== '' ? $requestedAffiliation : null);
        $adminRequest->setRequestedOrganisationId($requestedOrganisationId !== '' ? $requestedOrganisationId : null);
        $adminRequest->setRequestedParentDepartmentName($requestedParentDepartmentName !== '' ? $requestedParentDepartmentName : null);
        if ($requestedParentDepartmentId !== '' && $requestedOrganisationId !== '') {
            /** @var Department|null $parentDept */
            $parentDept = $this->entityManager->getRepository(Department::class)->find($requestedParentDepartmentId);
            if ($parentDept && $parentDept->getOrganisationId() === $requestedOrganisationId) {
                $adminRequest->setRequestedParentDepartment($parentDept);
            }
        }
        $adminRequest->setMessage($message !== '' ? $message : null);
        $adminRequest->setStatus('pending');

        $this->entityManager->persist($adminRequest);
        $this->logAdminJoinRequestEvent($adminRequest, $currentUser, 'created', [
            'requested_department_name' => $requestedDepartmentName,
            'requested_organisation_id' => $requestedOrganisationId !== '' ? $requestedOrganisationId : null,
            'requested_parent_department_name' => $requestedParentDepartmentName !== '' ? $requestedParentDepartmentName : null,
            'requested_parent_department_id' => $adminRequest->getRequestedParentDepartmentId(),
        ]);
        $this->entityManager->flush();

        try {
            $this->joinRequestManagerNotifications->notifyAdminJoinRequestCreated($adminRequest);
        } catch (\Throwable) {
            // Antrag bleibt gueltig auch wenn Mail fehlschlaegt
        }

        return new JsonResponse([
            'id' => $adminRequest->getId(),
            'status' => $adminRequest->getStatus(),
            'requested_department_name' => $adminRequest->getRequestedDepartmentName(),
            'requested_affiliation' => $adminRequest->getRequestedAffiliation(),
            'requested_organisation_id' => $adminRequest->getRequestedOrganisationId(),
            'requested_parent_department_name' => $adminRequest->getRequestedParentDepartmentName(),
            'requested_parent_department_id' => $adminRequest->getRequestedParentDepartmentId(),
            'created_at' => $adminRequest->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], 201);
    }

    #[Route('/admin-request/pending', name: 'admin_request_pending', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function pendingAdminRequests(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        $departmentId = trim((string) $request->query->get('department_id', ''));
        $isGlobalAdmin = $this->hasGlobalAdminRole($currentUser);

        if (!$isGlobalAdmin) {
            if ($departmentId === '') {
                return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
            }
            $myMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
                'userId' => $currentUser->getId(),
                'departmentId' => $departmentId,
            ]);
            if (!$myMembership || !in_array($myMembership->getRole(), ['mw', 'dc'], true)) {
                return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
            }
        }

        $pendingStatus = 'pending';

        // Auto-create support requests for users without any department membership.
        $usersWithoutDepartment = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->leftJoin(Membership::class, 'm', 'WITH', 'm.userId = u.id')
            ->leftJoin(
                AdminJoinRequest::class,
                'existing',
                'WITH',
                'existing.userId = u.id AND existing.status = :pendingStatus'
            )
            ->leftJoin(
                JoinRequest::class,
                'existingJoin',
                'WITH',
                'existingJoin.userId = u.id AND existingJoin.status = :pendingStatus'
            )
            ->where('m.userId IS NULL')
            ->andWhere('existing.id IS NULL')
            ->andWhere('existingJoin.id IS NULL')
            ->andWhere('u.state = :activeState')
            ->setParameter('pendingStatus', $pendingStatus)
            ->setParameter('activeState', 'active')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();

        foreach ($usersWithoutDepartment as $userWithoutDepartment) {
            if (!$userWithoutDepartment instanceof User) {
                continue;
            }
            if ($userWithoutDepartment->hasSuperAdminProfile()) {
                continue;
            }

            $autoRequest = new AdminJoinRequest();
            $autoRequest->setId(IdGenerator::generateUnique($this->entityManager, AdminJoinRequest::class));
            $autoRequest->setUser($userWithoutDepartment);
            $autoRequest->setRequestedDepartmentName('Unbekannte Abteilung');
            $autoRequest->setMessage('Automatisch erstellt: Benutzer ohne Department-Zuordnung.');
            $autoRequest->setStatus($pendingStatus);
            $this->entityManager->persist($autoRequest);
            $this->logAdminJoinRequestEvent($autoRequest, $currentUser, 'auto_created', [
                'requested_department_name' => 'Unbekannte Abteilung',
            ]);
        }
        if (count($usersWithoutDepartment) > 0) {
            $this->entityManager->flush();
        }

        $qb = $this->entityManager->getRepository(AdminJoinRequest::class)
            ->createQueryBuilder('ajr')
            ->innerJoin('ajr.user', 'u')
            ->innerJoin('u.profile', 'p')
            ->addSelect('u', 'p')
            ->where('ajr.status = :status')
            ->setParameter('status', $pendingStatus)
            ->orderBy('ajr.createdAt', 'ASC')
            ->setMaxResults(50);

        $isSuperAdmin = $this->adminCapabilityChecker->isSuperAdmin($currentUser);
        $managedOrgIds = null;
        if ($isGlobalAdmin && !$isSuperAdmin) {
            $managedOrgIds = $this->getManagedOrganisationIds($currentUser);
            if ($managedOrgIds === null) {
                // Kein Scope-Filter: alle Support-Anfragen sichtbar
            } elseif (count($managedOrgIds) > 0) {
                $qb->andWhere('(ajr.requestedOrganisationId IS NULL OR ajr.requestedOrganisationId IN (:managedOrgIds))')
                    ->setParameter('managedOrgIds', $managedOrgIds);
            } else {
                $qb->andWhere('ajr.requestedOrganisationId IS NULL');
            }
        }

        $requests = $qb->getQuery()->getResult();

        $this->removeStaleAutoAdminRequestsForUsersWithPendingJoin($requests);

        $result = [];
        foreach ($requests as $req) {
            if ($req->getStatus() !== $pendingStatus) {
                continue;
            }
            $profile = $req->getUser()?->getProfile();
            $result[] = [
                'id' => $req->getId(),
                'request_kind' => 'admin',
                'user_id' => $req->getUserId(),
                'name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
                'email' => $profile?->getEmail(),
                'requested_department_name' => $req->getRequestedDepartmentName(),
                'requested_affiliation' => $req->getRequestedAffiliation(),
                'requested_organisation_id' => $req->getRequestedOrganisationId(),
                'requested_parent_department_name' => $req->getRequestedParentDepartmentName(),
                'requested_parent_department_id' => $req->getRequestedParentDepartmentId(),
                'message' => $req->getMessage(),
                'status' => $req->getStatus(),
                'assigned_department_id' => $req->getAssignedDepartmentId(),
                'assigned_department_name' => $req->getAssignedDepartment()?->getName(),
                'created_at' => $req->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        if ($isGlobalAdmin) {
            $joinQb = $this->entityManager->getRepository(JoinRequest::class)
                ->createQueryBuilder('jr')
                ->innerJoin('jr.user', 'u')
                ->innerJoin('u.profile', 'p')
                ->innerJoin('jr.department', 'd')
                ->innerJoin('d.organisation', 'o')
                ->addSelect('u', 'p', 'd', 'o')
                ->leftJoin(Membership::class, 'm', 'WITH', 'm.userId = u.id')
                ->where('jr.status = :status')
                ->andWhere('m.userId IS NULL')
                ->setParameter('status', $pendingStatus)
                ->orderBy('jr.createdAt', 'ASC')
                ->setMaxResults(50);

            if (!$isSuperAdmin && $managedOrgIds !== null) {
                if (count($managedOrgIds) > 0) {
                    $joinQb->andWhere('d.organisationId IN (:managedOrgIds)')
                        ->setParameter('managedOrgIds', $managedOrgIds);
                } else {
                    $joinQb->andWhere('1 = 0');
                }
            }

            foreach ($joinQb->getQuery()->getResult() as $joinRequest) {
                if (!$joinRequest instanceof JoinRequest) {
                    continue;
                }
                $result[] = $this->serializePendingDepartmentJoinRequest($joinRequest);
            }
        }

        usort($result, static fn (array $a, array $b): int => strcmp($a['created_at'], $b['created_at']));

        return new JsonResponse($result);
    }

    #[Route('/admin-request/{id}/assign', name: 'admin_request_assign', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function assignAdminRequest(string $id, Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        /** @var AdminJoinRequest|null $adminRequest */
        $adminRequest = $this->entityManager->getRepository(AdminJoinRequest::class)->find($id);
        if (!$adminRequest) {
            return new JsonResponse(['error' => 'Admin-Anfrage nicht gefunden'], 404);
        }
        if ($adminRequest->getStatus() !== 'pending') {
            return new JsonResponse(['error' => 'Anfrage wurde bereits bearbeitet'], 409);
        }

        $actingDepartmentId = trim((string) $request->query->get('department_id', ''));
        $isGlobalAdmin = $this->hasGlobalAdminRole($currentUser);

        if (!$isGlobalAdmin) {
            if ($actingDepartmentId === '') {
                return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
            }
            $myMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
                'userId' => $currentUser->getId(),
                'departmentId' => $actingDepartmentId,
            ]);
            if (!$myMembership || $myMembership->getRole() !== 'mw') {
                return new JsonResponse(['error' => 'Nur Superadmin/OrgChef/SubOrgChef oder Abteilungsleiter (mw) darf eine Department-Zuordnung ausfuehren'], 403);
            }
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $targetDepartmentId = trim((string) ($data['target_department_id'] ?? ''));
        if ($targetDepartmentId === '') {
            return new JsonResponse(['error' => 'target_department_id ist erforderlich'], 400);
        }

        $validRoles = ['mw', 'dc', 'l1', 'l2', 'l3', 'u'];
        $requestedRole = trim((string) ($data['target_role'] ?? 'u'));
        if ($requestedRole === '') {
            $requestedRole = 'u';
        }
        if (!in_array($requestedRole, $validRoles, true)) {
            return new JsonResponse(['error' => 'Ungueltige Rolle. Erlaubt: mw, dc, l1, l2, l3, u'], 400);
        }

        $targetDepartment = $this->entityManager->getRepository(Department::class)->find($targetDepartmentId);
        if (!$targetDepartment) {
            return new JsonResponse(['error' => 'Ziel-Department nicht gefunden'], 404);
        }

        if ($isGlobalAdmin && !$this->adminCapabilityChecker->canAccessDepartment($currentUser, $targetDepartmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung für dieses Department'], 403);
        }

        $hasMwOrDc = (int) $this->entityManager->createQuery(
            'SELECT COUNT(m.userId) FROM App\Entity\Membership m WHERE m.departmentId = :deptId AND (m.role = :mw OR m.role = :dc)'
        )->setParameter('deptId', $targetDepartmentId)
            ->setParameter('mw', 'mw')
            ->setParameter('dc', 'dc')
            ->getSingleScalarResult() > 0;

        $assignedRole = $requestedRole;
        $roleForcedToMwWarning = null;
        if (!$hasMwOrDc && $requestedRole === 'u') {
            $assignedRole = 'mw';
            $roleForcedToMwWarning = 'Department hat keinen Materialchef (mw) oder Departmentchef (dc). User wurde automatisch als Materialchef (mw) zugeordnet.';
        }

        $userId = $adminRequest->getUserId();
        $existingMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $userId,
            'departmentId' => $targetDepartmentId,
        ]);
        if ($existingMembership) {
            return new JsonResponse(['error' => 'User ist bereits Mitglied im Ziel-Department'], 409);
        }

        $joinRequest = $this->entityManager->getRepository(JoinRequest::class)->findOneBy([
            'userId' => $userId,
            'departmentId' => $targetDepartmentId,
            'status' => 'pending',
        ]);
        if (!$joinRequest) {
            $joinRequest = new JoinRequest();
            $joinRequest->setId(IdGenerator::generateUnique($this->entityManager, JoinRequest::class));
            $joinRequest->setUser($adminRequest->getUser());
            $joinRequest->setDepartment($targetDepartment);
            $joinRequest->setStatus('pending');
            $joinRequest->setMessage($adminRequest->getMessage());
            $this->entityManager->persist($joinRequest);
        }

        // User sofort als Mitglied hinzufuegen
        $membership = new Membership();
        $membership->setUser($adminRequest->getUser());
        $membership->setDepartment($targetDepartment);
        $membership->setRole($assignedRole);
        $hasAnyMembership = count($this->entityManager->getRepository(Membership::class)->findBy([
            'userId' => $userId,
        ])) > 0;
        $membership->setIsPrimary(!$hasAnyMembership);
        $this->auditLogger->log(
            'membership',
            AuditLogger::buildMembershipEntityId($membership->getUserId(), $membership->getDepartmentId()),
            'membership_created',
            $currentUser,
            $membership->getUser(),
            $membership->getDepartment(),
            [
                'role' => ['old' => null, 'new' => $membership->getRole()],
                'is_primary' => ['old' => null, 'new' => $membership->getIsPrimary()],
            ]
        );
        $this->entityManager->persist($membership);

        $joinRequest->setStatus('approved');
        $joinRequest->setReviewedBy($currentUser);

        $adminRequest->setAssignedDepartment($targetDepartment);
        $adminRequest->setReviewedBy($currentUser);
        $adminRequest->setStatus('assigned');
        $this->logAdminJoinRequestEvent($adminRequest, $currentUser, 'assigned', [
            'target_department_id' => $targetDepartment->getId(),
            'target_department_name' => $targetDepartment->getName(),
            'assigned_role' => $assignedRole,
            'role_forced_to_mw' => $roleForcedToMwWarning !== null,
        ]);
        $this->entityManager->flush();

        $response = [
            'success' => true,
            'status' => $adminRequest->getStatus(),
            'assigned_department_id' => $targetDepartment->getId(),
            'assigned_department_name' => $targetDepartment->getName(),
            'assigned_role' => $assignedRole,
            'join_request_id' => $joinRequest->getId(),
        ];
        if ($roleForcedToMwWarning !== null) {
            $response['role_forced_to_mw_warning'] = $roleForcedToMwWarning;
        }
        return new JsonResponse($response);
    }

    #[Route('/admin-request/history', name: 'admin_request_history', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function adminRequestHistory(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        $departmentId = trim((string) $request->query->get('department_id', ''));
        $isGlobalAdmin = $this->hasGlobalAdminRole($currentUser);

        if (!$isGlobalAdmin) {
            if ($departmentId === '') {
                return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
            }
            $myMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
                'userId' => $currentUser->getId(),
                'departmentId' => $departmentId,
            ]);
            if (!$myMembership || !in_array($myMembership->getRole(), ['mw', 'dc'], true)) {
                return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
            }
        }

        $qb = $this->entityManager->getRepository(AdminJoinRequest::class)
            ->createQueryBuilder('ajr')
            ->leftJoin('ajr.reviewedBy', 'reviewer')
            ->leftJoin('reviewer.profile', 'reviewerProfile')
            ->leftJoin('ajr.user', 'u')
            ->leftJoin('u.profile', 'p')
            ->addSelect('reviewer', 'reviewerProfile', 'u', 'p')
            ->where('ajr.status <> :status')
            ->setParameter('status', 'pending')
            ->orderBy('ajr.updatedAt', 'DESC')
            ->setMaxResults(100);

        $isSuperAdmin = $this->adminCapabilityChecker->isSuperAdmin($currentUser);
        if ($isGlobalAdmin && !$isSuperAdmin) {
            $managedOrgIds = $this->getManagedOrganisationIds($currentUser);
            if ($managedOrgIds === null) {
                // Kein Scope-Filter
            } elseif (count($managedOrgIds) > 0) {
                $qb->andWhere('(ajr.requestedOrganisationId IS NULL OR ajr.requestedOrganisationId IN (:managedOrgIds))')
                    ->setParameter('managedOrgIds', $managedOrgIds);
            } else {
                $qb->andWhere('ajr.requestedOrganisationId IS NULL');
            }
        }

        $requests = $qb->getQuery()->getResult();

        $result = [];
        foreach ($requests as $req) {
            $profile = $req->getUser()?->getProfile();
            $reviewer = $req->getReviewedBy()?->getProfile();
            $result[] = [
                'id' => $req->getId(),
                'user_id' => $req->getUserId(),
                'name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
                'email' => $profile?->getEmail(),
                'requested_department_name' => $req->getRequestedDepartmentName(),
                'requested_affiliation' => $req->getRequestedAffiliation(),
                'requested_organisation_id' => $req->getRequestedOrganisationId(),
                'requested_parent_department_name' => $req->getRequestedParentDepartmentName(),
                'message' => $req->getMessage(),
                'status' => $req->getStatus(),
                'assigned_department_id' => $req->getAssignedDepartmentId(),
                'assigned_department_name' => $req->getAssignedDepartment()?->getName(),
                'created_at' => $req->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'updated_at' => $req->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                'reviewed_by_name' => $reviewer ? $reviewer->getDisplayName() : null,
            ];
        }

        return new JsonResponse($result);
    }

    #[Route('/admin-request/{id}', name: 'admin_request_decide', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function decideAdminRequest(string $id, Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        /** @var AdminJoinRequest|null $adminRequest */
        $adminRequest = $this->entityManager->getRepository(AdminJoinRequest::class)->find($id);
        if (!$adminRequest) {
            return new JsonResponse(['error' => 'Admin-Anfrage nicht gefunden'], 404);
        }
        if ($adminRequest->getStatus() !== 'pending') {
            return new JsonResponse(['error' => 'Anfrage wurde bereits bearbeitet'], 409);
        }

        $departmentId = trim((string) $request->query->get('department_id', ''));
        $isGlobalAdmin = $this->hasGlobalAdminRole($currentUser);

        if (!$isGlobalAdmin) {
            if ($departmentId === '') {
                return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
            }
            $myMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
                'userId' => $currentUser->getId(),
                'departmentId' => $departmentId,
            ]);
            if (!$myMembership || !in_array($myMembership->getRole(), ['mw', 'dc'], true)) {
                return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
            }
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $decision = strtolower(trim((string) ($data['status'] ?? '')));
        if ($decision !== 'rejected') {
            return new JsonResponse(['error' => 'Fuer Admin-Anfragen ist hier nur rejected erlaubt'], 400);
        }

        $adminRequest->setReviewedBy($currentUser);
        $adminRequest->setStatus($decision);
        $this->logAdminJoinRequestEvent($adminRequest, $currentUser, 'rejected', ['status' => $decision]);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'status' => $adminRequest->getStatus(),
        ]);
    }

    #[Route('/departments/search', name: 'departments_search', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function searchDepartments(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        $query = trim((string) $request->query->get('q', ''));
        if (mb_strlen($query) < 2) {
            return new JsonResponse([]);
        }

        $qb = $this->entityManager->getRepository(Department::class)->createQueryBuilder('d');
        $departments = $qb
            ->innerJoin('d.organisation', 'o')
            ->addSelect('o')
            ->where('LOWER(d.name) LIKE :q OR LOWER(o.name) LIKE :q OR d.id LIKE :qExact')
            ->setParameter('q', '%' . mb_strtolower($query) . '%')
            ->setParameter('qExact', '%' . strtoupper($query) . '%')
            ->orderBy('d.name', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($departments as $department) {
            $alreadyMember = $this->entityManager->getRepository(Membership::class)->findOneBy([
                'userId' => $currentUser->getId(),
                'departmentId' => $department->getId(),
            ]);
            if ($alreadyMember) {
                continue;
            }

            $result[] = [
                'id' => $department->getId(),
                'name' => $department->getName(),
                'organisation_name' => $department->getOrganisation()->getName(),
            ];
        }

        return new JsonResponse($result);
    }

    #[Route('/mine', name: 'mine', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function mine(): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        $requests = $this->entityManager->getRepository(JoinRequest::class)
            ->createQueryBuilder('jr')
            ->innerJoin('jr.department', 'd')
            ->innerJoin('d.organisation', 'o')
            ->addSelect('d', 'o')
            ->where('jr.userId = :userId')
            ->setParameter('userId', $currentUser->getId())
            ->orderBy('jr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($requests as $jr) {
            if (!$jr instanceof JoinRequest) {
                continue;
            }
            $dept = $jr->getDepartment();
            $result[] = [
                'id' => $jr->getId(),
                'request_kind' => 'department_join',
                'status' => $jr->getStatus(),
                'department_id' => $jr->getDepartmentId(),
                'department_name' => $dept?->getName(),
                'organisation_name' => $dept?->getOrganisation()?->getName(),
                'message' => $jr->getMessage(),
                'created_at' => $jr->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'updated_at' => $jr->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        $adminRequests = $this->entityManager->getRepository(AdminJoinRequest::class)->findBy(
            ['userId' => $currentUser->getId()],
            ['createdAt' => 'DESC'],
            20
        );
        foreach ($adminRequests as $adminRequest) {
            $displayStatus = $adminRequest->getStatus();
            if ($displayStatus === 'assigned') {
                $displayStatus = 'approved';
            }
            $orgName = null;
            if ($adminRequest->getRequestedOrganisationId() !== null) {
                $org = $this->entityManager->getRepository(Organisation::class)->find($adminRequest->getRequestedOrganisationId());
                $orgName = $org?->getName();
            }
            $result[] = [
                'id' => $adminRequest->getId(),
                'request_kind' => 'admin',
                'status' => $displayStatus,
                'department_id' => $adminRequest->getAssignedDepartmentId(),
                'department_name' => $adminRequest->getRequestedDepartmentName(),
                'organisation_name' => $orgName,
                'requested_parent_department_name' => $adminRequest->getRequestedParentDepartmentName(),
                'requested_affiliation' => $adminRequest->getRequestedAffiliation(),
                'message' => $adminRequest->getMessage(),
                'created_at' => $adminRequest->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'updated_at' => $adminRequest->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        usort($result, static fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return new JsonResponse($result);
    }

    #[Route('/invite', name: 'invite_get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getInvite(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        $departmentId = trim((string) $request->query->get('department_id', ''));
        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $myMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $currentUser->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$myMembership || !in_array($myMembership->getRole(), self::MANAGER_ROLES, true)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $inviteSetting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $departmentId,
            'settingKey' => self::INVITE_CODE_SETTING_KEY,
        ]);

        if (!$inviteSetting) {
            $inviteSetting = new DepartmentSetting();
            $inviteSetting->setId(IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class));
            $inviteSetting->setDepartment($department);
            $inviteSetting->setSettingKey(self::INVITE_CODE_SETTING_KEY);
            $inviteSetting->setSettingValue($this->generateInviteCode());
            $inviteSetting->setUpdatedAt(new \DateTime());
            $this->entityManager->persist($inviteSetting);
            $this->entityManager->flush();
        }

        return new JsonResponse($this->buildInviteResponse($department, $inviteSetting));
    }

    #[Route('/invite/regenerate', name: 'invite_regenerate', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function regenerateInvite(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $departmentId = trim((string) ($data['department_id'] ?? ''));
        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $myMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $currentUser->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$myMembership || !in_array($myMembership->getRole(), self::MANAGER_ROLES, true)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $inviteSetting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $departmentId,
            'settingKey' => self::INVITE_CODE_SETTING_KEY,
        ]);
        if (!$inviteSetting) {
            $inviteSetting = new DepartmentSetting();
            $inviteSetting->setId(IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class));
            $inviteSetting->setDepartment($department);
            $inviteSetting->setSettingKey(self::INVITE_CODE_SETTING_KEY);
            $this->entityManager->persist($inviteSetting);
        }

        $inviteSetting->setSettingValue($this->generateInviteCode());
        $inviteSetting->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return new JsonResponse($this->buildInviteResponse($department, $inviteSetting));
    }

    #[Route('/invite/pending', name: 'invite_pending_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createPendingInvite(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $departmentId = trim((string) ($data['department_id'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $userId = trim((string) ($data['user_id'] ?? ''));
        $requestedRole = strtolower(trim((string) ($data['role'] ?? 'u')));
        $isPrimary = !empty($data['is_primary']);
        $groupIds = is_array($data['group_ids'] ?? null) ? array_values(array_filter($data['group_ids'], 'is_string')) : [];

        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        if ($email === '' && $userId !== '') {
            $resolvedUser = $this->entityManager->getRepository(User::class)->find($userId);
            if (!$resolvedUser || !$resolvedUser->getProfile()) {
                return new JsonResponse(['error' => 'User nicht gefunden'], 404);
            }
            $email = strtolower(trim($resolvedUser->getProfile()->getEmail()));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Ungueltige E-Mail-Adresse'], 400);
        }
        if (!in_array($requestedRole, self::VALID_MEMBER_ROLES, true)) {
            return new JsonResponse(['error' => 'Ungueltige Rolle. Erlaubt: mw, dc, l1, l2, l3, u'], 400);
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $myMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $currentUser->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$myMembership || !in_array($myMembership->getRole(), self::MANAGER_ROLES, true)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $inviteSetting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $departmentId,
            'settingKey' => self::INVITE_CODE_SETTING_KEY,
        ]);
        if (!$inviteSetting) {
            $inviteSetting = new DepartmentSetting();
            $inviteSetting->setId(IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class));
            $inviteSetting->setDepartment($department);
            $inviteSetting->setSettingKey(self::INVITE_CODE_SETTING_KEY);
            $inviteSetting->setSettingValue($this->generateInviteCode());
            $inviteSetting->setUpdatedAt(new \DateTime());
            $this->entityManager->persist($inviteSetting);
        }

        $invitee = $this->findUserByEmail($email);
        if ($invitee !== null) {
            $existingMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
                'userId' => $invitee->getId(),
                'departmentId' => $departmentId,
            ]);
            if ($existingMembership) {
                return new JsonResponse(['error' => 'User ist bereits Mitglied dieses Departments'], 409);
            }
        }

        $pendingInvites = $this->readPendingInvites($departmentId);
        foreach ($pendingInvites as $invite) {
            if (
                strtolower((string) ($invite['email'] ?? '')) === $email
                && ($invite['status'] ?? 'pending') === 'pending'
            ) {
                return new JsonResponse(['error' => 'Fuer diese E-Mail existiert bereits eine offene Einladung'], 409);
            }
        }

        $entryId = IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class);
        $frontendBase = rtrim($this->frontendUrl, '/');
        $inviteUrl = $frontendBase
            . '/pending-assignment?join_code=' . urlencode($inviteSetting->getSettingValue())
            . '&invite_role=' . urlencode($requestedRole)
            . '&invite_email=' . urlencode($email)
            . '&invite_id=' . urlencode($entryId);

        $inviterName = trim((string) ($currentUser->getProfile()?->getDisplayName() ?? ''));
        if ($inviterName === '') {
            $inviterName = trim((string) ($currentUser->getProfile()?->getEmail() ?? ''));
        }
        if ($inviterName === '') {
            $dTpl = $this->mailTemplateContent->getTemplate('department.invite', 'de');
            $inviterName = (string) (is_array($dTpl) ? ($dTpl['inviter_name_fallback'] ?? '') : '');
        }

        $entry = [
            'id' => $entryId,
            'email' => $email,
            'role' => $requestedRole,
            'status' => 'pending',
            'is_primary' => $isPrimary,
            'group_ids' => $groupIds,
            'invited_user_id' => $invitee?->getId(),
            'invite_url' => $inviteUrl,
            'created_at' => (new \DateTime())->format(\DateTimeInterface::ATOM),
            'created_by_user_id' => $currentUser->getId(),
            'created_by_name' => $inviterName,
        ];

        $mailSent = false;
        if ($invitee === null) {
            try {
                $this->verificationEmailService->sendDepartmentInviteEmail(
                    $email,
                    $email,
                    $inviterName,
                    $department->getName(),
                    $inviteUrl,
                    $this->labelForMemberRole($requestedRole)
                );
                $mailSent = true;
            } catch (\Throwable) {
                return new JsonResponse([
                    'error' => 'Einladungs-E-Mail konnte nicht versendet werden. Bitte E-Mail-Adresse pruefen oder spaeter erneut versuchen.'
                ], 400);
            }
        } else {
            $this->userDepartmentInviteNotifications->notifyDepartmentInvite($invitee, $department, $entry);
        }

        $pendingInvites[] = $entry;
        $this->writePendingInvites($department, $pendingInvites);

        $entry['mail_sent'] = $mailSent;
        $entry['in_app_notified'] = $invitee !== null;

        return new JsonResponse($this->enrichInviteEntry($entry), 201);
    }

    #[Route('/invite/received', name: 'invite_received_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listReceivedDepartmentInvites(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        $bucket = strtolower(trim((string) $request->query->get('bucket', 'all')));
        if (!in_array($bucket, ['unread', 'read', 'all'], true)) {
            $bucket = 'all';
        }
        $limit = (int) $request->query->get('limit', 50);
        if ($limit < 1) {
            $limit = 50;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $items = $this->userDepartmentInviteNotifications->listInboxForUser($currentUser->getId(), $bucket, $limit);
        $unreadCount = $this->userDepartmentInviteNotifications->countUnreadPending($currentUser->getId());

        return new JsonResponse([
            'count' => count($items),
            'unread_count' => $unreadCount,
            'items' => $items,
        ]);
    }

    #[Route('/invite/received/{notificationId}/read', name: 'invite_received_read', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function markReceivedDepartmentInviteRead(string $notificationId): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        if (!$this->userDepartmentInviteNotifications->markRead($currentUser->getId(), $notificationId)) {
            return new JsonResponse(['error' => 'Benachrichtigung nicht gefunden'], 404);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/invite/accept', name: 'invite_accept', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function acceptDepartmentInvite(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $notificationId = trim((string) ($data['notification_id'] ?? ''));
        $departmentId = trim((string) ($data['department_id'] ?? ''));
        $inviteId = trim((string) ($data['invite_id'] ?? ''));

        $resolved = $this->resolveInviteForUser($currentUser, $notificationId, $departmentId, $inviteId);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        [$department, $invite] = $resolved;

        if (($invite['status'] ?? 'pending') !== 'pending') {
            return new JsonResponse(['error' => 'Einladung wurde bereits bearbeitet'], 409);
        }

        try {
            $this->applyDepartmentInviteMembership($currentUser, $department, $invite);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        }

        $this->finalizeInviteAccepted($department, $invite, $currentUser);
        return new JsonResponse([
            'success' => true,
            'department_id' => $department->getId(),
            'department_name' => $department->getName(),
            'reload_required' => true,
        ]);
    }

    #[Route('/invite/decline', name: 'invite_decline', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function declineDepartmentInvite(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $notificationId = trim((string) ($data['notification_id'] ?? ''));
        $departmentId = trim((string) ($data['department_id'] ?? ''));
        $inviteId = trim((string) ($data['invite_id'] ?? ''));

        $resolved = $this->resolveInviteForUser($currentUser, $notificationId, $departmentId, $inviteId);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        [$department, $invite] = $resolved;

        if (($invite['status'] ?? 'pending') !== 'pending') {
            return new JsonResponse(['error' => 'Einladung wurde bereits bearbeitet'], 409);
        }

        $this->finalizeInviteDeclined($department, $invite, $currentUser);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/invite/pending', name: 'invite_pending_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listPendingInvites(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        $departmentId = trim((string) $request->query->get('department_id', ''));
        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        if (!$this->canManageDepartmentInvites($currentUser, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        return new JsonResponse($this->listInvitesOverview($departmentId));
    }

    #[Route('/invite/notifications', name: 'invite_notifications_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listInviteNotifications(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        $departmentId = trim((string) $request->query->get('department_id', ''));
        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        if (!$this->canManageDepartmentInvites($currentUser, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $bucket = trim((string) $request->query->get('bucket', 'all'));
        if (!in_array($bucket, ['unread', 'read', 'all'], true)) {
            $bucket = 'all';
        }
        $limit = max(1, min(100, (int) $request->query->get('limit', 50)));

        return new JsonResponse(
            $this->inboxMessages->listInviteAcceptedForInviter($departmentId, $currentUser->getId(), $bucket, $limit),
        );
    }

    #[Route('/invite/notifications/{notificationId}/read', name: 'invite_notification_read', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function markInviteNotificationRead(string $notificationId, Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        $departmentId = trim((string) $request->query->get('department_id', ''));
        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        if (!$this->canManageDepartmentInvites($currentUser, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        if (!$this->inboxMessages->markInviteAcceptedRead($departmentId, $currentUser->getId(), $notificationId)) {
            return new JsonResponse(['error' => 'Benachrichtigung nicht gefunden'], 404);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/invite/pending/{inviteId}', name: 'invite_pending_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deletePendingInvite(string $inviteId, Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        $departmentId = trim((string) $request->query->get('department_id', ''));
        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $myMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $currentUser->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$myMembership || !in_array($myMembership->getRole(), self::MANAGER_ROLES, true)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $pendingInvites = $this->readPendingInvites($departmentId);
        $filtered = array_values(array_filter($pendingInvites, fn ($entry) => (string) ($entry['id'] ?? '') !== $inviteId));

        if (count($filtered) === count($pendingInvites)) {
            return new JsonResponse(['error' => 'Einladung nicht gefunden'], 404);
        }

        $this->writePendingInvites($department, $filtered);
        return new JsonResponse(['success' => true]);
    }

    #[Route('/pending', name: 'pending', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function pending(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        $departmentId = trim((string) $request->query->get('department_id', ''));
        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        $myMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $currentUser->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$myMembership || !in_array($myMembership->getRole(), self::MANAGER_ROLES, true)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $requests = $this->entityManager->getRepository(JoinRequest::class)
            ->createQueryBuilder('jr')
            ->innerJoin('jr.user', 'u')
            ->innerJoin('u.profile', 'p')
            ->addSelect('u', 'p')
            ->where('jr.departmentId = :departmentId')
            ->andWhere('jr.status = :status')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('status', 'pending')
            ->orderBy('jr.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($requests as $jr) {
            $profile = $jr->getUser()?->getProfile();
            $result[] = [
                'id' => $jr->getId(),
                'user_id' => $jr->getUserId(),
                'name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
                'email' => $profile?->getEmail(),
                'message' => $jr->getMessage(),
                'created_at' => $jr->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        return new JsonResponse($result);
    }

    #[Route('/{id}', name: 'decide', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function decide(string $id, Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        /** @var JoinRequest|null $joinRequest */
        $joinRequest = $this->entityManager->getRepository(JoinRequest::class)->find($id);
        if (!$joinRequest) {
            return new JsonResponse(['error' => 'Anfrage nicht gefunden'], 404);
        }

        if ($joinRequest->getStatus() !== 'pending') {
            return new JsonResponse(['error' => 'Anfrage wurde bereits bearbeitet'], 409);
        }

        if (!$this->canDecideJoinRequest($currentUser, $joinRequest)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $decision = strtolower(trim((string) ($data['status'] ?? '')));
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return new JsonResponse(['error' => 'Status muss approved oder rejected sein'], 400);
        }

        $joinRequest->setReviewedBy($currentUser);
        $joinRequest->setStatus($decision);

        if ($decision === 'approved' && $joinRequest->getDepartment()) {
            $this->createMembershipForUser($joinRequest->getUser(), $joinRequest->getDepartment(), 'u', $currentUser);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'status' => $joinRequest->getStatus(),
        ]);
    }

    private function canDecideJoinRequest(User $currentUser, JoinRequest $joinRequest): bool
    {
        if ($this->adminCapabilityChecker->can($currentUser, 'support_requests.assign')) {
            return true;
        }

        $departmentId = $joinRequest->getDepartmentId();
        $myMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $currentUser->getId(),
            'departmentId' => $departmentId,
        ]);

        return $myMembership !== null && in_array($myMembership->getRole(), self::MANAGER_ROLES, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePendingDepartmentJoinRequest(JoinRequest $joinRequest): array
    {
        $profile = $joinRequest->getUser()?->getProfile();
        $department = $joinRequest->getDepartment();
        $organisation = $department?->getOrganisation();

        return [
            'id' => $joinRequest->getId(),
            'request_kind' => 'department_join',
            'user_id' => $joinRequest->getUserId(),
            'name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
            'email' => $profile?->getEmail(),
            'requested_department_name' => $department?->getName(),
            'target_department_id' => $department?->getId(),
            'target_department_name' => $department?->getName(),
            'requested_organisation_id' => $department?->getOrganisationId(),
            'organisation_name' => $organisation?->getName(),
            'message' => $joinRequest->getMessage(),
            'status' => $joinRequest->getStatus(),
            'created_at' => $joinRequest->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param list<AdminJoinRequest> $adminRequests
     */
    private function removeStaleAutoAdminRequestsForUsersWithPendingJoin(array $adminRequests): void
    {
        $removed = false;
        foreach ($adminRequests as $adminRequest) {
            if (!$adminRequest instanceof AdminJoinRequest) {
                continue;
            }
            if (!$this->isAutoUnknownAdminRequest($adminRequest)) {
                continue;
            }
            $pendingJoin = $this->entityManager->getRepository(JoinRequest::class)->findOneBy([
                'userId' => $adminRequest->getUserId(),
                'status' => 'pending',
            ]);
            if ($pendingJoin instanceof JoinRequest) {
                $this->entityManager->remove($adminRequest);
                $removed = true;
            }
        }
        if ($removed) {
            $this->entityManager->flush();
        }
    }

    private function isAutoUnknownAdminRequest(AdminJoinRequest $adminRequest): bool
    {
        if ($adminRequest->getRequestedDepartmentName() !== 'Unbekannte Abteilung') {
            return false;
        }
        $message = $adminRequest->getMessage() ?? '';

        return str_starts_with($message, 'Automatisch erstellt:');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateTurnstileToken(Request $request, array $data): ?JsonResponse
    {
        if (!$this->turnstileVerifier->mustValidateCaptcha()) {
            return null;
        }

        $token = trim((string) ($data['turnstileToken'] ?? $data['turnstile_token'] ?? ''));
        $clientIp = (string) ($request->getClientIp() ?? 'unknown');
        if ($token === '' || !$this->turnstileVerifier->verify($token, $clientIp !== 'unknown' ? $clientIp : null)) {
            return new JsonResponse(['error' => 'Captcha-Verifikation fehlgeschlagen. Bitte erneut versuchen.'], 400);
        }

        return null;
    }

    private function createMembershipForUser(User $user, Department $department, string $role, ?User $actor): Membership
    {
        $existingMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $department->getId(),
        ]);
        if ($existingMembership) {
            return $existingMembership;
        }

        $membership = new Membership();
        $membership->setUser($user);
        $membership->setDepartment($department);
        $membership->setRole($role);

        $hasAnyMembership = count($this->entityManager->getRepository(Membership::class)->findBy([
            'userId' => $user->getId(),
        ])) > 0;
        $membership->setIsPrimary(!$hasAnyMembership);
        $this->auditLogger->log(
            'membership',
            AuditLogger::buildMembershipEntityId($membership->getUserId(), $membership->getDepartmentId()),
            'membership_created',
            $actor,
            $membership->getUser(),
            $membership->getDepartment(),
            [
                'role' => ['old' => null, 'new' => $membership->getRole()],
                'is_primary' => ['old' => null, 'new' => $membership->getIsPrimary()],
            ]
        );
        $this->entityManager->persist($membership);

        return $membership;
    }

    private function normalizeJoinCode(string $code): string
    {
        $upper = strtoupper($code);
        return preg_replace('/[^A-Z0-9]/', '', $upper) ?? '';
    }

    private function labelForMemberRole(string $role): string
    {
        return match (strtolower(trim($role))) {
            'mw' => 'Materialchef',
            'dc' => 'Departmentchef',
            'l1' => 'Leiter 1',
            'l2' => 'Leiter 2',
            'l3' => 'Leiter 3',
            default => 'Mitglied',
        };
    }

    private function generateInviteCode(): string
    {
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $existing = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
                'settingKey' => self::INVITE_CODE_SETTING_KEY,
                'settingValue' => $code,
            ]);
        } while ($existing !== null);

        return $code;
    }

    private function buildInviteResponse(Department $department, DepartmentSetting $setting): array
    {
        $joinCode = $setting->getSettingValue();
        $frontendBase = rtrim($this->frontendUrl, '/');
        $inviteUrl = $frontendBase . '/pending-assignment?join_code=' . urlencode($joinCode);
        $pendingPath = '/pending-assignment?join_code=' . urlencode($joinCode);
        $organisationId = $department->getOrganisationId();
        $departmentName = $department->getName();
        $organisationName = $department->getOrganisation()->getName();
        $registerInviteUrl = $frontendBase . '/login?' . http_build_query([
            'register' => '1',
            'org_id' => $organisationId,
            'org_name' => $organisationName,
            'dept_name' => $departmentName,
            'redirect' => $pendingPath,
        ], '', '&', \PHP_QUERY_RFC3986);

        return [
            'department_id' => $department->getId(),
            'department_name' => $departmentName,
            'organisation_id' => $organisationId,
            'join_code' => $joinCode,
            'invite_url' => $inviteUrl,
            'qr_payload' => $inviteUrl,
            'register_invite_url' => $registerInviteUrl,
            'register_qr_payload' => $registerInviteUrl,
            'updated_at' => $setting->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function readPendingInvites(string $departmentId): array
    {
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $departmentId,
            'settingKey' => self::PENDING_INVITES_SETTING_KEY,
        ]);
        if (!$setting) {
            return [];
        }

        $raw = trim((string) $setting->getSettingValue());
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? array_values($decoded) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function writePendingInvites(Department $department, array $entries): void
    {
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $department->getId(),
            'settingKey' => self::PENDING_INVITES_SETTING_KEY,
        ]);

        if (!$setting) {
            $setting = new DepartmentSetting();
            $setting->setId(IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class));
            $setting->setDepartment($department);
            $setting->setSettingKey(self::PENDING_INVITES_SETTING_KEY);
            $this->entityManager->persist($setting);
        }

        $setting->setSettingValue(json_encode(array_values($entries), JSON_UNESCAPED_SLASHES) ?: '[]');
        $setting->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }

    private function canManageDepartmentInvites(User $user, string $departmentId): bool
    {
        if ($this->hasGlobalAdminRole($user)) {
            return true;
        }
        $myMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);

        return $myMembership !== null && in_array($myMembership->getRole(), self::MANAGER_ROLES, true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listOpenPendingInvites(string $departmentId): array
    {
        return array_values(array_filter(
            $this->readPendingInvites($departmentId),
            static fn (array $entry): bool => ($entry['status'] ?? 'pending') === 'pending'
        ));
    }

    /**
     * Alle Einladungen (offen + angenommen) für die Übersicht beim Einladenden.
     *
     * @return list<array<string, mixed>>
     */
    private function listInvitesOverview(string $departmentId): array
    {
        $entries = array_map(
            fn (array $entry): array => $this->enrichInviteEntry($entry),
            $this->readPendingInvites($departmentId)
        );

        usort($entries, function (array $a, array $b): int {
            $aPending = ($a['status'] ?? 'pending') === 'pending';
            $bPending = ($b['status'] ?? 'pending') === 'pending';
            if ($aPending !== $bPending) {
                return $aPending ? -1 : 1;
            }

            $aTime = (string) ($aPending ? ($a['created_at'] ?? '') : ($a['accepted_at'] ?? $a['created_at'] ?? ''));
            $bTime = (string) ($bPending ? ($b['created_at'] ?? '') : ($b['accepted_at'] ?? $b['created_at'] ?? ''));

            return strcmp($bTime, $aTime);
        });

        return $entries;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function enrichInviteEntry(array $entry): array
    {
        $email = strtolower(trim((string) ($entry['email'] ?? '')));
        $invitee = $email !== '' ? $this->findUserByEmail($email) : null;
        $entry['user_registered'] = $invitee !== null;
        $profile = $invitee?->getProfile();
        if ($profile) {
            $displayName = trim($profile->getDisplayName());
            if ($displayName !== '') {
                $entry['user_name'] = $displayName;
            }
        }

        return $entry;
    }

    /**
     * @param array<string, mixed> $invite
     */
    private function applyDepartmentInviteMembership(User $user, Department $department, array $invite): void
    {
        $existing = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $department->getId(),
        ]);
        if ($existing) {
            throw new \RuntimeException('Du bist bereits Mitglied dieses Departments');
        }

        $role = strtolower(trim((string) ($invite['role'] ?? 'u')));
        if (!in_array($role, self::VALID_MEMBER_ROLES, true)) {
            $role = 'u';
        }

        $membership = new Membership();
        $membership->setUser($user);
        $membership->setDepartment($department);
        $membership->setRole($role);

        $hasAnyMembership = count($this->entityManager->getRepository(Membership::class)->findBy([
            'userId' => $user->getId(),
        ])) > 0;
        $isPrimary = !empty($invite['is_primary']);
        $membership->setIsPrimary($isPrimary || !$hasAnyMembership);

        $this->auditLogger->log(
            'membership',
            AuditLogger::buildMembershipEntityId($user->getId(), $department->getId()),
            'membership_created',
            $user,
            $user,
            $department,
            [
                'role' => ['old' => null, 'new' => $membership->getRole()],
                'is_primary' => ['old' => null, 'new' => $membership->getIsPrimary()],
            ]
        );
        $this->entityManager->persist($membership);

        $groupIds = is_array($invite['group_ids'] ?? null) ? $invite['group_ids'] : [];
        foreach ($groupIds as $groupId) {
            if (!is_string($groupId) || $groupId === '') {
                continue;
            }
            $group = $this->entityManager->getRepository(Group::class)->find($groupId);
            if (!$group || $group->getDepartmentId() !== $department->getId()) {
                continue;
            }
            $existingGroup = $this->entityManager->getRepository(GroupMembership::class)->findOneBy([
                'userId' => $user->getId(),
                'groupId' => $groupId,
            ]);
            if ($existingGroup) {
                continue;
            }
            $groupMembership = new GroupMembership();
            $groupMembership->setUser($user);
            $groupMembership->setGroup($group);
            $groupMembership->setRole('member');
            $groupMembership->setIsPrimary(false);
            $this->entityManager->persist($groupMembership);
        }

        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $invite
     */
    private function finalizeInviteAccepted(Department $department, array $invite, User $joinedUser): void
    {
        $profile = $joinedUser->getProfile();
        $email = strtolower(trim((string) ($profile?->getEmail() ?? $invite['email'] ?? '')));
        $inviteId = (string) ($invite['id'] ?? '');

        $pendingInvites = $this->readPendingInvites($department->getId());
        foreach ($pendingInvites as &$entry) {
            if ((string) ($entry['id'] ?? '') === $inviteId) {
                $entry['status'] = 'accepted';
                $entry['accepted_at'] = (new \DateTime())->format(\DateTimeInterface::ATOM);
                $entry['accepted_user_id'] = $joinedUser->getId();
                $entry['accepted_user_name'] = $profile ? $profile->getDisplayName() : '';
                $invite = $entry;
                break;
            }
        }
        unset($entry);

        $this->writePendingInvites($department, $pendingInvites);
        $this->appendInviteAcceptedNotification($department, $invite, $joinedUser);
        $this->userDepartmentInviteNotifications->markInviteAccepted($joinedUser, $department, $inviteId);
    }

    /**
     * @param array<string, mixed> $invite
     */
    private function finalizeInviteDeclined(Department $department, array $invite, User $user): void
    {
        $inviteId = (string) ($invite['id'] ?? '');
        $profile = $user->getProfile();
        $declinerName = $profile ? $profile->getDisplayName() : '';

        $pendingInvites = $this->readPendingInvites($department->getId());
        foreach ($pendingInvites as &$entry) {
            if ((string) ($entry['id'] ?? '') === $inviteId) {
                $entry['status'] = 'declined';
                $entry['declined_at'] = (new \DateTime())->format(\DateTimeInterface::ATOM);
                $entry['declined_user_id'] = $user->getId();
                $entry['declined_user_name'] = $declinerName;
                break;
            }
        }
        unset($entry);

        $this->writePendingInvites($department, $pendingInvites);
        $this->userDepartmentInviteNotifications->markInviteDeclined($user, $department, $inviteId);
    }

    /**
     * @return array{0: Department, 1: array<string, mixed>}|JsonResponse
     */
    private function resolveInviteForUser(
        User $user,
        string $notificationId,
        string $departmentId,
        string $inviteId
    ): array|JsonResponse {
        $profile = $user->getProfile();
        if (!$profile) {
            return new JsonResponse(['error' => 'Profil nicht gefunden'], 404);
        }

        $userEmail = strtolower(trim($profile->getEmail()));

        if ($notificationId !== '') {
            $settings = $this->entityManager->getRepository(DepartmentSetting::class)->findBy([
                'settingKey' => UserDepartmentInviteNotificationService::SETTING_KEY_PREFIX . $user->getId(),
            ]);
            foreach ($settings as $setting) {
                $entries = json_decode((string) $setting->getSettingValue(), true);
                if (!is_array($entries)) {
                    continue;
                }
                foreach ($entries as $entry) {
                    if (!is_array($entry) || (string) ($entry['id'] ?? '') !== $notificationId) {
                        continue;
                    }
                    $departmentId = (string) ($entry['department_id'] ?? $setting->getDepartmentId());
                    $inviteId = (string) ($entry['invite_id'] ?? '');
                    break 2;
                }
            }
        }

        if ($departmentId === '' || $inviteId === '') {
            return new JsonResponse(['error' => 'Einladung nicht gefunden'], 404);
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $invite = null;
        foreach ($this->readPendingInvites($departmentId) as $entry) {
            if ((string) ($entry['id'] ?? '') === $inviteId) {
                $invite = $entry;
                break;
            }
        }

        if (!$invite) {
            return new JsonResponse(['error' => 'Einladung nicht gefunden'], 404);
        }

        if (strtolower((string) ($invite['email'] ?? '')) !== $userEmail) {
            return new JsonResponse(['error' => 'Diese Einladung ist nicht fuer dich bestimmt'], 403);
        }

        return [$department, $invite];
    }

    private function findUserByEmail(string $email): ?User
    {
        $profile = $this->entityManager->getRepository(Profile::class)->findOneBy([
            'email' => strtolower(trim($email)),
        ]);
        if (!$profile) {
            return null;
        }

        return $this->entityManager->getRepository(User::class)->findOneBy([
            'profileId' => $profile->getId(),
        ]);
    }

    private function appendInviteAcceptedNotification(Department $department, array $invite, User $joinedUser): void
    {
        $this->inboxMessages->notifyInviteAccepted($department, $invite, $joinedUser);
    }
}
