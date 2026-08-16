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

    /** Mindestens ein shop-sichtbarer Katalog-Artikel bei einer aktiven Lieferantenfirma. */
    public function hasShopArticles(): bool
    {
        foreach ($this->listCatalogCompanies() as $company) {
            if ($this->catalogItemRepository->countShopVisibleByCompanyId($company->getId()) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listShopCatalog(?string $companyId = null): array
    {
        if ($companyId !== null) {
            return $this->listShopCatalogForCompany($companyId);
        }

        $items = [];
        foreach ($this->listCatalogCompanies() as $company) {
            $items = array_merge(
                $items,
                $this->mapCatalogItems(
                    $this->catalogItemRepository->findShopVisibleByCompanyId($company->getId()),
                    $company
                )
            );
        }

        usort(
            $items,
            static fn (array $a, array $b) => strcasecmp((string) $a['name'], (string) $b['name'])
                ?: strcasecmp((string) ($a['sku'] ?? ''), (string) ($b['sku'] ?? ''))
        );

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listShopTemplates(?string $companyId = null): array
    {
        if ($companyId !== null) {
            return $this->listShopTemplatesForCompany($companyId);
        }

        $templates = [];
        foreach ($this->listTemplateCompanies() as $company) {
            $templates = array_merge(
                $templates,
                $this->mapTemplateItems(
                    $this->templateRepository->findShopVisibleByCompanyId($company->getId()),
                    $company
                )
            );
        }

        usort(
            $templates,
            static fn (array $a, array $b) => strcasecmp((string) $a['name'], (string) $b['name'])
        );

        return $templates;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listShopCatalogForCompany(string $companyId): array
    {
        $company = $this->companyRepository->find($companyId);
        if (!$company instanceof SupplierCompany || $company->getStatus() !== SupplierCompany::STATUS_ACTIVE) {
            return [];
        }

        if (!\in_array(SupplierCompany::CAPABILITY_CATALOG, $company->getCapabilities(), true)) {
            return [];
        }

        return $this->mapCatalogItems(
            $this->catalogItemRepository->findShopVisibleByCompanyId($companyId),
            $company
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listShopTemplatesForCompany(string $companyId): array
    {
        $company = $this->companyRepository->find($companyId);
        if (!$company instanceof SupplierCompany || $company->getStatus() !== SupplierCompany::STATUS_ACTIVE) {
            return [];
        }

        if (!\in_array(SupplierCompany::CAPABILITY_TEMPLATES, $company->getCapabilities(), true)) {
            return [];
        }

        return $this->mapTemplateItems(
            $this->templateRepository->findShopVisibleByCompanyId($companyId),
            $company
        );
    }

    /**
     * @param list<SupplierCatalogItem> $items
     *
     * @return list<array<string, mixed>>
     */
    private function mapCatalogItems(array $items, SupplierCompany $company): array
    {
        return array_map(
            function (SupplierCatalogItem $item) use ($company): array {
                $payload = $item->toArray();
                $payload['supplier_company_name'] = $company->getName();

                return $payload;
            },
            $items
        );
    }

    /**
     * @param list<SupplierMaterialTemplate> $templates
     *
     * @return list<array<string, mixed>>
     */
    private function mapTemplateItems(array $templates, SupplierCompany $company): array
    {
        return array_map(
            function (SupplierMaterialTemplate $template) use ($company): array {
                $payload = $template->toArray(false);
                $payload['supplier_company_name'] = $company->getName();

                return $payload;
            },
            $templates
        );
    }

    /**
     * @return list<SupplierCompany>
     */
    private function listCatalogCompanies(): array
    {
        return $this->listCompaniesWithCapability(SupplierCompany::CAPABILITY_CATALOG);
    }

    /**
     * @return list<SupplierCompany>
     */
    private function listTemplateCompanies(): array
    {
        return $this->listCompaniesWithCapability(SupplierCompany::CAPABILITY_TEMPLATES);
    }

    /**
     * @return list<SupplierCompany>
     */
    private function listCompaniesWithCapability(string $capability): array
    {
        $companies = $this->companyRepository->findByStatus(SupplierCompany::STATUS_ACTIVE);
        $items = [];

        foreach ($companies as $company) {
            if (\in_array($capability, $company->getCapabilities(), true)) {
                $items[] = $company;
            }
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRepairCompanies(): array
    {
        $companies = $this->companyRepository->findByStatus(SupplierCompany::STATUS_ACTIVE);
        $items = [];

        foreach ($companies as $company) {
            if (!\in_array(SupplierCompany::CAPABILITY_REPAIRS, $company->getCapabilities(), true)) {
                continue;
            }
            $items[] = [
                'id' => $company->getId(),
                'name' => $company->getName(),
                'manufacturer_key' => $company->getManufacturerKey(),
            ];
        }

        return $items;
    }

    private function companyHasShopCapability(SupplierCompany $company): bool
    {
        $caps = $company->getCapabilities();

        return \in_array(SupplierCompany::CAPABILITY_CATALOG, $caps, true)
            || \in_array(SupplierCompany::CAPABILITY_DELIVERY, $caps, true)
            || \in_array(SupplierCompany::CAPABILITY_TEMPLATES, $caps, true);
    }
}
