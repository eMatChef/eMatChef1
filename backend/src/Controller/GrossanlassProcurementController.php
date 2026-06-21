<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassProcurementService;
use App\Service\GroupAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/grossanlass/beschaffung', name: 'api_grossanlass_procurement_')]
class GrossanlassProcurementController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassProcurementService $procurementService,
        private GroupAccessService $groupAccess,
    ) {}

    #[Route('/bedarf', name: 'bedarf_overview', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function bedarfOverview(string $departmentId): JsonResponse
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
            return new JsonResponse($this->procurementService->getBedarfOverview($department, $currentUser));
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[Route('/lines', name: 'lines_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createLine(string $departmentId, Request $request): JsonResponse
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
        $wishLineIds = is_array($data['wish_line_ids'] ?? null) ? $data['wish_line_ids'] : [];

        try {
            $line = $this->procurementService->createLineFromWishes($department, $currentUser, $wishLineIds, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($line, 201);
    }

    #[Route('/lines/{lineId}', name: 'lines_update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updateLine(string $departmentId, string $lineId, Request $request): JsonResponse
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
            $line = $this->procurementService->updateLine($department, $currentUser, $lineId, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($line);
    }

    #[Route('/lines/{lineId}', name: 'lines_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteLine(string $departmentId, string $lineId): JsonResponse
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
            $this->procurementService->deleteLine($department, $currentUser, $lineId);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/lines/{lineId}/wishes', name: 'lines_add_wishes', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addWishesToLine(string $departmentId, string $lineId, Request $request): JsonResponse
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
        $wishLineIds = is_array($data['wish_line_ids'] ?? null) ? $data['wish_line_ids'] : [];

        try {
            $line = $this->procurementService->addWishesToLine($department, $currentUser, $lineId, $wishLineIds, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($line);
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
