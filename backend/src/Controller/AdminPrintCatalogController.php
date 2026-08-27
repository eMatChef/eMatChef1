<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PrintDeviceModel;
use App\Entity\PrintLayout;
use App\Entity\PrintMedia;
use App\Entity\User;
use App\Service\PrintCatalog\PrintCatalogService;
use App\Service\PrintCatalog\PrintCatalogVisibility;
use App\Service\PrintCatalog\PrintLayoutService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/print-catalog', name: 'api_admin_print_catalog_')]
class AdminPrintCatalogController extends AbstractController
{
    public function __construct(
        private readonly PrintCatalogService $catalog,
        private readonly PrintLayoutService $layouts,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(): JsonResponse
    {
        $user = $this->requireReviewer();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return new JsonResponse([
            'is_superadmin' => PrintCatalogVisibility::isSuperAdmin($user->getRoles()),
            'can_review' => true,
            'families' => PrintCatalogVisibility::families(),
            'models' => array_map($this->catalog->serializeModel(...), $this->catalog->visibleModels($user)),
            'media' => array_map($this->catalog->serializeMedia(...), $this->catalog->visibleMedia($user)),
            'layouts' => array_map(
                fn (PrintLayout $layout) => $this->layouts->serialize($layout),
                $this->layouts->visibleLayouts($user),
            ),
        ]);
    }

    #[Route('/models', name: 'create_model', methods: ['POST'])]
    #[IsGranted('ROLE_SUPERADMIN')]
    public function createModel(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiger JSON-Body'], 400);
        }
        try {
            $model = $this->catalog->proposeOrCreateModel($user, null, $data, true);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse($this->catalog->serializeModel($model), 201);
    }

    #[Route('/models/{id}', name: 'update_model', methods: ['PATCH'])]
    #[IsGranted('ROLE_SUPERADMIN')]
    public function updateModel(string $id, Request $request): JsonResponse
    {
        $model = $this->entityManager->getRepository(PrintDeviceModel::class)->find($id);
        if (!$model instanceof PrintDeviceModel) {
            return new JsonResponse(['error' => 'Gerät nicht gefunden'], 404);
        }
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiger JSON-Body'], 400);
        }
        try {
            $model = $this->catalog->updateModel($model, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse($this->catalog->serializeModel($model));
    }

    #[Route('/models/{id}/review', name: 'review_model', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reviewModel(string $id, Request $request): JsonResponse
    {
        $user = $this->requireReviewer();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $model = $this->entityManager->getRepository(PrintDeviceModel::class)->find($id);
        if (!$model instanceof PrintDeviceModel) {
            return new JsonResponse(['error' => 'Gerät nicht gefunden'], 404);
        }
        $data = json_decode($request->getContent(), true);
        $action = \is_array($data) ? (string) ($data['action'] ?? '') : '';
        try {
            $model = $this->catalog->reviewModel($user, $model, $action);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($this->catalog->serializeModel($model));
    }

    #[Route('/media', name: 'create_media', methods: ['POST'])]
    #[IsGranted('ROLE_SUPERADMIN')]
    public function createMedia(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiger JSON-Body'], 400);
        }
        try {
            $media = $this->catalog->proposeOrCreateMedia($user, null, $data, true);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse($this->catalog->serializeMedia($media), 201);
    }

    #[Route('/media/{id}', name: 'update_media', methods: ['PATCH'])]
    #[IsGranted('ROLE_SUPERADMIN')]
    public function updateMedia(string $id, Request $request): JsonResponse
    {
        $media = $this->entityManager->getRepository(PrintMedia::class)->find($id);
        if (!$media instanceof PrintMedia) {
            return new JsonResponse(['error' => 'Medium nicht gefunden'], 404);
        }
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiger JSON-Body'], 400);
        }
        try {
            $media = $this->catalog->updateMedia($media, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse($this->catalog->serializeMedia($media));
    }

    #[Route('/media/{id}/review', name: 'review_media', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reviewMedia(string $id, Request $request): JsonResponse
    {
        $user = $this->requireReviewer();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $media = $this->entityManager->getRepository(PrintMedia::class)->find($id);
        if (!$media instanceof PrintMedia) {
            return new JsonResponse(['error' => 'Medium nicht gefunden'], 404);
        }
        $data = json_decode($request->getContent(), true);
        $action = \is_array($data) ? (string) ($data['action'] ?? '') : '';
        try {
            $media = $this->catalog->reviewMedia($user, $media, $action);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($this->catalog->serializeMedia($media));
    }

    #[Route('/layouts/{id}/review', name: 'review_layout', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reviewLayout(string $id, Request $request): JsonResponse
    {
        $user = $this->requireReviewer();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $layout = $this->layouts->get($id);
        if ($layout === null) {
            return new JsonResponse(['error' => 'Layout nicht gefunden'], 404);
        }
        $data = json_decode($request->getContent(), true);
        $action = \is_array($data) ? (string) ($data['action'] ?? '') : '';
        try {
            $layout = $this->layouts->review($user, $layout, $action);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($this->layouts->serialize($layout));
    }

    private function requireReviewer(): User|JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (!PrintCatalogVisibility::isReviewer($user->getRoles())) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        return $user;
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
