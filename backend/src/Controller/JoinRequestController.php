<?php

namespace App\Controller;

use App\Entity\AdminJoinRequest;
use App\Entity\AdminJoinRequestEvent;
use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Entity\JoinRequest;
use App\Entity\Membership;
use App\Entity\User;
use App\Service\AuditLogger;
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

    private function hasGlobalAdminRole(User $user): bool
    {
        return count(array_intersect(self::GLOBAL_ADMIN_ROLES, $user->getRoles())) > 0;
    }

    /** @return string[] Organisation-IDs aus den Departments des Users (für ORG/SUBORG-Filter) */
    private function getManagedOrganisationIds(User $user): array
    {
        $rows = $this->entityManager->createQuery(
            'SELECT DISTINCT d.organisationId FROM App\Entity\Membership m
             JOIN App\Entity\Department d WITH d.id = m.departmentId
             WHERE m.userId = :userId'
        )->setParameter('userId', $user->getId())->getResult();
        return array_map(fn ($r) => $r['organisationId'], $rows);
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

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuditLogger $auditLogger,
        private VerificationEmailService $verificationEmailService,
        #[Autowire('%env(APP_FRONTEND_URL)%')] private string $frontendUrl
    )
    {
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

        $joinRequest = new JoinRequest();
        $joinRequest->setId(IdGenerator::generateUnique($this->entityManager, JoinRequest::class));
        $joinRequest->setUser($currentUser);
        $joinRequest->setDepartment($department);
        $joinRequest->setMessage($message !== '' ? $message : null);

        // Join-Link (join_code) fuehrt direkt zur Department-Zuordnung mit gewaehlter Rolle.
        $autoJoinByInviteLink = $joinCode !== '';
        if ($autoJoinByInviteLink) {
            $membership = new Membership();
            $membership->setUser($currentUser);
            $membership->setDepartment($department);
            $membership->setRole($requestedRole);
            $hasAnyMembership = count($this->entityManager->getRepository(Membership::class)->findBy([
                'userId' => $currentUser->getId(),
            ])) > 0;
            $membership->setIsPrimary(!$hasAnyMembership);
            $this->entityManager->persist($membership);

            $joinRequest->setStatus('approved');
            $joinRequest->setReviewedBy($currentUser);
        } else {
            $joinRequest->setStatus('pending');
        }

        $this->entityManager->persist($joinRequest);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $joinRequest->getId(),
            'status' => $joinRequest->getStatus(),
            'department_id' => $department->getId(),
            'department_name' => $department->getName(),
            'assigned_role' => $autoJoinByInviteLink ? $requestedRole : null,
            'auto_joined' => $autoJoinByInviteLink,
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
        $requestedDepartmentName = trim((string) ($data['requested_department_name'] ?? ''));
        $requestedAffiliation = trim((string) ($data['requested_affiliation'] ?? ''));
        $requestedOrganisationId = trim((string) ($data['requested_organisation_id'] ?? ''));
        $requestedParentDepartmentName = trim((string) ($data['requested_parent_department_name'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));

        if ($requestedDepartmentName === '') {
            return new JsonResponse(['error' => 'requested_department_name ist erforderlich'], 400);
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
        $adminRequest->setMessage($message !== '' ? $message : null);
        $adminRequest->setStatus('pending');

        $this->entityManager->persist($adminRequest);
        $this->logAdminJoinRequestEvent($adminRequest, $currentUser, 'created', [
            'requested_department_name' => $requestedDepartmentName,
            'requested_organisation_id' => $requestedOrganisationId !== '' ? $requestedOrganisationId : null,
            'requested_parent_department_name' => $requestedParentDepartmentName !== '' ? $requestedParentDepartmentName : null,
        ]);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $adminRequest->getId(),
            'status' => $adminRequest->getStatus(),
            'requested_department_name' => $adminRequest->getRequestedDepartmentName(),
            'requested_affiliation' => $adminRequest->getRequestedAffiliation(),
            'requested_organisation_id' => $adminRequest->getRequestedOrganisationId(),
            'requested_parent_department_name' => $adminRequest->getRequestedParentDepartmentName(),
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
            ->where('m.userId IS NULL')
            ->andWhere('existing.id IS NULL')
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

        $isSuperAdmin = in_array('ROLE_SUPERADMIN', $currentUser->getRoles(), true);
        if ($isGlobalAdmin && !$isSuperAdmin) {
            $managedOrgIds = $this->getManagedOrganisationIds($currentUser);
            if (count($managedOrgIds) > 0) {
                // Ohne requested_organisation_id (z. B. Auto-Anfragen) fuer alle Org/Sub-Admins sichtbar
                $qb->andWhere('(ajr.requestedOrganisationId IS NULL OR ajr.requestedOrganisationId IN (:managedOrgIds))')
                    ->setParameter('managedOrgIds', $managedOrgIds);
            } else {
                // Kein Department-Membership: nur globale/unzugeordnete Warteschlange (NULL), nicht 1=0
                $qb->andWhere('ajr.requestedOrganisationId IS NULL');
            }
        }

        $requests = $qb->getQuery()->getResult();

        $result = [];
        foreach ($requests as $req) {
            $profile = $req->getUser()?->getProfile();
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
            ];
        }

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

        $isSuperAdmin = in_array('ROLE_SUPERADMIN', $currentUser->getRoles(), true);
        if ($isGlobalAdmin && !$isSuperAdmin) {
            $managedOrgIds = $this->getManagedOrganisationIds($currentUser);
            if (count($managedOrgIds) > 0) {
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
            ->addSelect('d')
            ->where('jr.userId = :userId')
            ->setParameter('userId', $currentUser->getId())
            ->orderBy('jr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($requests as $jr) {
            $result[] = [
                'id' => $jr->getId(),
                'status' => $jr->getStatus(),
                'department_id' => $jr->getDepartmentId(),
                'department_name' => $jr->getDepartment()?->getName(),
                'message' => $jr->getMessage(),
                'created_at' => $jr->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'updated_at' => $jr->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

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
        $requestedRole = strtolower(trim((string) ($data['role'] ?? 'u')));

        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
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

        $pendingInvites = $this->readPendingInvites($departmentId);
        foreach ($pendingInvites as $invite) {
            if (strtolower((string) ($invite['email'] ?? '')) === $email) {
                return new JsonResponse(['error' => 'Fuer diese E-Mail existiert bereits eine pending Einladung'], 409);
            }
        }

        $frontendBase = rtrim($this->frontendUrl, '/');
        $inviteUrl = $frontendBase
            . '/pending-assignment?join_code=' . urlencode($inviteSetting->getSettingValue())
            . '&invite_role=' . urlencode($requestedRole)
            . '&invite_email=' . urlencode($email)
            . '&auto_join=1';

        $entry = [
            'id' => IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class),
            'email' => $email,
            'role' => $requestedRole,
            'invite_url' => $inviteUrl,
            'created_at' => (new \DateTime())->format(\DateTimeInterface::ATOM),
            'created_by_user_id' => $currentUser->getId(),
        ];

        $inviterName = trim((string) ($currentUser->getProfile()?->getDisplayName() ?? ''));
        if ($inviterName === '') {
            $inviterName = trim((string) ($currentUser->getProfile()?->getEmail() ?? ''));
        }
        if ($inviterName === '') {
            $inviterName = 'Ein Teammitglied';
        }

        try {
            $this->verificationEmailService->sendDepartmentInviteEmail(
                $email,
                $email,
                $inviterName,
                $department->getName(),
                $inviteUrl,
                $this->labelForMemberRole($requestedRole)
            );
        } catch (\Throwable) {
            return new JsonResponse([
                'error' => 'Einladungs-E-Mail konnte nicht versendet werden. Bitte E-Mail-Adresse pruefen oder spaeter erneut versuchen.'
            ], 400);
        }

        $pendingInvites[] = $entry;
        $this->writePendingInvites($department, $pendingInvites);

        $entry['mail_sent'] = true;
        return new JsonResponse($entry, 201);
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

        $myMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $currentUser->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$myMembership || !in_array($myMembership->getRole(), self::MANAGER_ROLES, true)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        return new JsonResponse($this->readPendingInvites($departmentId));
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

        $departmentId = $joinRequest->getDepartmentId();
        $myMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $currentUser->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$myMembership || !in_array($myMembership->getRole(), self::MANAGER_ROLES, true)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $decision = strtolower(trim((string) ($data['status'] ?? '')));
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return new JsonResponse(['error' => 'Status muss approved oder rejected sein'], 400);
        }

        $joinRequest->setReviewedBy($currentUser);
        $joinRequest->setStatus($decision);

        if ($decision === 'approved') {
            $existingMembership = $this->entityManager->getRepository(Membership::class)->findOneBy([
                'userId' => $joinRequest->getUserId(),
                'departmentId' => $departmentId,
            ]);

            if (!$existingMembership) {
                $membership = new Membership();
                $membership->setUser($joinRequest->getUser());
                $membership->setDepartment($joinRequest->getDepartment());
                $membership->setRole('u');

                $hasAnyMembership = count($this->entityManager->getRepository(Membership::class)->findBy([
                    'userId' => $joinRequest->getUserId(),
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
            }
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'status' => $joinRequest->getStatus(),
        ]);
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

        return [
            'department_id' => $department->getId(),
            'department_name' => $department->getName(),
            'join_code' => $joinCode,
            'invite_url' => $inviteUrl,
            'qr_payload' => $inviteUrl,
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
}
