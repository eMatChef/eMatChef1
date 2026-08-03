<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassWishService;
use App\Service\GroupAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/grossanlass', name: 'api_grossanlass_wishes_')]
class GrossanlassWishController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassWishService $wishService,
        private GroupAccessService $groupAccess,
    ) {}

    #[Route('/planung/rounds/{roundId}/wishes', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId, string $roundId, Request $request): JsonResponse
    {
        $department = $this->resolveGrossanlassDepartment($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }

        $currentUser = $this->requireMember($departmentId);
        if ($currentUser instanceof JsonResponse) {
            return $currentUser;
        }

        $groupId = $request->query->get('group_id');
        $paginated = $request->query->has('page') || $request->query->has('limit')
            || $request->query->has('status') || $request->query->has('q');

        try {
            if ($paginated) {
                return new JsonResponse($this->wishService->listWishesPaginated(
                    $department,
                    $currentUser,
                    $roundId,
                    [
                        'group_id' => is_string($groupId) ? $groupId : null,
                        'status' => $request->query->get('status'),
                        'q' => $request->query->get('q'),
                        'page' => $request->query->get('page'),
                        'limit' => $request->query->get('limit'),
                    ],
                ));
            }

            return new JsonResponse($this->wishService->listWishes(
                $department,
                $currentUser,
                $roundId,
                is_string($groupId) ? $groupId : null,
            ));
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/planung/rounds/{roundId}/wishes', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(string $departmentId, string $roundId, Request $request): JsonResponse
    {
        $department = $this->resolveGrossanlassDepartment($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }

        $currentUser = $this->requireMember($departmentId);
        if ($currentUser instanceof JsonResponse) {
            return $currentUser;
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $wish = $this->wishService->createWish($department, $currentUser, $roundId, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler beim Erstellen: ' . $e->getMessage()], 500);
        }

        return new JsonResponse($wish, 201);
    }

    #[Route('/planung/rounds/{roundId}/wishes/{wishId}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $departmentId, string $roundId, string $wishId, Request $request): JsonResponse
    {
        $department = $this->resolveGrossanlassDepartment($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }

        $currentUser = $this->requireMember($departmentId);
        if ($currentUser instanceof JsonResponse) {
            return $currentUser;
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $wish = $this->wishService->updateWish($department, $currentUser, $roundId, $wishId, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler beim Aktualisieren: ' . $e->getMessage()], 500);
        }

        return new JsonResponse($wish);
    }

    #[Route('/planung/rounds/{roundId}/wishes/{wishId}/accept', name: 'accept', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function accept(string $departmentId, string $roundId, string $wishId): JsonResponse
    {
        $department = $this->resolveGrossanlassDepartment($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }

        $currentUser = $this->requireMember($departmentId);
        if ($currentUser instanceof JsonResponse) {
            return $currentUser;
        }

        try {
            $wish = $this->wishService->acceptWish($department, $currentUser, $roundId, $wishId);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler beim Annehmen: ' . $e->getMessage()], 500);
        }

        return new JsonResponse($wish);
    }

    #[Route('/planung/rounds/{roundId}/wishes/{wishId}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $departmentId, string $roundId, string $wishId): JsonResponse
    {
        $department = $this->resolveGrossanlassDepartment($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }

        $currentUser = $this->requireMember($departmentId);
        if ($currentUser instanceof JsonResponse) {
            return $currentUser;
        }

        try {
            $this->wishService->deleteWish($department, $currentUser, $roundId, $wishId);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler beim Löschen: ' . $e->getMessage()], 500);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/mein-ressort/wishes', name: 'mine', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listMine(string $departmentId): JsonResponse
    {
        $department = $this->resolveGrossanlassDepartment($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }

        $currentUser = $this->requireMember($departmentId);
        if ($currentUser instanceof JsonResponse) {
            return $currentUser;
        }

        try {
            return new JsonResponse($this->wishService->listWishesForUserRessort($department, $currentUser));
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler beim Laden: ' . $e->getMessage()], 500);
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

    private function requireMember(string $departmentId): User|JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->groupAccess->userHasDepartmentMembership($currentUser->getId(), $departmentId)) {
            return new JsonResponse(['error' => 'Kein Zugriff auf diese Abteilung'], 403);
        }

        return $currentUser;
    }
}
