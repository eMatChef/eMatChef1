<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Supplier\SupplierGlobalReviewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/supplier-global-review', name: 'api_admin_supplier_global_review_')]
class SupplierGlobalReviewController extends AbstractController
{
    public function __construct(
        private SupplierGlobalReviewService $reviewService,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(): JsonResponse
    {
        if (!$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        return new JsonResponse($this->reviewService->listPending());
    }

    #[Route('/catalog/{itemId}/approve', name: 'catalog_approve', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function approveCatalog(string $itemId): JsonResponse
    {
        if (!$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        try {
            $item = $this->reviewService->approveCatalogItem($itemId);

            return new JsonResponse([
                'item' => $item,
                'message' => 'Katalog-Artikel global freigegeben',
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/catalog/{itemId}/reject', name: 'catalog_reject', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function rejectCatalog(string $itemId, Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $reason = isset($data['reason']) ? trim((string) $data['reason']) : null;

        try {
            $item = $this->reviewService->rejectCatalogItem($itemId, $reason);

            return new JsonResponse([
                'item' => $item,
                'message' => 'Katalog-Artikel abgelehnt',
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/templates/{templateId}/approve', name: 'template_approve', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function approveTemplate(string $templateId): JsonResponse
    {
        if (!$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        try {
            $item = $this->reviewService->approveTemplate($templateId);

            return new JsonResponse([
                'item' => $item,
                'message' => 'Vorlage global freigegeben',
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/templates/{templateId}/reject', name: 'template_reject', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function rejectTemplate(string $templateId, Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $reason = isset($data['reason']) ? trim((string) $data['reason']) : null;

        try {
            $item = $this->reviewService->rejectTemplate($templateId, $reason);

            return new JsonResponse([
                'item' => $item,
                'message' => 'Vorlage abgelehnt',
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }
}
