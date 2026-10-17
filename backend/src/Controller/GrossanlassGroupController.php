<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\Group;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassAccessService;
use App\Service\Grossanlass\GrossanlassBauprojektService;
use App\Service\Grossanlass\HelperNeedValidationException;
use App\Service\Grossanlass\GrossanlassGroupService;
use App\Service\Grossanlass\GrossanlassHelperService;
use App\Service\Grossanlass\GrossanlassOrderArchiveService;
use App\Service\Grossanlass\GrossanlassOrderCardService;
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
        private GrossanlassBauprojektService $bauprojekt,
        private GrossanlassHelperService $helperService,
        private GrossanlassOrderCardService $orderCards,
        private GrossanlassOrderArchiveService $orderArchive,
        private GrossanlassAccessService $access,
        private GroupAccessService $groupAccess,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId, Request $request): JsonResponse
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

        return new JsonResponse($this->groupService->listGroups($department, $request->query->getBoolean('include_archived')));
    }

    #[Route('/order-cards', name: 'order_cards', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function orderCards(string $departmentId, Request $request): JsonResponse
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

        return new JsonResponse($this->orderCards->cards($department, $currentUser, $request->query->getBoolean('include_archived')));
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

    #[Route('/{groupId}/archive', name: 'archive_state', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function archiveState(string $departmentId, string $groupId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->groupService->archiveState($d, $g),
        );
    }

    #[Route('/{groupId}/archive', name: 'archive', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function archive(string $departmentId, string $groupId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $reason = is_array($data) ? (string) ($data['reason'] ?? '') : '';

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->groupService->archiveGroup($d, $u, $g, $reason),
        );
    }

    #[Route('/{groupId}/restore', name: 'restore', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function restore(string $departmentId, string $groupId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $note = is_array($data) ? (string) ($data['note'] ?? '') : '';

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->groupService->restoreGroup($d, $u, $g, $note),
        );
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

    #[Route('/{groupId}/shares', name: 'share_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function share(string $departmentId, string $groupId, Request $request): JsonResponse
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
            $share = $this->groupService->shareGroup($department, $currentUser, $group, is_array($data) ? $data : []);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($share, 201);
    }

    #[Route('/{groupId}/shares/{shareId}', name: 'share_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function unshare(string $departmentId, string $groupId, string $shareId): JsonResponse
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
            $this->groupService->unshareGroup($department, $currentUser, $group, $shareId);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
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

    #[Route('/{groupId}/helpers', name: 'add_helper', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addHelper(string $departmentId, string $groupId, Request $request): JsonResponse
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
            $this->orderArchive->assertNotArchived($group); // archivierte Bauaufträge sind nicht mehr bearbeitbar
            $result = $this->helperService->inviteOrCreate($department, $currentUser, $group, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler beim Anlegen: ' . $e->getMessage()], 500);
        }

        return new JsonResponse($result, 201);
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

    #[Route('/{groupId}/bauprojekt', name: 'bauprojekt_get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function bauprojekt(string $departmentId, string $groupId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->briefingForGroup($d, $u, $g),
        );
    }

    #[Route('/{groupId}/bauprojekt', name: 'bauprojekt_patch', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function patchBauprojekt(string $departmentId, string $groupId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->updateWindow($d, $u, $g, is_array($data) ? $data : []),
        );
    }

    #[Route('/{groupId}/helper-need', name: 'helper_need_get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function helperNeed(string $departmentId, string $groupId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->getHelperNeed($d, $u, $g),
        );
    }

    #[Route('/{groupId}/helper-need', name: 'helper_need_put', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function saveHelperNeed(string $departmentId, string $groupId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->saveHelperNeed($d, $u, $g, is_array($data) ? $data : []),
        );
    }

    #[Route('/{groupId}/places', name: 'places_add', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addPlace(string $departmentId, string $groupId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->groupService->addGroupPlace($d, $u, $g, is_array($data) ? $data : []),
            201,
        );
    }

    #[Route('/{groupId}/places/{placeId}', name: 'places_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updatePlace(string $departmentId, string $groupId, string $placeId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->groupService->updateGroupPlace($d, $u, $g, $placeId, is_array($data) ? $data : []),
        );
    }

    #[Route('/{groupId}/places/{placeId}', name: 'places_remove', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function removePlace(string $departmentId, string $groupId, string $placeId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->groupService->removeGroupPlace($d, $u, $g, $placeId),
        );
    }

    #[Route('/tasks', name: 'tasks_all', methods: ['GET'], priority: 10)]
    #[IsGranted('ROLE_USER')]
    public function listAllTasks(string $departmentId, Request $request): JsonResponse
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
        try {
            return new JsonResponse($this->bauprojekt->listAllTasks($department, $currentUser, $request->query->getBoolean('include_archived')));
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[Route('/{groupId}/tasks', name: 'tasks_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listTasks(string $departmentId, string $groupId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->listTasks($d, $u, $g),
        );
    }

    #[Route('/{groupId}/tasks', name: 'tasks_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createTask(string $departmentId, string $groupId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->createTask($d, $u, $g, is_array($data) ? $data : []),
            201,
        );
    }

    #[Route('/{groupId}/tasks/{taskId}', name: 'tasks_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateTask(string $departmentId, string $groupId, string $taskId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->updateTask($d, $u, $g, $taskId, is_array($data) ? $data : []),
        );
    }

    #[Route('/{groupId}/tasks/{taskId}/steps', name: 'tasks_steps_add', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addTaskStep(string $departmentId, string $groupId, string $taskId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $label = is_array($data) ? (string) ($data['label'] ?? '') : '';

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->addTaskStep($d, $u, $g, $taskId, $label),
            201,
        );
    }

    #[Route('/{groupId}/tasks/{taskId}/steps/order', name: 'tasks_steps_order', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function reorderTaskSteps(string $departmentId, string $groupId, string $taskId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $ids = is_array($data) && is_array($data['ids'] ?? null) ? array_map('strval', $data['ids']) : [];

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->reorderTaskSteps($d, $u, $g, $taskId, $ids),
        );
    }

    #[Route('/{groupId}/tasks/{taskId}/steps/{stepId}', name: 'tasks_steps_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateTaskStep(string $departmentId, string $groupId, string $taskId, string $stepId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->updateTaskStep($d, $u, $g, $taskId, $stepId, is_array($data) ? $data : []),
        );
    }

    #[Route('/{groupId}/tasks/{taskId}/steps/{stepId}', name: 'tasks_steps_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteTaskStep(string $departmentId, string $groupId, string $taskId, string $stepId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->deleteTaskStep($d, $u, $g, $taskId, $stepId),
        );
    }

    #[Route('/{groupId}/tasks/{taskId}/report', name: 'tasks_report', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reportTaskDone(string $departmentId, string $groupId, string $taskId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $note = is_array($data) ? (string) ($data['note'] ?? '') : '';

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->reportTaskDone($d, $u, $g, $taskId, $note),
        );
    }

    #[Route('/{groupId}/tasks/{taskId}/accept', name: 'tasks_accept', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function acceptTask(string $departmentId, string $groupId, string $taskId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->acceptTask($d, $u, $g, $taskId),
        );
    }

    #[Route('/{groupId}/tasks/{taskId}/return', name: 'tasks_return', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function returnTask(string $departmentId, string $groupId, string $taskId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $reason = is_array($data) ? (string) ($data['reason'] ?? '') : '';

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->returnTask($d, $u, $g, $taskId, $reason),
        );
    }

    #[Route('/{groupId}/tasks/{taskId}/dependencies', name: 'tasks_dependencies_add', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addTaskDependency(string $departmentId, string $groupId, string $taskId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $predecessor = is_array($data) ? (string) ($data['predecessor_task_id'] ?? '') : '';
        $mode = is_array($data) ? (string) ($data['mode'] ?? 'sequential') : 'sequential';

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->addTaskDependency($d, $u, $g, $taskId, $predecessor, $mode),
            201,
        );
    }

    #[Route('/{groupId}/tasks/{taskId}/dependencies', name: 'tasks_dependencies_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listTaskDependencies(string $departmentId, string $groupId, string $taskId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->taskDependencies($d, $u, $g, $taskId),
        );
    }

    #[Route('/{groupId}/tasks/{taskId}/dependencies/{dependencyId}', name: 'tasks_dependencies_mode', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function changeTaskDependencyMode(string $departmentId, string $groupId, string $taskId, string $dependencyId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $mode = is_array($data) ? (string) ($data['mode'] ?? '') : '';

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->changeTaskDependencyMode($d, $u, $g, $taskId, $dependencyId, $mode),
        );
    }

    #[Route('/{groupId}/tasks/{taskId}/dependencies/{dependencyId}', name: 'tasks_dependencies_remove', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function removeTaskDependency(string $departmentId, string $groupId, string $taskId, string $dependencyId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->removeTaskDependency($d, $u, $g, $taskId, $dependencyId),
        );
    }

    #[Route('/{groupId}/tasks/{taskId}/dependencies/{dependencyId}/release', name: 'tasks_dependencies_release', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function releaseTaskDependency(string $departmentId, string $groupId, string $taskId, string $dependencyId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $reason = is_array($data) ? (string) ($data['reason'] ?? '') : '';

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->releaseTaskDependency($d, $u, $g, $taskId, $dependencyId, $reason),
        );
    }

    #[Route('/{groupId}/acceptance', name: 'group_acceptance_get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getAcceptance(string $departmentId, string $groupId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->orderAcceptance($d, $u, $g),
        );
    }

    #[Route('/{groupId}/acceptance/report', name: 'group_acceptance_report', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reportAcceptance(string $departmentId, string $groupId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->reportOrder($d, $u, $g),
        );
    }

    #[Route('/{groupId}/acceptance/withdraw', name: 'group_acceptance_withdraw', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function withdrawAcceptance(string $departmentId, string $groupId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->withdrawOrder($d, $u, $g),
        );
    }

    #[Route('/{groupId}/acceptance/accept', name: 'group_acceptance_accept', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function acceptAcceptance(string $departmentId, string $groupId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->acceptOrder($d, $u, $g),
        );
    }

    #[Route('/{groupId}/acceptance/return', name: 'group_acceptance_return', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function returnAcceptance(string $departmentId, string $groupId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $reason = is_array($data) ? (string) ($data['reason'] ?? '') : '';

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->returnOrder($d, $u, $g, $reason),
        );
    }

    #[Route('/{groupId}/acceptance/reopen', name: 'group_acceptance_reopen', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reopenAcceptance(string $departmentId, string $groupId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $reason = is_array($data) ? (string) ($data['reason'] ?? '') : '';

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->reopenOrder($d, $u, $g, $reason),
        );
    }

    #[Route('/{groupId}/dependencies', name: 'group_dependencies_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listGroupDependencies(string $departmentId, string $groupId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->groupDependencies($d, $u, $g),
        );
    }

    #[Route('/{groupId}/dependencies', name: 'group_dependencies_add', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addGroupDependency(string $departmentId, string $groupId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $predecessor = is_array($data) ? (string) ($data['predecessor_group_id'] ?? '') : '';

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->addGroupDependency($d, $u, $g, $predecessor),
            201,
        );
    }

    #[Route('/{groupId}/dependencies/{dependencyId}', name: 'group_dependencies_remove', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function removeGroupDependency(string $departmentId, string $groupId, string $dependencyId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->removeGroupDependency($d, $u, $g, $dependencyId),
        );
    }

    #[Route('/{groupId}/dependencies/{dependencyId}/release', name: 'group_dependencies_release', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function releaseGroupDependency(string $departmentId, string $groupId, string $dependencyId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $reason = is_array($data) ? (string) ($data['reason'] ?? '') : '';

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->releaseGroupDependency($d, $u, $g, $dependencyId, $reason),
        );
    }

    #[Route('/{groupId}/tasks/{taskId}/block', name: 'tasks_block', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function blockTask(string $departmentId, string $groupId, string $taskId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $reason = is_array($data) ? (string) ($data['reason'] ?? '') : '';

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->blockTask($d, $u, $g, $taskId, $reason),
        );
    }

    #[Route('/{groupId}/tasks/{taskId}/block', name: 'tasks_unblock', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function unblockTask(string $departmentId, string $groupId, string $taskId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->unblockTask($d, $u, $g, $taskId),
        );
    }

    #[Route('/{groupId}/tasks/{taskId}', name: 'tasks_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteTask(string $departmentId, string $groupId, string $taskId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            function (Department $d, User $u, Group $g) use ($taskId) {
                $this->bauprojekt->deleteTask($d, $u, $g, $taskId);

                return ['success' => true];
            },
        );
    }

    #[Route('/{groupId}/vehicles', name: 'vehicles_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createVehicleNeed(string $departmentId, string $groupId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->createVehicleNeed($d, $u, $g, is_array($data) ? $data : []),
            201,
        );
    }

    #[Route('/{groupId}/vehicles/{needId}', name: 'vehicles_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateVehicleNeed(string $departmentId, string $groupId, string $needId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->updateVehicleNeed($d, $u, $g, $needId, is_array($data) ? $data : []),
        );
    }

    #[Route('/{groupId}/vehicles/{needId}', name: 'vehicles_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteVehicleNeed(string $departmentId, string $groupId, string $needId): JsonResponse
    {
        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            function (Department $d, User $u, Group $g) use ($needId) {
                $this->bauprojekt->deleteVehicleNeed($d, $u, $g, $needId);

                return ['success' => true];
            },
        );
    }

    #[Route('/{groupId}/material/{lineId}', name: 'material_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateMaterial(string $departmentId, string $groupId, string $lineId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->updateMaterial($d, $u, $g, $lineId, is_array($data) ? $data : []),
        );
    }

    #[Route('/{groupId}/material', name: 'material_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addMaterial(string $departmentId, string $groupId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->handleBauprojekt(
            $departmentId,
            $groupId,
            fn (Department $d, User $u, Group $g) => $this->bauprojekt->addMaterial($d, $u, $g, is_array($data) ? $data : []),
            201,
        );
    }

    /**
     * @param callable(Department, User, Group): mixed $fn
     */
    private function handleBauprojekt(string $departmentId, string $groupId, callable $fn, int $okStatus = 200): JsonResponse
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
        if (!$this->groupAccess->userHasDepartmentMembership($currentUser->getId(), $departmentId)) {
            return new JsonResponse(['error' => 'Kein Zugriff auf diese Abteilung'], 403);
        }
        try {
            return new JsonResponse($fn($department, $currentUser, $group), $okStatus);
        } catch (HelperNeedValidationException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
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
