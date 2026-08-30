<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Membership;
use App\Entity\Department;
use App\Entity\Profile;
use App\Repository\UserRepository;
use App\Service\Grossanlass\GrossanlassDepartmentSerializer;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\Admin\AdminCapabilityRegistry;
use App\Service\SystemScopeVisibility;
use App\Service\AuditLogger;
use App\Service\MembershipRoleCatalog;
use App\Util\E2eSmokeUser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users', name: 'api_users_')]
class UserController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private AuditLogger $auditLogger,
        private AdminCapabilityChecker $adminCapabilityChecker,
    ) {}

    private function isGlobalAdmin(User $user): bool
    {
        return $this->adminCapabilityChecker->hasGlobalAdminRole($user);
    }

    private function canManageGlobalUsers(User $user): bool
    {
        return $this->adminCapabilityChecker->isSuperAdmin($user)
            || $this->adminCapabilityChecker->can($user, 'users.global_manage');
    }

    private function serializeAdminUserListItem(User $user, Profile $profile, int $membershipCount): array
    {
        $capData = $this->adminCapabilityChecker->serializeForProfile($profile);

        return [
            'id' => $user->getId(),
            'profile_id' => $profile->getId(),
            'name' => $profile->getDisplayName(),
            'first_name' => $profile->getFirstName(),
            'last_name' => $profile->getLastName(),
            'nickname' => $profile->getNickname(),
            'email' => $profile->getEmail(),
            'state' => $user->getState(),
            'created_at' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'departments_count' => $membershipCount,
            'global_admin_role' => $capData['global_admin_role'],
        ];
    }

    /**
     * @param list<array{department_id: string, department_name: string, role: string, is_primary: bool}> $membershipData
     */
    private function serializeAdminUserDetail(User $user, Profile $profile, array $membershipData): array
    {
        $capData = $this->adminCapabilityChecker->serializeForProfile($profile);

        return [
            'id' => $user->getId(),
            'profile_id' => $profile->getId(),
            'name' => $profile->getDisplayName(),
            'first_name' => $profile->getFirstName(),
            'last_name' => $profile->getLastName(),
            'nickname' => $profile->getNickname(),
            'email' => $profile->getEmail(),
            'state' => $user->getState(),
            'created_at' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'memberships' => $membershipData,
            'global_admin_role' => $capData['global_admin_role'],
            'admin_capabilities' => $capData['admin_capabilities'],
            'admin_capabilities_stored' => $profile->getAdminCapabilities(),
        ];
    }

    /**
     * Admin: Alle User mit Membership-Count.
     */
    #[Route('/admin/list', name: 'admin_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listForAdmin(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User || !$this->canManageGlobalUsers($currentUser)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $qb = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->innerJoin('u.profile', 'p')
            ->leftJoin(Membership::class, 'm', 'WITH', 'm.userId = u.id')
            ->select('u', 'p')
            ->addSelect('COUNT(m.departmentId) AS membership_count')
            ->groupBy('u.id, p.id');

        $search = trim((string) $request->query->get('q', ''));
        if ($search !== '') {
            $qb->andWhere('LOWER(p.email) LIKE :q OR LOWER(p.firstName) LIKE :q OR LOWER(p.lastName) LIKE :q OR LOWER(p.nickname) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($search) . '%');
        }

        $allowedSort = [
            'created_at' => 'u.createdAt',
            'name' => 'p.lastName',
            'email' => 'p.email',
            'departments_count' => 'membership_count',
        ];
        $sortBy = strtolower((string) $request->query->get('sortBy', 'created_at'));
        $sortDir = strtolower((string) $request->query->get('sortDir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $sortColumn = $allowedSort[$sortBy] ?? $allowedSort['created_at'];

        $qb->orderBy($sortColumn, $sortDir);
        if ($sortBy === 'name') {
            $qb->addOrderBy('p.firstName', $sortDir);
        }

        $rows = $qb->getQuery()->getResult();
        $result = [];

        foreach ($rows as $row) {
            $user = $row[0] ?? null;
            $membershipCount = isset($row['membership_count']) ? (int) $row['membership_count'] : 0;
            if (!$user instanceof User) {
                continue;
            }
            $profile = $user->getProfile();
            if (!$profile) {
                continue;
            }

            if ($profile->hasSuperAdminRole() || E2eSmokeUser::isExcluded($profile->getEmail())) {
                continue;
            }

            $result[] = $this->serializeAdminUserListItem($user, $profile, $membershipCount);
        }

        return new JsonResponse($result);
    }

    /**
     * Admin: Kompakte Übersicht aller User mit Memberships und globalem Scope (Tree/Kanban).
     */
    #[Route('/admin/org-overview', name: 'admin_org_overview', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function orgOverviewForAdmin(): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User || !$this->canManageGlobalUsers($currentUser)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $users = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->innerJoin('u.profile', 'p')
            ->addSelect('p')
            ->orderBy('p.lastName', 'ASC')
            ->addOrderBy('p.firstName', 'ASC')
            ->getQuery()
            ->getResult();

        $memberships = $this->entityManager->getRepository(Membership::class)
            ->createQueryBuilder('m')
            ->innerJoin('m.department', 'd')
            ->addSelect('d')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();

        $membershipsByUser = [];
        foreach ($memberships as $membership) {
            if (!$membership instanceof Membership) {
                continue;
            }
            $userId = $membership->getUserId();
            $department = $membership->getDepartment();
            if (!SystemScopeVisibility::isDepartmentVisibleForAssignment($department)) {
                continue;
            }
            $membershipsByUser[$userId][] = [
                'department_id' => $department->getId(),
                'department_name' => $department->getName(),
                'role' => $membership->getRole(),
                'is_primary' => $membership->getIsPrimary(),
            ];
        }

        $result = [];
        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }
            $profile = $user->getProfile();
            if (!$profile || $profile->hasSuperAdminRole() || E2eSmokeUser::isExcluded($profile->getEmail())) {
                continue;
            }

            $capData = $this->adminCapabilityChecker->serializeForProfile($profile);
            $scope = $capData['admin_capabilities']['scope'] ?? [];
            $orgIds = \is_array($scope['organisation_ids'] ?? null)
                ? SystemScopeVisibility::filterOrganisationIds(
                    array_values(array_filter(array_map('strval', $scope['organisation_ids'])))
                )
                : [];
            $rootIds = \is_array($scope['department_root_ids'] ?? null)
                ? SystemScopeVisibility::filterDepartmentIds(
                    array_values(array_filter(array_map('strval', $scope['department_root_ids'])))
                )
                : [];

            $result[] = [
                'id' => $user->getId(),
                'name' => $profile->getDisplayName(),
                'email' => $profile->getEmail(),
                'global_admin_role' => $capData['global_admin_role'],
                'memberships' => $membershipsByUser[$user->getId()] ?? [],
                'organisation_ids' => $orgIds,
                'department_root_ids' => $rootIds,
            ];
        }

        return new JsonResponse(['users' => $result]);
    }

    /**
     * Admin: Detailansicht für User inkl. Memberships.
     */
    #[Route('/{id}/admin-detail', name: 'admin_detail', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getAdminDetail(string $id): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User || !$this->canManageGlobalUsers($currentUser)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $user = $this->userRepository->find($id);
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }
        $profile = $user->getProfile();
        if (!$profile) {
            return new JsonResponse(['error' => 'Profile not found'], 404);
        }

        if ($profile->hasSuperAdminRole()) {
            return new JsonResponse(['error' => 'Superadmin-Konten werden hier nicht verwaltet'], 403);
        }

        $memberships = $this->entityManager->getRepository(Membership::class)
            ->createQueryBuilder('m')
            ->innerJoin('m.department', 'd')
            ->addSelect('d')
            ->where('m.userId = :userId')
            ->setParameter('userId', $id)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();

        $membershipData = [];
        foreach ($memberships as $m) {
            $department = $m->getDepartment();
            if (!SystemScopeVisibility::isDepartmentVisibleForAssignment($department)) {
                continue;
            }
            $membershipData[] = [
                'department_id' => $department->getId(),
                'department_name' => $department->getName(),
                'role' => $m->getRole(),
                'is_primary' => $m->getIsPrimary(),
            ];
        }

        return new JsonResponse($this->serializeAdminUserDetail($user, $profile, $membershipData));
    }

    /**
     * Admin: User + Memberships bearbeiten.
     */
    #[Route('/{id}/admin', name: 'admin_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateAdminUser(string $id, Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User || !$this->canManageGlobalUsers($currentUser)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $user = $this->userRepository->find($id);
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $profile = $user->getProfile();
        if (!$profile) {
            return new JsonResponse(['error' => 'Profile not found'], 404);
        }

        if ($profile->hasSuperAdminRole()) {
            return new JsonResponse(['error' => 'Superadmin-Konten werden hier nicht verwaltet'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $profileChanges = [];

        if (array_key_exists('email', $data)) {
            $oldEmail = $profile->getEmail();
            $email = trim((string) $data['email']);
            if ($email === '') {
                return new JsonResponse(['error' => 'E-Mail darf nicht leer sein'], 400);
            }
            $profile->setEmail($email);
            if ($oldEmail !== $email) {
                $profileChanges['email'] = ['old' => $oldEmail, 'new' => $email];
            }
        }

        if (array_key_exists('first_name', $data)) {
            $oldFirstName = $profile->getFirstName();
            $newFirstName = ($data['first_name'] ?? '') !== '' ? (string) $data['first_name'] : null;
            $profile->setFirstName($newFirstName);
            if ($oldFirstName !== $newFirstName) {
                $profileChanges['first_name'] = ['old' => $oldFirstName, 'new' => $newFirstName];
            }
        }
        if (array_key_exists('last_name', $data)) {
            $oldLastName = $profile->getLastName();
            $newLastName = ($data['last_name'] ?? '') !== '' ? (string) $data['last_name'] : null;
            $profile->setLastName($newLastName);
            if ($oldLastName !== $newLastName) {
                $profileChanges['last_name'] = ['old' => $oldLastName, 'new' => $newLastName];
            }
        }
        if (array_key_exists('nickname', $data)) {
            $oldNickname = $profile->getNickname();
            $newNickname = ($data['nickname'] ?? '') !== '' ? (string) $data['nickname'] : null;
            $profile->setNickname($newNickname);
            if ($oldNickname !== $newNickname) {
                $profileChanges['nickname'] = ['old' => $oldNickname, 'new' => $newNickname];
            }
        }
        if (array_key_exists('state', $data)) {
            $user->setState((string) $data['state']);
        }

        if ($this->adminCapabilityChecker->isSuperAdmin($currentUser)) {
            if (array_key_exists('global_admin_role', $data)) {
                $globalRole = strtolower(trim((string) $data['global_admin_role']));
                if (!\in_array($globalRole, [
                    AdminCapabilityRegistry::GLOBAL_ROLE_NONE,
                    AdminCapabilityRegistry::GLOBAL_ROLE_ORG,
                    AdminCapabilityRegistry::GLOBAL_ROLE_SUB,
                ], true)) {
                    return new JsonResponse(['error' => 'Ungültige globale Rolle'], 400);
                }

                $oldGlobalRole = AdminCapabilityRegistry::resolveGlobalRole($profile->getRoles());
                $profile->setRoles($this->adminCapabilityChecker->profileRolesForGlobalRole($globalRole));
                if ($globalRole === AdminCapabilityRegistry::GLOBAL_ROLE_NONE) {
                    $profile->setAdminCapabilities(null);
                } elseif (array_key_exists('admin_capabilities', $data) && \is_array($data['admin_capabilities'])) {
                    $profile->setAdminCapabilities(
                        AdminCapabilityRegistry::sanitizePayload($data['admin_capabilities'], $globalRole)
                    );
                } elseif ($oldGlobalRole !== $globalRole) {
                    $profile->setAdminCapabilities(null);
                }
                $profileChanges['global_admin_role'] = ['old' => $oldGlobalRole, 'new' => $globalRole];
            } elseif (array_key_exists('admin_capabilities', $data)) {
                $globalRole = AdminCapabilityRegistry::resolveGlobalRole($profile->getRoles());
                if ($globalRole === AdminCapabilityRegistry::GLOBAL_ROLE_NONE) {
                    return new JsonResponse(['error' => 'Admin-Rechte nur für Org-/Suborgchef setzbar'], 400);
                }
                if (!\is_array($data['admin_capabilities'])) {
                    return new JsonResponse(['error' => 'admin_capabilities muss ein Objekt sein'], 400);
                }
                $profile->setAdminCapabilities(
                    AdminCapabilityRegistry::sanitizePayload($data['admin_capabilities'], $globalRole)
                );
                $profileChanges['admin_capabilities'] = ['old' => null, 'new' => 'updated'];
            }
        } elseif (array_key_exists('global_admin_role', $data) || array_key_exists('admin_capabilities', $data)) {
            return new JsonResponse(['error' => 'Nur Superadmin darf globale Rollen und Rechte setzen'], 403);
        }

        if (array_key_exists('memberships', $data)) {
            if (!is_array($data['memberships'])) {
                return new JsonResponse(['error' => 'memberships muss ein Array sein'], 400);
            }

            $requestedMemberships = $data['memberships'];
            $departmentIds = [];
            foreach ($requestedMemberships as $membershipRow) {
                if (!is_array($membershipRow) || empty($membershipRow['department_id'])) {
                    return new JsonResponse(['error' => 'Ungültige memberships-Struktur'], 400);
                }
                $departmentIds[] = (string) $membershipRow['department_id'];
            }
            if (count($departmentIds) !== count(array_unique($departmentIds))) {
                return new JsonResponse(['error' => 'Department darf nur einmal zugewiesen werden'], 400);
            }

            $departments = [];
            if (!empty($departmentIds)) {
                $departmentRows = $this->entityManager->getRepository(Department::class)
                    ->createQueryBuilder('d')
                    ->where('d.id IN (:ids)')
                    ->setParameter('ids', $departmentIds)
                    ->getQuery()
                    ->getResult();

                foreach ($departmentRows as $department) {
                    $departments[$department->getId()] = $department;
                }
                foreach ($departmentIds as $departmentId) {
                    if (!isset($departments[$departmentId])) {
                        return new JsonResponse(['error' => "Department {$departmentId} nicht gefunden"], 404);
                    }
                    if (!SystemScopeVisibility::isDepartmentVisibleForAssignment($departments[$departmentId])) {
                        return new JsonResponse([
                            'error' => 'Dieses Department ist ein System-Department und kann keinen Benutzern zugeordnet werden',
                        ], 400);
                    }
                }
            }

            $existingMemberships = $this->entityManager->getRepository(Membership::class)
                ->findBy(['userId' => $id]);
            $existingByDepartment = [];
            foreach ($existingMemberships as $membership) {
                $existingByDepartment[$membership->getDepartmentId()] = $membership;
            }

            $hasPrimary = false;
            foreach ($requestedMemberships as $membershipRow) {
                $departmentId = (string) $membershipRow['department_id'];
                $role = strtolower((string) ($membershipRow['role'] ?? 'u'));
                $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
                if (!MembershipRoleCatalog::isAllowed($department, $role)) {
                    return new JsonResponse(['error' => "Ungültige Rolle für Department {$departmentId}"], 400);
                }
                $isPrimary = (bool) ($membershipRow['is_primary'] ?? false);
                if ($isPrimary) {
                    if ($hasPrimary) {
                        return new JsonResponse(['error' => 'Nur ein primäres Department ist erlaubt'], 400);
                    }
                    $hasPrimary = true;
                }
            }

            if (!$hasPrimary && !empty($requestedMemberships)) {
                $requestedMemberships[0]['is_primary'] = true;
            }

            $requestedDepartments = array_map(fn(array $m) => (string) $m['department_id'], $requestedMemberships);
            foreach ($existingByDepartment as $departmentId => $existingMembership) {
                if (!in_array($departmentId, $requestedDepartments, true)) {
                    $this->auditLogger->log(
                        'membership',
                        AuditLogger::buildMembershipEntityId($existingMembership->getUserId(), $existingMembership->getDepartmentId()),
                        'membership_removed',
                        $currentUser,
                        $user,
                        $existingMembership->getDepartment(),
                        [
                            'role' => ['old' => $existingMembership->getRole(), 'new' => null],
                            'is_primary' => ['old' => $existingMembership->getIsPrimary(), 'new' => null],
                        ]
                    );
                    $this->entityManager->remove($existingMembership);
                }
            }

            foreach ($requestedMemberships as $membershipRow) {
                $departmentId = (string) $membershipRow['department_id'];
                $role = strtolower((string) ($membershipRow['role'] ?? 'u'));
                $isPrimary = (bool) ($membershipRow['is_primary'] ?? false);

                $membership = $existingByDepartment[$departmentId] ?? null;
                if (!$membership) {
                    $membership = new Membership();
                    $membership->setUser($user);
                    $membership->setDepartment($departments[$departmentId]);
                    $this->entityManager->persist($membership);
                    $membership->setRole($role);
                    $membership->setIsPrimary($isPrimary);
                    $this->auditLogger->log(
                        'membership',
                        AuditLogger::buildMembershipEntityId($user->getId(), $departmentId),
                        'membership_created',
                        $currentUser,
                        $user,
                        $departments[$departmentId],
                        [
                            'role' => ['old' => null, 'new' => $role],
                            'is_primary' => ['old' => null, 'new' => $isPrimary],
                        ]
                    );
                    continue;
                }
                $oldRole = $membership->getRole();
                $oldIsPrimary = $membership->getIsPrimary();

                $membership->setRole($role);
                $membership->setIsPrimary($isPrimary);

                if ($oldRole !== $role) {
                    $this->auditLogger->log(
                        'membership',
                        AuditLogger::buildMembershipEntityId($membership->getUserId(), $membership->getDepartmentId()),
                        'membership_role_changed',
                        $currentUser,
                        $user,
                        $membership->getDepartment(),
                        [
                            'role' => ['old' => $oldRole, 'new' => $role],
                        ]
                    );
                }

                if ($oldIsPrimary !== $isPrimary) {
                    $this->auditLogger->log(
                        'membership',
                        AuditLogger::buildMembershipEntityId($membership->getUserId(), $membership->getDepartmentId()),
                        'membership_primary_changed',
                        $currentUser,
                        $user,
                        $membership->getDepartment(),
                        [
                            'is_primary' => ['old' => $oldIsPrimary, 'new' => $isPrimary],
                        ]
                    );
                }
            }
        }

        if (!empty($profileChanges)) {
            $profile->setUpdatedAt(new \DateTime());
            $this->auditLogger->log(
                'profile',
                $profile->getId(),
                'profile_updated',
                $currentUser,
                $user,
                null,
                $profileChanges
            );
        }

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return new JsonResponse(['error' => 'E-Mail ist bereits vergeben'], 409);
        }

        return $this->getAdminDetail($id);
    }

    /**
     * Lädt User-Daten
     */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getUserData(string $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        // Prüfe ob User auf eigenen Account zugreift oder Admin ist
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        // User kann nur eigenen Account sehen, außer er ist Admin
        if ($user->getId() !== $currentUser->getId() && !in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'state' => $user->getState(),
            'profile_id' => $user->getProfileId(),
            'last_used_department' => $user->getLastUsedDepartmentId(),
        ]);
    }

    /**
     * Speichert die zuletzt gewählte Abteilung (nur bei bestehender Mitgliedschaft).
     */
    #[Route('/{id}/last-used-department', name: 'set_last_used_department', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function setLastUsedDepartment(string $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'User nicht gefunden'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht autorisiert'], 403);
        }

        if ($user->getId() !== $currentUser->getId() && !in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            return new JsonResponse(['error' => 'Nicht berechtigt'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $departmentId = isset($data['department_id']) ? trim((string) $data['department_id']) : '';

        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $id,
            'departmentId' => $departmentId,
        ]);

        if (!$membership) {
            return new JsonResponse(['error' => 'Keine Mitgliedschaft in diesem Department'], 404);
        }

        $user->setLastUsedDepartment($membership->getDepartment());
        $user->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'last_used_department' => $membership->getDepartment()->getId(),
        ]);
    }

    /**
     * Lädt User Memberships (Departments)
     */
    #[Route('/{id}/memberships', name: 'memberships', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getMemberships(string $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        // Prüfe ob User auf eigenen Account zugreift oder Admin ist
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        if ($user->getId() !== $currentUser->getId() && !in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        // Lade Memberships (mit Department-Relation)
        $memberships = $this->entityManager->getRepository(Membership::class)
            ->createQueryBuilder('m')
            ->innerJoin('m.department', 'd')
            ->leftJoin('d.grossanlassConfig', 'gc')
            ->addSelect('d', 'gc')
            ->where('m.userId = :userId')
            ->setParameter('userId', $id)
            ->getQuery()
            ->getResult();

        $membershipData = [];
        foreach ($memberships as $m) {
            $department = $m->getDepartment();
            $membershipData[] = [
                'department_id' => $department->getId(),
                'role' => $m->getRole(),
                'is_primary' => $m->getIsPrimary(),
                'department' => GrossanlassDepartmentSerializer::serializeDepartmentForMembership($department),
            ];
        }

        return new JsonResponse(['memberships' => $membershipData]);
    }

    /**
     * Setzt das primäre Department für den User
     */
    #[Route('/{id}/set-primary-department', name: 'set_primary_department', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function setPrimaryDepartment(string $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return new JsonResponse(['error' => 'User nicht gefunden'], 404);
        }

        // Prüfe ob User auf eigenen Account zugreift
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht autorisiert'], 403);
        }

        if ($user->getId() !== $currentUser->getId() && !in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            return new JsonResponse(['error' => 'Nicht berechtigt'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $departmentId = $data['department_id'] ?? null;

        if (!$departmentId) {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        // Alle Memberships des Users laden
        $memberships = $this->entityManager->getRepository(Membership::class)
            ->findBy(['userId' => $id]);

        $found = false;
        foreach ($memberships as $membership) {
            if ($membership->getDepartmentId() === $departmentId) {
                $membership->setIsPrimary(true);
                $found = true;
            } else {
                $membership->setIsPrimary(false);
            }
        }

        if (!$found) {
            return new JsonResponse(['error' => 'Keine Mitgliedschaft in diesem Department'], 404);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'primary_department_id' => $departmentId
        ]);
    }
}
