<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\Organisation;
use App\Entity\Profile;
use App\Entity\User;
use App\Entity\Membership;
use App\Repository\DepartmentRepository;
use App\Service\Accounting\AccountingCostCenterBootstrapService;
use App\Service\Workshop\WorkshopSparePartsCategoryBootstrapService;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\AuditLogger;
use App\Service\OrganisationUserPickerFilter;
use App\Service\DepartmentDefaultCoachSyncService;
use App\Service\DepartmentResetService;
use App\Service\DepartmentRoleLabelService;
use App\Service\DevEnvironmentService;
use App\Service\Grossanlass\GrossanlassDepartmentCreateService;
use App\Service\Grossanlass\GrossanlassDepartmentSerializer;
use App\Service\VerificationEmailService;
use App\Util\E2eSmokeUser;
use App\Util\IdGenerator;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments', name: 'api_departments_')]
class DepartmentController extends AbstractController
{
    public function __construct(
        private DepartmentRepository $departmentRepository,
        private EntityManagerInterface $entityManager,
        private AuditLogger $auditLogger,
        private DepartmentResetService $departmentResetService,
        private DevEnvironmentService $devEnvironmentService,
        private AccountingCostCenterBootstrapService $accountingCostCenterBootstrap,
        private WorkshopSparePartsCategoryBootstrapService $workshopSparePartsCategoryBootstrap,
        private VerificationEmailService $verificationEmailService,
        private AdminCapabilityChecker $adminCapabilityChecker,
        private GrossanlassDepartmentCreateService $grossanlassDepartmentCreateService,
        private DepartmentRoleLabelService $departmentRoleLabelService,
        private DepartmentDefaultCoachSyncService $departmentDefaultCoachSync,
        #[Autowire('%kernel.secret%')]
        private string $appSecret,
    ) {}

