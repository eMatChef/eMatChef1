<?php

declare(strict_types=1);

namespace App\Service\Supplier;

use App\Entity\MaterialTemplate;
use App\Entity\MaterialTemplateComponent;
use App\Entity\MaterialTemplateOption;
use App\Entity\MaterialTemplateOptionDelta;
use App\Entity\MaterialTemplateOptionGroup;
use App\Entity\SupplierCompany;
use App\Entity\SupplierMaterialTemplate;
use App\Entity\SupplierMaterialTemplateComponent;
use App\Entity\SupplierMaterialTemplateOption;
use App\Entity\SupplierMaterialTemplateOptionDelta;
use App\Entity\SupplierMaterialTemplateOptionGroup;
use App\Repository\SupplierCompanyRepository;
use App\Repository\SupplierMaterialTemplateRepository;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Kopiert globale material_template-Vorlagen in supplier_material_template* (Paket 11 Legacy-Übernahme).
 */
class SupplierLegacyTemplateImportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierCompanyRepository $companyRepository,
        private SupplierMaterialTemplateRepository $supplierTemplateRepository,
    ) {
    }

    /**
     * @return array{
     *   manufacturer_key: string|null,
     *   available_count: int,
     *   already_imported_count: int,
     *   templates: list<array<string, mixed>>
     * }
     */
    public function getPreview(string $companyId): array
    {
        $company = $this->requireCompany($companyId);
        $manufacturerKey = $company->getManufacturerKey();
        if ($manufacturerKey === null || trim($manufacturerKey) === '') {
            return [
                'manufacturer_key' => null,
                'available_count' => 0,
                'already_imported_count' => 0,
                'templates' => [],
                'message' => 'manufacturer_key fehlt — kein automatisches Matching möglich',
            ];
        }

        $importedLegacyIds = $this->supplierTemplateRepository->findImportedLegacyIdsByCompanyId($companyId);
        $importedSet = array_flip($importedLegacyIds);

        $templates = [];
        $availableCount = 0;

        foreach ($this->findMatchingGlobalTemplates($manufacturerKey) as $legacy) {
            $legacyId = (string) $legacy->getId();
            $alreadyImported = isset($importedSet[$legacyId]);
            if (!$alreadyImported) {
                ++$availableCount;
            }
            $templates[] = [
                'legacy_material_template_id' => $legacyId,
                'name' => $legacy->getName(),
                'manufacturer' => $legacy->getManufacturer(),
                'model' => $legacy->getModel(),
                'material_type' => $legacy->getMaterialType(),
                'component_count' => $legacy->getComponents()->count(),
                'already_imported' => $alreadyImported,
            ];
        }

        return [
            'manufacturer_key' => $manufacturerKey,
            'available_count' => $availableCount,
            'already_imported_count' => \count($importedLegacyIds),
            'templates' => $templates,
        ];
    }

    /**
     * @param list<string>|null $legacyTemplateIds null = alle noch nicht importierten
     *
     * @return array{
     *   imported: list<array<string, mixed>>,
     *   skipped: list<array<string, mixed>>,
     *   message: string
     * }
     */
    public function import(string $companyId, ?array $legacyTemplateIds = null): array
    {
        $company = $this->requireCompany($companyId);
        $manufacturerKey = $company->getManufacturerKey();
        if ($manufacturerKey === null || trim($manufacturerKey) === '') {
            throw new \InvalidArgumentException('manufacturer_key fehlt — Import nicht möglich');
        }

        $importedLegacyIds = $this->supplierTemplateRepository->findImportedLegacyIdsByCompanyId($companyId);
        $importedSet = array_flip($importedLegacyIds);

        $matching = $this->findMatchingGlobalTemplates($manufacturerKey);
        $byId = [];
        foreach ($matching as $legacy) {
            $byId[(string) $legacy->getId()] = $legacy;
        }

        $targetIds = $legacyTemplateIds ?? array_keys(array_filter(
            $byId,
            static fn (MaterialTemplate $t, string $id) => !isset($importedSet[$id]),
            ARRAY_FILTER_USE_BOTH
        ));

        if ($targetIds === []) {
            return [
                'imported' => [],
                'skipped' => [],
                'message' => 'Keine Vorlagen zum Importieren',
            ];
        }

        $imported = [];
        $skipped = [];

        $this->entityManager->beginTransaction();
        try {
            foreach ($targetIds as $legacyId) {
                $legacyId = trim((string) $legacyId);
                if ($legacyId === '') {
                    continue;
                }
                if (isset($importedSet[$legacyId])) {
                    $skipped[] = ['legacy_material_template_id' => $legacyId, 'reason' => 'bereits importiert'];
                    continue;
                }
                if (!isset($byId[$legacyId])) {
                    $skipped[] = ['legacy_material_template_id' => $legacyId, 'reason' => 'nicht gefunden oder Hersteller passt nicht'];
                    continue;
                }

                $supplierTemplate = $this->copyTemplate($company, $byId[$legacyId]);
                $imported[] = [
                    'legacy_material_template_id' => $legacyId,
                    'supplier_material_template_id' => $supplierTemplate->getId(),
                    'name' => $supplierTemplate->getName(),
                ];
                $importedSet[$legacyId] = true;
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'message' => \count($imported) . ' Vorlage(n) übernommen',
        ];
    }

    private function requireCompany(string $companyId): SupplierCompany
    {
        $company = $this->companyRepository->find($companyId);
        if (!$company instanceof SupplierCompany) {
            throw new \InvalidArgumentException('Supplier-Firma nicht gefunden');
        }

        return $company;
    }

    /** @return list<MaterialTemplate> */
    private function findMatchingGlobalTemplates(string $manufacturerKey): array
    {
        $normalizedKey = $this->normalizeManufacturer($manufacturerKey);
        if ($normalizedKey === '') {
            return [];
        }

        $candidates = $this->entityManager->getRepository(MaterialTemplate::class)
            ->createQueryBuilder('t')
            ->leftJoin('t.components', 'c')
            ->addSelect('c')
            ->where('t.scope = :scope')
            ->andWhere('t.departmentId IS NULL')
            ->andWhere('t.isActive = true')
            ->setParameter('scope', 'global')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        $matched = [];
        foreach ($candidates as $template) {
            if (!$template instanceof MaterialTemplate) {
                continue;
            }
            if ($this->normalizeManufacturer($template->getManufacturer()) === $normalizedKey) {
                $matched[] = $template;
            }
        }

        return $matched;
    }

    private function copyTemplate(SupplierCompany $company, MaterialTemplate $legacy): SupplierMaterialTemplate
    {
        $template = new SupplierMaterialTemplate();
        $template->setId(IdGenerator::generateUnique($this->entityManager, SupplierMaterialTemplate::class));
        $template->setSupplierCompany($company);
        $template->setName($legacy->getName());
        $template->setDescription($legacy->getDescription());
        $template->setManufacturer($legacy->getManufacturer());
        $template->setModel($legacy->getModel());
        $template->setMaterialType($legacy->getMaterialType());
        $template->setTentType($legacy->getTentType());
        $template->setCapacity($legacy->getCapacity());
        $template->setSource($legacy->getSource() ?? 'legacy_import');
        $template->setLegacyMaterialTemplateId($legacy->getId());
        $template->setVisibility(SupplierMaterialTemplate::VISIBILITY_PRIVATE);
        $template->setStatus(SupplierMaterialTemplate::STATUS_DRAFT);
        $template->setIsActive(true);

        if ($legacy->getCategory()?->getName()) {
            $template->setCategoryHint($legacy->getCategory()->getName());
        }

        foreach ($legacy->getComponents() as $legacyComp) {
            if (!$legacyComp instanceof MaterialTemplateComponent) {
                continue;
            }
            $component = new SupplierMaterialTemplateComponent();
            $component->setId(IdGenerator::generateUnique($this->entityManager, SupplierMaterialTemplateComponent::class));
            $component->setComponentType($legacyComp->getComponentType());
            $component->setName($legacyComp->getName());
            $component->setRequiredQty($legacyComp->getRequiredQty());
            $component->setIsOptional($legacyComp->getIsOptional());
            $component->setTracking($legacyComp->getTracking());
            $component->setComponentSource($legacyComp->getComponentSource());
            $component->setIsGeneric($legacyComp->getIsGeneric());
            $component->setSortOrder($legacyComp->getSortOrder());
            $template->addComponent($component);
        }

        $this->copyOptions($template, $legacy);
        $this->entityManager->persist($template);

        return $template;
    }

    private function copyOptions(SupplierMaterialTemplate $template, MaterialTemplate $legacy): void
    {
        $legacyId = $legacy->getId();
        if ($legacyId === null) {
            return;
        }

        $groups = $this->entityManager->getRepository(MaterialTemplateOptionGroup::class)
            ->findBy(['templateId' => $legacyId], ['sortOrder' => 'ASC']);

        /** @var array<string, SupplierMaterialTemplateOptionGroup> $groupMap */
        $groupMap = [];

        foreach ($groups as $legacyGroup) {
            if (!$legacyGroup instanceof MaterialTemplateOptionGroup) {
                continue;
            }
            $group = new SupplierMaterialTemplateOptionGroup();
            $group->setId(IdGenerator::generateUnique($this->entityManager, SupplierMaterialTemplateOptionGroup::class));
            $group->setName($legacyGroup->getName());
            $group->setSelectionType($legacyGroup->getSelectionType());
            $group->setMinSelect($legacyGroup->getMinSelect());
            $group->setMaxSelect($legacyGroup->getMaxSelect());
            $group->setSortOrder($legacyGroup->getSortOrder());
            $template->addOptionGroup($group);
            $groupMap[(string) $legacyGroup->getId()] = $group;
        }

        $legacyOptions = $this->entityManager->getRepository(MaterialTemplateOption::class)
            ->findBy(['templateId' => $legacyId], ['sortOrder' => 'ASC']);

        foreach ($legacyOptions as $legacyOption) {
            if (!$legacyOption instanceof MaterialTemplateOption) {
                continue;
            }
            $option = new SupplierMaterialTemplateOption();
            $option->setId(IdGenerator::generateUnique($this->entityManager, SupplierMaterialTemplateOption::class));
            $option->setName($legacyOption->getName());
            $option->setDisplayMode($legacyOption->getDisplayMode());
            $option->setDefaultSelected($legacyOption->getDefaultSelected());
            $option->setSortOrder($legacyOption->getSortOrder());

            $legacyGroupId = $legacyOption->getOptionGroupId();
            if ($legacyGroupId !== null && isset($groupMap[$legacyGroupId])) {
                $option->setOptionGroup($groupMap[$legacyGroupId]);
            }

            $template->addOption($option);

            $deltas = $this->entityManager->getRepository(MaterialTemplateOptionDelta::class)
                ->findBy(['optionId' => $legacyOption->getId()], ['sortOrder' => 'ASC']);

            foreach ($deltas as $legacyDelta) {
                if (!$legacyDelta instanceof MaterialTemplateOptionDelta) {
                    continue;
                }
                $delta = new SupplierMaterialTemplateOptionDelta();
                $delta->setId(IdGenerator::generateUnique($this->entityManager, SupplierMaterialTemplateOptionDelta::class));
                $delta->setComponentType($legacyDelta->getComponentType());
                $delta->setName($legacyDelta->getName());
                $delta->setQtyDelta($legacyDelta->getQtyDelta());
                $delta->setTracking($legacyDelta->getTracking());
                $delta->setComponentSource($legacyDelta->getComponentSource());
                $delta->setIsGeneric($legacyDelta->getIsGeneric());
                $delta->setSortOrder($legacyDelta->getSortOrder());
                $option->addDelta($delta);
            }
        }
    }

    private function normalizeManufacturer(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $normalized = mb_strtolower(trim($value));

        return preg_replace('/\s+/', '', $normalized) ?? '';
    }
}
