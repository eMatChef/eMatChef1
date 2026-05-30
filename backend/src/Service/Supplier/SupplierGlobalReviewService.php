<?php

declare(strict_types=1);

namespace App\Service\Supplier;

use App\Entity\SupplierCatalogItem;
use App\Entity\SupplierMaterialTemplate;
use App\Repository\SupplierCatalogItemRepository;
use App\Repository\SupplierCompanyRepository;
use App\Repository\SupplierMaterialTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Admin-Freigabe für supplier_*-Inhalte mit visibility=global.
 */
class SupplierGlobalReviewService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierCatalogItemRepository $catalogItemRepository,
        private SupplierMaterialTemplateRepository $templateRepository,
        private SupplierCompanyRepository $companyRepository,
    ) {
    }

    /** @return array{catalog_items: list<array<string, mixed>>, material_templates: list<array<string, mixed>>} */
    public function listPending(): array
    {
        $catalogItems = [];
        foreach ($this->catalogItemRepository->findPendingGlobalReview() as $item) {
            $catalogItems[] = $this->catalogItemToReviewRow($item);
        }

        $templates = [];
        foreach ($this->templateRepository->findPendingGlobalReview() as $template) {
            $templates[] = $this->templateToReviewRow($template);
        }

        return [
            'catalog_items' => $catalogItems,
            'material_templates' => $templates,
        ];
    }

    /** @return array<string, mixed> */
    public function approveCatalogItem(string $itemId): array
    {
        $item = $this->catalogItemRepository->find($itemId);
        if (!$item instanceof SupplierCatalogItem) {
            throw new \InvalidArgumentException('Katalog-Artikel nicht gefunden');
        }
        $this->assertPendingGlobal($item->getVisibility(), $item->getStatus());

        $item->setStatus(SupplierCatalogItem::STATUS_PUBLISHED);
        $item->touch();
        $this->entityManager->flush();

        return $this->catalogItemToReviewRow($item);
    }

    /** @return array<string, mixed> */
    public function rejectCatalogItem(string $itemId, ?string $reason = null): array
    {
        $item = $this->catalogItemRepository->find($itemId);
        if (!$item instanceof SupplierCatalogItem) {
            throw new \InvalidArgumentException('Katalog-Artikel nicht gefunden');
        }
        $this->assertPendingGlobal($item->getVisibility(), $item->getStatus());

        $item->setStatus(SupplierCatalogItem::STATUS_DRAFT);
        $item->setVisibility(SupplierCatalogItem::VISIBILITY_PRIVATE);
        $item->touch();
        $this->entityManager->flush();

        $row = $this->catalogItemToReviewRow($item);
        if ($reason !== null && $reason !== '') {
            $row['rejection_reason'] = $reason;
        }

        return $row;
    }

    /** @return array<string, mixed> */
    public function approveTemplate(string $templateId): array
    {
        $template = $this->templateRepository->find($templateId);
        if (!$template instanceof SupplierMaterialTemplate) {
            throw new \InvalidArgumentException('Vorlage nicht gefunden');
        }
        $this->assertPendingGlobal($template->getVisibility(), $template->getStatus());

        $template->setStatus(SupplierMaterialTemplate::STATUS_PUBLISHED);
        $template->touch();
        $this->entityManager->flush();

        return $this->templateToReviewRow($template);
    }

    /** @return array<string, mixed> */
    public function rejectTemplate(string $templateId, ?string $reason = null): array
    {
        $template = $this->templateRepository->find($templateId);
        if (!$template instanceof SupplierMaterialTemplate) {
            throw new \InvalidArgumentException('Vorlage nicht gefunden');
        }
        $this->assertPendingGlobal($template->getVisibility(), $template->getStatus());

        $template->setStatus(SupplierMaterialTemplate::STATUS_DRAFT);
        $template->setVisibility(SupplierMaterialTemplate::VISIBILITY_PRIVATE);
        $template->touch();
        $this->entityManager->flush();

        $row = $this->templateToReviewRow($template);
        if ($reason !== null && $reason !== '') {
            $row['rejection_reason'] = $reason;
        }

        return $row;
    }

    private function assertPendingGlobal(string $visibility, string $status): void
    {
        if (
            $visibility !== SupplierCatalogItem::VISIBILITY_GLOBAL
            || $status !== SupplierCatalogItem::STATUS_PENDING_REVIEW
        ) {
            throw new \InvalidArgumentException('Eintrag steht nicht zur globalen Freigabe an');
        }
    }

    /** @return array<string, mixed> */
    private function catalogItemToReviewRow(SupplierCatalogItem $item): array
    {
        $row = $item->toArray();
        $row['item_type'] = 'catalog';
        $row['supplier_company_name'] = $this->resolveCompanyName($item->getSupplierCompanyId());

        return $row;
    }

    /** @return array<string, mixed> */
    private function templateToReviewRow(SupplierMaterialTemplate $template): array
    {
        $row = $template->toArray(false);
        $row['item_type'] = 'template';
        $row['supplier_company_name'] = $this->resolveCompanyName($template->getSupplierCompanyId());
        $row['component_count'] = $template->getComponents()->count();

        return $row;
    }

    private function resolveCompanyName(string $companyId): string
    {
        $company = $this->companyRepository->find($companyId);

        return $company?->getName() ?? $companyId;
    }
}
