<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DepartmentPrintPreset;
use App\Entity\PrintDeviceModel;
use App\Entity\PrintMedia;
use App\Entity\User;
use App\Service\PrintCatalog\PrintCatalogService;
use App\Service\PrintCatalog\PrintCatalogVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/print', name: 'api_department_print_')]
class DepartmentPrintSettingsController extends AbstractController
{
    public function __construct(
        private readonly PrintCatalogService $catalog,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/catalog', name: 'catalog', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function catalog(string $departmentId): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (!$this->catalog->isDepartmentMember($user, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }
        $department = $this->catalog->findDepartment($departmentId);
        if ($department === null) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        return new JsonResponse([
            'families' => PrintCatalogVisibility::families(),
            'can_manage_presets' => $this->catalog->canManageDepartment($user, $departmentId),
            'can_propose' => $this->catalog->canManageDepartment($user, $departmentId),
            'models' => array_map($this->catalog->serializeModel(...), $this->catalog->visibleModels($user)),
            'media' => array_map($this->catalog->serializeMedia(...), $this->catalog->visibleMedia($user)),
            'published_models' => array_map($this->catalog->serializeModel(...), $this->catalog->publishedModelsForPresets($user)),
            'published_media' => array_map($this->catalog->serializeMedia(...), $this->catalog->publishedMediaForPresets($user)),
        ]);
    }

    #[Route('/catalog/models', name: 'propose_model', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function proposeModel(string $departmentId, Request $request): JsonResponse
    {
        return $this->propose($departmentId, $request, 'model');
    }

    #[Route('/catalog/media', name: 'propose_media', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function proposeMedia(string $departmentId, Request $request): JsonResponse
    {
        return $this->propose($departmentId, $request, 'media');
    }

    #[Route('/catalog/models/{id}/request-global', name: 'request_global_model', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function requestGlobalModel(string $departmentId, string $id): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $department = $this->catalog->findDepartment($departmentId);
        if ($department === null) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }
        $model = $this->entityManager->getRepository(PrintDeviceModel::class)->find($id);
        if (!$model instanceof PrintDeviceModel) {
            return new JsonResponse(['error' => 'Gerät nicht gefunden'], 404);
        }
        try {
            $model = $this->catalog->requestGlobalForModel($user, $department, $model);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($this->catalog->serializeModel($model));
    }

    #[Route('/catalog/media/{id}/request-global', name: 'request_global_media', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function requestGlobalMedia(string $departmentId, string $id): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $department = $this->catalog->findDepartment($departmentId);
        if ($department === null) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }
        $media = $this->entityManager->getRepository(PrintMedia::class)->find($id);
        if (!$media instanceof PrintMedia) {
            return new JsonResponse(['error' => 'Medium nicht gefunden'], 404);
        }
        try {
            $media = $this->catalog->requestGlobalForMedia($user, $department, $media);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($this->catalog->serializeMedia($media));
    }

    #[Route('/presets', name: 'presets_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listPresets(string $departmentId): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (!$this->catalog->isDepartmentMember($user, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        return new JsonResponse(array_map(
            $this->catalog->serializePreset(...),
            $this->catalog->listPresets($departmentId),
        ));
    }

    #[Route('/presets', name: 'presets_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createPreset(string $departmentId, Request $request): JsonResponse
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
            $preset = $this->catalog->createPreset($user, $department, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse($this->catalog->serializePreset($preset), 201);
    }

    #[Route('/presets/{presetId}', name: 'presets_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updatePreset(string $departmentId, string $presetId, Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (!$this->catalog->canManageDepartment($user, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }
        $preset = $this->findPreset($departmentId, $presetId);
        if ($preset instanceof JsonResponse) {
            return $preset;
        }
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiger JSON-Body'], 400);
        }
        try {
            $preset = $this->catalog->updatePreset($preset, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse($this->catalog->serializePreset($preset));
    }

    #[Route('/presets/{presetId}', name: 'presets_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deletePreset(string $departmentId, string $presetId): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (!$this->catalog->canManageDepartment($user, $departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }
        $preset = $this->findPreset($departmentId, $presetId);
        if ($preset instanceof JsonResponse) {
            return $preset;
        }
        $this->catalog->deletePreset($preset);

        return new JsonResponse(['ok' => true]);
    }

    private function propose(string $departmentId, Request $request, string $kind): JsonResponse
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
            if ($kind === 'model') {
                $item = $this->catalog->proposeOrCreateModel($user, $department, $data, false);

                return new JsonResponse($this->catalog->serializeModel($item), 201);
            }
            $item = $this->catalog->proposeOrCreateMedia($user, $department, $data, false);

            return new JsonResponse($this->catalog->serializeMedia($item), 201);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    private function findPreset(string $departmentId, string $presetId): DepartmentPrintPreset|JsonResponse
    {
        $preset = $this->entityManager->getRepository(DepartmentPrintPreset::class)->find($presetId);
        if (!$preset instanceof DepartmentPrintPreset || $preset->getDepartmentId() !== $departmentId) {
            return new JsonResponse(['error' => 'Favorit nicht gefunden'], 404);
        }

        return $preset;
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
