<?php

declare(strict_types=1);

namespace App\Service\Supplier;

use App\Entity\Address;
use App\Entity\Category;
use App\Entity\Department;
use App\Entity\MaterialBatch;
use App\Entity\MaterialComboComponent;
use App\Entity\MaterialItem;
use App\Entity\SupplierMaterialTemplate;
use App\Entity\SupplierMaterialTemplateComponent;
use App\Repository\SupplierMaterialTemplateRepository;
use App\Service\Public\PublicCodeService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Importiert supplier_material_template → Department-Material (Combo-Entwurf).
 */
class SupplierTemplateImportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierMaterialTemplateRepository $templateRepository,
        private PublicCodeService $publicCodeService,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     *   department_id implied; optional: name, category_id, storage_address_id,
     *   purchase_date, supplier_id, components[{component_type, serial_number?, qty?}]
     *
     * @return array<string, mixed>
     */
    public function importTemplate(string $departmentId, string $templateId, array $options = []): array
    {
        $template = $this->templateRepository->findShopVisibleById($templateId);
        if (!$template instanceof SupplierMaterialTemplate) {
            throw new \InvalidArgumentException('Vorlage nicht gefunden oder nicht im Shop verfügbar');
        }

        $department = $this->entityManager->find(Department::class, $departmentId);
        if (!$department instanceof Department) {
            throw new \InvalidArgumentException('Department nicht gefunden');
        }

        $creationMode = $template->getMaterialType();
        if (!\in_array($creationMode, [
            SupplierMaterialTemplate::MATERIAL_TYPE_PHYSICAL_COMBO,
            SupplierMaterialTemplate::MATERIAL_TYPE_VIRTUAL_COMBO,
        ], true)) {
            throw new \InvalidArgumentException('Nur Combo-Vorlagen können importiert werden');
        }

        $isPhysicalCombo = $creationMode === SupplierMaterialTemplate::MATERIAL_TYPE_PHYSICAL_COMBO;
        $isVirtualCombo = !$isPhysicalCombo;

        $name = trim((string) ($options['name'] ?? $template->getName()));
        if ($name === '') {
            throw new \InvalidArgumentException('name ist erforderlich');
        }

        $inputByType = $this->indexComponentInput($options['components'] ?? []);
        $acquiredOn = !empty($options['purchase_date'])
            ? new \DateTime((string) $options['purchase_date'])
            : new \DateTime();
        $year = $acquiredOn->format('Y');

        $supplier = null;
        if (!empty($options['supplier_id'])) {
            $supplier = $this->entityManager->find(Address::class, (string) $options['supplier_id']);
        }
        if ($supplier === null) {
            $supplier = $template->getSupplierCompany()->getSupplierAddress();
        }

        $category = null;
        if (!empty($options['category_id'])) {
            $category = $this->entityManager->find(Category::class, (string) $options['category_id']);
            if ($category && $category->getDepartmentId() !== $departmentId) {
                $category = null;
            }
        }

        $storageAddress = null;
        if (!empty($options['storage_address_id'])) {
            $storageAddress = $this->entityManager->find(Address::class, (string) $options['storage_address_id']);
            if ($storageAddress && $storageAddress->getDepartmentId() !== $departmentId) {
                $storageAddress = null;
            }
        }

        $this->entityManager->beginTransaction();

        try {
            $comboMaterial = new MaterialItem();
            $comboMaterial->setId(IdGenerator::generate());
            $comboMaterial->setDepartment($department);
            $comboMaterial->setName($name);
            $comboMaterial->setDescription($template->getDescription());
            $comboMaterial->setMaterialType($creationMode);
            $comboMaterial->setTrackingType('serialized');
            $comboMaterial->setIsContainer(true);
            $comboMaterial->setComboStatus('draft');
            $comboMaterial->setTentType($template->getTentType());
            $comboMaterial->setTentCapacity($template->getCapacity());
            $comboMaterial->setManufacturer($template->getManufacturer());
            $comboMaterial->setModel($template->getModel());
            if ($category) {
                $comboMaterial->setCategory($category);
            }
            if ($storageAddress) {
                $comboMaterial->setStorageAddress($storageAddress);
            }
            $this->entityManager->persist($comboMaterial);

            $comboMainBatch = null;
            if ($isPhysicalCombo) {
                $comboMainBatch = new MaterialBatch();
                $comboMainBatch->setId(IdGenerator::generate13('ba', $year));
                $comboMainBatch->setMaterialItem($comboMaterial);
                $comboMainBatch->setQty(1);
                $comboMainBatch->setIsInitial(true);
                $comboMainBatch->setBatchType('initial');
                $comboMainBatch->setAcquiredOn($acquiredOn);
                if (!empty($options['serial_number'])) {
                    $comboMainBatch->setSerialNumber(trim((string) $options['serial_number']));
                }
                if ($supplier instanceof Address) {
                    $comboMainBatch->setSupplier($supplier);
                }
                $this->entityManager->persist($comboMainBatch);
            }

            $createdArticles = [];
            $sortOrder = 0;

            foreach ($template->getComponents() as $tplComp) {
                if (!$tplComp instanceof SupplierMaterialTemplateComponent) {
                    continue;
                }
                $article = $this->importComponent(
                    $tplComp,
                    $template,
                    $department,
                    $comboMaterial,
                    $isPhysicalCombo,
                    $isVirtualCombo,
                    $inputByType,
                    $acquiredOn,
                    $year,
                    $supplier,
                    $category,
                    $storageAddress,
                    $sortOrder
                );
                if ($article !== null) {
                    $createdArticles[] = $article;
                }
            }

            $this->publicCodeService->ensureMaterialPublicCode($comboMaterial, null);
            if ($comboMainBatch instanceof MaterialBatch && $comboMainBatch->getSerialNumber()) {
                $this->publicCodeService->ensureBatchPublicCode($comboMainBatch, null);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return [
                'combo_material_id' => $comboMaterial->getId(),
                'combo_material_name' => $comboMaterial->getName(),
                'combo_status' => $comboMaterial->getComboStatus(),
                'material_type' => $comboMaterial->getMaterialType(),
                'components' => $createdArticles,
            ];
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * @param array<string, array<string, mixed>> $inputByType
     *
     * @return array<string, mixed>|null
     */
    private function importComponent(
        SupplierMaterialTemplateComponent $tplComp,
        SupplierMaterialTemplate $template,
        Department $department,
        MaterialItem $comboMaterial,
        bool $isPhysicalCombo,
        bool $isVirtualCombo,
        array $inputByType,
        \DateTime $acquiredOn,
        string $year,
        ?Address $supplier,
        ?Category $category,
        ?Address $storageAddress,
        int &$sortOrder,
    ): ?array {
        $compType = $tplComp->getComponentType();
        $compName = $this->buildComponentName($tplComp, $template);
        $requiredQty = $tplComp->getRequiredQty();
        $tracking = $tplComp->getTracking();
        $isOptional = $tplComp->getIsOptional();
        $componentSource = $tplComp->getComponentSource();
        $input = $inputByType[$compType] ?? null;

        if ($isOptional && $input === null) {
            return null;
        }

        $qty = (int) ($input['qty'] ?? $requiredQty);
        if ($isOptional && $tracking === 'bulk' && $qty <= 0) {
            return null;
        }

        $serialNumber = trim((string) ($input['serial_number'] ?? ''));
        if ($isOptional && $tracking === 'serialized' && $serialNumber === '') {
            return null;
        }

        if ($tracking === 'serialized' && $serialNumber !== '' && !$isVirtualCombo) {
            $this->assertSerialAvailable($department->getId(), [$serialNumber]);
        }

        $componentMaterial = new MaterialItem();
        $componentMaterial->setId(IdGenerator::generate());
        $componentMaterial->setDepartment($department);
        $componentMaterial->setName($compName);
        $componentMaterial->setMaterialType('physical');
        $componentMaterial->setTrackingType($tracking);
        $componentMaterial->setManufacturer($template->getManufacturer());
        if ($category) {
            $componentMaterial->setCategory($category);
        }
        if ($storageAddress) {
            $componentMaterial->setStorageAddress($storageAddress);
        }
        $this->entityManager->persist($componentMaterial);

        $componentBatch = null;
        if (!$isVirtualCombo) {
            if ($tracking === 'serialized') {
                $componentBatch = new MaterialBatch();
                $componentBatch->setId(IdGenerator::generate13('ba', $year));
                $componentBatch->setMaterialItem($componentMaterial);
                $componentBatch->setQty(1);
                $componentBatch->setIsInitial(true);
                $componentBatch->setBatchType('initial');
                $componentBatch->setAcquiredOn($acquiredOn);
                if ($serialNumber !== '') {
                    $componentBatch->setSerialNumber($serialNumber);
                }
                if ($supplier instanceof Address) {
                    $componentBatch->setSupplier($supplier);
                }
                $this->entityManager->persist($componentBatch);
                $qty = 1;
            } else {
                $componentBatch = new MaterialBatch();
                $componentBatch->setId(IdGenerator::generate13('ba', $year));
                $componentBatch->setMaterialItem($componentMaterial);
                $componentBatch->setQty(max(1, $qty));
                $componentBatch->setIsInitial(true);
                $componentBatch->setBatchType('initial');
                $componentBatch->setAcquiredOn($acquiredOn);
                if ($supplier instanceof Address) {
                    $componentBatch->setSupplier($supplier);
                }
                $this->entityManager->persist($componentBatch);
            }
        }

        $comboComp = new MaterialComboComponent();
        $comboComp->setId(IdGenerator::generate13('cc'));
        $comboComp->setParentMaterial($comboMaterial);
        $comboComp->setComponentMaterial($componentMaterial);
        $comboComp->setQty($qty);
        $comboComp->setComponentRole($compType);
        $comboComp->setIsOptional($isOptional);
        $comboComp->setComponentSource($componentSource === 'self_provided' ? 'self_provided' : 'stock');
        $comboComp->setSortOrder($sortOrder++);

        if ($tracking === 'bulk') {
            $comboComp->setAssignmentMode('bulk');
        } elseif ($isPhysicalCombo) {
            $comboComp->setAssignmentMode('fixed');
            if ($componentBatch) {
                $comboComp->setComponentBatch($componentBatch);
            }
        } else {
            $comboComp->setAssignmentMode('on_issue');
        }

        $this->entityManager->persist($comboComp);

        if ($componentBatch && $componentBatch->getSerialNumber()) {
            $this->publicCodeService->ensureBatchPublicCode($componentBatch, null);
        }

        return [
            'id' => $componentMaterial->getId(),
            'name' => $componentMaterial->getName(),
            'component_type' => $compType,
            'batch_id' => $componentBatch?->getId(),
            'serial_number' => $componentBatch?->getSerialNumber(),
            'qty' => $qty,
        ];
    }

    private function buildComponentName(
        SupplierMaterialTemplateComponent $tplComp,
        SupplierMaterialTemplate $template,
    ): string {
        $compName = $tplComp->getName();
        if ($tplComp->getIsGeneric()) {
            return $compName;
        }

        $manufacturer = $template->getManufacturer() ?? '';
        $model = $template->getModel() ?? '';
        $nameLower = mb_strtolower($compName);

        if ($model && !str_contains($nameLower, mb_strtolower($model))) {
            $compName .= ' ' . $model;
            $nameLower = mb_strtolower($compName);
        }
        if ($manufacturer && !str_contains($nameLower, mb_strtolower($manufacturer))) {
            $compName .= ' ' . $manufacturer;
        }

        return $compName;
    }

    /** @param mixed $components @return array<string, array<string, mixed>> */
    private function indexComponentInput(mixed $components): array
    {
        if (!\is_array($components)) {
            return [];
        }
        $map = [];
        foreach ($components as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            $type = trim((string) ($entry['component_type'] ?? ''));
            if ($type !== '') {
                $map[$type] = $entry;
            }
        }

        return $map;
    }

    /** @param list<string> $serialNumbers */
    private function assertSerialAvailable(string $departmentId, array $serialNumbers): void
    {
        if ($serialNumbers === []) {
            return;
        }
        if (\count(array_unique($serialNumbers)) !== \count($serialNumbers)) {
            throw new \InvalidArgumentException('Seriennummern enthalten Duplikate');
        }

        $existing = $this->entityManager->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(MaterialBatch::class, 'b')
            ->innerJoin('b.materialItem', 'm')
            ->where('m.departmentId = :departmentId')
            ->andWhere('b.serialNumber IN (:serials)')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('serials', $serialNumbers)
            ->getQuery()
            ->getSingleScalarResult();

        if ((int) $existing > 0) {
            throw new \InvalidArgumentException('Mindestens eine Seriennummer existiert bereits im Department');
        }
    }
}
