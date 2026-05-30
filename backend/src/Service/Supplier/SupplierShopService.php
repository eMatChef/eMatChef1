<?php

declare(strict_types=1);

namespace App\Service\Supplier;

use App\Entity\SupplierCatalogItem;
use App\Entity\SupplierCompany;
use App\Entity\SupplierMaterialTemplate;
use App\Repository\SupplierCatalogItemRepository;
use App\Repository\SupplierCompanyRepository;
use App\Repository\SupplierMaterialTemplateRepository;

/**
 * MW-Lieferanten-Shop: lesbare Firmen, Katalog und Vorlagen.
 */
class SupplierShopService
{
    public function __construct(
        private SupplierCompanyRepository $companyRepository,
        private SupplierCatalogItemRepository $catalogItemRepository,
        private SupplierMaterialTemplateRepository $templateRepository,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listShopCompanies(): array
    {
        $companies = $this->companyRepository->findByStatus(SupplierCompany::STATUS_ACTIVE);
        $items = [];

        foreach ($companies as $company) {
            if (!$this->companyHasShopCapability($company)) {
                continue;
            }
            $items[] = [
                'id' => $company->getId(),
                'name' => $company->getName(),
                'manufacturer_key' => $company->getManufacturerKey(),
                'capabilities' => $company->getCapabilities(),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listShopCatalog(string $companyId): array
    {
        $company = $this->companyRepository->find($companyId);
        if (!$company instanceof SupplierCompany || $company->getStatus() !== SupplierCompany::STATUS_ACTIVE) {
            return [];
        }

        $items = $this->catalogItemRepository->findShopVisibleByCompanyId($companyId);

        return array_map(
            static fn (SupplierCatalogItem $item) => $item->toArray(),
            $items
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listShopTemplates(string $companyId): array
    {
        $company = $this->companyRepository->find($companyId);
        if (!$company instanceof SupplierCompany || $company->getStatus() !== SupplierCompany::STATUS_ACTIVE) {
            return [];
        }

        if (!\in_array(SupplierCompany::CAPABILITY_TEMPLATES, $company->getCapabilities(), true)) {
            return [];
        }

        $templates = $this->templateRepository->findShopVisibleByCompanyId($companyId);

        return array_map(
            static fn (SupplierMaterialTemplate $template) => $template->toArray(false),
            $templates
        );
    }

    private function companyHasShopCapability(SupplierCompany $company): bool
    {
        $caps = $company->getCapabilities();

        return \in_array(SupplierCompany::CAPABILITY_CATALOG, $caps, true)
            || \in_array(SupplierCompany::CAPABILITY_DELIVERY, $caps, true)
            || \in_array(SupplierCompany::CAPABILITY_TEMPLATES, $caps, true);
    }
}