    /**
     * Lädt alle Departments OHNE User (für Performance bei vielen Departments)
     * User werden erst bei Bedarf über /api/departments/{id} geladen
     * Sortiert hierarchisch: Haupt-Departments zuerst, dann Unter-Departments
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        // Lade alle Departments OHNE User (später: gefiltert nach Organisation/Rechten)
        $departments = $this->departmentRepository->findAll();

        // Sortiere hierarchisch: Haupt-Departments zuerst, dann nach Hierarchie-Ebene, dann alphabetisch
        // Erstelle Map für schnellen Zugriff auf Parent-Beziehungen
        $deptMap = [];
        foreach ($departments as $dept) {
            $deptMap[$dept->getId()] = $dept;
        }
        
        // Funktion um Hierarchie-Ebene zu berechnen (0 = Root, 1 = Kind von Root, etc.)
        $getLevel = function($dept) use (&$getLevel, $deptMap): int {
            if ($dept->getParentId() === null) {
                return 0;
            }
            $parent = $deptMap[$dept->getParentId()] ?? null;
            if (!$parent) {
                return 0; // Fallback wenn Parent nicht gefunden
            }
            return 1 + $getLevel($parent);
        };
        
        usort($departments, function($a, $b) use ($getLevel) {
            $levelA = $getLevel($a);
            $levelB = $getLevel($b);
            
            // Nach Hierarchie-Ebene sortieren (tiefere Ebenen zuerst)
            if ($levelA !== $levelB) {
                return $levelA <=> $levelB;
            }
            
            // Innerhalb der gleichen Ebene: alphabetisch nach Name
            return strcmp($a->getName(), $b->getName());
        });

        $accessibleDeptIds = null;
        if (!$this->adminCapabilityChecker->isSuperAdmin($currentUser)) {
            $accessibleDeptIds = $this->adminCapabilityChecker->getAccessibleDepartmentIds($currentUser);
        }

        $result = [];
        foreach ($departments as $department) {
            if (\is_array($accessibleDeptIds) && !\in_array($department->getId(), $accessibleDeptIds, true)) {
                continue;
            }
            // KEINE User laden - nur Department-Info
            $result[] = [
                'id' => $department->getId(),
                'name' => $department->getName(),
                'organisation_id' => $department->getOrganisationId(),
                'parent_id' => $department->getParentId(),
                'users' => [] // Leer - wird erst bei Bedarf geladen
            ];
        }

        return new JsonResponse($result);
    }

    /**
     * Gibt ALLE Gruppen des Departments zurück.
     * 
     * Gruppen wo der User member/leader ist → selectable: true, role: 'leader'/'member'
     * Gruppen wo der User KEIN Mitglied ist → selectable: false, role: null
     * Admins (globale Profilrollen oder Department-Rolle mw/dc) → alle selectable: true
     * 
     * Response: Hierarchisch sortierte Liste mit { id, name, parent_id, level, role, selectable, is_direct_member, member_count }
     */
    #[Route('/{departmentId}/my-groups', name: 'my_groups', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myGroups(string $departmentId): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        // Department-Kontext prüfen
        $contextDepartment = $this->departmentRepository->find($departmentId);
        if (!$contextDepartment) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        // 1. ALLE Gruppen dieses Departments laden (sortiert nach sort_order, dann name)
        $allGroups = $this->entityManager->getRepository(Group::class)
            ->createQueryBuilder('g')
            ->where('g.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('g.sortOrder', 'ASC')
            ->addOrderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();

        if (empty($allGroups)) {
            return new JsonResponse([]);
        }

        // Group-Map aufbauen
        $groupMap = [];
        foreach ($allGroups as $grp) {
            $groupMap[$grp->getId()] = $grp;
        }

        // 2. Prüfen ob User Admin ist (globale Profilrolle oder Department-Rolle mw/dc)
        $deptMembership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $currentUser->getId(), 'departmentId' => $departmentId]);

        $isGlobalAdmin = $this->adminCapabilityChecker->hasGlobalAdminRole($currentUser);
        $deptRole = $deptMembership ? strtolower(trim((string) $deptMembership->getRole())) : '';
        $isDepartmentAdmin = in_array($deptRole, ['mw', 'dc'], true);
        $isAdmin = $isGlobalAdmin || $isDepartmentAdmin;

        // 3. Group-Memberships des Users laden (für ALLE seine Gruppen in diesem Department)
        $groupIds = array_map(fn(Group $g) => $g->getId(), $allGroups);
        $groupMemberships = $this->entityManager->getRepository(GroupMembership::class)
            ->createQueryBuilder('gm')
            ->where('gm.userId = :userId')
            ->andWhere('gm.groupId IN (:groupIds)')
            ->setParameter('userId', $currentUser->getId())
            ->setParameter('groupIds', $groupIds)
            ->getQuery()
            ->getResult();

        $memberRoles = [];
        foreach ($groupMemberships as $gm) {
            $memberRoles[$gm->getGroupId()] = $gm->getRole();
        }

        // 4. Mitglieder-Anzahl pro Gruppe laden (für Anzeige)
        $memberCounts = [];
        $countResult = $this->entityManager->getRepository(GroupMembership::class)
            ->createQueryBuilder('gm')
            ->select('gm.groupId, COUNT(gm.userId) as cnt')
            ->where('gm.groupId IN (:groupIds)')
            ->setParameter('groupIds', $groupIds)
            ->groupBy('gm.groupId')
            ->getQuery()
            ->getResult();
        foreach ($countResult as $row) {
            $memberCounts[$row['groupId']] = (int) $row['cnt'];
        }

        // 5. Hierarchische Ebene berechnen
        $getLevel = function(string $groupId) use (&$getLevel, $groupMap): int {
            $grp = $groupMap[$groupId] ?? null;
            if (!$grp || !$grp->getParentId()) {
                return 0;
            }
            if (!isset($groupMap[$grp->getParentId()])) {
                return 0;
            }
            return 1 + $getLevel($grp->getParentId());
        };

        // 6. Ergebnis: ALLE Gruppen, aber mit selectable-Flag
        $result = [];
        foreach ($allGroups as $grp) {
            $gid = $grp->getId();
            $role = $memberRoles[$gid] ?? null;
            $isDirectMember = $role !== null;

            // Selectable: User ist Mitglied/Leader dieser Gruppe ODER ist Admin
            $selectable = $isAdmin || $isDirectMember;

            $result[] = [
                'id' => $gid,
                'name' => $grp->getName(),
                'parent_id' => $grp->getParentId(),
                'level' => $getLevel($gid),
                'role' => $role,                    // 'leader', 'member', oder null
                'selectable' => $selectable,        // true = auswählbar, false = ausgegraut
                'is_direct_member' => $isDirectMember,
                'member_count' => $memberCounts[$gid] ?? 0,
            ];
        }

        // Sortieren: nach Level, dann nach sort_order (schon vorsortiert), dann alphabetisch
        usort($result, function($a, $b) {
            if ($a['level'] !== $b['level']) {
                return $a['level'] <=> $b['level'];
            }
            return strcmp($a['name'], $b['name']);
        });

        return new JsonResponse($result);
    }

    /**
     * Lädt ein einzelnes Department mit Details
     */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get(string $id): JsonResponse
    {
        $department = $this->departmentRepository->find($id);
        
        if (!$department) {
            return new JsonResponse(['error' => 'Department not found'], 404);
        }

        // Lade Memberships
        $memberships = $this->entityManager->getRepository(Membership::class)
            ->createQueryBuilder('m')
            ->innerJoin('m.user', 'u')
            ->innerJoin('u.profile', 'p')
            ->addSelect('u', 'p')
            ->where('m.departmentId = :departmentId')
            ->setParameter('departmentId', $id)
            ->getQuery()
            ->getResult();

        $users = [];
        foreach ($memberships as $m) {
            $user = $m->getUser();
            $profile = $user->getProfile();
            if ($profile) {
                $users[] = [
                    'id' => $user->getId(),
                    'profile_id' => $profile->getId(),
                    'name' => $profile->getDisplayName(),
                    'first_name' => $profile->getFirstName(),
                    'last_name' => $profile->getLastName(),
                    'nickname' => $profile->getNickname(),
                    'email' => $profile->getEmail(),
                    'avatar_initials' => $profile->getAvatarInitials(),
                    'background_color' => $profile->getBackgroundColor(),
                    'text_color' => $profile->getTextColor(),
                    'role' => $m->getRole(),
                    'is_primary' => $m->getIsPrimary()
                ];
            }
        }

        return new JsonResponse([
            'id' => $department->getId(),
            'name' => $department->getName(),
            'organisation_id' => $department->getOrganisationId(),
            'parent_id' => $department->getParentId(),
            'is_grossanlass' => $department->isGrossanlass(),
            'users' => $users
        ]);
    }

    /**
     * Erstellt ein neues Department
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        if (!$this->adminCapabilityChecker->can($currentUser, 'departments.create')) {
            return new JsonResponse(['error' => 'Zugriff verweigert'], 403);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['name']) || !isset($data['organisation_id'])) {
            return new JsonResponse(['error' => 'Name und organisation_id sind erforderlich'], 400);
        }

        // Organisation prüfen
        $organisation = $this->entityManager->getRepository(Organisation::class)
            ->find($data['organisation_id']);
        
        if (!$organisation) {
            return new JsonResponse(['error' => 'Organisation nicht gefunden'], 404);
        }
        if (!OrganisationUserPickerFilter::isVisibleForUserPickers($organisation)) {
            return new JsonResponse(['error' => 'Organisation nicht verfuegbar'], 400);
        }
        if (!$this->adminCapabilityChecker->canAccessOrganisation($currentUser, $organisation->getId())) {
            return new JsonResponse(['error' => 'Zugriff verweigert'], 403);
        }

        // Parent Department prüfen (optional)
        $parent = null;
        if (isset($data['parent_id']) && !empty($data['parent_id'])) {
            $parent = $this->departmentRepository->find($data['parent_id']);
            if (!$parent) {
                return new JsonResponse(['error' => 'Parent Department nicht gefunden'], 404);
            }
            if (!$this->adminCapabilityChecker->canAccessDepartment($currentUser, $parent->getId())) {
                return new JsonResponse(['error' => 'Zugriff verweigert'], 403);
            }
            // Prüfe ob Parent zur gleichen Organisation gehört
            if ($parent->getOrganisationId() !== $organisation->getId()) {
                return new JsonResponse(['error' => 'Parent Department muss zur gleichen Organisation gehören'], 400);
            }
        } elseif (!$this->adminCapabilityChecker->canAccessOrganisation($currentUser, $organisation->getId())) {
            return new JsonResponse(['error' => 'Zugriff verweigert'], 403);
        }

        $departmentName = trim((string) $data['name']);
        if ($departmentName === '') {
            return new JsonResponse(['error' => 'Name ist erforderlich'], 400);
        }
        $conflict = $this->departmentRepository->findConflictingByOrganisationAndName(
            $organisation->getId(),
            $departmentName,
        );
        if ($conflict instanceof Department) {
            return new JsonResponse(
                ['error' => 'Ein Department mit diesem oder einem sehr ähnlichen Namen existiert bereits: «' . $conflict->getName() . '»'],
                409,
            );
        }

        try {
            // Neues Department erstellen
            $department = new Department();
            
            // ID muss VOR persist() gesetzt werden (GeneratedValue strategy: 'NONE')
            $department->setId(IdGenerator::generateUnique($this->entityManager, Department::class));
            $department->setName($departmentName);
            $department->setOrganisation($organisation);
            
            if ($parent) {
                $department->setParent($parent);
            }

            $this->entityManager->persist($department);
            $this->entityManager->flush();

            $this->accountingCostCenterBootstrap->ensureDefaultCostCenters($this->entityManager, $department);
            $this->workshopSparePartsCategoryBootstrap->ensure($department);

            // Prüfe ob ID generiert wurde
            if (!$department->getId()) {
                return new JsonResponse(['error' => 'ID konnte nicht generiert werden'], 500);
            }
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Erstellen des Departments: ' . $e->getMessage()
            ], 500);
        }

        return new JsonResponse([
            'id' => $department->getId(),
            'name' => $department->getName(),
            'organisation_id' => $department->getOrganisationId(),
            'parent_id' => $department->getParentId(),
            'users' => []
        ], 201);
    }

    /**
     * Erstellt ein Grossanlass-Department (Phase 1)
     */
    #[Route('/grossanlass', name: 'create_grossanlass', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createGrossanlass(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiger Request-Body'], 400);
        }

        try {
            $result = $this->grossanlassDepartmentCreateService->create($currentUser, $data);
        } catch (\InvalidArgumentException $e) {
            $status = $e->getCode() === 409 ? 409 : 400;

            return new JsonResponse(['error' => $e->getMessage()], $status);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Fehler beim Erstellen: ' . $e->getMessage()], 500);
        }

        $department = $result['department'];
        $config = $result['config'];
        $chiefMw = $result['chief_mw_user'];
        if ($chiefMw instanceof User) {
            $this->grossanlassDepartmentCreateService->notifyChiefMw($currentUser, $department, $config, $chiefMw);
        }

        return new JsonResponse(
            GrossanlassDepartmentSerializer::serializeCreateResponse($department, $config),
            201
        );
    }

    /**
     * Globale User-Suche für Grossanlass-Wizard (Chief-MW) — alle aktiven User, org-übergreifend.
     */
    #[Route('/grossanlass/available-users', name: 'grossanlass_available_users', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function grossanlassAvailableUsers(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        if (!$this->adminCapabilityChecker->hasGlobalAdminRole($currentUser)) {
            return new JsonResponse(['error' => 'Zugriff verweigert'], 403);
        }

        $organisationId = trim((string) $request->query->get('organisation_id', ''));
        if ($organisationId !== '' && !$this->adminCapabilityChecker->canAccessOrganisation($currentUser, $organisationId)) {
            return new JsonResponse(['error' => 'Zugriff verweigert'], 403);
        }

        $search = trim((string) $request->query->get('q', ''));
        if ($search === '' || mb_strlen($search) < 2) {
            return new JsonResponse([]);
        }

        $qb = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->innerJoin('u.profile', 'p')
            ->addSelect('p')
            ->where('u.state = :state')
            ->setParameter('state', 'active');

        $tokens = preg_split('/\s+/u', mb_strtolower($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($tokens as $index => $token) {
            $param = 'searchToken' . $index;
            $qb->andWhere(
                $qb->expr()->orX(
                    "LOWER(p.email) LIKE :{$param}",
                    "LOWER(p.firstName) LIKE :{$param}",
                    "LOWER(p.lastName) LIKE :{$param}",
                    "LOWER(p.nickname) LIKE :{$param}",
                    "LOWER(CONCAT(COALESCE(p.firstName, ''), ' ', COALESCE(p.lastName, ''))) LIKE :{$param}",
                    "LOWER(CONCAT(COALESCE(p.lastName, ''), ' ', COALESCE(p.firstName, ''))) LIKE :{$param}",
                    "EXISTS (
                        SELECT 1 FROM App\Entity\Membership ms
                        INNER JOIN ms.department ds
                        WHERE ms.userId = u.id AND LOWER(ds.name) LIKE :{$param}
                    )"
                )
            )->setParameter($param, '%' . $token . '%');
        }

        $users = $qb->orderBy('p.lastName', 'ASC')
            ->addOrderBy('p.firstName', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        $userIds = [];
        foreach ($users as $user) {
            if (!$user->hasSuperAdminProfile()) {
                $userIds[] = $user->getId();
            }
        }

        $membershipsByUser = [];
        if ($userIds !== []) {
            $memberships = $this->entityManager->getRepository(Membership::class)
                ->createQueryBuilder('m')
                ->innerJoin('m.department', 'd')
                ->addSelect('d')
                ->where('m.userId IN (:userIds)')
                ->setParameter('userIds', $userIds)
                ->orderBy('d.name', 'ASC')
                ->getQuery()
                ->getResult();

            foreach ($memberships as $membership) {
                if (!$membership instanceof Membership) {
                    continue;
                }
                $uid = $membership->getUserId();
                $membershipsByUser[$uid][] = $membership;
            }
        }

        $result = [];
        foreach ($users as $user) {
            if (!$user instanceof User || $user->hasSuperAdminProfile()) {
                continue;
            }
            $profile = $user->getProfile();
            if (!$profile || E2eSmokeUser::isExcluded($profile->getEmail())) {
                continue;
            }

            $deptNames = [];
            $primaryDepartmentName = null;
            foreach ($membershipsByUser[$user->getId()] ?? [] as $membership) {
                $deptName = $membership->getDepartment()->getName();
                $deptNames[] = $deptName;
                if ($membership->getIsPrimary()) {
                    $primaryDepartmentName = $deptName;
                }
            }
            if ($primaryDepartmentName === null && $deptNames !== []) {
                $primaryDepartmentName = $deptNames[0];
            }

            $result[] = [
                'id' => $user->getId(),
                'name' => $profile->getDisplayName(),
                'email' => $profile->getEmail(),
                'first_name' => $profile->getFirstName(),
                'last_name' => $profile->getLastName(),
                'nickname' => $profile->getNickname(),
                'primary_department_name' => $primaryDepartmentName,
                'departments_label' => $deptNames !== [] ? implode(', ', $deptNames) : null,
            ];
        }

        return new JsonResponse($result);
    }

    /**
     * Prüft ob das Department bereits mw oder dc hat (für Support-Zuordnung)
     */
    #[Route('/{departmentId}/has-manager', name: 'has_manager', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function hasManager(string $departmentId): JsonResponse
    {
        $department = $this->departmentRepository->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $count = (int) $this->entityManager->createQuery(
            'SELECT COUNT(m.userId) FROM App\Entity\Membership m WHERE m.departmentId = :deptId AND (m.role = :mw OR m.role = :dc)'
        )->setParameter('deptId', $departmentId)
            ->setParameter('mw', 'mw')
            ->setParameter('dc', 'dc')
            ->getSingleScalarResult();

        return new JsonResponse(['has_mw_or_dc' => $count > 0]);
    }

    /**
     * Listet alle Mitglieder eines Departments
     * Für die Benutzer-Verwaltung in den Settings
     */
    #[Route('/{departmentId}/members', name: 'list_members', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listMembers(string $departmentId): JsonResponse
    {
        $department = $this->departmentRepository->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $memberships = $this->entityManager->getRepository(Membership::class)
            ->createQueryBuilder('m')
            ->innerJoin('m.user', 'u')
            ->innerJoin('u.profile', 'p')
            ->addSelect('u', 'p')
            ->where('m.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('m.role', 'ASC')
            ->addOrderBy('p.lastName', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($memberships as $m) {
            $user = $m->getUser();
            if ($user->hasSuperAdminProfile()) {
                continue;
            }
            $profile = $user->getProfile();
            if ($profile) {
                $result[] = [
                    'user_id' => $user->getId(),
                    'profile_id' => $profile->getId(),
                    'name' => $profile->getDisplayName(),
                    'first_name' => $profile->getFirstName(),
                    'last_name' => $profile->getLastName(),
                    'nickname' => $profile->getNickname(),
                    'email' => $profile->getEmail(),
                    'avatar_initials' => $profile->getAvatarInitials(),
                    'background_color' => $profile->getBackgroundColor(),
                    'text_color' => $profile->getTextColor(),
                    'language' => $profile->getLanguage(),
                    'pending_email' => $user->getPendingEmail(),
                    'role' => $m->getRole(),
                    'is_primary' => $m->getIsPrimary(),
                    'is_js_coach' => $m->getIsJsCoach(),
                    'state' => $user->getState(),
                ];
            }
        }

        return new JsonResponse($result);
    }

    /**
     * Rollen-Hierarchie: Index 0 = höchste Berechtigung
     */
    private const MEMBERSHIP_ROLE_HIERARCHY = ['mw', 'dc', 'l1', 'l2', 'l3', 'u'];
    private const GLOBAL_ADMIN_ROLES = ['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'];
    private const PASSWORD_RESET_CODE_TTL_MINUTES = 10;
    private const PASSWORD_RESET_REQUEST_COOLDOWN_SECONDS = 60;
    private const PASSWORD_RESET_MAX_REQUESTS_PER_HOUR = 5;

    /**
     * Prüft ob der aktuelle User die Zielrolle vergeben darf (streng niedriger als eigene).
     */
    private function canAssignRole(string $departmentId, string $targetRole): bool|JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $existingMemberCount = (int) $this->entityManager->getRepository(Membership::class)
            ->createQueryBuilder('m')
            ->select('COUNT(m.userId)')
            ->where('m.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->getQuery()
            ->getSingleScalarResult();

        $bootstrapRoles = ['mw', 'dc'];
        $hasBootstrapPrivilege = $this->adminCapabilityChecker->hasGlobalAdminRole($currentUser);

        // Bootstrap-Sonderfall: leeres Department darf initial mit MW/DC besetzt werden.
        if ($existingMemberCount === 0 && in_array($targetRole, $bootstrapRoles, true) && $hasBootstrapPrivilege) {
            return true;
        }

        // Globale Admins dürfen Department-Rollen ohne eigene Membership vergeben.
        if ($hasBootstrapPrivilege) {
            return true;
        }

        $myMembership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $currentUser->getId(), 'departmentId' => $departmentId]);

        if (!$myMembership) {
            return new JsonResponse(['error' => 'Du bist kein Mitglied dieses Departments'], 403);
        }

        $myRole = $myMembership->getRole();
        $myIndex = array_search($myRole, self::MEMBERSHIP_ROLE_HIERARCHY, true);
        $targetIndex = array_search($targetRole, self::MEMBERSHIP_ROLE_HIERARCHY, true);

        if ($myIndex === false || $targetIndex === false) {
            return new JsonResponse(['error' => 'Ungültige Rolle'], 400);
        }

        // Streng niedriger: gleiche Rolle und darüber = verboten (höherer Index = niedrigere Rolle)
        if ($targetIndex <= $myIndex) {
            return new JsonResponse([
                'error' => 'Du kannst nur Rollen unterhalb deiner eigenen vergeben',
            ], 403);
        }

        return true;
    }

    /**
     * Darf der aktuelle User ein bestehendes Mitglied (mit gegebener Rolle) verwalten?
     * Streng: nur niedrigere Rollen; Global-Admin immer.
     */
    private function canManageMembershipTarget(string $departmentId, string $targetRole): bool|JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        if ($this->adminCapabilityChecker->hasGlobalAdminRole($currentUser)) {
            return true;
        }

        $myMembership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $currentUser->getId(), 'departmentId' => $departmentId]);

        if (!$myMembership) {
            return new JsonResponse(['error' => 'Du bist kein Mitglied dieses Departments'], 403);
        }

        $myIndex = array_search($myMembership->getRole(), self::MEMBERSHIP_ROLE_HIERARCHY, true);
        $targetIndex = array_search($targetRole, self::MEMBERSHIP_ROLE_HIERARCHY, true);

        if ($myIndex === false || $targetIndex === false) {
            return new JsonResponse(['error' => 'Ungültige Rolle'], 400);
        }

        if ($targetIndex <= $myIndex) {
            return new JsonResponse([
                'error' => 'Du kannst nur Mitglieder mit niedrigerer Rolle bearbeiten',
            ], 403);
        }

        return true;
    }

    private function hashPasswordResetCode(string $email, string $code): string
    {
        return hash('sha256', strtolower($email) . '|' . strtoupper($code) . '|' . $this->appSecret);
    }

    /**
     * Fügt einen bestehenden User zu einem Department hinzu (erstellt Membership)
     */
    #[Route('/{departmentId}/members', name: 'add_member', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addMember(string $departmentId, Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $department = $this->departmentRepository->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['user_id'])) {
            return new JsonResponse(['error' => 'user_id ist erforderlich'], 400);
        }

        $user = $this->entityManager->getRepository(User::class)->find($data['user_id']);
        if (!$user) {
            return new JsonResponse(['error' => 'User nicht gefunden'], 404);
        }

        if ($user->hasSuperAdminProfile()) {
            return new JsonResponse(['error' => 'Superadmin-Konten sind keiner Abteilung zuordenbar'], 400);
        }

        // Prüfe ob User schon Mitglied ist
        $existing = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $data['user_id'], 'departmentId' => $departmentId]);
        if ($existing) {
            return new JsonResponse(['error' => 'User ist bereits Mitglied dieses Departments'], 409);
        }

        // Rolle validieren
        $role = $data['role'] ?? 'u';
        if (!in_array($role, self::MEMBERSHIP_ROLE_HIERARCHY, true)) {
            return new JsonResponse(['error' => 'Ungültige Rolle'], 400);
        }

        if ($department->isGrossanlass()) {
            if (!in_array($role, ['mw', 'u'], true)) {
                return new JsonResponse(['error' => 'Bei Grossanlass-Departments sind nur Materialchef (mw) und Mitglied (u) erlaubt'], 400);
            }
            if ($role === 'mw') {
                $existingMw = $this->entityManager->getRepository(Membership::class)
                    ->createQueryBuilder('m')
                    ->select('COUNT(m.userId)')
                    ->where('m.departmentId = :departmentId')
                    ->andWhere('m.role = :role')
                    ->setParameter('departmentId', $departmentId)
                    ->setParameter('role', 'mw')
                    ->getQuery()
                    ->getSingleScalarResult();
                if ((int) $existingMw > 0) {
                    return new JsonResponse(['error' => 'Dieser Grossanlass hat bereits einen Materialchef'], 409);
                }
            }
        }

        // Rollen-Hierarchie prüfen: darf der aktuelle User diese Rolle vergeben?
        $roleCheck = $this->canAssignRole($departmentId, $role);
        if ($roleCheck instanceof JsonResponse) {
            return $roleCheck;
        }

        try {
            $membership = new Membership();
            $membership->setUser($user);
            $membership->setDepartment($department);
            $membership->setRole($role);
            $membership->setIsPrimary($data['is_primary'] ?? false);
            $membership->setIsJsCoach(!empty($data['is_js_coach']));

            $this->auditLogger->log(
                'membership',
                AuditLogger::buildMembershipEntityId($user->getId(), $department->getId()),
                'membership_created',
                $currentUser,
                $user,
                $department,
                [
                    'role' => ['old' => null, 'new' => $membership->getRole()],
                    'is_primary' => ['old' => null, 'new' => $membership->getIsPrimary()],
                    'is_js_coach' => ['old' => null, 'new' => $membership->getIsJsCoach()],
                ]
            );

            $this->entityManager->persist($membership);
            if ($membership->getIsJsCoach()) {
                $this->departmentDefaultCoachSync->refreshDefaultAfterFlagChange($department, $user->getId());
            }
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Hinzufügen: ' . $e->getMessage()
            ], 500);
        }

        $profile = $user->getProfile();

        $notificationEmailSent = false;
        if ($profile && filter_var($profile->getEmail(), FILTER_VALIDATE_EMAIL)) {
            $adderName = trim((string) ($currentUser->getProfile()?->getDisplayName() ?? ''));
            if ($adderName === '') {
                $adderName = trim((string) ($currentUser->getProfile()?->getEmail() ?? ''));
            }
            if ($adderName === '') {
                $adderName = 'Ein Teammitglied';
            }
            try {
                $this->verificationEmailService->sendDepartmentMemberAddedEmail(
                    $profile->getEmail(),
                    $profile->getDisplayName(),
                    $adderName,
                    $department->getName(),
                    $this->departmentRoleLabelService->labelForRole($membership->getRole(), $department->getId()),
                    $department->getId(),
                    $department->isGrossanlass(),
                    $profile->getLanguage()
                );
                $notificationEmailSent = true;
            } catch (\Throwable) {
                $notificationEmailSent = false;
            }
        }

        return new JsonResponse([
            'user_id' => $user->getId(),
            'profile_id' => $profile ? $profile->getId() : null,
            'name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
            'email' => $profile ? $profile->getEmail() : '',
            'role' => $membership->getRole(),
            'is_primary' => $membership->getIsPrimary(),
            'is_js_coach' => $membership->getIsJsCoach(),
            'notification_email_sent' => $notificationEmailSent,
        ], 201);
    }

    /**
     * Aktualisiert die Rolle eines Mitglieds in einem Department
     */
    #[Route('/{departmentId}/members/{userId}', name: 'update_member', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateMember(string $departmentId, string $userId, Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $userId, 'departmentId' => $departmentId]);

        if (!$membership) {
            return new JsonResponse(['error' => 'Mitgliedschaft nicht gefunden'], 404);
        }

        $targetUser = $membership->getUser();
        if ($targetUser->hasSuperAdminProfile()) {
            return new JsonResponse(['error' => 'Superadmin-Konten haben keine Abteilungsrollen in der Verwaltung'], 403);
        }

        $manageCheck = $this->canManageMembershipTarget($departmentId, $membership->getRole());
        if ($manageCheck instanceof JsonResponse) {
            return $manageCheck;
        }

        $data = json_decode($request->getContent(), true);
        $oldRole = $membership->getRole();
        $oldIsPrimary = $membership->getIsPrimary();
        $oldIsJsCoach = $membership->getIsJsCoach();

        if (isset($data['role'])) {
            if (!in_array($data['role'], self::MEMBERSHIP_ROLE_HIERARCHY, true)) {
                return new JsonResponse(['error' => 'Ungültige Rolle'], 400);
            }

            $department = $membership->getDepartment();
            if ($department->isGrossanlass()) {
                if (!in_array($data['role'], ['mw', 'u'], true)) {
                    return new JsonResponse(['error' => 'Bei Grossanlass-Departments sind nur Materialchef (mw) und Mitglied (u) erlaubt'], 400);
                }
                if ($data['role'] === 'mw' && $oldRole !== 'mw') {
                    $existingMw = $this->entityManager->getRepository(Membership::class)
                        ->createQueryBuilder('m')
                        ->select('COUNT(m.userId)')
                        ->where('m.departmentId = :departmentId')
                        ->andWhere('m.role = :role')
                        ->andWhere('m.userId != :userId')
                        ->setParameter('departmentId', $departmentId)
                        ->setParameter('role', 'mw')
                        ->setParameter('userId', $userId)
                        ->getQuery()
                        ->getSingleScalarResult();
                    if ((int) $existingMw > 0) {
                        return new JsonResponse(['error' => 'Dieser Grossanlass hat bereits einen Materialchef'], 409);
                    }
                }
            }

            // Rollen-Hierarchie prüfen: darf der aktuelle User diese Rolle vergeben?
            $roleCheck = $this->canAssignRole($departmentId, $data['role']);
            if ($roleCheck instanceof JsonResponse) {
                return $roleCheck;
            }

            $membership->setRole($data['role']);
        }

        if (isset($data['is_primary'])) {
            $membership->setIsPrimary((bool) $data['is_primary']);
        }

        if (array_key_exists('is_js_coach', $data)) {
            $membership->setIsJsCoach((bool) $data['is_js_coach']);
        }

        if ($oldRole !== $membership->getRole()) {
            $this->auditLogger->log(
                'membership',
                AuditLogger::buildMembershipEntityId($membership->getUserId(), $membership->getDepartmentId()),
                'membership_role_changed',
                $currentUser,
                $membership->getUser(),
                $membership->getDepartment(),
                [
                    'role' => ['old' => $oldRole, 'new' => $membership->getRole()],
                ]
            );
        }

        if ($oldIsPrimary !== $membership->getIsPrimary()) {
            $this->auditLogger->log(
                'membership',
                AuditLogger::buildMembershipEntityId($membership->getUserId(), $membership->getDepartmentId()),
                'membership_primary_changed',
                $currentUser,
                $membership->getUser(),
                $membership->getDepartment(),
                [
                    'is_primary' => ['old' => $oldIsPrimary, 'new' => $membership->getIsPrimary()],
                ]
            );
        }

        if ($oldIsJsCoach !== $membership->getIsJsCoach()) {
            $this->auditLogger->log(
                'membership',
                AuditLogger::buildMembershipEntityId($membership->getUserId(), $membership->getDepartmentId()),
                'membership_js_coach_changed',
                $currentUser,
                $membership->getUser(),
                $membership->getDepartment(),
                [
                    'is_js_coach' => ['old' => $oldIsJsCoach, 'new' => $membership->getIsJsCoach()],
                ]
            );
            $this->departmentDefaultCoachSync->refreshDefaultAfterFlagChange(
                $membership->getDepartment(),
                $membership->getIsJsCoach() ? $membership->getUserId() : null,
            );
        }

        $this->entityManager->flush();

        $user = $membership->getUser();
        $profile = $user->getProfile();

        return new JsonResponse([
            'user_id' => $user->getId(),
            'name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
            'email' => $profile ? $profile->getEmail() : '',
            'role' => $membership->getRole(),
            'is_primary' => $membership->getIsPrimary(),
            'is_js_coach' => $membership->getIsJsCoach(),
        ]);
    }

    /**
     * Profil eines Department-Mitglieds bearbeiten (Hierarchie-streng).
     */
    #[Route('/{departmentId}/members/{userId}/profile', name: 'update_member_profile', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateMemberProfile(string $departmentId, string $userId, Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $userId, 'departmentId' => $departmentId]);
        if (!$membership) {
            return new JsonResponse(['error' => 'Mitgliedschaft nicht gefunden'], 404);
        }

        if ($currentUser->getId() === $userId) {
            return new JsonResponse(['error' => 'Eigenes Profil bitte über «Profil bearbeiten» ändern'], 400);
        }

        $targetUser = $membership->getUser();
        if ($targetUser->hasSuperAdminProfile()) {
            return new JsonResponse(['error' => 'Superadmin-Konten können hier nicht bearbeitet werden'], 403);
        }

        $manageCheck = $this->canManageMembershipTarget($departmentId, $membership->getRole());
        if ($manageCheck instanceof JsonResponse) {
            return $manageCheck;
        }

        $profile = $targetUser->getProfile();
        if (!$profile instanceof Profile) {
            return new JsonResponse(['error' => 'Profil nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $profileChanges = [];
        $emailChangeRequested = null;

        if (array_key_exists('email', $data)) {
            $email = trim((string) $data['email']);
            if ($email === '') {
                return new JsonResponse(['error' => 'E-Mail darf nicht leer sein'], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return new JsonResponse(['error' => 'Ungültige E-Mail-Adresse'], 400);
            }
            $currentEmail = strtolower($profile->getEmail());
            $requestedEmail = strtolower($email);

            if ($requestedEmail !== $currentEmail) {
                $existing = $this->entityManager->getRepository(Profile::class)->findOneBy(['email' => $requestedEmail]);
                if ($existing && $existing->getId() !== $profile->getId()) {
                    return new JsonResponse(['error' => 'E-Mail ist bereits vergeben'], 409);
                }

                $pendingToken = bin2hex(random_bytes(32));
                $pendingExpiresAt = (new \DateTime())->modify('+10 days');
                $previousPendingEmail = $targetUser->getPendingEmail();

                $targetUser->setPendingEmail($requestedEmail);
                $targetUser->setEmailVerificationToken($pendingToken);
                $targetUser->setEmailVerificationExpiresAt($pendingExpiresAt);

                try {
                    $this->verificationEmailService->sendPendingEmailChangeVerification(
                        $targetUser,
                        $requestedEmail,
                        $pendingToken,
                        $pendingExpiresAt
                    );
                } catch (\Throwable) {
                    $targetUser->setPendingEmail(null);
                    $targetUser->setEmailVerificationToken(null);
                    $targetUser->setEmailVerificationExpiresAt(null);

                    return new JsonResponse([
                        'error' => 'Bestätigungslink konnte nicht gesendet werden. Bitte E-Mail-Adresse prüfen.',
                    ], 400);
                }

                $emailChangeRequested = [
                    'email' => ['old' => $currentEmail, 'new' => $requestedEmail],
                    'pending_email' => ['old' => $previousPendingEmail, 'new' => $requestedEmail],
                ];
            }
        }

        if (array_key_exists('first_name', $data)) {
            $oldFirstName = $profile->getFirstName();
            $firstName = trim((string) ($data['first_name'] ?? ''));
            $newFirstName = $firstName !== '' ? $firstName : null;
            $profile->setFirstName($newFirstName);
            if ($oldFirstName !== $newFirstName) {
                $profileChanges['first_name'] = ['old' => $oldFirstName, 'new' => $newFirstName];
            }
        }

        if (array_key_exists('last_name', $data)) {
            $oldLastName = $profile->getLastName();
            $lastName = trim((string) ($data['last_name'] ?? ''));
            $newLastName = $lastName !== '' ? $lastName : null;
            $profile->setLastName($newLastName);
            if ($oldLastName !== $newLastName) {
                $profileChanges['last_name'] = ['old' => $oldLastName, 'new' => $newLastName];
            }
        }

        if (array_key_exists('nickname', $data)) {
            $oldNickname = $profile->getNickname();
            $nickname = trim((string) ($data['nickname'] ?? ''));
            $newNickname = $nickname !== '' ? $nickname : null;
            $profile->setNickname($newNickname);
            if ($oldNickname !== $newNickname) {
                $profileChanges['nickname'] = ['old' => $oldNickname, 'new' => $newNickname];
            }
        }

        if (array_key_exists('avatar_initials', $data)) {
            $oldAvatarInitials = $profile->getAvatarInitials();
            $avatarInitials = strtoupper(trim((string) ($data['avatar_initials'] ?? '')));
            if ($avatarInitials !== '' && mb_strlen($avatarInitials) > 2) {
                return new JsonResponse(['error' => 'Initialen dürfen maximal 2 Zeichen haben'], 400);
            }
            $newAvatarInitials = $avatarInitials !== '' ? $avatarInitials : null;
            $profile->setAvatarInitials($newAvatarInitials);
            if ($oldAvatarInitials !== $newAvatarInitials) {
                $profileChanges['avatar_initials'] = ['old' => $oldAvatarInitials, 'new' => $newAvatarInitials];
            }
        }

        if (array_key_exists('language', $data)) {
            $oldLanguage = $profile->getLanguage();
            $language = strtolower(trim((string) ($data['language'] ?? '')));
            $allowedLanguages = ['de', 'en', 'fr', 'it'];
            if (!in_array($language, $allowedLanguages, true)) {
                return new JsonResponse(['error' => 'Ungültige Sprache'], 400);
            }
            $profile->setLanguage($language);
            if ($oldLanguage !== $language) {
                $profileChanges['language'] = ['old' => $oldLanguage, 'new' => $language];
            }
        }

        if ($emailChangeRequested !== null || $profileChanges !== []) {
            $profile->setUpdatedAt(new \DateTime());
        }

        if ($emailChangeRequested !== null) {
            $this->auditLogger->log(
                'profile',
                $profile->getId(),
                'profile_email_change_requested',
                $currentUser,
                $targetUser,
                $membership->getDepartment(),
                $emailChangeRequested
            );
        }

        if ($profileChanges !== []) {
            $this->auditLogger->log(
                'profile',
                $profile->getId(),
                'profile_updated',
                $currentUser,
                $targetUser,
                $membership->getDepartment(),
                $profileChanges
            );
        }

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return new JsonResponse(['error' => 'E-Mail ist bereits vergeben'], 409);
        }

        return new JsonResponse([
            'user_id' => $targetUser->getId(),
            'profile_id' => $profile->getId(),
            'name' => $profile->getDisplayName(),
            'first_name' => $profile->getFirstName(),
            'last_name' => $profile->getLastName(),
            'nickname' => $profile->getNickname(),
            'email' => $profile->getEmail(),
            'avatar_initials' => $profile->getAvatarInitials(),
            'background_color' => $profile->getBackgroundColor(),
            'text_color' => $profile->getTextColor(),
            'language' => $profile->getLanguage(),
            'pending_email' => $targetUser->getPendingEmail(),
            'role' => $membership->getRole(),
            'is_primary' => $membership->getIsPrimary(),
            'is_js_coach' => $membership->getIsJsCoach(),
        ]);
    }

    /**
     * Passwort-Reset-Code an ein Department-Mitglied senden (Hierarchie-streng).
     */
    #[Route('/{departmentId}/members/{userId}/send-password-reset', name: 'send_member_password_reset', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function sendMemberPasswordReset(string $departmentId, string $userId): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $userId, 'departmentId' => $departmentId]);
        if (!$membership) {
            return new JsonResponse(['error' => 'Mitgliedschaft nicht gefunden'], 404);
        }

        if ($currentUser->getId() === $userId) {
            return new JsonResponse(['error' => 'Eigenen Passwort-Reset bitte über Login anfordern'], 400);
        }

        $targetUser = $membership->getUser();
        if ($targetUser->hasSuperAdminProfile()) {
            return new JsonResponse(['error' => 'Superadmin-Konten können hier nicht bearbeitet werden'], 403);
        }

        $manageCheck = $this->canManageMembershipTarget($departmentId, $membership->getRole());
        if ($manageCheck instanceof JsonResponse) {
            return $manageCheck;
        }

        $profile = $targetUser->getProfile();
        if (!$profile instanceof Profile) {
            return new JsonResponse(['error' => 'Profil nicht gefunden'], 404);
        }

        $email = strtolower(trim($profile->getEmail()));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Mitglied hat keine gültige E-Mail-Adresse'], 400);
        }

        $now = new \DateTime();
        $lockedUntil = $targetUser->getPasswordResetLockedUntil();
        if ($lockedUntil && $lockedUntil > $now) {
            return new JsonResponse(['error' => 'Passwort-Reset ist vorübergehend gesperrt. Bitte später erneut versuchen.'], 429);
        }

        $windowStartedAt = $targetUser->getPasswordResetWindowStartedAt();
        $windowExpired = !$windowStartedAt || $windowStartedAt < (clone $now)->modify('-1 hour');
        if ($windowExpired) {
            $targetUser->setPasswordResetWindowStartedAt(clone $now);
            $targetUser->setPasswordResetRequestCount(0);
        }

        if ($targetUser->getPasswordResetRequestCount() >= self::PASSWORD_RESET_MAX_REQUESTS_PER_HOUR) {
            $targetUser->setPasswordResetLockedUntil((clone $now)->modify('+1 hour'));
            $this->entityManager->flush();

            return new JsonResponse(['error' => 'Zu viele Reset-Anfragen. Bitte später erneut versuchen.'], 429);
        }

        $lastRequestedAt = $targetUser->getPasswordResetLastRequestedAt();
        if ($lastRequestedAt && $lastRequestedAt > (clone $now)->modify('-' . self::PASSWORD_RESET_REQUEST_COOLDOWN_SECONDS . ' seconds')) {
            return new JsonResponse(['error' => 'Bitte kurz warten, bevor erneut ein Reset gesendet wird.'], 429);
        }

        $code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $expiresAt = (clone $now)->modify('+' . self::PASSWORD_RESET_CODE_TTL_MINUTES . ' minutes');

        $targetUser->setPasswordResetCodeHash($this->hashPasswordResetCode($email, $code));
        $targetUser->setPasswordResetExpiresAt($expiresAt);
        $targetUser->setPasswordResetLastRequestedAt(clone $now);
        $targetUser->setPasswordResetAttemptCount(0);
        $targetUser->setPasswordResetLockedUntil(null);
        $targetUser->setPasswordResetRequestCount($targetUser->getPasswordResetRequestCount() + 1);

        try {
            $this->verificationEmailService->sendPasswordResetCode($targetUser, $code, $expiresAt);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Reset-E-Mail konnte nicht gesendet werden'], 500);
        }

        $this->auditLogger->log(
            'user',
            $targetUser->getId(),
            'password_reset_sent_by_manager',
            $currentUser,
            $targetUser,
            $membership->getDepartment(),
            ['email' => $email]
        );

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Passwort-Reset wurde an ' . $email . ' gesendet.',
        ]);
    }

    /**
     * Entfernt ein Mitglied aus einem Department
     */
    #[Route('/{departmentId}/members/{userId}', name: 'remove_member', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function removeMember(string $departmentId, string $userId): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['userId' => $userId, 'departmentId' => $departmentId]);

        if (!$membership) {
            return new JsonResponse(['error' => 'Mitgliedschaft nicht gefunden'], 404);
        }

        if ($currentUser->getId() === $userId) {
            return new JsonResponse([
                'error' => 'Du kannst dich hier nicht selbst aus dem Department entfernen.',
            ], 400);
        }

        $manageCheck = $this->canManageMembershipTarget($departmentId, $membership->getRole());
        if ($manageCheck instanceof JsonResponse) {
            return $manageCheck;
        }

        $wasJsCoach = $membership->getIsJsCoach();
        $department = $membership->getDepartment();

        $this->auditLogger->log(
            'membership',
            AuditLogger::buildMembershipEntityId($membership->getUserId(), $membership->getDepartmentId()),
            'membership_removed',
            $currentUser,
            $membership->getUser(),
            $department,
            [
                'role' => ['old' => $membership->getRole(), 'new' => null],
                'is_primary' => ['old' => $membership->getIsPrimary(), 'new' => null],
                'is_js_coach' => ['old' => $wasJsCoach, 'new' => null],
            ]
        );

        // Alle Gruppen-Zugehörigkeiten in diesem Department entfernen
        $departmentGroupIds = $this->entityManager->getRepository(Group::class)
            ->createQueryBuilder('g')
            ->select('g.id')
            ->where('g.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->getQuery()
            ->getSingleColumnResult();

        if ($departmentGroupIds !== []) {
            $this->entityManager->createQueryBuilder()
                ->delete(GroupMembership::class, 'gm')
                ->where('gm.userId = :userId')
                ->andWhere('gm.groupId IN (:groupIds)')
                ->setParameter('userId', $userId)
                ->setParameter('groupIds', $departmentGroupIds)
                ->getQuery()
                ->execute();
        }

        $this->entityManager->remove($membership);
        if ($wasJsCoach) {
            $this->departmentDefaultCoachSync->refreshDefaultAfterFlagChange($department);
        }
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * Listet alle User die NICHT im Department sind (für Hinzufügen-Dialog)
     */
    #[Route('/{departmentId}/available-users', name: 'available_users', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function availableUsers(string $departmentId, Request $request): JsonResponse
    {
        $department = $this->departmentRepository->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        // Alle User laden die NICHT im Department sind
        $qb = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->innerJoin('u.profile', 'p')
            ->addSelect('p')
            ->where('u.state = :state')
            ->setParameter('state', 'active')
            ->andWhere(
                'NOT EXISTS (
                    SELECT 1 FROM App\Entity\Membership m
                    WHERE m.userId = u.id AND m.departmentId = :departmentId
                )'
            )
            ->setParameter('departmentId', $departmentId);

        $search = trim((string) $request->query->get('q', ''));
        if ($search !== '') {
            $tokens = preg_split('/\s+/u', mb_strtolower($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($tokens as $index => $token) {
                $param = 'searchToken' . $index;
                $qb->andWhere(
                    $qb->expr()->orX(
                        "LOWER(p.email) LIKE :{$param}",
                        "LOWER(p.firstName) LIKE :{$param}",
                        "LOWER(p.lastName) LIKE :{$param}",
                        "LOWER(p.nickname) LIKE :{$param}",
                        "LOWER(CONCAT(COALESCE(p.firstName, ''), ' ', COALESCE(p.lastName, ''))) LIKE :{$param}",
                        "LOWER(CONCAT(COALESCE(p.lastName, ''), ' ', COALESCE(p.firstName, ''))) LIKE :{$param}",
                        "EXISTS (
                            SELECT 1 FROM App\Entity\Membership ms
                            INNER JOIN ms.department ds
                            WHERE ms.userId = u.id AND LOWER(ds.name) LIKE :{$param}
                        )"
                    )
                )->setParameter($param, '%' . $token . '%');
            }
        }

        $users = $qb->orderBy('p.lastName', 'ASC')
            ->addOrderBy('p.firstName', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        $userIds = [];
        foreach ($users as $user) {
            if (!$user->hasSuperAdminProfile()) {
                $userIds[] = $user->getId();
            }
        }

        $membershipsByUser = [];
        if ($userIds !== []) {
            $memberships = $this->entityManager->getRepository(Membership::class)
                ->createQueryBuilder('m')
                ->innerJoin('m.department', 'd')
                ->addSelect('d')
                ->where('m.userId IN (:userIds)')
                ->setParameter('userIds', $userIds)
                ->orderBy('d.name', 'ASC')
                ->getQuery()
                ->getResult();

            foreach ($memberships as $membership) {
                if (!$membership instanceof Membership) {
                    continue;
                }
                $uid = $membership->getUserId();
                $membershipsByUser[$uid][] = $membership;
            }
        }

        $result = [];
        foreach ($users as $user) {
            if ($user->hasSuperAdminProfile()) {
                continue;
            }
            $profile = $user->getProfile();
            if (!$profile || E2eSmokeUser::isExcluded($profile->getEmail())) {
                continue;
            }

            $deptNames = [];
            $primaryDepartmentName = null;
            foreach ($membershipsByUser[$user->getId()] ?? [] as $membership) {
                $deptName = $membership->getDepartment()->getName();
                $deptNames[] = $deptName;
                if ($membership->getIsPrimary()) {
                    $primaryDepartmentName = $deptName;
                }
            }
            if ($primaryDepartmentName === null && $deptNames !== []) {
                $primaryDepartmentName = $deptNames[0];
            }

            $result[] = [
                'id' => $user->getId(),
                'name' => $profile->getDisplayName(),
                'email' => $profile->getEmail(),
                'first_name' => $profile->getFirstName(),
                'last_name' => $profile->getLastName(),
                'nickname' => $profile->getNickname(),
                'primary_department_name' => $primaryDepartmentName,
                'departments_label' => $deptNames !== [] ? implode(', ', $deptNames) : null,
            ];
        }

        return new JsonResponse($result);
    }

    /**
     * DB zurücksetzen – löscht alle Daten des Departments (Aktivitäten, Materialien, Adressen, etc.)
     * Nur für Dev/Test. Erfordert Superadmin oder Department-Manager.
     */
    #[Route('/{departmentId}/reset-db', name: 'reset_db', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function resetDb(string $departmentId): JsonResponse
    {
        if (!$this->devEnvironmentService->isDevToolsEnabled()) {
            return new JsonResponse(['error' => 'Nur in Dev/Test verfügbar'], 403);
        }

        return $this->runDepartmentManagerReset(
            $departmentId,
            fn () => $this->departmentResetService->resetDepartment($departmentId),
            'Department-Daten zurückgesetzt',
            'Keine Berechtigung für DB-Reset',
            'Fehler beim Zurücksetzen'
        );
    }

    /**
     * Aktivitäten löschen – setzt die Aktivitäten-Anzahl auf 0 (Material/Adressen bleiben).
     * Nur für Dev/Test. Erfordert Superadmin oder Department-Manager.
     */
    #[Route('/{departmentId}/reset-activities', name: 'reset_activities', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function resetActivities(string $departmentId): JsonResponse
    {
        if (!$this->devEnvironmentService->isDevToolsEnabled()) {
            return new JsonResponse(['error' => 'Nur in Dev/Test verfügbar'], 403);
        }

        return $this->runDepartmentManagerReset(
            $departmentId,
            fn () => $this->departmentResetService->resetActivities($departmentId),
            'Aktivitäten gelöscht',
            'Keine Berechtigung für Aktivitäten-Reset',
            'Fehler beim Löschen der Aktivitäten'
        );
    }

    /**
     * @param callable(): array<string, int> $resetFn
     */
    private function runDepartmentManagerReset(
        string $departmentId,
        callable $resetFn,
        string $successPrefix,
        string $forbiddenMessage,
        string $errorPrefix,
    ): JsonResponse {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $department = $this->departmentRepository->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $isSuperadmin = $this->isGranted('ROLE_SUPERADMIN');
        $membership = $this->entityManager->getRepository(Membership::class)
            ->findOneBy(['departmentId' => $departmentId, 'userId' => $currentUser->getId()]);
        $role = $membership?->getRole() ?? '';
        $managerRoles = ['sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef', 'mw', 'matwart', 'dc', 'depchef'];
        $isManager = in_array(strtolower($role), array_map('strtolower', $managerRoles));

        if (!$isSuperadmin && !$isManager) {
            return new JsonResponse(['error' => $forbiddenMessage], 403);
        }

        try {
            $deleted = $resetFn();
            $total = array_sum($deleted);
            return new JsonResponse([
                'success' => true,
                'message' => "$successPrefix. $total Datensätze gelöscht.",
                'deleted' => $deleted,
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => "$errorPrefix: " . $e->getMessage()], 500);
        }
    }

    /**
     * Aktualisiert ein Department
     */
    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $id, Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }
        if (!$this->adminCapabilityChecker->can($currentUser, 'departments.edit')) {
            return new JsonResponse(['error' => 'Zugriff verweigert'], 403);
        }

        $department = $this->departmentRepository->find($id);
        
        if (!$department) {
            return new JsonResponse(['error' => 'Department not found'], 404);
        }

        if (!$this->adminCapabilityChecker->canAccessDepartment($currentUser, $department->getId())) {
            return new JsonResponse(['error' => 'Zugriff verweigert'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $newName = trim((string) $data['name']);
            if ($newName === '') {
                return new JsonResponse(['error' => 'Name ist erforderlich'], 400);
            }
            $targetOrganisationId = isset($data['organisation_id'])
                ? (string) $data['organisation_id']
                : $department->getOrganisationId();
            $conflict = $this->departmentRepository->findConflictingByOrganisationAndName(
                $targetOrganisationId,
                $newName,
                $department->getId(),
            );
            if ($conflict instanceof Department) {
                return new JsonResponse(
                    ['error' => 'Ein Department mit diesem oder einem sehr ähnlichen Namen existiert bereits: «' . $conflict->getName() . '»'],
                    409,
                );
            }
            $department->setName($newName);
        }

        if (isset($data['organisation_id'])) {
            $organisation = $this->entityManager->getRepository(Organisation::class)
                ->find($data['organisation_id']);
            
            if (!$organisation) {
                return new JsonResponse(['error' => 'Organisation nicht gefunden'], 404);
            }
            if (!OrganisationUserPickerFilter::isVisibleForUserPickers($organisation)) {
                return new JsonResponse(['error' => 'Organisation nicht verfuegbar'], 400);
            }
            if (!$this->adminCapabilityChecker->canAccessOrganisation($currentUser, $organisation->getId())) {
                return new JsonResponse(['error' => 'Zugriff verweigert'], 403);
            }

            $department->setOrganisation($organisation);
        }

        if (isset($data['parent_id'])) {
            if (empty($data['parent_id'])) {
                // Parent entfernen (wird zu Haupt-Department)
                $department->setParent(null);
            } else {
                $parent = $this->departmentRepository->find($data['parent_id']);
                if (!$parent) {
                    return new JsonResponse(['error' => 'Parent Department nicht gefunden'], 404);
                }
                // Prüfe ob Parent zur gleichen Organisation gehört
                if ($parent->getOrganisationId() !== $department->getOrganisationId()) {
                    return new JsonResponse(['error' => 'Parent Department muss zur gleichen Organisation gehören'], 400);
                }
                $department->setParent($parent);
            }
        }

        $department->updateTimestamps();
        $this->entityManager->flush();

        // Memberships laden für Response
        $memberships = $this->entityManager->getRepository(Membership::class)
            ->createQueryBuilder('m')
            ->innerJoin('m.user', 'u')
            ->innerJoin('u.profile', 'p')
            ->addSelect('u', 'p')
            ->where('m.departmentId = :departmentId')
            ->setParameter('departmentId', $id)
            ->getQuery()
            ->getResult();

        $users = [];
        foreach ($memberships as $m) {
            $user = $m->getUser();
            $profile = $user->getProfile();
            if ($profile) {
                $users[] = [
                    'id' => $user->getId(),
                    'profile_id' => $profile->getId(),
                    'name' => $profile->getDisplayName(),
                    'first_name' => $profile->getFirstName(),
                    'last_name' => $profile->getLastName(),
                    'nickname' => $profile->getNickname(),
                    'email' => $profile->getEmail(),
                    'avatar_initials' => $profile->getAvatarInitials(),
                    'background_color' => $profile->getBackgroundColor(),
                    'text_color' => $profile->getTextColor(),
                    'role' => $m->getRole(),
                    'is_primary' => $m->getIsPrimary()
                ];
            }
        }

        return new JsonResponse([
            'id' => $department->getId(),
            'name' => $department->getName(),
            'organisation_id' => $department->getOrganisationId(),
            'parent_id' => $department->getParentId(),
            'users' => $users
        ]);
    }
}
