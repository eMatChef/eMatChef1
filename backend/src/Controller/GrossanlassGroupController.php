<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\Group;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassAccessService;
use App\Service\Grossanlass\GrossanlassGroupService;
use App\Service\GroupAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/grossanlass/groups', name: 'api_grossanlass_groups_')]
class GrossanlassGroupController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassGroupService $groupService,
        private GrossanlassAccessService $access,
        private GroupAccessService $groupAccess,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId): JsonResponse
    {
        $department = $this->resolveGrossanlassDepartment($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->groupAccess->userHasDepartmentMembership($currentUser->getId(), $departmentId)) {
            return new JsonResponse(['error' => 'Kein Zugriff auf diese Abteilung'], 403);
        }

        return new JsonResponse($this->groupService->listGroups($department));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(string $departmentId, Request $request): JsonResponse
    {
        $department = $this->resolveGrossanlassDepartment($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->groupAccess->userHasDepartmentMembership($currentUser->getId(), $departmentId)) {
            return new JsonResponse(['error' => 'Kein Zugriff auf diese Abteilung'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $group = $this->groupService->createGroup($department, $currentUser, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler beim Erstellen: ' . $e->getMessage()], 500);
        }

        return new JsonResponse($group, 201);
    }

    #[Route('/{groupId}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $departmentId, string $groupId, Request $request): JsonResponse
    {
        $department = $this->resolveGrossanlassDepartment($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }

        $group = $this->entityManager->getRepository(Group::class)->find($groupId);
        if ($group === null || $group->getDepartmentId() !== $departmentId) {
            return new JsonResponse(['error' => 'Gruppe nicht gefunden'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $result = $this->groupService->updateGroup($department, $currentUser, $group, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler beim Aktualisieren: ' . $e->getMessage()], 500);
        }

        return new JsonResponse($result);
    }

    #[Route('/{groupId}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $departmentId, string $groupId): JsonResponse
    {
        $department = $this->resolveGrossanlassDepartment($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }

        $group = $this->entityManager->getRepository(Group::class)->find($groupId);
        if ($group === null || $group->getDepartmentId() !== $departmentId) {
            return new JsonResponse(['error' => 'Gruppe nicht gefunden'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        try {
            $this->groupService->deleteGroup($department, $currentUser, $group);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler beim Löschen: ' . $e->getMessage()], 500);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{groupId}/members', name: 'add_member', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addMember(string $departmentId, string $groupId, Request $request): JsonResponse
    {
        $department = $this->resolveGrossanlassDepartment($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }

        $group = $this->entityManager->getRepository(Group::class)->find($groupId);
        if ($group === null || $group->getDepartmentId() !== $departmentId) {
            return new JsonResponse(['error' => 'Gruppe nicht gefunden'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $member = $this->groupService->addMember($department, $currentUser, $group, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'bereits Mitglied') ? 409 : 403;
            return new JsonResponse(['error' => $e->getMessage()], $status);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler beim Hinzufügen: ' . $e->getMessage()], 500);
        }

        return new JsonResponse($member, 201);
    }

    #[Route('/{groupId}/members/{userId}', name: 'update_member', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateMember(string $departmentId, string $groupId, string $userId, Request $request): JsonResponse
    {
        $department = $this->resolveGrossanlassDepartment($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }

        $group = $this->entityManager->getRepository(Group::class)->find($groupId);
        if ($group === null || $group->getDepartmentId() !== $departmentId) {
            return new JsonResponse(['error' => 'Gruppe nicht gefunden'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $member = $this->groupService->updateMember($department, $currentUser, $group, $userId, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler beim Aktualisieren: ' . $e->getMessage()], 500);
        }

        return new JsonResponse($member);
    }

    #[Route('/{groupId}/members/{userId}', name: 'remove_member', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function removeMember(string $departmentId, string $groupId, string $userId): JsonResponse
    {
        $department = $this->resolveGrossanlassDepartment($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }

        $group = $this->entityManager->getRepository(Group::class)->find($groupId);
        if ($group === null || $group->getDepartmentId() !== $departmentId) {
            return new JsonResponse(['error' => 'Gruppe nicht gefunden'], 404);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        try {
            $this->groupService->removeMember($department, $currentUser, $group, $userId);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler beim Entfernen: ' . $e->getMessage()], 500);
        }

        return new JsonResponse(['success' => true]);
    }

    private function resolveGrossanlassDepartment(string $departmentId): Department|JsonResponse
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if ($department === null) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }
        if (!$department->isGrossanlass()) {
            return new JsonResponse(['error' => 'Kein Grossanlass-Department'], 400);
        }

        return $department;
    }
}
