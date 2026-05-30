<?php

namespace App\Controller;

use App\Entity\MaterialTemplate;
use App\Entity\MaterialTemplateComponent;
use App\Entity\MaterialTemplateOption;
use App\Entity\MaterialTemplateOptionDelta;
use App\Entity\MaterialTemplateOptionGroup;
use App\Entity\MaterialTemplateRelatedAccessory;
use App\Entity\MaterialItem;
use App\Entity\MaterialBatch;
use App\Entity\MaterialComboComponent;
use App\Entity\MaterialComboOption;
use App\Entity\MaterialComboOptionDelta;
use App\Entity\MaterialComboOptionGroup;
use App\Entity\MaterialRelatedAccessory;
use App\Entity\Category;
use App\Entity\Department;
use App\Entity\Address;
use App\Entity\BatchStorageAllocation;
use App\Entity\StorageRack;
use App\Entity\StorageSlot;
use App\Service\Public\PublicCodeService;
use App\Service\TemplateImportExportService;
use App\Service\MaterialWizardSupplierService;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/templates', name: 'api_templates_')]
class TemplateController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PublicCodeService $publicCodeService,
        private TemplateImportExportService $templateImportExportService,
        private MaterialWizardSupplierService $materialWizardSupplierService,
    ) {}

    /**
     * Liste aller Vorlagen: Zentrale (global) + Department-eigene
     * 
     * Zeigt:
     * - Alle globalen Vorlagen (department_id IS NULL / scope='global')
     * - Department-eigene Vorlagen (department_id = angefragte ID)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $departmentId = $request->query->get('department_id');
        $scope = $request->query->get('scope');

        $qb = $this->entityManager->getRepository(MaterialTemplate::class)
            ->createQueryBuilder('t')
            ->leftJoin('t.components', 'c')
            ->addSelect('c')
            ->leftJoin('t.category', 'cat')
            ->addSelect('cat');

        if ($scope === 'global') {
            if (!$this->canEditGlobalTemplates()) {
                return new JsonResponse(['error' => 'Keine Berechtigung für zentrale Vorlagen'], 403);
            }
            $qb->where('t.departmentId IS NULL');
        } elseif (!$departmentId) {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        } else {
            // Zentrale (global) + Department-eigene Vorlagen laden
            $qb->where('t.departmentId IS NULL OR t.departmentId = :departmentId')
                ->setParameter('departmentId', $departmentId);
        }

        $templates = $qb
            ->orderBy('t.scope', 'ASC')
            ->addOrderBy('t.manufacturer', 'ASC', 'NULLS LAST')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Optional: nur aktive
        $activeOnly = $request->query->getBoolean('active_only', false);

        // Berechtigungen für den aktuellen User
        $canEditGlobal = $this->canEditGlobalTemplates();

        $result = [];
        foreach ($templates as $template) {
            if ($activeOnly && !$template->getIsActive()) {
                continue;
            }
            $data = $this->serializeTemplate($template, false);
            // Can-edit Flag: Zentrale Vorlagen nur für berechtigte User
            $data['can_edit'] = $template->isGlobal() ? $canEditGlobal : true;
            $result[] = $data;
        }

        return new JsonResponse($result);
    }

    /**
     * Lieferanten/Hersteller für Vorlagen-Picker (Address-Scope-Modell).
     */
    #[Route('/manufacturer-options', name: 'manufacturer_options', methods: ['GET'], priority: 5)]
    #[IsGranted('ROLE_USER')]
    public function manufacturerOptions(Request $request): JsonResponse
    {
        $scope = (string) $request->query->get('scope', 'department');
        $departmentId = trim((string) $request->query->get('department_id', ''));

        if ($scope === 'global') {
            if (!$this->canEditGlobalTemplates()) {
                return new JsonResponse(['error' => 'Keine Berechtigung für zentrale Vorlagen'], 403);
            }
            $addresses = $this->materialWizardSupplierService->listCatalogSuppliers();
        } else {
            if ($departmentId === '') {
                return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
            }
            $addresses = $this->materialWizardSupplierService->listForDepartment($departmentId);
        }

        return new JsonResponse([
            'options' => array_map(fn (Address $address) => [
                'id' => $address->getId(),
                'label' => $this->addressDisplayLabel($address),
                'scope' => $address->getScope(),
            ], $addresses),
        ]);
    }

    /**
     * JSON-Datei importieren (v4/v5-Format)
     * Body: { department_id?, scope?, templates_json, duplicate_action?, dry_run?, force? }
     */
    #[Route('/import', name: 'import', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function import(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiger JSON-Body'], 400);
        }

        if (!isset($data['templates_json']) || !is_array($data['templates_json'])) {
            return new JsonResponse(['error' => 'templates_json ist erforderlich'], 400);
        }

        $scope = (string) ($data['scope'] ?? 'department');
        $departmentId = isset($data['department_id']) ? trim((string) $data['department_id']) : null;

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $accessError = $this->templateImportExportService->assertCanImport($departmentId, $scope, $user);
        if ($accessError !== null) {
            return new JsonResponse(['error' => $accessError], 403);
        }

        $duplicateAction = (string) ($data['duplicate_action'] ?? 'skip');
        if (!in_array($duplicateAction, ['skip', 'update', 'create'], true)) {
            $duplicateAction = 'skip';
        }

        $dryRun = (bool) ($data['dry_run'] ?? false);

        try {
            $result = $this->templateImportExportService->importFromJson($data['templates_json'], [
                'scope' => $scope,
                'department_id' => $departmentId,
                'duplicate_action' => $duplicateAction,
                'dry_run' => $dryRun,
                'force' => (bool) ($data['force'] ?? false),
            ]);

            if (!empty($result['error'])) {
                return new JsonResponse(['error' => $result['error']], 400);
            }

            $status = $dryRun ? 200 : ($result['success'] ? 201 : 207);

            return new JsonResponse($result, $status);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Import: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Vorlagen als v5-JSON exportieren
     * Query: scope=global|department, department_id?, manufacturer?
     */
    #[Route('/export', name: 'export', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function export(Request $request): JsonResponse
    {
        $scope = (string) ($request->query->get('scope', 'department'));
        $departmentId = trim((string) ($request->query->get('department_id', '')));
        $manufacturer = trim((string) ($request->query->get('manufacturer', '')));

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $accessError = $this->templateImportExportService->assertCanExport(
            $departmentId !== '' ? $departmentId : null,
            $scope,
            $user,
        );
        if ($accessError !== null) {
            return new JsonResponse(['error' => $accessError], 403);
        }

        try {
            $result = $this->templateImportExportService->exportToJson(
                $scope,
                $departmentId !== '' ? $departmentId : null,
                $manufacturer !== '' ? $manufacturer : null,
            );

            if (!empty($result['error'])) {
                return new JsonResponse(['error' => $result['error']], 404);
            }

            return new JsonResponse($result);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'Export fehlgeschlagen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Einzelne Vorlage mit Komponenten laden
     */
    #[Route('/{id}', name: 'get', methods: ['GET'], requirements: ['id' => '[a-zA-Z0-9]+'])]
    #[IsGranted('ROLE_USER')]
    public function get(string $id, Request $request): JsonResponse
    {
        $template = $this->entityManager->getRepository(MaterialTemplate::class)->find($id);

        if (!$template) {
            return new JsonResponse(['error' => 'Vorlage nicht gefunden'], 404);
        }

        $departmentId = trim((string) $request->query->get('department_id', ''));
        $data = $this->serializeTemplate(
            $template,
            true,
            $departmentId !== '' ? $departmentId : null,
        );
        $data['can_edit'] = $template->isGlobal() ? $this->canEditGlobalTemplates() : true;

        return new JsonResponse($data);
    }

    /**
     * Neue Vorlage erstellen
     * 
     * - Mit department_id → Department-eigene Vorlage (scope=department)
     * - Ohne department_id → Zentrale Vorlage (scope=global), nur für berechtigte User
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'])) {
            return new JsonResponse(['error' => 'name ist erforderlich'], 400);
        }

        $department = null;
        $scope = $data['scope'] ?? 'department';

        if ($scope === 'global') {
            // Zentrale Vorlage: Nur berechtigte User
            if (!$this->canEditGlobalTemplates()) {
                return new JsonResponse(['error' => 'Keine Berechtigung für zentrale Vorlagen'], 403);
            }
        } else {
            // Department-eigene Vorlage: department_id erforderlich
            if (!isset($data['department_id'])) {
                return new JsonResponse(['error' => 'department_id ist erforderlich für Department-Vorlagen'], 400);
            }
            $department = $this->entityManager->getRepository(Department::class)
                ->find($data['department_id']);
            if (!$department) {
                return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
            }
        }

        try {
            $template = new MaterialTemplate();
            $template->setId(IdGenerator::generate());
            $template->setDepartment($department);
            $template->setScope($scope);
            $template->setName($data['name']);

            if (isset($data['description'])) {
                $template->setDescription($data['description']);
            }
            $this->applyManufacturerFields($template, $data);
            if (isset($data['template_kind'])) {
                $template->setTemplateKind($this->nullableString($data['template_kind']));
            }
            if (isset($data['template_domain'])) {
                $template->setTemplateDomain($this->nullableString($data['template_domain']));
            }
            if (isset($data['model'])) {
                $template->setModel($data['model']);
            }
            if (isset($data['material_type'])) {
                $template->setMaterialType($data['material_type']);
            }
            if (isset($data['tent_type'])) {
                $template->setTentType($data['tent_type']);
            }
            if (isset($data['capacity'])) {
                $template->setCapacity((int) $data['capacity']);
            }
            if (isset($data['source'])) {
                $template->setSource($data['source']);
            }
            if (isset($data['is_active'])) {
                $template->setIsActive((bool) $data['is_active']);
            }

            // Kategorie
            if (isset($data['category_id']) && $data['category_id']) {
                $category = $this->entityManager->getRepository(Category::class)
                    ->find($data['category_id']);
                if ($category) {
                    $template->setCategory($category);
                }
            }

            // Komponenten
            if (isset($data['components']) && is_array($data['components'])) {
                foreach ($data['components'] as $index => $compData) {
                    $component = $this->createComponent($compData, $index);
                    $template->addComponent($component);
                }
            }

            // Verwandtes Zubehör (Empfehlung, kein Stücklisten-Teil)
            if (isset($data['related_accessories']) && is_array($data['related_accessories'])) {
                foreach ($data['related_accessories'] as $index => $accData) {
                    $template->addRelatedAccessory($this->createTemplateAccessory($accData, $index));
                }
            }

            $this->entityManager->persist($template);
            $this->entityManager->flush();

            // Options-Gruppen/Optionen (Weg B, Paket 6) – nach dem ersten Flush (Template-Id steht).
            $this->applyTemplateOptions($template, $data);
            $this->entityManager->flush();

            return new JsonResponse($this->serializeTemplate($template, true), 201);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Erstellen der Vorlage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vorlage aktualisieren
     */
    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $id, Request $request): JsonResponse
    {
        $template = $this->entityManager->getRepository(MaterialTemplate::class)->find($id);

        if (!$template) {
            return new JsonResponse(['error' => 'Vorlage nicht gefunden'], 404);
        }

        // Bearbeitungsschutz: Zentrale Vorlagen nur für berechtigte User
        if ($template->isGlobal() && !$this->canEditGlobalTemplates()) {
            return new JsonResponse(['error' => 'Keine Berechtigung zum Bearbeiten dieser Vorlage'], 403);
        }

        $data = json_decode($request->getContent(), true);

        try {
            if (isset($data['name'])) {
                $template->setName($data['name']);
            }
            if (isset($data['description'])) {
                $template->setDescription($data['description']);
            }
            $this->applyManufacturerFields($template, $data);
            if (array_key_exists('template_kind', $data)) {
                $template->setTemplateKind($this->nullableString($data['template_kind']));
            }
            if (array_key_exists('template_domain', $data)) {
                $template->setTemplateDomain($this->nullableString($data['template_domain']));
            }
            if (isset($data['model'])) {
                $template->setModel($data['model']);
            }
            if (isset($data['material_type'])) {
                $template->setMaterialType($data['material_type']);
            }
            if (isset($data['tent_type'])) {
                $template->setTentType($data['tent_type']);
            }
            if (array_key_exists('capacity', $data)) {
                $template->setCapacity($data['capacity'] !== null ? (int) $data['capacity'] : null);
            }
            if (isset($data['source'])) {
                $template->setSource($data['source']);
            }
            if (isset($data['is_active'])) {
                $template->setIsActive((bool) $data['is_active']);
            }

            // Kategorie
            if (array_key_exists('category_id', $data)) {
                if ($data['category_id']) {
                    $category = $this->entityManager->getRepository(Category::class)
                        ->find($data['category_id']);
                    if ($category) {
                        $template->setCategory($category);
                    }
                } else {
                    $template->setCategory(null);
                }
            }

            // Komponenten ersetzen (wenn mitgeliefert)
            if (isset($data['components']) && is_array($data['components'])) {
                // Alle bestehenden entfernen
                foreach ($template->getComponents()->toArray() as $existing) {
                    $template->removeComponent($existing);
                    $this->entityManager->remove($existing);
                }

                // Neue anlegen
                foreach ($data['components'] as $index => $compData) {
                    $component = $this->createComponent($compData, $index);
                    $template->addComponent($component);
                }
            }

            // Verwandtes Zubehör ersetzen (wenn mitgeliefert)
            if (isset($data['related_accessories']) && is_array($data['related_accessories'])) {
                foreach ($template->getRelatedAccessories()->toArray() as $existing) {
                    $template->removeRelatedAccessory($existing);
                    $this->entityManager->remove($existing);
                }
                foreach ($data['related_accessories'] as $index => $accData) {
                    $template->addRelatedAccessory($this->createTemplateAccessory($accData, $index));
                }
            }

            // Options-Gruppen/Optionen (Weg B, Paket 6) ersetzen.
            $this->applyTemplateOptions($template, $data);

            $template->updateTimestamps();
            $this->entityManager->flush();

            return new JsonResponse($this->serializeTemplate($template, true));

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Aktualisieren der Vorlage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vorlage löschen
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $id): JsonResponse
    {
        $template = $this->entityManager->getRepository(MaterialTemplate::class)->find($id);

        if (!$template) {
            return new JsonResponse(['error' => 'Vorlage nicht gefunden'], 404);
        }

        // Bearbeitungsschutz: Zentrale Vorlagen nur für berechtigte User
        if ($template->isGlobal() && !$this->canEditGlobalTemplates()) {
            return new JsonResponse(['error' => 'Keine Berechtigung zum Löschen dieser Vorlage'], 403);
        }

        // Komponenten werden per CASCADE gelöscht
        $this->entityManager->remove($template);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * Material aus Vorlage erstellen
     * 
     * Unterstützt 3 Erstellungsmodi (creation_mode):
     * - individual:      Einzelartikel erstellen/ergänzen (kein Combo)
     * - physical_combo:  Physische Kombo (feste Einheit, name Pflicht)
     * - virtual_combo:   Virtuelle Kombo (Planungsgruppe)
     */
    #[Route('/{id}/create-material', name: 'create_material', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createMaterial(string $id, Request $request): JsonResponse
    {
        $template = $this->entityManager->getRepository(MaterialTemplate::class)->find($id);
        if (!$template) {
            return new JsonResponse(['error' => 'Vorlage nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);

        $creationMode = $data['creation_mode'] ?? 'physical_combo';
        $isIndividual = $creationMode === 'individual';
        $isPhysicalCombo = $creationMode === 'physical_combo';
        $isVirtualCombo = $creationMode === 'virtual_combo';

        // Name ist Pflicht bei Kombo-Modi
        if (!$isIndividual && empty($data['name'])) {
            return new JsonResponse(['error' => 'name ist erforderlich für Kombo-Erstellung'], 400);
        }

        // Department: aus Request oder aus Template (globale Templates haben kein Department)
        $department = null;
        if (isset($data['department_id']) && $data['department_id']) {
            $department = $this->entityManager->getRepository(Department::class)
                ->find($data['department_id']);
        }
        if (!$department) {
            $department = $template->getDepartment();
        }
        if (!$department) {
            return new JsonResponse(['error' => 'department_id ist erforderlich (Template hat kein Department)'], 400);
        }

        $acquiredOnStr = $data['purchase_date'] ?? date('Y-m-d');
        $acquiredOn = new \DateTime($acquiredOnStr);
        $year = $acquiredOn->format('Y');

        // Supplier (optional, geteilt für alle neuen Batches)
        $supplier = null;
        if (isset($data['supplier_id']) && $data['supplier_id']) {
            $supplier = $this->entityManager->getRepository(Address::class)
                ->find($data['supplier_id']);
        }

        // Storage Address
        $storageAddress = null;
        if (isset($data['storage_address_id']) && $data['storage_address_id']) {
            $storageAddress = $this->entityManager->getRepository(Address::class)
                ->find($data['storage_address_id']);
        }

        // Kategorie (aus Template oder Override)
        $category = $template->getCategory();
        if (isset($data['category_id']) && $data['category_id']) {
            $category = $this->entityManager->getRepository(Category::class)
                ->find($data['category_id']);
        }

        try {
            $this->entityManager->beginTransaction();

            $comboMaterial = null;
            /** @var MaterialBatch|null Haupt-Batch der physischen Kombination (Zelt als Einheit) */
            $comboMainBatch = null;
            /** @var array<string, MaterialItem> */
            $comboComponentMaterialsForPublicCode = [];
            /** @var array<string, MaterialBatch> */
            $comboComponentBatchesForPublicCode = [];

            // ══════════════════════════════════════════════
            // Combo-Modi: Combo-Material (das Zelt) erstellen
            // ══════════════════════════════════════════════
            if (!$isIndividual) {
                $comboMaterial = new MaterialItem();
                $comboMaterial->setId(IdGenerator::generate());
                $comboMaterial->setDepartment($department);
                $comboMaterial->setName($data['name']);
                $comboMaterial->setDescription($data['description'] ?? $template->getDescription());
                $comboMaterial->setMaterialType($creationMode); // physical_combo oder virtual_combo
                $comboMaterial->setTrackingType('serialized');
                $comboMaterial->setIsContainer(true);
                // Aus Vorlage erstellte Kombo startet als Entwurf (Detail-Tab → fertigstellen).
                $comboMaterial->setComboStatus('draft');
                $comboMaterial->setTentType($data['tent_type'] ?? $template->getTentType());
                $comboMaterial->setTentCapacity($data['tent_capacity'] ?? $template->getCapacity());
                $comboMaterial->setManufacturer($data['manufacturer'] ?? $template->getManufacturer());
                $comboMaterial->setModel($data['model'] ?? $template->getModel());

                if ($category) {
                    $comboMaterial->setCategory($category);
                }
                if ($storageAddress) {
                    $comboMaterial->setStorageAddress($storageAddress);
                }
                $this->entityManager->persist($comboMaterial);

                // Für physical_combo: Eigenen Batch erstellen (das Zelt als Einheit = 1 Stk.)
                if ($isPhysicalCombo) {
                    $comboMainBatch = new MaterialBatch();
                    $comboMainBatch->setId(IdGenerator::generate13('ba', $year));
                    $comboMainBatch->setMaterialItem($comboMaterial);
                    $comboMainBatch->setQty(1);
                    $comboMainBatch->setIsInitial(true);
                    $comboMainBatch->setBatchType('initial');
                    $comboMainBatch->setAcquiredOn($acquiredOn);
                    if (isset($data['serial_number'])) {
                        $comboMainBatch->setSerialNumber($data['serial_number']);
                    }
                    if ($supplier) {
                        $comboMainBatch->setSupplier($supplier);
                    }
                    $this->entityManager->persist($comboMainBatch);

                    $allocRes = $this->allocateInitialPhysicalComboBatch($comboMainBatch, $department->getId(), $data);
                    if ($allocRes instanceof JsonResponse) {
                        $this->entityManager->rollback();
                        return $allocRes;
                    }
                }
            }

            // ══════════════════════════════════════════════
            // Komponenten verarbeiten
            // ══════════════════════════════════════════════
            $templateComponents = $template->getComponents();
            $inputComponents = $data['components'] ?? [];

            // Input-Komponenten als Map nach component_type indizieren
            $inputByType = [];
            foreach ($inputComponents as $inputComp) {
                $type = $inputComp['component_type'] ?? '';
                $inputByType[$type] = $inputComp;
            }

            $createdArticles = [];
            $sortOrder = 0;
            /** @var array<string, MaterialItem> $materialByComponentType Bindung component_type → konkretes Material (für Options-Auflösung) */
            $materialByComponentType = [];

            foreach ($templateComponents as $tplComp) {
                $compType = $tplComp->getComponentType();
                $compNameRaw = $tplComp->getName();
                $requiredQty = $tplComp->getRequiredQty();
                $tracking = $tplComp->getTracking(); // serialized oder bulk
                $isOptional = $tplComp->getIsOptional();
                $componentSource = $tplComp->getComponentSource(); // stock | self_provided

                // ── Artikelname zusammensetzen (eine zentrale Stelle) ──
                $compName = $this->buildExpectedComponentName($template, $tplComp);

                // Input-Daten für diese Komponente
                $input = $inputByType[$compType] ?? null;

                // Optionale Komponente ohne Input: überspringen
                if ($isOptional && !$input) {
                    continue;
                }

                if (!$isOptional && $input === null) {
                    $this->entityManager->rollback();

                    return new JsonResponse([
                        'error' => sprintf('Pflicht-Komponente "%s" fehlt in der Anfrage', $compType),
                    ], 422);
                }

                $mode = $input['mode'] ?? 'new'; // new oder existing

                // Optionale Komponente mit Menge 0 bzw. ohne SN: nichts anlegen (kein Artikel, keine Charge mit 0)
                if (!$isVirtualCombo && $isOptional && $input !== null) {
                    if ($tracking === 'bulk') {
                        $declaredQty = $input['qty'] ?? null;
                        if ($declaredQty !== null && (int) $declaredQty <= 0) {
                            continue;
                        }
                    }
                    if ($tracking === 'serialized' && $mode === 'new' && trim((string) ($input['serial_number'] ?? '')) === '') {
                        continue;
                    }
                }

                // ── Komponenten-MaterialItem suchen oder erstellen ──
                $componentMaterial = null;
                $isNewArticle = false;

                if ($mode === 'existing') {
                    $materialId = trim((string) ($input['material_id'] ?? ''));
                    if ($materialId === '') {
                        $this->entityManager->rollback();

                        return new JsonResponse([
                            'error' => sprintf(
                                'Komponente "%s": mode=existing erfordert material_id (erwartet: %s)',
                                $compType,
                                $compName,
                            ),
                        ], 422);
                    }
                    $componentMaterial = $this->entityManager->getRepository(MaterialItem::class)->find($materialId);
                    if (
                        !$componentMaterial
                        || $componentMaterial->getDepartmentId() !== $department->getId()
                        || $componentMaterial->getDeletedAt() !== null
                    ) {
                        $this->entityManager->rollback();

                        return new JsonResponse([
                            'error' => sprintf('Komponente "%s": Material nicht gefunden oder gehört nicht zum Department', $compType),
                        ], 422);
                    }
                } elseif ($mode === 'new') {
                    $isNewArticle = true;
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
                } else {
                    $this->entityManager->rollback();

                    return new JsonResponse([
                        'error' => sprintf('Komponente "%s": ungültiger mode "%s"', $compType, $mode),
                    ], 422);
                }

                // ── Batch erstellen (für individual + physical_combo) ──
                // Bei virtual_combo: keine Batches (wird bei Ausgabe zugewiesen)
                $componentBatch = null;
                $qty = $input['qty'] ?? $requiredQty;

                if ($isVirtualCombo) {
                    // Virtuelle Kombo: keine Batches erstellen, nur Verknüpfung
                    $qty = $requiredQty;
                } elseif ($mode === 'existing' && isset($input['batch_id'])) {
                    // Bestehenden Batch verwenden (serialisiert)
                    $componentBatch = $this->entityManager->getRepository(MaterialBatch::class)
                        ->find($input['batch_id']);
                    $qty = $componentBatch ? $componentBatch->getQty() : 1;
                } elseif ($mode === 'existing' && !isset($input['batch_id'])) {
                    // Existing bulk: kein Batch nötig, nur Mengen-Verknüpfung
                    $componentBatch = null;
                    $qty = $input['qty'] ?? $requiredQty;
                } elseif ($mode === 'new') {
                    if ($tracking === 'serialized') {
                        $componentBatch = new MaterialBatch();
                        $componentBatch->setId(IdGenerator::generate13('ba', $year));
                        $componentBatch->setMaterialItem($componentMaterial);
                        $componentBatch->setQty(1);
                        $componentBatch->setIsInitial(true);
                        $componentBatch->setBatchType('initial');
                        $componentBatch->setAcquiredOn($acquiredOn);
                        if (isset($input['serial_number'])) {
                            $componentBatch->setSerialNumber($input['serial_number']);
                        }
                        if (isset($input['unit_price'])) {
                            $componentBatch->setUnitPrice($input['unit_price']);
                        }
                        if ($supplier) {
                            $componentBatch->setSupplier($supplier);
                        }
                        $this->entityManager->persist($componentBatch);
                        $qty = 1;
                    } else {
                        // Bulk: eine Charge mit Menge — keine Seriennummer (SN nur bei serialisierten Artikeln)
                        $qtyInt = (int) $qty;
                        $componentBatch = new MaterialBatch();
                        $componentBatch->setId(IdGenerator::generate13('ba', $year));
                        $componentBatch->setMaterialItem($componentMaterial);
                        $componentBatch->setQty($qtyInt);
                        $componentBatch->setIsInitial(true);
                        $componentBatch->setBatchType('initial');
                        $componentBatch->setAcquiredOn($acquiredOn);
                        if (isset($input['unit_price'])) {
                            $componentBatch->setUnitPrice($input['unit_price']);
                        }
                        if ($supplier) {
                            $componentBatch->setSupplier($supplier);
                        }
                        $this->entityManager->persist($componentBatch);
                    }
                }

                // ── ComboComponent-Verknüpfung (nur bei Kombo-Modi) ──
                if (!$isIndividual && $comboMaterial) {
                    $comboComp = new MaterialComboComponent();
                    $comboComp->setId(IdGenerator::generate13('cc'));
                    $comboComp->setParentMaterial($comboMaterial);
                    $comboComp->setComponentMaterial($componentMaterial);
                    $comboComp->setQty((int)$qty);
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
                        // Virtual Combo: on_issue (wird bei Ausgabe zugewiesen)
                        $comboComp->setAssignmentMode('on_issue');
                    }

                    $this->entityManager->persist($comboComp);
                }

                if (!$isIndividual && $comboMaterial) {
                    $comboComponentMaterialsForPublicCode[$componentMaterial->getId()] = $componentMaterial;
                    if ($componentBatch && !$isVirtualCombo) {
                        $comboComponentBatchesForPublicCode[$componentBatch->getId()] = $componentBatch;
                    }
                }

                if ($compType !== '' && !isset($materialByComponentType[$compType])) {
                    $materialByComponentType[$compType] = $componentMaterial;
                }

                $createdArticles[] = [
                    'id' => $componentMaterial->getId(),
                    'name' => $componentMaterial->getName(),
                    'is_new' => $isNewArticle,
                    'tracking' => $tracking,
                    'batch_id' => $componentBatch?->getId(),
                    'serial_number' => $componentBatch?->getSerialNumber(),
                    'qty' => (int)$qty,
                ];
            }

            // ══════════════════════════════════════════════
            // Verwandtes Zubehör übertragen (Empfehlung, kein Stücklisten-Teil)
            // ══════════════════════════════════════════════
            if (!$isIndividual && $comboMaterial) {
                $accSort = 0;
                foreach ($template->getRelatedAccessories() as $tplAcc) {
                    $accName = $tplAcc->getName();
                    if (!$tplAcc->getIsGeneric()) {
                        $manufacturer = $template->getManufacturer() ?? '';
                        $model = $template->getModel() ?? '';
                        $nameLower = mb_strtolower($accName);
                        if ($model && !str_contains($nameLower, mb_strtolower($model))) {
                            $accName .= ' ' . $model;
                            $nameLower = mb_strtolower($accName);
                        }
                        if ($manufacturer && !str_contains($nameLower, mb_strtolower($manufacturer))) {
                            $accName .= ' ' . $manufacturer;
                        }
                    }

                    $accessoryMaterial = $this->entityManager->getRepository(MaterialItem::class)
                        ->findOneBy([
                            'departmentId' => $department->getId(),
                            'name' => $accName,
                            'deletedAt' => null,
                        ]);

                    if (!$accessoryMaterial) {
                        $accessoryMaterial = new MaterialItem();
                        $accessoryMaterial->setId(IdGenerator::generate());
                        $accessoryMaterial->setDepartment($department);
                        $accessoryMaterial->setName($accName);
                        $accessoryMaterial->setMaterialType('physical');
                        $accessoryMaterial->setManufacturer($template->getManufacturer());
                        if ($category) {
                            $accessoryMaterial->setCategory($category);
                        }
                        if ($storageAddress) {
                            $accessoryMaterial->setStorageAddress($storageAddress);
                        }
                        $this->entityManager->persist($accessoryMaterial);
                        $comboComponentMaterialsForPublicCode[$accessoryMaterial->getId()] = $accessoryMaterial;
                    }

                    $link = new MaterialRelatedAccessory();
                    $link->setId(IdGenerator::generate13Unique($this->entityManager, MaterialRelatedAccessory::class, 'ra'));
                    $link->setMaterial($comboMaterial);
                    $link->setAccessoryMaterial($accessoryMaterial);
                    $link->setSortOrder($accSort++);
                    $this->entityManager->persist($link);
                }
            }

            // ══════════════════════════════════════════════
            // Options-Gruppen/Optionen (Konfigurator, Weg B) – nur virtuelle Kombo, generisch → konkret binden
            // ══════════════════════════════════════════════
            if ($isVirtualCombo && $comboMaterial) {
                $this->resolveTemplateOptionsToCombo($template, $comboMaterial, $materialByComponentType, $department->getId());
            }

            $actorId = $this->getActorUserId();
            if (!$isIndividual && $comboMaterial) {
                $this->publicCodeService->ensureMaterialPublicCode($comboMaterial, $actorId);
            }
            if ($isPhysicalCombo && $comboMainBatch) {
                $this->publicCodeService->ensureBatchPublicCode($comboMainBatch, $actorId);
            }
            foreach ($comboComponentMaterialsForPublicCode as $mat) {
                $this->publicCodeService->ensureMaterialPublicCode($mat, $actorId);
            }
            foreach ($comboComponentBatchesForPublicCode as $b) {
                $bm = $b->getMaterialItem();
                if ($bm && $bm->getTrackingType() === 'serialized' && trim((string) $b->getSerialNumber()) === '') {
                    continue;
                }
                $this->publicCodeService->ensureBatchPublicCode($b, $actorId);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            // Response
            if ($isIndividual) {
                return new JsonResponse([
                    'success' => true,
                    'creation_mode' => 'individual',
                    'articles' => $createdArticles,
                    'template_id' => $template->getId(),
                    'template_name' => $template->getName(),
                ], 201);
            }

            return new JsonResponse([
                'success' => true,
                'creation_mode' => $creationMode,
                'material' => [
                    'id' => $comboMaterial->getId(),
                    'name' => $comboMaterial->getName(),
                    'material_type' => $comboMaterial->getMaterialType(),
                    'is_container' => $comboMaterial->getIsContainer(),
                    'tent_type' => $comboMaterial->getTentType(),
                    'tent_capacity' => $comboMaterial->getTentCapacity(),
                    'manufacturer' => $comboMaterial->getManufacturer(),
                ],
                'components' => $createdArticles,
                'template_id' => $template->getId(),
                'template_name' => $template->getName(),
            ], 201);

        } catch (\Exception $e) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
            return new JsonResponse([
                'error' => 'Fehler beim Erstellen des Materials: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========== Private Helpers ==========

    /**
     * Erstellt eine TemplateComponent aus Request-Daten
     */
    private function createComponent(array $compData, int $index): MaterialTemplateComponent
    {
        $comp = new MaterialTemplateComponent();
        $comp->setId(IdGenerator::generate());
        $comp->setComponentType($compData['component_type'] ?? $compData['type'] ?? 'unknown');
        $comp->setName($compData['name'] ?? 'Unbenannt');
        $comp->setRequiredQty(isset($compData['required_qty']) ? (int) $compData['required_qty'] : 1);
        $comp->setIsOptional($compData['is_optional'] ?? false);
        $comp->setIsGeneric($compData['is_generic'] ?? false);
        $comp->setTracking($compData['tracking'] ?? 'bulk');
        $comp->setComponentSource(($compData['component_source'] ?? null) === 'self_provided' ? 'self_provided' : 'stock');
        $comp->setSortOrder($compData['sort_order'] ?? $index);

        if (isset($compData['repair_types']) && is_array($compData['repair_types'])) {
            $comp->setRepairTypes($compData['repair_types']);
        }

        return $comp;
    }

    private function createTemplateAccessory(array $accData, int $index): MaterialTemplateRelatedAccessory
    {
        $acc = new MaterialTemplateRelatedAccessory();
        $acc->setId(IdGenerator::generate());
        $acc->setName($accData['name'] ?? 'Zubehör');
        $type = $accData['component_type'] ?? null;
        $acc->setComponentType(is_string($type) && trim($type) !== '' ? trim($type) : null);
        $acc->setIsGeneric($accData['is_generic'] ?? false);
        $acc->setSortOrder($accData['sort_order'] ?? $index);

        return $acc;
    }

    /**
     * Options-Gruppen + Optionen einer Vorlage (Weg B, Paket 6) ersetzen (replace-all), generisch über component_type.
     *
     * @param array<string, mixed> $data
     */
    private function applyTemplateOptions(MaterialTemplate $template, array $data): void
    {
        $hasGroups = array_key_exists('option_groups', $data) && is_array($data['option_groups']);
        $hasOptions = array_key_exists('options', $data) && is_array($data['options']);
        if (!$hasGroups && !$hasOptions) {
            return;
        }

        // Bestehende entfernen (Deltas → Optionen → Gruppen).
        $existingOptions = $this->entityManager->getRepository(MaterialTemplateOption::class)
            ->findBy(['templateId' => $template->getId()]);
        foreach ($existingOptions as $opt) {
            foreach ($this->entityManager->getRepository(MaterialTemplateOptionDelta::class)->findBy(['optionId' => $opt->getId()]) as $d) {
                $this->entityManager->remove($d);
            }
            $this->entityManager->remove($opt);
        }
        foreach ($this->entityManager->getRepository(MaterialTemplateOptionGroup::class)->findBy(['templateId' => $template->getId()]) as $g) {
            $this->entityManager->remove($g);
        }
        $this->entityManager->flush();

        // Gruppen anlegen, payload-id → reale id abbilden.
        $groupIdMap = [];
        foreach (($data['option_groups'] ?? []) as $index => $g) {
            $group = new MaterialTemplateOptionGroup();
            $group->setId(IdGenerator::generate());
            $group->setTemplate($template);
            $group->setName(trim((string) ($g['name'] ?? 'Gruppe')) ?: 'Gruppe');
            $st = (string) ($g['selection_type'] ?? 'exclusive');
            $group->setSelectionType(in_array($st, ['exclusive', 'multi', 'quantity'], true) ? $st : 'exclusive');
            $group->setMinSelect(max(0, (int) ($g['min_select'] ?? 0)));
            $group->setMaxSelect(isset($g['max_select']) && $g['max_select'] !== null ? max(0, (int) $g['max_select']) : null);
            $group->setSortOrder((int) ($g['sort_order'] ?? $index));
            $this->entityManager->persist($group);
            $payloadId = (string) ($g['id'] ?? $g['_key'] ?? $index);
            $groupIdMap[$payloadId] = $group->getId();
        }

        // Optionen + Deltas anlegen.
        foreach (($data['options'] ?? []) as $index => $o) {
            $option = new MaterialTemplateOption();
            $option->setId(IdGenerator::generate());
            $option->setTemplate($template);
            $option->setName(trim((string) ($o['name'] ?? 'Option')) ?: 'Option');
            $dm = (string) ($o['display_mode'] ?? 'toggle');
            $option->setDisplayMode($dm === 'group' ? 'group' : 'toggle');
            $option->setDefaultSelected((bool) ($o['default_selected'] ?? false));
            $option->setSortOrder((int) ($o['sort_order'] ?? $index));
            $gid = $o['option_group_id'] ?? null;
            if ($gid !== null && $gid !== '' && isset($groupIdMap[(string) $gid])) {
                $group = $this->entityManager->getRepository(MaterialTemplateOptionGroup::class)->find($groupIdMap[(string) $gid]);
                if ($group) {
                    $option->setOptionGroup($group);
                    $option->setDisplayMode('group');
                }
            }
            $this->entityManager->persist($option);

            $deltaSort = 0;
            foreach (($o['deltas'] ?? []) as $d) {
                $delta = new MaterialTemplateOptionDelta();
                $delta->setId(IdGenerator::generate());
                $delta->setOption($option);
                $delta->setComponentType(trim((string) ($d['component_type'] ?? '')) ?: 'unknown');
                $delta->setName(trim((string) ($d['name'] ?? '')) ?: (string) ($d['component_type'] ?? 'Teil'));
                $delta->setQtyDelta((int) ($d['qty_delta'] ?? 0));
                $delta->setTracking(($d['tracking'] ?? 'bulk') === 'serialized' ? 'serialized' : 'bulk');
                $delta->setComponentSource(($d['component_source'] ?? null) === 'self_provided' ? 'self_provided' : 'stock');
                $delta->setIsGeneric((bool) ($d['is_generic'] ?? false));
                $delta->setSortOrder((int) ($d['sort_order'] ?? $deltaSort++));
                $this->entityManager->persist($delta);
            }
        }
    }

    /**
     * „Vorlage → Material": löst die generischen Vorlagen-Optionen an konkrete Kombo-Optionen mit
     * material_item-Deltas. Bindung über component_type → erstelltes/gefundenes Material; fehlende überspringen.
     *
     * @param array<string, MaterialItem> $materialByComponentType
     */
    private function resolveTemplateOptionsToCombo(
        MaterialTemplate $template,
        MaterialItem $combo,
        array $materialByComponentType,
        string $departmentId,
    ): void {
        $tplGroups = $this->entityManager->getRepository(MaterialTemplateOptionGroup::class)
            ->findBy(['templateId' => $template->getId()], ['sortOrder' => 'ASC']);
        $tplOptions = $this->entityManager->getRepository(MaterialTemplateOption::class)
            ->findBy(['templateId' => $template->getId()], ['sortOrder' => 'ASC']);
        if ($tplGroups === [] && $tplOptions === []) {
            return;
        }

        $groupIdMap = [];
        foreach ($tplGroups as $tg) {
            $group = new MaterialComboOptionGroup();
            $group->setId(IdGenerator::generate13('og'));
            $group->setMaterialItem($combo);
            $group->setName($tg->getName());
            $group->setSelectionType($tg->getSelectionType());
            $group->setMinSelect($tg->getMinSelect());
            $group->setMaxSelect($tg->getMaxSelect());
            $group->setSortOrder($tg->getSortOrder());
            $this->entityManager->persist($group);
            $groupIdMap[(string) $tg->getId()] = $group;
        }

        foreach ($tplOptions as $to) {
            $tplDeltas = $this->entityManager->getRepository(MaterialTemplateOptionDelta::class)
                ->findBy(['optionId' => $to->getId()], ['sortOrder' => 'ASC']);

            // Konkrete Delta-Zeilen ermitteln (fehlende component_types überspringen).
            $resolvedDeltas = [];
            foreach ($tplDeltas as $td) {
                $material = $this->resolveComponentMaterial($td->getComponentType(), $td->getName(), $materialByComponentType, $departmentId);
                if ($material === null) {
                    continue;
                }
                $resolvedDeltas[] = [$td, $material];
            }
            if ($resolvedDeltas === []) {
                continue; // Option ohne bindbare Teile → überspringen
            }

            $option = new MaterialComboOption();
            $option->setId(IdGenerator::generate13('op'));
            $option->setMaterialItem($combo);
            $option->setName($to->getName());
            $option->setDisplayMode($to->getDisplayMode());
            $option->setDefaultSelected($to->getDefaultSelected());
            $option->setSortOrder($to->getSortOrder());
            if ($to->getOptionGroupId() !== null && isset($groupIdMap[(string) $to->getOptionGroupId()])) {
                $option->setOptionGroup($groupIdMap[(string) $to->getOptionGroupId()]);
            }
            $this->entityManager->persist($option);

            foreach ($resolvedDeltas as [$td, $material]) {
                $delta = new MaterialComboOptionDelta();
                $delta->setId(IdGenerator::generate13('dt'));
                $delta->setOption($option);
                $delta->setComponentMaterial($material);
                $delta->setQtyDelta($td->getQtyDelta());
                $delta->setAssignmentMode($td->getTracking() === 'serialized' ? 'on_issue' : 'bulk');
                $delta->setTracking($td->getTracking());
                $delta->setComponentSource($td->getComponentSource());
                $delta->setSortOrder($td->getSortOrder());
                $this->entityManager->persist($delta);
            }
        }
    }

    /**
     * Findet ein konkretes Material für einen generischen component_type (erst Map aus Basis-Stückliste, dann per Name).
     *
     * @param array<string, MaterialItem> $materialByComponentType
     */
    private function resolveComponentMaterial(
        string $componentType,
        string $name,
        array $materialByComponentType,
        string $departmentId,
        ?string $expectedName = null,
    ): ?MaterialItem {
        if ($componentType !== '' && isset($materialByComponentType[$componentType])) {
            return $materialByComponentType[$componentType];
        }
        $searchName = trim($expectedName ?? $name);
        if ($searchName !== '') {
            $found = $this->findMaterialsByExactName($departmentId, $searchName);
            if (count($found) === 1) {
                return $found[0];
            }
        }

        return null;
    }

    /**
     * Erwarteter Materialname für eine Vorlagen-Komponente (eine zentrale Stelle).
     */
    private function buildExpectedComponentName(MaterialTemplate $template, MaterialTemplateComponent $comp): string
    {
        $compName = $comp->getName();
        if ($comp->getIsGeneric()) {
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

    /**
     * @return MaterialItem[]
     */
    private function findMaterialsByExactName(string $departmentId, string $name): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(MaterialItem::class, 'm')
            ->where('m.departmentId = :dept')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('LOWER(m.name) = LOWER(:name)')
            ->setParameter('dept', $departmentId)
            ->setParameter('name', $name)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{expected_name: string, match_state: string, matched_material_id: ?string, candidates: array<int, array{id: string, name: string}>}
     */
    private function resolveComponentMatchFields(
        MaterialTemplate $template,
        MaterialTemplateComponent $comp,
        string $departmentId,
    ): array {
        $expectedName = $this->buildExpectedComponentName($template, $comp);
        $matches = $this->findMaterialsByExactName($departmentId, $expectedName);
        $candidates = array_map(
            fn (MaterialItem $m) => ['id' => $m->getId(), 'name' => $m->getName()],
            $matches,
        );

        if (count($matches) === 1) {
            return [
                'expected_name' => $expectedName,
                'match_state' => 'found',
                'matched_material_id' => $matches[0]->getId(),
                'candidates' => $candidates,
            ];
        }

        if (count($matches) > 1) {
            return [
                'expected_name' => $expectedName,
                'match_state' => 'ambiguous',
                'matched_material_id' => null,
                'candidates' => $candidates,
            ];
        }

        return [
            'expected_name' => $expectedName,
            'match_state' => 'missing',
            'matched_material_id' => null,
            'candidates' => [],
        ];
    }

    /**
     * Serialisiert ein Template für die JSON-Response
     */
    private function serializeTemplate(MaterialTemplate $template, bool $includeComponents, ?string $departmentId = null): array
    {
        $data = [
            'id' => $template->getId(),
            'department_id' => $template->getDepartmentId(),
            'scope' => $template->getScope(),
            'is_global' => $template->isGlobal(),
            'name' => $template->getName(),
            'description' => $template->getDescription(),
            'manufacturer' => $template->getManufacturer(),
            'manufacturer_address_id' => $template->getManufacturerAddressId(),
            'template_kind' => $template->getTemplateKind(),
            'template_domain' => $template->getTemplateDomain(),
            'model' => $template->getModel(),
            'category' => $template->getCategory() ? [
                'id' => $template->getCategory()->getId(),
                'name' => $template->getCategory()->getName(),
            ] : null,
            'material_type' => $template->getMaterialType(),
            'tent_type' => $template->getTentType(),
            'capacity' => $template->getCapacity(),
            'is_active' => $template->getIsActive(),
            'source' => $template->getSource(),
            'component_count' => $template->getTotalComponentCount(),
            'created_at' => $template->getCreatedAt()->format('Y-m-d\TH:i:s'),
            'updated_at' => $template->getUpdatedAt()->format('Y-m-d\TH:i:s'),
        ];

        if ($includeComponents) {
            $data['components'] = [];
            $missingRequired = [];
            foreach ($template->getComponents() as $comp) {
                $row = [
                    'id' => $comp->getId(),
                    'component_type' => $comp->getComponentType(),
                    'name' => $comp->getName(),
                    'required_qty' => $comp->getRequiredQty(),
                    'is_optional' => $comp->getIsOptional(),
                    'is_generic' => $comp->getIsGeneric(),
                    'tracking' => $comp->getTracking(),
                    'component_source' => $comp->getComponentSource(),
                    'repair_types' => $comp->getRepairTypes(),
                    'sort_order' => $comp->getSortOrder(),
                ];
                if ($departmentId !== null) {
                    $row = array_merge($row, $this->resolveComponentMatchFields($template, $comp, $departmentId));
                    if (!$comp->getIsOptional() && ($row['match_state'] ?? '') === 'missing') {
                        $missingRequired[] = [
                            'component_type' => $comp->getComponentType(),
                            'name' => $comp->getName(),
                            'expected_name' => $row['expected_name'],
                        ];
                    }
                }
                $data['components'][] = $row;
            }
            if ($departmentId !== null) {
                $data['missing_required_components'] = $missingRequired;
            }

            $data['related_accessories'] = [];
            foreach ($template->getRelatedAccessories() as $acc) {
                $data['related_accessories'][] = [
                    'id' => $acc->getId(),
                    'name' => $acc->getName(),
                    'component_type' => $acc->getComponentType(),
                    'is_generic' => $acc->getIsGeneric(),
                    'sort_order' => $acc->getSortOrder(),
                ];
            }

            // Options-Gruppen + Optionen (Weg B, Paket 6)
            $data['option_groups'] = [];
            $groups = $this->entityManager->getRepository(MaterialTemplateOptionGroup::class)
                ->findBy(['templateId' => $template->getId()], ['sortOrder' => 'ASC']);
            foreach ($groups as $g) {
                $data['option_groups'][] = [
                    'id' => $g->getId(),
                    'template_id' => $g->getTemplateId(),
                    'name' => $g->getName(),
                    'selection_type' => $g->getSelectionType(),
                    'min_select' => $g->getMinSelect(),
                    'max_select' => $g->getMaxSelect(),
                    'sort_order' => $g->getSortOrder(),
                ];
            }

            $data['options'] = [];
            $options = $this->entityManager->getRepository(MaterialTemplateOption::class)
                ->findBy(['templateId' => $template->getId()], ['sortOrder' => 'ASC']);
            foreach ($options as $o) {
                $deltas = $this->entityManager->getRepository(MaterialTemplateOptionDelta::class)
                    ->findBy(['optionId' => $o->getId()], ['sortOrder' => 'ASC']);
                $data['options'][] = [
                    'id' => $o->getId(),
                    'template_id' => $o->getTemplateId(),
                    'option_group_id' => $o->getOptionGroupId(),
                    'name' => $o->getName(),
                    'display_mode' => $o->getDisplayMode(),
                    'default_selected' => $o->getDefaultSelected(),
                    'sort_order' => $o->getSortOrder(),
                    'deltas' => array_map(static fn (MaterialTemplateOptionDelta $d) => [
                        'id' => $d->getId(),
                        'option_id' => $d->getOptionId(),
                        'component_type' => $d->getComponentType(),
                        'name' => $d->getName(),
                        'qty_delta' => $d->getQtyDelta(),
                        'tracking' => $d->getTracking(),
                        'component_source' => $d->getComponentSource(),
                        'is_generic' => $d->getIsGeneric(),
                        'sort_order' => $d->getSortOrder(),
                    ], $deltas),
                ];
            }
        }

        return $data;
    }

    /**
     * Prüft ob der aktuelle User zentrale (globale) Vorlagen bearbeiten darf.
     * Erlaubt für: superadmin (sa), organisationschef (org), suborgchef (sub)
     */
    private function canEditGlobalTemplates(): bool
    {
        $user = $this->getUser();
        if (!$user) {
            return false;
        }

        // Symfony-Rollen prüfen
        if ($this->isGranted('ROLE_SUPERADMIN') || $this->isGranted('ROLE_ORGANISATIONSCHEF') || $this->isGranted('ROLE_SUBORGCHEF')) {
            return true;
        }

        return false;
    }

    /**
     * Physische Kombo aus Vorlage: initialen Batch im Gestell/Fach oder in einer Kiste verorten.
     *
     * @return JsonResponse|null null bei Erfolg
     */
    private function allocateInitialPhysicalComboBatch(MaterialBatch $batch, string $departmentId, array $data): ?JsonResponse
    {
        $containerBatchId = !empty($data['initial_container_batch_id']) ? (string) $data['initial_container_batch_id'] : null;
        $rackId = isset($data['initial_rack_id']) && $data['initial_rack_id'] !== '' ? (string) $data['initial_rack_id'] : null;
        $slotId = isset($data['initial_slot_id']) && $data['initial_slot_id'] !== '' ? (string) $data['initial_slot_id'] : null;

        if ($containerBatchId) {
            $containerBatch = $this->entityManager->getRepository(MaterialBatch::class)->find($containerBatchId);
            if (!$containerBatch || $containerBatch->getMaterialItem()->getDepartmentId() !== $departmentId) {
                return new JsonResponse(['error' => 'initial_container_batch_id ist ungültig'], 400);
            }
            $allocation = new BatchStorageAllocation();
            $allocation->setId(IdGenerator::generate13Unique($this->entityManager, BatchStorageAllocation::class, 'al'));
            $allocation->setBatch($batch);
            $allocation->setContainerBatch($containerBatch);
            $allocation->setQty(1);
            $allocation->setDepartmentId($departmentId);
            $batch->addAllocation($allocation);
            $this->entityManager->persist($allocation);

            return null;
        }

        if ($rackId && $slotId) {
            $rack = $this->entityManager->getRepository(StorageRack::class)->find($rackId);
            $slot = $this->entityManager->getRepository(StorageSlot::class)->find($slotId);
            if (!$rack || $rack->getDepartmentId() !== $departmentId) {
                return new JsonResponse(['error' => 'initial_rack_id ist ungültig'], 400);
            }
            if (!$slot || $slot->getRack()->getDepartmentId() !== $departmentId) {
                return new JsonResponse(['error' => 'initial_slot_id ist ungültig'], 400);
            }
            $batch->setRack($rack);
            $batch->setSlot($slot);

            return null;
        }

        return new JsonResponse([
            'error' => 'Für physische Kombination: Gestell/Fach oder Kiste (initial_rack_id + initial_slot_id bzw. initial_container_batch_id) ist erforderlich',
        ], 400);
    }

    private function getActorUserId(): ?string
    {
        $user = $this->getUser();

        return $user instanceof User ? $user->getId() : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyManufacturerFields(MaterialTemplate $template, array $data): void
    {
        if (array_key_exists('manufacturer_address_id', $data)) {
            $addressId = $data['manufacturer_address_id'];
            if ($addressId === null || $addressId === '') {
                $template->setManufacturerAddress(null);
                $template->setManufacturer(null);
            } else {
                $address = $this->entityManager->getRepository(Address::class)->find((string) $addressId);
                if (!$address || $address->isDeleted()) {
                    throw new \InvalidArgumentException('Hersteller-Adresse nicht gefunden');
                }
                $template->setManufacturerAddress($address);
                $template->setManufacturer($this->addressDisplayLabel($address));
            }

            return;
        }

        if (array_key_exists('manufacturer', $data)) {
            $template->setManufacturer($this->nullableString($data['manufacturer']));
        }
    }

    private function addressDisplayLabel(Address $address): string
    {
        $label = trim((string) ($address->getCompany() ?: $address->getName() ?: ''));

        return $label !== '' ? $label : (string) $address->getId();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
