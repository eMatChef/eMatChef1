<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/groups', name: 'api_groups_')]
class GroupController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    // ========================================
    // GRUPPEN CRUD
    // ========================================

    /**
     * Listet alle Gruppen eines Departments (hierarchisch sortiert)
     * inkl. Mitglieder mit Name und Rolle
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $departmentId = $request->query->get('department_id');
        if (!$departmentId) {
            return new JsonResponse(['error' => 'department_id Parameter erforderlich'], 400);
        }

        // Gruppen laden
        $groups = $this->entityManager->getRepository(Group::class)
            ->createQueryBuilder('g')
            ->where('g.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('g.sortOrder', 'ASC')
            ->addOrderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Alle Memberships für diese Gruppen laden (mit User + Profile)
        $groupIds = array_map(fn(Group $g) => $g->getId(), $groups);
        
        $memberships = [];
        if (!empty($groupIds)) {
            $memberships = $this->entityManager->getRepository(GroupMembership::class)
                ->createQueryBuilder('gm')
                ->innerJoin('gm.user', 'u')
                ->innerJoin('u.profile', 'p')
                ->addSelect('u', 'p')
                ->where('gm.groupId IN (:groupIds)')
                ->setParameter('groupIds', $groupIds)
                ->orderBy('gm.role', 'ASC')
                ->getQuery()
                ->getResult();
        }

        // Memberships nach Group-ID gruppieren
        $membershipsByGroup = [];
        foreach ($memberships as $m) {
            $gid = $m->getGroupId();
            if (!isset($membershipsByGroup[$gid])) {
                $membershipsByGroup[$gid] = [];
            }
            $user = $m->getUser();
            $profile = $user->getProfile();
            $membershipsByGroup[$gid][] = [
                'user_id' => $user->getId(),
                'name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
                'email' => $profile ? $profile->getEmail() : '',
                'role' => $m->getRole(),
                'role_label' => $m->getRoleLabel(),
                'is_leader' => $m->isLeader(),
                'is_primary' => $m->getIsPrimary(),
            ];
        }

        // Response bauen
        $result = [];
        foreach ($groups as $group) {
            $gid = $group->getId();
            $members = $membershipsByGroup[$gid] ?? [];
            
            // Leiter extrahieren
            $leaders = array_filter($members, fn($m) => $m['is_leader']);
            
            $result[] = [
                'id' => $gid,
                'name' => $group->getName(),
                'department_id' => $group->getDepartmentId(),
                'parent_id' => $group->getParentId(),
                'sort_order' => $group->getSortOrder(),
                'member_count' => count($members),
                'leader_count' => count($leaders),
                'members' => array_values($members),
                'leaders' => array_values($leaders),
                'created_at' => $group->getCreatedAt()->format('c'),
                'updated_at' => $group->getUpdatedAt()->format('c'),
            ];
        }

        return new JsonResponse($result);
    }

    /**
     * Einzelne Gruppe mit Details laden
     */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get(string $id): JsonResponse
    {
        $group = $this->entityManager->getRepository(Group::class)->find($id);
        if (!$group) {
            return new JsonResponse(['error' => 'Gruppe nicht gefunden'], 404);
        }

        // Memberships laden
        $memberships = $this->entityManager->getRepository(GroupMembership::class)
            ->createQueryBuilder('gm')
            ->innerJoin('gm.user', 'u')
            ->innerJoin('u.profile', 'p')
            ->addSelect('u', 'p')
            ->where('gm.groupId = :groupId')
            ->setParameter('groupId', $id)
            ->orderBy('gm.role', 'ASC')
            ->getQuery()
            ->getResult();

        $members = [];
        foreach ($memberships as $m) {
            $user = $m->getUser();
            $profile = $user->getProfile();
            $members[] = [
                'user_id' => $user->getId(),
                'name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
                'email' => $profile ? $profile->getEmail() : '',
                'role' => $m->getRole(),
                'role_label' => $m->getRoleLabel(),
                'is_leader' => $m->isLeader(),
                'is_primary' => $m->getIsPrimary(),
            ];
        }

        $leaders = array_values(array_filter($members, fn($m) => $m['is_leader']));

        return new JsonResponse([
            'id' => $group->getId(),
            'name' => $group->getName(),
            'department_id' => $group->getDepartmentId(),
            'parent_id' => $group->getParentId(),
            'sort_order' => $group->getSortOrder(),
            'member_count' => count($members),
            'leader_count' => count($leaders),
            'members' => $members,
            'leaders' => $leaders,
            'created_at' => $group->getCreatedAt()->format('c'),
            'updated_at' => $group->getUpdatedAt()->format('c'),
        ]);
    }

    /**
     * Neue Gruppe erstellen
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name']) || !isset($data['department_id'])) {
            return new JsonResponse(['error' => 'name und department_id sind erforderlich'], 400);
        }

        // Department prüfen
        $department = $this->entityManager->getRepository(Department::class)
            ->find($data['department_id']);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        // Parent prüfen (optional)
        $parent = null;
        if (!empty($data['parent_id'])) {
            $parent = $this->entityManager->getRepository(Group::class)
                ->find($data['parent_id']);
            if (!$parent) {
                return new JsonResponse(['error' => 'Übergeordnete Gruppe nicht gefunden'], 404);
            }
            if ($parent->getDepartmentId() !== $data['department_id']) {
                return new JsonResponse(['error' => 'Übergeordnete Gruppe muss zum gleichen Department gehören'], 400);
            }
        }

        try {
            $group = new Group();
            $group->setId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, Group::class, 'grp'));
            $group->setDepartment($department);
            $group->setName($data['name']);
            
            if ($parent) {
                $group->setParent($parent);
            }
            
            if (isset($data['sort_order'])) {
                $group->setSortOrder((int) $data['sort_order']);
            }

            $this->entityManager->persist($group);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Erstellen der Gruppe: ' . $e->getMessage()
            ], 500);
        }

        return new JsonResponse([
            'id' => $group->getId(),
            'name' => $group->getName(),
            'department_id' => $group->getDepartmentId(),
            'parent_id' => $group->getParentId(),
            'sort_order' => $group->getSortOrder(),
            'member_count' => 0,
            'leader_count' => 0,
            'members' => [],
            'leaders' => [],
            'created_at' => $group->getCreatedAt()->format('c'),
            'updated_at' => $group->getUpdatedAt()->format('c'),
        ], 201);
    }

    /**
     * Gruppe aktualisieren
     */
    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $id, Request $request): JsonResponse
    {
        $group = $this->entityManager->getRepository(Group::class)->find($id);
        if (!$group) {
            return new JsonResponse(['error' => 'Gruppe nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $group->setName($data['name']);
        }

        if (array_key_exists('parent_id', $data)) {
            if (empty($data['parent_id'])) {
                $group->setParent(null);
            } else {
                // Zirkuläre Referenz verhindern
                if ($data['parent_id'] === $id) {
                    return new JsonResponse(['error' => 'Gruppe kann nicht sich selbst übergeordnet werden'], 400);
                }
                $parent = $this->entityManager->getRepository(Group::class)
                    ->find($data['parent_id']);
                if (!$parent) {
                    return new JsonResponse(['error' => 'Übergeordnete Gruppe nicht gefunden'], 404);
                }
                if ($parent->getDepartmentId() !== $group->getDepartmentId()) {
                    return new JsonResponse(['error' => 'Übergeordnete Gruppe muss zum gleichen Department gehören'], 400);
                }
                $group->setParent($parent);
            }
        }

        if (isset($data['sort_order'])) {
            $group->setSortOrder((int) $data['sort_order']);
        }

        $group->updateTimestamps();
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $group->getId(),
            'name' => $group->getName(),
            'department_id' => $group->getDepartmentId(),
            'parent_id' => $group->getParentId(),
            'sort_order' => $group->getSortOrder(),
            'updated_at' => $group->getUpdatedAt()->format('c'),
        ]);
    }

    /**
     * Gruppe löschen
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $id): JsonResponse
    {
        $group = $this->entityManager->getRepository(Group::class)->find($id);
        if (!$group) {
            return new JsonResponse(['error' => 'Gruppe nicht gefunden'], 404);
        }

        // Kinder-Gruppen auf null setzen (werden zu Root-Gruppen)
        $children = $this->entityManager->getRepository(Group::class)
            ->findBy(['parentId' => $id]);
        foreach ($children as $child) {
            $child->setParent(null);
        }

        $this->entityManager->remove($group);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    // ========================================
    // MITGLIEDER VERWALTUNG
    // ========================================

    /**
     * Mitglied zu Gruppe hinzufügen
     */
    #[Route('/{groupId}/members', name: 'add_member', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addMember(string $groupId, Request $request): JsonResponse
    {
        $group = $this->entityManager->getRepository(Group::class)->find($groupId);
        if (!$group) {
            return new JsonResponse(['error' => 'Gruppe nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['user_id'])) {
            return new JsonResponse(['error' => 'user_id ist erforderlich'], 400);
        }

        $user = $this->entityManager->getRepository(User::class)->find($data['user_id']);
        if (!$user) {
            return new JsonResponse(['error' => 'User nicht gefunden'], 404);
        }

        // Prüfe ob User schon Mitglied ist
        $existing = $this->entityManager->getRepository(GroupMembership::class)
            ->findOneBy(['userId' => $data['user_id'], 'groupId' => $groupId]);
        if ($existing) {
            return new JsonResponse(['error' => 'User ist bereits Mitglied dieser Gruppe'], 409);
        }

        // Rolle validieren
        $role = $data['role'] ?? 'member';
        $validRoles = ['leader', 'member'];
        if (!in_array($role, $validRoles, true)) {
            return new JsonResponse(['error' => 'Ungültige Rolle. Erlaubt: ' . implode(', ', $validRoles)], 400);
        }

        try {
            $membership = new GroupMembership();
            $membership->setUser($user);
            $membership->setGroup($group);
            $membership->setRole($role);
            $membership->setIsPrimary($data['is_primary'] ?? false);

            $this->entityManager->persist($membership);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Hinzufügen: ' . $e->getMessage()
            ], 500);
        }

        $profile = $user->getProfile();

        return new JsonResponse([
            'user_id' => $user->getId(),
            'group_id' => $groupId,
            'name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
            'email' => $profile ? $profile->getEmail() : '',
            'role' => $membership->getRole(),
            'role_label' => $membership->getRoleLabel(),
            'is_leader' => $membership->isLeader(),
            'is_primary' => $membership->getIsPrimary(),
        ], 201);
    }

    /**
     * Mitglied-Rolle ändern
     */
    #[Route('/{groupId}/members/{userId}', name: 'update_member', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateMember(string $groupId, string $userId, Request $request): JsonResponse
    {
        $membership = $this->entityManager->getRepository(GroupMembership::class)
            ->findOneBy(['userId' => $userId, 'groupId' => $groupId]);
        
        if (!$membership) {
            return new JsonResponse(['error' => 'Mitgliedschaft nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['role'])) {
            $validRoles = ['leader', 'member'];
            if (!in_array($data['role'], $validRoles, true)) {
                return new JsonResponse(['error' => 'Ungültige Rolle'], 400);
            }
            $membership->setRole($data['role']);
        }

        if (isset($data['is_primary'])) {
            $membership->setIsPrimary((bool) $data['is_primary']);
        }

        $this->entityManager->flush();

        $user = $membership->getUser();
        $profile = $user->getProfile();

        return new JsonResponse([
            'user_id' => $user->getId(),
            'group_id' => $groupId,
            'name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
            'email' => $profile ? $profile->getEmail() : '',
            'role' => $membership->getRole(),
            'role_label' => $membership->getRoleLabel(),
            'is_leader' => $membership->isLeader(),
            'is_primary' => $membership->getIsPrimary(),
        ]);
    }

    /**
     * Mitglied aus Gruppe entfernen
     */
    #[Route('/{groupId}/members/{userId}', name: 'remove_member', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function removeMember(string $groupId, string $userId): JsonResponse
    {
        $membership = $this->entityManager->getRepository(GroupMembership::class)
            ->findOneBy(['userId' => $userId, 'groupId' => $groupId]);
        
        if (!$membership) {
            return new JsonResponse(['error' => 'Mitgliedschaft nicht gefunden'], 404);
        }

        $this->entityManager->remove($membership);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

}
