<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SupplierCatalogItem;
use App\Entity\User;
use App\Repository\SupplierCatalogItemRepository;
use App\Security\Voter\SupplierCompanyVoter;
use App\Service\Supplier\SupplierCompanyAccessService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/supplier-companies/{companyId}/catalog-items', name: 'api_supplier_catalog_items_')]
class SupplierCatalogItemController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierCatalogItemRepository $catalogItemRepository,
        private SupplierCompanyAccessService $accessService,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function list(string $companyId): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireCatalogAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $items = $this->catalogItemRepository->findByCompanyId($companyId);

        return new JsonResponse([
            'catalog_items' => array_map(
                static fn (SupplierCatalogItem $item) => $item->toArray(),
                $items
            ),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function create(string $companyId, Request $request): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $company = $this->accessService->requireCatalogAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return new JsonResponse(['error' => 'name ist erforderlich'], 400);
        }

        try {
            $item = new SupplierCatalogItem();
            $item->setId(IdGenerator::generateUnique($this->entityManager, SupplierCatalogItem::class));
            $item->setSupplierCompany($company);
            $this->applyItemData($item, $data);

            $this->entityManager->persist($item);
            $this->entityManager->flush();

            return new JsonResponse([
                'catalog_item' => $item->toArray(),
                'message' => 'Katalog-Artikel erstellt',
            ], 201);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            return new JsonResponse(['error' => 'SKU ist in dieser Firma bereits vergeben'], 409);
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => 'Fehler beim Erstellen: ' . $exception->getMessage()], 500);
        }
    }

    #[Route('/{itemId}', name: 'show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function show(string $companyId, string $itemId): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireCatalogAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $item = $this->requireItem($companyId, $itemId);

        return new JsonResponse(['catalog_item' => $item->toArray()]);
    }

    #[Route('/{itemId}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function update(string $companyId, string $itemId, Request $request): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireCatalogAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $item = $this->requireItem($companyId, $itemId);
        $data = json_decode($request->getContent(), true) ?: [];

        try {
            $this->applyItemData($item, $data);
            $item->touch();
            $this->entityManager->flush();

            return new JsonResponse([
                'catalog_item' => $item->toArray(),
                'message' => 'Katalog-Artikel aktualisiert',
            ]);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            return new JsonResponse(['error' => 'SKU ist in dieser Firma bereits vergeben'], 409);
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => 'Fehler beim Aktualisieren: ' . $exception->getMessage()], 500);
        }
    }

    #[Route('/{itemId}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function delete(string $companyId, string $itemId): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireCatalogAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $item = $this->requireItem($companyId, $itemId);

        try {
            $this->entityManager->remove($item);
            $this->entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Katalog-Artikel gelöscht']);
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => 'Fehler beim Löschen: ' . $exception->getMessage()], 500);
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

    private function requireItem(string $companyId, string $itemId): SupplierCatalogItem
    {
        $item = $this->catalogItemRepository->find($itemId);
        if (!$item instanceof SupplierCatalogItem || $item->getSupplierCompanyId() !== $companyId) {
            throw $this->createNotFoundException('Katalog-Artikel nicht gefunden');
        }

        return $item;
    }

    /** @param array<string, mixed> $data */
    private function applyItemData(SupplierCatalogItem $item, array $data): void
    {
        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new \InvalidArgumentException('name darf nicht leer sein');
            }
            $item->setName($name);
        }
        if (array_key_exists('sku', $data)) {
            $item->setSku($this->nullableString($data['sku']));
        }
        if (array_key_exists('manufacturer', $data)) {
            $item->setManufacturer($this->nullableString($data['manufacturer']));
        }
        if (array_key_exists('tracking_type', $data)) {
            $trackingType = strtolower(trim((string) $data['tracking_type']));
            if (!\in_array($trackingType, [SupplierCatalogItem::TRACKING_BULK, SupplierCatalogItem::TRACKING_SERIALIZED], true)) {
                throw new \InvalidArgumentException('Ungültiger tracking_type');
            }
            $item->setTrackingType($trackingType);
        }
        if (array_key_exists('unit_price', $data)) {
            $item->setUnitPrice($this->nullableDecimal($data['unit_price']));
        }
        if (array_key_exists('currency', $data)) {
            $currency = strtoupper(trim((string) $data['currency']));
            if ($currency === '') {
                throw new \InvalidArgumentException('currency darf nicht leer sein');
            }
            $item->setCurrency($currency);
        }
        if (array_key_exists('min_qty', $data)) {
            $item->setMinQty($this->nullableInt($data['min_qty']));
        }
        if (array_key_exists('pack_size', $data)) {
            $item->setPackSize($this->nullableInt($data['pack_size']));
        }
        if (array_key_exists('category_hint', $data)) {
            $item->setCategoryHint($this->nullableString($data['category_hint']));
        }
        if (array_key_exists('description', $data)) {
            $item->setDescription($this->nullableString($data['description']));
        }
        if (array_key_exists('external_ref', $data)) {
            $item->setExternalRef($this->nullableString($data['external_ref']));
        }
        if (array_key_exists('is_active', $data)) {
            $item->setIsActive((bool) $data['is_active']);
        }
        if (array_key_exists('visibility', $data)) {
            $visibility = strtolower(trim((string) $data['visibility']));
            if (!\in_array($visibility, [
                SupplierCatalogItem::VISIBILITY_PRIVATE,
                SupplierCatalogItem::VISIBILITY_DEPARTMENTS,
                SupplierCatalogItem::VISIBILITY_GLOBAL,
            ], true)) {
                throw new \InvalidArgumentException('Ungültige visibility');
            }
            $item->setVisibility($visibility);
        }
        if (array_key_exists('status', $data)) {
            $status = strtolower(trim((string) $data['status']));
            if (!\in_array($status, [
                SupplierCatalogItem::STATUS_DRAFT,
                SupplierCatalogItem::STATUS_PUBLISHED,
                SupplierCatalogItem::STATUS_PENDING_REVIEW,
            ], true)) {
                throw new \InvalidArgumentException('Ungültiger status');
            }
            $item->setStatus($status);
        }

        $this->enforceSupplierVisibilityRules($item);
    }

    private function enforceSupplierVisibilityRules(SupplierCatalogItem $item): void
    {
        if (
            $item->getVisibility() === SupplierCatalogItem::VISIBILITY_GLOBAL
            && $item->getStatus() === SupplierCatalogItem::STATUS_PUBLISHED
        ) {
            $item->setStatus(SupplierCatalogItem::STATUS_PENDING_REVIEW);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
