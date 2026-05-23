<?php

namespace App\Controller;

use App\Entity\DepartmentDisplayScreen;
use App\Entity\User;
use App\Service\Display\DepartmentDisplayScreenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/display-screens', name: 'api_department_display_screens_')]
class DepartmentDisplayScreenController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DepartmentDisplayScreenService $displayScreenService,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId): JsonResponse
    {
        $user = $this->requireManager($departmentId);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $screens = $this->displayScreenService->listForDepartment($departmentId, true);
        $rows = array_map(
            fn (DepartmentDisplayScreen $s) => $this->displayScreenService->serializeForSettings($s),
            $screens,
        );

        return new JsonResponse($rows);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(string $departmentId, Request $request): JsonResponse
    {
        $user = $this->requireManager($departmentId);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $name = trim((string) ($data['name'] ?? ''));

        try {
            $result = $this->displayScreenService->create($departmentId, $name, $user);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        $payload = $this->displayScreenService->serializeForSettings($result['screen']);
        $payload['access_code'] = $result['access_code'];

        return new JsonResponse($payload, 201);
    }

    #[Route('/{screenId}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $departmentId, string $screenId, Request $request): JsonResponse
    {
        $user = $this->requireManager($departmentId);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $screen = $this->findScreen($departmentId, $screenId);
        if ($screen instanceof JsonResponse) {
            return $screen;
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $screen = $this->displayScreenService->updateSettings($screen, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse($this->displayScreenService->serializeForSettings($screen));
    }

    #[Route('/{screenId}/rotate-code', name: 'rotate_code', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function rotateCode(string $departmentId, string $screenId): JsonResponse
    {
        $user = $this->requireManager($departmentId);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $screen = $this->findScreen($departmentId, $screenId);
        if ($screen instanceof JsonResponse) {
            return $screen;
        }

        try {
            $result = $this->displayScreenService->rotateAccessCode($screen);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        $payload = $this->displayScreenService->serializeForSettings($result['screen']);
        $payload['access_code'] = $result['access_code'];

        return new JsonResponse($payload);
    }

    #[Route('/{screenId}/revoke', name: 'revoke', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function revoke(string $departmentId, string $screenId): JsonResponse
    {
        $user = $this->requireManager($departmentId);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $screen = $this->findScreen($departmentId, $screenId);
        if ($screen instanceof JsonResponse) {
            return $screen;
        }

        $this->displayScreenService->revoke($screen);

        return new JsonResponse($this->displayScreenService->serializeForSettings($screen));
    }

    private function requireManager(string $departmentId): User|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        if (!$this->displayScreenService->canManageDepartment($user, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        return $user;
    }

    private function findScreen(string $departmentId, string $screenId): DepartmentDisplayScreen|JsonResponse
    {
        $screen = $this->entityManager->getRepository(DepartmentDisplayScreen::class)->find($screenId);
        if (!$screen || $screen->getDepartmentId() !== $departmentId) {
            return new JsonResponse(['error' => 'Screen nicht gefunden'], 404);
        }

        return $screen;
    }
}
