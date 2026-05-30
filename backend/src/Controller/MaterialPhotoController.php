<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Material\MaterialItemPhotoService;
use App\Service\Material\MaterialPhotoAccessService;
use App\Service\Material\MaterialPhotoStorageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/materials', name: 'api_material_photos_')]
class MaterialPhotoController extends AbstractController
{
    public function __construct(
        private MaterialPhotoAccessService $photoAccess,
        private MaterialPhotoStorageService $photoStorage,
        private MaterialItemPhotoService $itemPhotoService,
    ) {
    }

    #[Route('/{materialId}/photos', name: 'upload', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function upload(string $materialId, Request $request): JsonResponse
    {
        $file = $request->files->get('photo');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return new JsonResponse(['error' => 'photo ist erforderlich'], 400);
        }

        try {
            $material = $this->photoAccess->requireMaterialById($materialId);
            $result = $this->itemPhotoService->replacePrimaryPhoto(
                $material,
                $this->requireUser(),
                $file,
            );

            return new JsonResponse([...$result, 'message' => 'Foto hochgeladen']);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{materialId}/photos/from-url', name: 'upload_from_url', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function uploadFromUrl(string $materialId, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $url = \is_array($payload) && \is_string($payload['url'] ?? null) ? trim($payload['url']) : '';

        if ($url === '') {
            return new JsonResponse(['error' => 'url ist erforderlich'], 400);
        }

        try {
            $material = $this->photoAccess->requireMaterialById($materialId);
            $result = $this->itemPhotoService->replacePrimaryPhotoFromUrl(
                $material,
                $this->requireUser(),
                $url,
            );

            return new JsonResponse([...$result, 'message' => 'Foto importiert']);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => $e instanceof \Exception || $e instanceof \Error
                    ? $e->getMessage()
                    : 'Bild konnte nicht importiert werden',
            ], 400);
        }
    }

    #[Route('/{materialId}/photos/{filename}', name: 'show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(string $materialId, string $filename): Response
    {
        try {
            $user = $this->requireUser();
            $material = $this->photoAccess->requireMaterialById($materialId);
            $this->photoAccess->assertCanViewPhoto($user, $material);

            $path = $this->photoStorage->resolveFilePath(
                $material->getDepartmentId(),
                $materialId,
                $filename,
            );
            $response = new BinaryFileResponse($path);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);

            return $response;
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
