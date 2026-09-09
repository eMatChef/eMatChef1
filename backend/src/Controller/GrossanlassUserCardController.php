<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassUserCardService;
use App\Service\GroupAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/grossanlass/user-cards', name: 'api_grossanlass_user_cards_')]
class GrossanlassUserCardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassUserCardService $cards,
        private GroupAccessService $groupAccess,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId): JsonResponse
    {
        $department = $this->resolve($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        if (!$this->groupAccess->userHasDepartmentMembership($user->getId(), $departmentId)) {
            return new JsonResponse(['error' => 'Kein Zugriff auf diese Abteilung'], 403);
        }

        return new JsonResponse($this->cards->listCards($department));
    }

    #[Route('/print-missing', name: 'print_missing', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function printMissing(string $departmentId): JsonResponse
    {
        $department = $this->resolve($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        try {
            return new JsonResponse($this->cards->printMissing($department, $user));
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[Route('/{userId}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $departmentId, string $userId, Request $request): JsonResponse
    {
        $department = $this->resolve($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            return new JsonResponse($this->cards->updateCard($department, $user, $userId, $data));
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[Route('/{userId}/drive-proof', name: 'upload_drive_proof', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function uploadDriveProof(string $departmentId, string $userId, Request $request): JsonResponse
    {
        $department = $this->resolve($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        $file = $request->files->get('proof') ?? $request->files->get('file');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return new JsonResponse(['error' => 'Datei ist erforderlich'], 400);
        }

        try {
            return new JsonResponse($this->cards->uploadDriveProof($department, $user, $userId, $file));
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[Route('/{userId}/drive-proof', name: 'delete_drive_proof', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteDriveProof(string $departmentId, string $userId): JsonResponse
    {
        $department = $this->resolve($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        try {
            return new JsonResponse($this->cards->deleteDriveProof($department, $user, $userId));
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    private function resolve(string $departmentId): Department|JsonResponse
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
