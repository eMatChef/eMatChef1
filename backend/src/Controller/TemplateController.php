<?php

namespace App\Controller;

use App\Entity\MaterialTemplate;
use App\Entity\MaterialTemplateComponent;
use App\Entity\MaterialItem;
use App\Entity\MaterialBatch;
use App\Entity\MaterialComboComponent;
use App\Entity\Category;
use App\Entity\Department;
use App\Entity\Address;
use App\Entity\BatchStorageAllocation;
use App\Entity\StorageRack;
use App\Entity\StorageSlot;
use App\Service\Public\PublicCodeService;
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

        if (!$departmentId) {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        // Zentrale (global) + Department-eigene Vorlagen laden
        $templates = $this->entityManager->getRepository(MaterialTemplate::class)
            ->createQueryBuilder('t')
            ->leftJoin('t.components', 'c')
            ->addSelect('c')
            ->leftJoin('t.category', 'cat')
            ->addSelect('cat')
            ->where('t.departmentId IS NULL OR t.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('t.scope', 'ASC')
            ->addOrderBy('t.manufacturer', 'ASC')
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
     * Einzelne Vorlage mit Komponenten laden
     */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get(string $id): JsonResponse
    {
        $template = $this->entityManager->getRepository(MaterialTemplate::class)->find($id);

        if (!$template) {
            return new JsonResponse(['error' => 'Vorlage nicht gefunden'], 404);
        }

        $data = $this->serializeTemplate($template, true);
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
            if (isset($data['manufacturer'])) {
                $template->setManufacturer($data['manufacturer']);
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
            if (isset($data['reservation_mode'])) {
                $template->setReservationMode($data['reservation_mode']);
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

            $this->entityManager->persist($template);
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
            if (isset($data['manufacturer'])) {
                $template->setManufacturer($data['manufacturer']);
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
            if (isset($data['reservation_mode'])) {
                $template->setReservationMode($data['reservation_mode']);
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
     * - virtual_combo:   Virtuelle Kombo (Planungsgruppe, reservation_mode)
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
                $comboMaterial->setTentType($data['tent_type'] ?? $template->getTentType());
                $comboMaterial->setTentCapacity($data['tent_capacity'] ?? $template->getCapacity());
                $comboMaterial->setManufacturer($data['manufacturer'] ?? $template->getManufacturer());
                $comboMaterial->setModel($data['model'] ?? $template->getModel());

                // Reservation Mode: nur bei virtual_combo relevant
                if ($isVirtualCombo) {
                    $comboMaterial->setReservationMode($data['reservation_mode'] ?? $template->getReservationMode() ?? 'complete_only');
                } else {
                    $comboMaterial->setReservationMode('complete_only');
                }

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

            foreach ($templateComponents as $tplComp) {
                $compType = $tplComp->getComponentType();
                $compNameRaw = $tplComp->getName();
                $requiredQty = $tplComp->getRequiredQty();
                $tracking = $tplComp->getTracking(); // serialized oder bulk
                $isOptional = $tplComp->getIsOptional();

                // ── Artikelname zusammensetzen ──
                // is_generic=true  → Name bleibt generisch: "Heringe" (übergreifendes Material)
                // is_generic=false → Name + Modell + Hersteller: "Außenzelt Phoenix Zelthangar"
                $compName = $compNameRaw;

                if (!$tplComp->getIsGeneric()) {
                    $manufacturer = $template->getManufacturer() ?? '';
                    $model = $template->getModel() ?? '';
                    $nameLower = mb_strtolower($compName);

                    // Modell anhängen (wenn nicht schon enthalten)
                    if ($model && !str_contains($nameLower, mb_strtolower($model))) {
                        $compName .= ' ' . $model;
                        $nameLower = mb_strtolower($compName);
                    }

                    // Hersteller anhängen (wenn nicht schon enthalten)
                    if ($manufacturer && !str_contains($nameLower, mb_strtolower($manufacturer))) {
                        $compName .= ' ' . $manufacturer;
                    }
                }

                // Input-Daten für diese Komponente
                $input = $inputByType[$compType] ?? null;

                // Optionale Komponente ohne Input: überspringen
                if ($isOptional && !$input) {
                    continue;
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

                if ($mode === 'existing' && isset($input['material_id'])) {
                    $componentMaterial = $this->entityManager->getRepository(MaterialItem::class)
                        ->find($input['material_id']);
                }

                if (!$componentMaterial) {
                    // Suche nach existierendem Material mit gleichem Namen im Department
                    $componentMaterial = $this->entityManager->getRepository(MaterialItem::class)
                        ->findOneBy([
                            'departmentId' => $department->getId(),
                            'name' => $compName,
                            'deletedAt' => null,
                        ]);
                }

                $isNewArticle = false;
                if (!$componentMaterial) {
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
                    'reservation_mode' => $comboMaterial->getReservationMode(),
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

    /**
     * JSON-Datei importieren (v4-Format)
     * Erwartet: { "manufacturer": "...", "templates": [...] }
     */
    #[Route('/import', name: 'import', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function import(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['department_id'])) {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }
        if (!isset($data['templates_json'])) {
            return new JsonResponse(['error' => 'templates_json ist erforderlich'], 400);
        }

        $department = $this->entityManager->getRepository(Department::class)
            ->find($data['department_id']);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $json = $data['templates_json'];
        if (!isset($json['manufacturer']) || !isset($json['templates'])) {
            return new JsonResponse(['error' => 'Ungültiges JSON-Format. Erwartet: { "manufacturer": "...", "templates": [...] }'], 400);
        }

        $manufacturer = $json['manufacturer'];
        $created = 0;
        $skipped = 0;

        try {
            foreach ($json['templates'] as $tplData) {
                // Prüfe ob schon vorhanden (gleicher Name + Department)
                $existing = $this->entityManager->getRepository(MaterialTemplate::class)
                    ->findOneBy([
                        'departmentId' => $department->getId(),
                        'name' => $tplData['name'] ?? $tplData['id'] ?? 'Unbenannt'
                    ]);

                if ($existing) {
                    $skipped++;
                    continue;
                }

                $template = new MaterialTemplate();
                $template->setId(IdGenerator::generate());
                $template->setDepartment($department);
                $template->setName($tplData['name'] ?? $tplData['id'] ?? 'Unbenannt');
                $template->setDescription($tplData['description'] ?? null);
                $template->setManufacturer($manufacturer);
                $template->setModel($tplData['model'] ?? null);
                $template->setMaterialType($tplData['materialType'] ?? 'physical_combo');
                $template->setTentType($tplData['tentType'] ?? null);
                $template->setCapacity(isset($tplData['capacity']) ? (int) $tplData['capacity'] : null);
                $template->setReservationMode($tplData['reservationMode'] ?? null);
                $template->setIsActive($tplData['isActive'] ?? true);
                $template->setSource($manufacturer);

                // Komponenten
                if (isset($tplData['components']) && is_array($tplData['components'])) {
                    foreach ($tplData['components'] as $index => $compData) {
                        $comp = new MaterialTemplateComponent();
                        $comp->setId(IdGenerator::generate());
                        $comp->setComponentType($compData['type'] ?? 'unknown');
                        $comp->setName($compData['name'] ?? $compData['type'] ?? 'Unbenannt');
                        $comp->setRequiredQty(isset($compData['required']) ? (int) $compData['required'] : 1);
                        $comp->setIsOptional($compData['optional'] ?? false);
                        $comp->setSortOrder($index);

                        // Tracking: aus JSON oder Standard bulk (Stücklisten; SN nur bei explizit serialized)
                        if (isset($compData['tracking'])) {
                            $comp->setTracking($compData['tracking']);
                        } else {
                            $comp->setTracking('bulk');
                        }

                        // Repair Types
                        if (isset($compData['repair_types']) && is_array($compData['repair_types'])) {
                            $comp->setRepairTypes($compData['repair_types']);
                        }

                        $template->addComponent($comp);
                    }
                }

                $this->entityManager->persist($template);
                $created++;
            }

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'manufacturer' => $manufacturer,
                'created' => $created,
                'skipped' => $skipped,
                'total' => count($json['templates'])
            ], 201);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Import: ' . $e->getMessage()
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
        $comp->setSortOrder($compData['sort_order'] ?? $index);

        if (isset($compData['repair_types']) && is_array($compData['repair_types'])) {
            $comp->setRepairTypes($compData['repair_types']);
        }

        return $comp;
    }

    /**
     * Serialisiert ein Template für die JSON-Response
     */
    private function serializeTemplate(MaterialTemplate $template, bool $includeComponents): array
    {
        $data = [
            'id' => $template->getId(),
            'department_id' => $template->getDepartmentId(),
            'scope' => $template->getScope(),
            'is_global' => $template->isGlobal(),
            'name' => $template->getName(),
            'description' => $template->getDescription(),
            'manufacturer' => $template->getManufacturer(),
            'model' => $template->getModel(),
            'category' => $template->getCategory() ? [
                'id' => $template->getCategory()->getId(),
                'name' => $template->getCategory()->getName(),
            ] : null,
            'material_type' => $template->getMaterialType(),
            'tent_type' => $template->getTentType(),
            'capacity' => $template->getCapacity(),
            'reservation_mode' => $template->getReservationMode(),
            'is_active' => $template->getIsActive(),
            'source' => $template->getSource(),
            'component_count' => $template->getTotalComponentCount(),
            'created_at' => $template->getCreatedAt()->format('Y-m-d\TH:i:s'),
            'updated_at' => $template->getUpdatedAt()->format('Y-m-d\TH:i:s'),
        ];

        if ($includeComponents) {
            $data['components'] = [];
            foreach ($template->getComponents() as $comp) {
                $data['components'][] = [
                    'id' => $comp->getId(),
                    'component_type' => $comp->getComponentType(),
                    'name' => $comp->getName(),
                    'required_qty' => $comp->getRequiredQty(),
                    'is_optional' => $comp->getIsOptional(),
                    'is_generic' => $comp->getIsGeneric(),
                    'tracking' => $comp->getTracking(),
                    'repair_types' => $comp->getRepairTypes(),
                    'sort_order' => $comp->getSortOrder(),
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
}
