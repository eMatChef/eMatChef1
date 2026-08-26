<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PrintLayout;
use App\Entity\User;
use App\Service\PrintCatalog\PrintCatalogService;
use App\Service\PrintCatalog\PrintLayoutService;
use App\Service\PrintCatalog\PrintLayoutStorageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/print/layouts', name: 'api_department_print_layouts_')]
class DepartmentPrintLayoutController extends AbstractController
{
    public function __construct(
        private readonly PrintLayoutService $layouts,
        private readonly PrintCatalogService $catalog,
        private readonly PrintLayoutStorageService $storage,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (!$this->catalog->isDepartmentMember($user, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        return new JsonResponse(array_map(
            fn (PrintLayout $layout) => $this->layouts->serialize($layout),
            $this->layouts->visibleLayouts($user),
        ));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(string $departmentId, Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (!$this->catalog->canManageDepartment($user, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }
        $department = $this->catalog->findDepartment($departmentId);
        if ($department === null) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiger JSON-Body'], 400);
        }
        try {
            $layout = $this->layouts->create($user, $department, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse($this->layouts->serialize($layout), 201);
    }

    #[Route('/{layoutId}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $departmentId, string $layoutId, Request $request): JsonResponse
    {
        $ctx = $this->loadManaged($departmentId, $layoutId);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$user, $department, $layout] = $ctx;
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiger JSON-Body'], 400);
        }
        try {
            $this->layouts->assertCanManage($user, $department, $layout);
            $layout = $this->layouts->update($layout, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($this->layouts->serialize($layout));
    }

    #[Route('/{layoutId}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $departmentId, string $layoutId): JsonResponse
    {
        $ctx = $this->loadManaged($departmentId, $layoutId);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$user, $department, $layout] = $ctx;
        try {
            $this->layouts->assertCanManage($user, $department, $layout);
            $this->layouts->delete($layout);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/{layoutId}/template', name: 'template_upload', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function uploadTemplate(string $departmentId, string $layoutId, Request $request): JsonResponse
    {
        $ctx = $this->loadManaged($departmentId, $layoutId);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$user, $department, $layout] = $ctx;
        $file = $request->files->get('file');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return new JsonResponse(['error' => 'PDF-Datei fehlt (Feld file)'], 400);
        }
        try {
            $this->layouts->assertCanManage($user, $department, $layout);
            $layout = $this->layouts->attachTemplate($layout, $file);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $payload = $this->layouts->serialize($layout);
        $sha = $layout->getTemplateSha256();
        $payload['duplicate_templates'] = $sha
            ? array_map(
                fn (PrintLayout $item) => $this->layouts->serializeDuplicate($item),
                $this->layouts->layoutsWithTemplateSha($sha, $layout->getId()),
            )
            : [];

        return new JsonResponse($payload);
    }

    #[Route('/{layoutId}/template', name: 'template_get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getTemplate(string $departmentId, string $layoutId): BinaryFileResponse|JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (!$this->catalog->isDepartmentMember($user, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }
        $layout = $this->layouts->get($layoutId);
        if (
            $layout === null
            || !$this->layouts->canSee($user, $layout)
            || ($layout->getTemplateFilename() === null && $layout->getTemplateSha256() === null)
        ) {
            return new JsonResponse(['error' => 'Vorlage nicht gefunden'], 404);
        }
        try {
            $path = $this->storage->resolvePath(
                $layout->getId(),
                $layout->getTemplateSha256(),
                $layout->getTemplateFilename(),
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'template.pdf');
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setPrivate();

        return $response;
    }

    #[Route('/{layoutId}/copy', name: 'copy', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function copy(string $departmentId, string $layoutId): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (!$this->catalog->canManageDepartment($user, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }
        $department = $this->catalog->findDepartment($departmentId);
        if ($department === null) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }
        $source = $this->layouts->get($layoutId);
        if ($source === null || !$this->layouts->canSee($user, $source)) {
            return new JsonResponse(['error' => 'Layout nicht gefunden'], 404);
        }
        try {
            $copy = $this->layouts->copyToDepartment($user, $department, $source);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($this->layouts->serialize($copy), 201);
    }

    #[Route('/{layoutId}/request-global', name: 'request_global', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function requestGlobal(string $departmentId, string $layoutId): JsonResponse
    {
        $ctx = $this->loadManaged($departmentId, $layoutId);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$user, $department, $layout] = $ctx;
        try {
            $layout = $this->layouts->requestGlobal($user, $department, $layout);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($this->layouts->serialize($layout));
    }

    /**
     * @return array{0: User, 1: \App\Entity\Department, 2: PrintLayout}|JsonResponse
     */
    private function loadManaged(string $departmentId, string $layoutId): array|JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (!$this->catalog->canManageDepartment($user, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }
        $department = $this->catalog->findDepartment($departmentId);
        if ($department === null) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }
        $layout = $this->layouts->get($layoutId);
        if ($layout === null || !$this->layouts->canSee($user, $layout)) {
            return new JsonResponse(['error' => 'Layout nicht gefunden'], 404);
        }

        return [$user, $department, $layout];
    }

    private function requireUser(): User|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        return $user;
    }
}
