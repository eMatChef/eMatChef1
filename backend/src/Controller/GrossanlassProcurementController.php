<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassCostService;
use App\Service\Grossanlass\GrossanlassGmailAccountService;
use App\Service\Grossanlass\GrossanlassMailMergeService;
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
        private GrossanlassGmailAccountService $gmail,
        private GroupAccessService $groupAccess,
        private GrossanlassCostService $costService,
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

    #[Route('/categories', name: 'categories_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listCategories(string $departmentId): JsonResponse
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
            return new JsonResponse($this->procurementService->listCategories($department, $currentUser));
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[Route('/categories', name: 'categories_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createCategory(string $departmentId, Request $request): JsonResponse
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
            $category = $this->procurementService->createCategory($department, $currentUser, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $this->gmail->syncLabelsIfConnected($department, $currentUser);

        return new JsonResponse($category, 201);
    }

    #[Route('/categories/{categoryId}', name: 'categories_update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updateCategory(string $departmentId, string $categoryId, Request $request): JsonResponse
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
        $oldPath = $this->categoryPackagePathById($department, $currentUser, $categoryId);

        try {
            $category = $this->procurementService->updateCategory($department, $currentUser, $categoryId, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $newPath = GrossanlassMailMergeService::categoryPackagePathFromRow($category);
        if ($oldPath !== '' && $newPath !== '' && $oldPath !== $newPath) {
            $this->gmail->renameCategoryPackagePath($department, $currentUser, $oldPath, $newPath);
        }
        if (array_key_exists('name', $data) || array_key_exists('parent_id', $data)) {
            $this->gmail->syncLabelsIfConnected($department, $currentUser);
        }

        return new JsonResponse($category);
    }

    #[Route('/categories/{categoryId}', name: 'categories_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteCategory(string $departmentId, string $categoryId): JsonResponse
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
            $this->procurementService->deleteCategory($department, $currentUser, $categoryId);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $this->gmail->syncLabelsIfConnected($department, $currentUser);

        return new JsonResponse(['success' => true]);
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

    #[Route('/overview', name: 'overview', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function overview(string $departmentId): JsonResponse
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
            return new JsonResponse($this->procurementService->getOverview($department, $currentUser));
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[Route('/overview/rahmen', name: 'overview_rahmen', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function saveRahmen(string $departmentId, Request $request): JsonResponse
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
        if (!is_array($data)) {
            $data = [];
        }

        try {
            return new JsonResponse($this->procurementService->saveFinance($department, $currentUser, $data));
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[Route('/lines', name: 'lines_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listLines(string $departmentId, Request $request): JsonResponse
    {
        $department = $this->resolveGrossanlassDepartment($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }

        $currentUser = $this->requireMember($departmentId);
        if ($currentUser instanceof JsonResponse) {
            return $currentUser;
        }

        $status = $request->query->get('status');

        try {
            return new JsonResponse($this->procurementService->listAllLines(
                $department,
                $currentUser,
                is_string($status) ? $status : null,
            ));
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[Route('/lines/{lineId}/quotes', name: 'quotes_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createQuote(string $departmentId, string $lineId, Request $request): JsonResponse
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
            $quote = $this->procurementService->createQuote($department, $currentUser, $lineId, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($quote, 201);
    }

    #[Route('/lines/{lineId}/quotes/{quoteId}', name: 'quotes_update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updateQuote(string $departmentId, string $lineId, string $quoteId, Request $request): JsonResponse
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
            $quote = $this->procurementService->updateQuote($department, $currentUser, $lineId, $quoteId, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($quote);
    }

    #[Route('/lines/{lineId}/quotes/{quoteId}', name: 'quotes_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteQuote(string $departmentId, string $lineId, string $quoteId): JsonResponse
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
            $this->procurementService->deleteQuote($department, $currentUser, $lineId, $quoteId);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/quotes/extract-contact', name: 'quotes_extract_contact', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function extractContactFromPdf(string $departmentId, Request $request): JsonResponse
    {
        $department = $this->resolveGrossanlassDepartment($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }

        $currentUser = $this->requireMember($departmentId);
        if ($currentUser instanceof JsonResponse) {
            return $currentUser;
        }

        $file = $request->files->get('pdf');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return new JsonResponse(['error' => 'pdf ist erforderlich'], 400);
        }

        try {
            return new JsonResponse($this->procurementService->extractContactFromQuotePdf($department, $currentUser, $file));
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[Route('/lines/{lineId}/quotes/{quoteId}/pdf', name: 'quotes_upload_pdf', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function uploadQuotePdf(string $departmentId, string $lineId, string $quoteId, Request $request): JsonResponse
    {
        $department = $this->resolveGrossanlassDepartment($departmentId);
        if ($department instanceof JsonResponse) {
            return $department;
        }

        $currentUser = $this->requireMember($departmentId);
        if ($currentUser instanceof JsonResponse) {
            return $currentUser;
        }

        $file = $request->files->get('pdf');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return new JsonResponse(['error' => 'pdf ist erforderlich'], 400);
        }

        try {
            $quote = $this->procurementService->uploadQuotePdf($department, $currentUser, $lineId, $quoteId, $file);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($quote);
    }

    #[Route('/lines/{lineId}/quotes/{quoteId}/select', name: 'quotes_select', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function selectQuote(string $departmentId, string $lineId, string $quoteId): JsonResponse
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
            $line = $this->procurementService->selectQuote($department, $currentUser, $lineId, $quoteId);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($line);
    }

    #[Route('/lines/{lineId}/order', name: 'order_upsert', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function upsertOrder(string $departmentId, string $lineId, Request $request): JsonResponse
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
            $line = $this->procurementService->upsertOrder($department, $currentUser, $lineId, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($line);
    }

    #[Route('/lines/{lineId}/received', name: 'received_record', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function recordReceived(string $departmentId, string $lineId, Request $request): JsonResponse
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
            $line = $this->procurementService->recordReceived($department, $currentUser, $lineId, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($line);
    }

    #[Route('/wishes/{wishLineId}', name: 'wish_update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updateBedarfWish(string $departmentId, string $wishLineId, Request $request): JsonResponse
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
            $overview = $this->procurementService->updateBedarfWish($department, $currentUser, $wishLineId, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($overview);
    }

    #[Route('/collector/{wishId}/to-inquiry', name: 'collector_to_inquiry', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function collectorToInquiry(string $departmentId, string $wishId, Request $request): JsonResponse
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
            $overview = $this->procurementService->collectorToInquiry($department, $currentUser, $wishId, is_array($data) ? $data : []);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($overview);
    }

    #[Route('/collector/{wishId}/to-material', name: 'collector_to_material', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function collectorToMaterial(string $departmentId, string $wishId, Request $request): JsonResponse
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
            $overview = $this->procurementService->collectorToMaterial($department, $currentUser, $wishId, is_array($data) ? $data : []);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($overview);
    }

    #[Route('/collector/{wishId}/discard', name: 'collector_discard', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function collectorDiscard(string $departmentId, string $wishId): JsonResponse
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
            $overview = $this->procurementService->collectorDiscard($department, $currentUser, $wishId);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($overview);
    }

    #[Route('/lines/{lineId}/wishes/{wishLineId}', name: 'lines_remove_wish', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function removeWishFromLine(string $departmentId, string $lineId, string $wishLineId): JsonResponse
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
            $line = $this->procurementService->removeWishFromLine($department, $currentUser, $lineId, $wishLineId);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse($line);
    }

    #[Route('/costs', name: 'costs_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listCosts(string $departmentId, Request $request): JsonResponse
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
            return new JsonResponse($this->costService->list($department, $currentUser, $request->query->all()));
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[Route('/costs', name: 'costs_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createCost(string $departmentId, Request $request): JsonResponse
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
        if (!is_array($data)) {
            $data = [];
        }

        try {
            return new JsonResponse($this->costService->create($department, $currentUser, $data), 201);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[Route('/costs/{costId}', name: 'costs_update', methods: ['PATCH', 'PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updateCost(string $departmentId, string $costId, Request $request): JsonResponse
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
        if (!is_array($data)) {
            $data = [];
        }

        try {
            return new JsonResponse($this->costService->update($department, $currentUser, $costId, $data));
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[Route('/costs/{costId}', name: 'costs_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteCost(string $departmentId, string $costId): JsonResponse
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
            $this->costService->delete($department, $currentUser, $costId);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse(null, 204);
    }

    #[Route('/budgets', name: 'budgets_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listBudgets(string $departmentId): JsonResponse
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
            return new JsonResponse($this->costService->listBudgets($department, $currentUser));
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[Route('/budgets', name: 'budgets_upsert', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function upsertBudget(string $departmentId, Request $request): JsonResponse
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
        if (!is_array($data)) {
            $data = [];
        }

        try {
            return new JsonResponse($this->costService->upsertBudget($department, $currentUser, $data));
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    private function categoryPackagePathById(Department $department, User $user, string $categoryId): string
    {
        try {
            foreach ($this->procurementService->listCategories($department, $user) as $row) {
                if (($row['id'] ?? '') === $categoryId) {
                    return GrossanlassMailMergeService::categoryPackagePathFromRow($row);
                }
            }
        } catch (\Throwable) {
        }

        return '';
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
