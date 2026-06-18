<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Category;
use App\Entity\Department;
use App\Entity\MaterialBatch;
use App\Entity\MaterialItem;
use App\Service\JsDotationRulesService;
use App\Service\JsLeihkatalogCatalogService;
use App\Service\JsPdfCatalogSyncService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/js-leihkatalog', name: 'api_admin_js_leihkatalog_')]
#[IsGranted('ROLE_SUPERADMIN')]
class JsLeihkatalogAdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JsLeihkatalogCatalogService $catalogService,
        private JsDotationRulesService $dotationRules,
        private JsPdfCatalogSyncService $pdfCatalogSync,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $category = $this->catalogService->findOrderFormCategory();

        $qb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(MaterialItem::class, 'm');
        $this->catalogService->applyOrderFormFilters($qb, 'm');
        $this->catalogService->applyOrderFormSort($qb, 'm');

        $items = [];
        foreach ($qb->getQuery()->getResult() as $material) {
            if (!$material instanceof MaterialItem) {
                continue;
            }
            $items[] = $this->serializeItem($material);
        }

        return new JsonResponse([
            'category' => $category ? [
                'id' => $category->getId(),
                'name' => $category->getName(),
            ] : null,
            'department_id' => JsLeihkatalogCatalogService::DEPARTMENT_ID,
            'items' => $items,
            'total' => count($items),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiger JSON-Body'], 400);
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return new JsonResponse(['error' => 'name ist erforderlich'], 400);
        }

        $pdfLineNo = (int) ($data['pdf_line_no'] ?? 0);
        if ($pdfLineNo < 1) {
            return new JsonResponse(['error' => 'pdf_line_no muss mindestens 1 sein'], 400);
        }

        $category = $this->requireOrderFormCategory();
        if ($category instanceof JsonResponse) {
            return $category;
        }

        $department = $this->entityManager->find(Department::class, JsLeihkatalogCatalogService::DEPARTMENT_ID);
        if (!$department instanceof Department) {
            return new JsonResponse(['error' => 'J+S-Department nicht gefunden'], 404);
        }

        $material = new MaterialItem();
        $material->setId(IdGenerator::generate());
        $material->setDepartment($department);
        $material->setName($name);
        $material->setIsJsMaterial(true);
        $material->setExternalSource('js_ch');
        $material->setCategory($category);
        $material->setNo($pdfLineNo);
        $material->setLocation('J&S Lager');

        if (isset($data['description']) && trim((string) $data['description']) !== '') {
            $material->setDescription(trim((string) $data['description']));
        }

        $storage = $this->entityManager->getRepository(Address::class)->findOneBy([
            'departmentId' => JsLeihkatalogCatalogService::DEPARTMENT_ID,
            'type' => 'storage',
        ]);
        if ($storage instanceof Address) {
            $material->setStorageAddress($storage);
        }

        $stockQty = (int) ($data['stock_qty'] ?? 999999);
        if ($stockQty < 0) {
            $stockQty = 0;
        }

        $batch = new MaterialBatch();
        $batch->setId(IdGenerator::generate13('ba', date('Y')));
        $batch->setMaterialItem($material);
        $batch->setQty($stockQty);
        $batch->setLabel('J+S Leih-Material');
        $batch->setBatchType('initial');
        $batch->setStatus('active');
        $batch->setAcquiredOn(new \DateTime());

        $this->entityManager->persist($material);
        $this->entityManager->persist($batch);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeItem($material), 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $material = $this->entityManager->find(MaterialItem::class, $id);
        if (!$material instanceof MaterialItem || !$material->getIsJsMaterial() || $material->getDeletedAt() !== null) {
            return new JsonResponse(['error' => 'J+S-Material nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiger JSON-Body'], 400);
        }

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                return new JsonResponse(['error' => 'name darf nicht leer sein'], 400);
            }
            $material->setName($name);
        }

        if (array_key_exists('description', $data)) {
            $material->setDescription(trim((string) $data['description']) ?: null);
        }

        if (array_key_exists('pdf_line_no', $data)) {
            $pdfLineNo = (int) $data['pdf_line_no'];
            if ($pdfLineNo < 1) {
                return new JsonResponse(['error' => 'pdf_line_no muss mindestens 1 sein'], 400);
            }
            $material->setNo($pdfLineNo);
        }

        if (array_key_exists('stock_qty', $data)) {
            $stockQty = max(0, (int) $data['stock_qty']);
            $batch = $this->primaryBatchForMaterial($material);
            if ($batch instanceof MaterialBatch) {
                $batch->setQty($stockQty);
            }
        }

        if (array_key_exists('category_id', $data) && $data['category_id']) {
            $category = $this->entityManager->find(Category::class, (string) $data['category_id']);
            if ($category instanceof Category) {
                $material->setCategory($category);
            }
        }

        $this->entityManager->flush();

        return new JsonResponse($this->serializeItem($material));
    }

    #[Route('/sync-manifest', name: 'sync_manifest', methods: ['POST'])]
    public function syncManifest(): JsonResponse
    {
        $stats = $this->pdfCatalogSync->sync(false);

        return new JsonResponse([
            'ok' => true,
            'stats' => $stats,
        ]);
    }

    private function requireOrderFormCategory(): Category|JsonResponse
    {
        $category = $this->catalogService->findOrderFormCategory();
        if (!$category instanceof Category) {
            return new JsonResponse([
                'error' => 'Kategorie «' . JsLeihkatalogCatalogService::ORDER_FORM_CATEGORY_NAME . '» fehlt — Migration/Seed ausführen.',
            ], 409);
        }

        return $category;
    }

    private function primaryBatchForMaterial(MaterialItem $material): ?MaterialBatch
    {
        $batches = $this->entityManager->getRepository(MaterialBatch::class)->findBy(
            ['materialItem' => $material, 'status' => 'active'],
            ['createdAt' => 'ASC'],
            1,
        );

        return $batches[0] ?? null;
    }

    /** @return array<string, mixed> */
    private function serializeItem(MaterialItem $material): array
    {
        $stock = $this->dotationRules->stockAvailableForMaterial($material);
        $limits = $this->dotationRules->limitsForMaterial($material);

        return [
            'id' => $material->getId(),
            'name' => $material->getName(),
            'description' => $material->getDescription(),
            'pdf_line_no' => $material->getNo(),
            'pdf_line_order' => $this->catalogService->pdfLineOrderForMaterial($material),
            'pdf_form_line' => $this->dotationRules->pdfFormLineForMaterial($material),
            'dotation_hint' => $this->dotationRules->dotationHintForMaterial($material),
            'dotation_max' => $limits['max'],
            'stock_available' => $stock,
            'category' => $material->getCategory() ? [
                'id' => $material->getCategory()->getId(),
                'name' => $material->getCategory()->getName(),
            ] : null,
            'department_id' => $material->getDepartmentId(),
            'is_js_material' => true,
        ];
    }
}
