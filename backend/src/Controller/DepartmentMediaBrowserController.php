<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Media\DepartmentMediaBrowserService;
use App\Service\Media\DepartmentMediaReplaceService;
use App\Service\Media\MediaFileAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/media', name: 'api_department_media_')]
class DepartmentMediaBrowserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DepartmentMediaBrowserService $browser,
        private DepartmentMediaReplaceService $replaceService,
        private MediaFileAccessService $mediaFileAccess,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $dept = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$dept) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        try {
            $this->mediaFileAccess->assertCanBrowseDepartmentMedia($user, $departmentId);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $kind = (string) $request->query->get('kind', '');
        $context = (string) $request->query->get('context', '');
        $query = (string) $request->query->get('q', '');

        return new JsonResponse($this->browser->list($departmentId, $kind, $context, $query));
    }

    #[Route('/replace', name: 'replace', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function replace(string $departmentId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $dept = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$dept) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        try {
            $this->mediaFileAccess->assertCanBrowseDepartmentMedia($user, $departmentId);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $file = $request->files->get('file') ?? $request->files->get('photo');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return new JsonResponse(['error' => 'file ist erforderlich'], 400);
        }

        $context = trim((string) $request->request->get('context', ''));
        $contextId = trim((string) $request->request->get('context_id', ''));
        $filename = trim((string) $request->request->get('filename', ''));
        if ($context === '' || $contextId === '' || $filename === '') {
            return new JsonResponse(['error' => 'context, context_id und filename sind erforderlich'], 400);
        }

        try {
            $item = $this->replaceService->replace($user, $departmentId, $context, $contextId, $filename, $file);

            return new JsonResponse(['item' => $item, 'message' => 'Datei ersetzt']);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/rename', name: 'rename', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function rename(string $departmentId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $dept = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$dept) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        try {
            $this->mediaFileAccess->assertCanBrowseDepartmentMedia($user, $departmentId);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Ungültige Anfrage'], 400);
        }

        $context = trim((string) ($data['context'] ?? ''));
        $contextId = trim((string) ($data['context_id'] ?? ''));
        $filename = trim((string) ($data['filename'] ?? ''));
        $originalFilename = trim((string) ($data['original_filename'] ?? ''));
        if ($context === '' || $contextId === '' || $filename === '' || $originalFilename === '') {
            return new JsonResponse(['error' => 'context, context_id, filename und original_filename sind erforderlich'], 400);
        }

        try {
            $item = $this->replaceService->rename($departmentId, $context, $contextId, $filename, $originalFilename);

            return new JsonResponse(['item' => $item, 'message' => 'Name gespeichert']);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }
}
