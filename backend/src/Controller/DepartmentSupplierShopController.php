<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Membership;
use App\Entity\User;
use App\Service\Supplier\SupplierImportService;
use App\Service\Supplier\SupplierShopService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/supplier-shop', name: 'api_department_supplier_shop_')]
class DepartmentSupplierShopController extends AbstractController
{
    private const SHOP_ROLES = ['mw', 'dc', 'matwart', 'depchef'];

    public function __construct(
        private SupplierShopService $shopService,
        private SupplierImportService $importService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/companies', name: 'companies', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function companies(string $departmentId): JsonResponse
    {
        if (!$this->canAccessShop($departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        return new JsonResponse([
            'companies' => $this->shopService->listShopCompanies(),
        ]);
    }

    #[Route('/catalog', name: 'catalog', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function catalog(string $departmentId, Request $request): JsonResponse
    {
        if (!$this->canAccessShop($departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $companyId = trim((string) $request->query->get('supplier_company_id', ''));
        if ($companyId === '') {
            return new JsonResponse(['error' => 'supplier_company_id ist erforderlich'], 400);
        }

        return new JsonResponse([
            'catalog_items' => $this->shopService->listShopCatalog($companyId),
        ]);
    }

    #[Route('/templates', name: 'templates', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function templates(string $departmentId, Request $request): JsonResponse
    {
        if (!$this->canAccessShop($departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $companyId = trim((string) $request->query->get('supplier_company_id', ''));
        if ($companyId === '') {
            return new JsonResponse(['error' => 'supplier_company_id ist erforderlich'], 400);
        }

        return new JsonResponse([
            'material_templates' => $this->shopService->listShopTemplates($companyId),
        ]);
    }

    #[Route('/catalog-import', name: 'catalog_import', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function catalogImport(string $departmentId, Request $request): JsonResponse
    {
        if (!$this->canAccessShop($departmentId)) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $catalogItemId = trim((string) ($data['catalog_item_id'] ?? ''));
        $qty = (int) ($data['qty'] ?? 1);

        if ($catalogItemId === '') {
            return new JsonResponse(['error' => 'catalog_item_id ist erforderlich'], 400);
        }

        try {
            $result = $this->importService->importCatalogItem($departmentId, $catalogItemId, $qty, $data);

            return new JsonResponse([
                'material' => $result,
                'message' => 'Katalog-Artikel importiert',
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Import fehlgeschlagen: ' . $e->getMessage()], 500);
        }
    }

    private function canAccessShop(string $departmentId): bool
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return false;
        }
        if ($this->isGranted('ROLE_SUPERADMIN')) {
            return true;
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$membership instanceof Membership) {
            return false;
        }

        return \in_array(strtolower(trim($membership->getRole())), self::SHOP_ROLES, true);
    }
}
