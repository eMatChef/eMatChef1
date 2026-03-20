<?php

namespace App\Controller;

use App\Entity\MaterialItem;
use App\Entity\MaterialBatch;
use App\Entity\BatchStorageAllocation;
use App\Entity\MaterialHistory;
use App\Entity\MaterialComboComponent;
use App\Entity\ActivityIssueReport;
use App\Entity\WorkshopTicket;
use App\Entity\Category;
use App\Entity\Department;
use App\Entity\Address;
use App\Entity\Membership;
use App\Entity\StorageRack;
use App\Entity\StorageSlot;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/materials', name: 'api_materials_')]
class MaterialController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Liste aller Materialien für ein Department
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $departmentId = $request->query->get('department_id');
        $materialSource = strtolower((string) $request->query->get('material_source', 'internal'));
        $includeGlobalJs = filter_var($request->query->get('include_global_js', '0'), FILTER_VALIDATE_BOOLEAN);
        
        if (!$departmentId) {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }
        $accessCheck = $this->assertDepartmentAccess($departmentId);
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('m', 'c', 's')
            ->from(MaterialItem::class, 'm')
            ->leftJoin('m.category', 'c')
            ->leftJoin('m.storageAddress', 's')
            ->andWhere('m.deletedAt IS NULL')
            ->orderBy('m.name', 'ASC');

        if (!in_array($materialSource, ['internal', 'js', 'all'], true)) {
            $materialSource = 'internal';
        }

        if ($materialSource === 'internal') {
            $qb->andWhere('m.departmentId = :departmentId')
                ->andWhere('m.isJsMaterial = false')
                ->setParameter('departmentId', $departmentId);
        } elseif ($materialSource === 'js') {
            $qb->andWhere('m.isJsMaterial = true');
            if (!$includeGlobalJs) {
                $qb->andWhere('m.departmentId = :departmentId')
                    ->setParameter('departmentId', $departmentId);
            }
        } else { // all
            if ($includeGlobalJs) {
                $qb->andWhere('(m.departmentId = :departmentId OR m.isJsMaterial = true)')
                    ->setParameter('departmentId', $departmentId);
            } else {
                $qb->andWhere('m.departmentId = :departmentId')
                    ->setParameter('departmentId', $departmentId);
            }
        }

        // Suchfilter (Name, Beschreibung, Seriennummer, Barcode, EAN)
        $search = $request->query->get('search');
        if ($search) {
            $qb->andWhere('m.name LIKE :search OR m.description LIKE :search OR m.barcodeTag LIKE :search OR m.ean LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Kategoriefilter
        $categoryId = $request->query->get('category_id');
        if ($categoryId) {
            $qb->andWhere('m.categoryId = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        $materials = $qb->getQuery()->getResult();

        // Activity-basierte Bestandsdaten in einem Query laden (draussen / reserviert)
        $activityStockData = $this->getActivityStockBreakdown($departmentId);

        // Combo-Allokation in einem Query laden
        $comboStockData = $this->getComboStockBreakdown($departmentId);
        
        // Offene Verlustmeldungen in einem Query laden
        $openLossData = $this->getOpenLossReportBreakdown($departmentId);

        // Bestand für jedes Material berechnen
        $result = [];
        foreach ($materials as $material) {
            $result[] = $this->serializeMaterial($material, false, $activityStockData, $comboStockData, $openLossData);
        }

        return new JsonResponse($result);
    }

    /**
     * Einzelnes Material laden
     */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get(string $id): JsonResponse
    {
        $material = $this->entityManager->getRepository(MaterialItem::class)
            ->createQueryBuilder('m')
            ->leftJoin('m.category', 'c')
            ->leftJoin('m.storageAddress', 's')
            ->addSelect('c', 's')
            ->where('m.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$material) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }
        $accessCheck = $this->assertDepartmentAccess($material->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $activityStockData = $this->getActivityStockBreakdown($material->getDepartmentId());
        $comboStockData = $this->getComboStockBreakdown($material->getDepartmentId());
        $openLossData = $this->getOpenLossReportBreakdown($material->getDepartmentId());
        return new JsonResponse($this->serializeMaterial($material, true, $activityStockData, $comboStockData, $openLossData));
    }

    /**
     * Neues Material erstellen (mit initialem Batch)
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validierung
        if (!isset($data['department_id']) || !isset($data['name'])) {
            return new JsonResponse(['error' => 'department_id und name sind erforderlich'], 400);
        }
        $accessCheck = $this->assertDepartmentAccess((string) $data['department_id']);
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        // J&S-Material darf nur von Superadmin gepflegt werden
        if (
            ((bool)($data['is_js_material'] ?? false)) ||
            (array_key_exists('external_source', $data) && $data['external_source'])
        ) {
            if (!$this->isGranted('ROLE_SUPERADMIN')) {
                return new JsonResponse(['error' => 'Nur Superadmin darf J&S-Material erstellen oder die externe Quelle setzen'], 403);
            }
        }

        // Department prüfen
        $department = $this->entityManager->getRepository(Department::class)
            ->find($data['department_id']);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        try {
            // Material erstellen
            $material = new MaterialItem();
            $material->setId(IdGenerator::generate()); // ID manuell setzen
            $material->setDepartment($department);
            $material->setName($data['name']);
            
            // Optionale Felder
            if (isset($data['description'])) {
                $material->setDescription($data['description']);
            }
            
            // Kategorie
            if (isset($data['category_id']) && $data['category_id']) {
                $category = $this->entityManager->getRepository(Category::class)
                    ->find($data['category_id']);
                if ($category) {
                    $material->setCategory($category);
                }
            }
            
            // Lagerort (Address mit type='storage')
            if (isset($data['storage_address_id']) && $data['storage_address_id']) {
                $storageAddress = $this->entityManager->getRepository(Address::class)
                    ->find($data['storage_address_id']);
                if ($storageAddress) {
                    $material->setStorageAddress($storageAddress);
                }
            }
            
            if (isset($data['location'])) {
                $material->setLocation($data['location']);
            }
            
            // Material- und Tracking-Typ
            if (isset($data['material_type'])) {
                $material->setMaterialType($data['material_type']);
            }

            // Details
            if (isset($data['is_tent'])) $material->setIsTent((bool)$data['is_tent']);
            if (isset($data['color'])) $material->setColor($data['color']);
            if (isset($data['material'])) $material->setMaterial($data['material']);
            if (isset($data['size_length'])) $material->setSizeLength($data['size_length']);
            if (isset($data['size_width'])) $material->setSizeWidth($data['size_width']);
            if (isset($data['size_height'])) $material->setSizeHeight($data['size_height']);
            if (isset($data['weight'])) $material->setWeight($data['weight']);
            
            // Identifikation
            if (isset($data['ean'])) $material->setEan($data['ean']);
            if (isset($data['barcode_tag'])) $material->setBarcodeTag($data['barcode_tag']);
            if (isset($data['manufacturer'])) $material->setManufacturer($data['manufacturer']);
            if (isset($data['model'])) $material->setModel($data['model']);
            if (isset($data['warranty_until']) && $data['warranty_until']) {
                $material->setWarrantyUntil(new \DateTime($data['warranty_until']));
            }
            
            // Verleih
            if (isset($data['rental_external_allowed'])) $material->setRentalExternalAllowed((bool)$data['rental_external_allowed']);
            if (isset($data['rental_scope'])) $material->setRentalScope($data['rental_scope']);
            if (isset($data['rental_requires_approval'])) $material->setRentalRequiresApproval((bool)$data['rental_requires_approval']);
            if (isset($data['rental_price_day'])) $material->setRentalPriceDay($data['rental_price_day']);
            if (isset($data['rental_price_week'])) $material->setRentalPriceWeek($data['rental_price_week']);
            if (isset($data['rental_price_month'])) $material->setRentalPriceMonth($data['rental_price_month']);
            if (isset($data['rental_deposit'])) $material->setRentalDeposit($data['rental_deposit']);
            if (isset($data['rental_lead_days'])) $material->setRentalLeadDays((int)$data['rental_lead_days']);
            if (isset($data['rental_max_days'])) $material->setRentalMaxDays((int)$data['rental_max_days']);
            if (isset($data['rental_notes'])) $material->setRentalNotes($data['rental_notes']);
            if (isset($data['is_js_material'])) $material->setIsJsMaterial((bool)$data['is_js_material']);
            if (array_key_exists('external_source', $data)) $material->setExternalSource($data['external_source'] ?: null);

            // Verbrauchsmaterial
            if (isset($data['is_consumable'])) $material->setIsConsumable((bool)$data['is_consumable']);
            if (isset($data['is_food'])) $material->setIsFood((bool)$data['is_food']);
            if ($material->getIsFood()) {
                // Esswaren nur als Massenartikel erlauben
                $material->setTrackingType('bulk');
            } elseif (isset($data['tracking_type'])) {
                $material->setTrackingType($data['tracking_type']);
            }
            if (isset($data['sale_price'])) $material->setSalePrice($data['sale_price']);
            if (isset($data['min_stock'])) $material->setMinStock((int)$data['min_stock']);

            // Verpackungseinheit (Bündel)
            if (isset($data['pack_size'])) $material->setPackSize($data['pack_size'] ? (int)$data['pack_size'] : null);
            if (isset($data['pack_unit'])) $material->setPackUnit($data['pack_unit'] ?: null);

            $this->entityManager->persist($material);
            $this->entityManager->flush();

            // History-Eintrag: Erstellung
            $this->createHistoryEntry($material, 'created');
            $this->entityManager->flush();

            // Bei serialisierten Materialien: Seriennummern als einzelne Batches erstellen
            if (isset($data['serial_numbers']) && is_array($data['serial_numbers']) && count($data['serial_numbers']) > 0) {
                if (empty($data['initial_acquired_on'])) {
                    return new JsonResponse(['error' => 'Einkaufsdatum (initial_acquired_on) ist Pflicht'], 400);
                }
                if ($material->getIsFood() && empty($data['initial_expiry_date'])) {
                    return new JsonResponse(['error' => 'Ablaufdatum (initial_expiry_date) ist für Esswaren Pflicht'], 400);
                }
                $acquiredOn = !empty($data['initial_acquired_on'])
                    ? new \DateTime($data['initial_acquired_on'])
                    : (!empty($data['initial_expiry_date']) ? new \DateTime($data['initial_expiry_date']) : new \DateTime());
                $expiryDate = !empty($data['initial_expiry_date']) ? new \DateTime($data['initial_expiry_date']) : null;
                
                $supplier = null;
                if (isset($data['initial_supplier_id']) && $data['initial_supplier_id']) {
                    $supplier = $this->entityManager->getRepository(Address::class)
                        ->find($data['initial_supplier_id']);
                }
                
                foreach ($data['serial_numbers'] as $index => $serialEntry) {
                    if (empty($serialEntry['serial_number'])) continue;
                    
                    $batch = new MaterialBatch();
                    $batch->setId(IdGenerator::generate13('ba', $acquiredOn->format('Y')));
                    $batch->setMaterialItem($material);
                    $batch->setQty(1); // Serialisiert = immer 1 Stück pro Batch
                    $batch->setIsInitial($index === 0);
                    $batch->setBatchType('initial');
                    $batch->setSerialNumber($serialEntry['serial_number']);
                    $batch->setAcquiredOn($acquiredOn);
                    if ($expiryDate) {
                        $batch->setExpiryDate($expiryDate);
                    }
                    
                    if (isset($serialEntry['notes'])) {
                        $batch->setNotes($serialEntry['notes']);
                    }
                    if (isset($serialEntry['label']) && trim((string)$serialEntry['label']) !== '') {
                        $batch->setLabel((string) $serialEntry['label']);
                    }

                    $entryContainerBatchId = $serialEntry['container_batch_id'] ?? null;
                    $entryRackId = $serialEntry['rack_id'] ?? null;
                    $entrySlotId = $serialEntry['slot_id'] ?? null;

                    if ($entryContainerBatchId) {
                        $containerBatch = $this->entityManager->getRepository(MaterialBatch::class)->find((string) $entryContainerBatchId);
                        if ($containerBatch && $containerBatch->getMaterialItem()->getDepartmentId() === $material->getDepartmentId()) {
                            $allocation = new BatchStorageAllocation();
                            $allocation->setId(IdGenerator::generate13Unique($this->entityManager, BatchStorageAllocation::class, 'al'));
                            $allocation->setBatch($batch);
                            $allocation->setContainerBatch($containerBatch);
                            $allocation->setQty(1);
                            $allocation->setDepartmentId($material->getDepartmentId());
                            $batch->addAllocation($allocation);
                            $this->entityManager->persist($allocation);
                        }
                    } elseif ($entryRackId || $entrySlotId) {
                        $entryRack = null;
                        if ($entryRackId) {
                            $entryRack = $this->entityManager->getRepository(StorageRack::class)->find($entryRackId);
                            if ($entryRack && $entryRack->getDepartmentId() === $material->getDepartmentId()) {
                                $batch->setRack($entryRack);
                            }
                        }
                        if ($entrySlotId) {
                            $entrySlot = $this->entityManager->getRepository(StorageSlot::class)->find($entrySlotId);
                            if ($entrySlot && $entrySlot->getRack()->getDepartmentId() === $material->getDepartmentId()) {
                                $batch->setSlot($entrySlot);
                                if (!$entryRack) {
                                    $entryRack = $entrySlot->getRack();
                                    $batch->setRack($entryRack);
                                }
                            }
                        }
                    } elseif (!empty($data['initial_container_batch_id'])) {
                        $containerBatch = $this->entityManager->getRepository(MaterialBatch::class)->find((string) $data['initial_container_batch_id']);
                        if ($containerBatch && $containerBatch->getMaterialItem()->getDepartmentId() === $material->getDepartmentId()) {
                            $allocation = new BatchStorageAllocation();
                            $allocation->setId(IdGenerator::generate13Unique($this->entityManager, BatchStorageAllocation::class, 'al'));
                            $allocation->setBatch($batch);
                            $allocation->setContainerBatch($containerBatch);
                            $allocation->setQty(1);
                            $allocation->setDepartmentId($material->getDepartmentId());
                            $batch->addAllocation($allocation);
                            $this->entityManager->persist($allocation);
                        }
                    } else {
                        if (isset($data['initial_rack_id']) && $data['initial_rack_id']) {
                            $rack = $this->entityManager->getRepository(StorageRack::class)->find($data['initial_rack_id']);
                            if ($rack && $rack->getDepartmentId() === $material->getDepartmentId()) {
                                $batch->setRack($rack);
                            }
                        }
                        if (isset($data['initial_slot_id']) && $data['initial_slot_id']) {
                            $slot = $this->entityManager->getRepository(StorageSlot::class)->find($data['initial_slot_id']);
                            if ($slot && $slot->getRack()->getDepartmentId() === $material->getDepartmentId()) {
                                $batch->setSlot($slot);
                                if (!$batch->getRack()) {
                                    $batch->setRack($slot->getRack());
                                }
                            }
                        }
                    }
                    
                    if (isset($data['initial_unit_price'])) {
                        $batch->setUnitPrice($data['initial_unit_price']);
                    }
                    
                    if ($supplier) {
                        $batch->setSupplier($supplier);
                    }
                    
                    $this->entityManager->persist($batch);
                }
                $this->entityManager->flush();
            }
            // Bei Massenartikeln: Standard-Batch
            elseif (isset($data['initial_qty']) && $data['initial_qty'] > 0) {
                if (!$material->getIsFood() && empty($data['initial_acquired_on'])) {
                    return new JsonResponse(['error' => 'Einkaufsdatum (initial_acquired_on) ist Pflicht'], 400);
                }
                if ($material->getIsFood() && empty($data['initial_expiry_date'])) {
                    return new JsonResponse(['error' => 'Ablaufdatum (initial_expiry_date) ist für Esswaren Pflicht'], 400);
                }
                $acquiredOnDate = !empty($data['initial_acquired_on'])
                    ? new \DateTime($data['initial_acquired_on'])
                    : (!empty($data['initial_expiry_date']) ? new \DateTime($data['initial_expiry_date']) : new \DateTime());
                $expiryDate = !empty($data['initial_expiry_date']) ? new \DateTime($data['initial_expiry_date']) : null;

                $batch = new MaterialBatch();
                $batch->setId(IdGenerator::generate13('ba', $acquiredOnDate->format('Y')));
                $batch->setMaterialItem($material);
                $batch->setQty((int)$data['initial_qty']);
                $batch->setIsInitial(true);
                $batch->setBatchType('initial');
                $batch->setAcquiredOn($acquiredOnDate);
                if ($expiryDate) {
                    $batch->setExpiryDate($expiryDate);
                }
                
                if (isset($data['initial_unit_price'])) {
                    $batch->setUnitPrice($data['initial_unit_price']);
                }

                $initialAllocations = $data['initial_allocations'] ?? null;
                if (is_array($initialAllocations) && count($initialAllocations) > 0) {
                    // Auf mehrere Lagerplätze aufteilen: Batch ohne rack/slot, Allokationen erstellen
                    $allocSum = 0;
                    foreach ($initialAllocations as $alloc) {
                        $allocResult = $this->createAllocationFromPayload($alloc, $material->getDepartmentId());
                        if ($allocResult instanceof JsonResponse) return $allocResult;
                        $allocation = $allocResult;
                        $allocation->setId(IdGenerator::generate13Unique($this->entityManager, BatchStorageAllocation::class, 'al'));
                        $allocation->setBatch($batch);
                        $allocation->setDepartmentId($material->getDepartmentId());
                        $batch->addAllocation($allocation);
                        $this->entityManager->persist($allocation);
                        $allocSum += $allocation->getQty();
                    }
                    if ($allocSum !== (int)$data['initial_qty']) {
                        return new JsonResponse(['error' => 'Summe der Allokationen muss initial_qty entsprechen (' . (int)$data['initial_qty'] . ')'], 400);
                    }
                } elseif (!empty($data['initial_container_batch_id'])) {
                    // Einzelner Container (Kiste/Tasche)
                    $containerBatch = $this->entityManager->getRepository(MaterialBatch::class)->find((string) $data['initial_container_batch_id']);
                    if (!$containerBatch || $containerBatch->getMaterialItem()->getDepartmentId() !== $material->getDepartmentId()) {
                        return new JsonResponse(['error' => 'initial_container_batch_id ist ungültig'], 400);
                    }
                    $allocation = new BatchStorageAllocation();
                    $allocation->setId(IdGenerator::generate13Unique($this->entityManager, BatchStorageAllocation::class, 'al'));
                    $allocation->setBatch($batch);
                    $allocation->setContainerBatch($containerBatch);
                    $allocation->setQty((int) $data['initial_qty']);
                    $allocation->setDepartmentId($material->getDepartmentId());
                    $batch->addAllocation($allocation);
                    $this->entityManager->persist($allocation);
                } else {
                    // Einzelner Lagerplatz (Gestell/Slot)
                    if (isset($data['initial_rack_id']) && $data['initial_rack_id']) {
                        $rack = $this->entityManager->getRepository(StorageRack::class)->find($data['initial_rack_id']);
                        if ($rack && $rack->getDepartmentId() === $material->getDepartmentId()) {
                            $batch->setRack($rack);
                        }
                    }
                    if (isset($data['initial_slot_id']) && $data['initial_slot_id']) {
                        $slot = $this->entityManager->getRepository(StorageSlot::class)->find($data['initial_slot_id']);
                        if ($slot && $slot->getRack()->getDepartmentId() === $material->getDepartmentId()) {
                            $batch->setSlot($slot);
                        }
                    }
                }
                
                // Supplier für initialen Batch
                if (isset($data['initial_supplier_id']) && $data['initial_supplier_id']) {
                    $supplier = $this->entityManager->getRepository(Address::class)
                        ->find($data['initial_supplier_id']);
                    if ($supplier) {
                        $batch->setSupplier($supplier);
                    }
                }

                $this->entityManager->persist($batch);
                $this->entityManager->flush();
            }

            return new JsonResponse($this->serializeMaterial($material, true), 201);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Erstellen des Materials: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Combo aus Lagerplatz-Inhalt erstellen (ohne Vorlage)
     * POST /api/materials/create-combo-from-rack
     * Body: { rack_id, name, material_type?: 'physical_combo'|'virtual_combo', department_id, ... }
     */
    #[Route('/create-combo-from-rack', name: 'create_combo_from_rack', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createComboFromRack(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $rackId = (string) ($data['rack_id'] ?? '');
        $name = trim((string) ($data['name'] ?? ''));
        $departmentId = (string) ($data['department_id'] ?? '');
        $materialType = $data['material_type'] ?? 'physical_combo';

        if (!$rackId || !$name || !$departmentId) {
            return new JsonResponse(['error' => 'rack_id, name und department_id sind erforderlich'], 400);
        }
        if (!in_array($materialType, ['physical_combo', 'virtual_combo'], true)) {
            $materialType = 'physical_combo';
        }

        $accessCheck = $this->assertDepartmentAccess($departmentId);
        if ($accessCheck instanceof JsonResponse) return $accessCheck;

        $rack = $this->entityManager->getRepository(StorageRack::class)->find($rackId);
        if (!$rack || $rack->getDepartmentId() !== $departmentId) {
            return new JsonResponse(['error' => 'Lagerplatz nicht gefunden oder gehört nicht zum Department'], 404);
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) return new JsonResponse(['error' => 'Department nicht gefunden'], 404);

        $conn = $this->entityManager->getConnection();
        $sql = "
            SELECT mi.id AS material_id, mi.name AS material_name, mi.tracking_type,
                   SUM(a.qty) AS qty
            FROM batch_storage_allocation a
            INNER JOIN material_batch b ON a.batch_id = b.id
            INNER JOIN material_item mi ON b.material_item_id = mi.id
            LEFT JOIN material_batch cb ON a.container_batch_id = cb.id
            WHERE (a.rack_id = :rackId OR (a.container_batch_id IS NOT NULL AND cb.rack_id = :rackId))
              AND (mi.deleted_at IS NULL)
              AND b.status = 'active'
            GROUP BY mi.id, mi.name, mi.tracking_type
            ORDER BY mi.name
        ";
        $rows = $conn->executeQuery($sql, ['rackId' => $rackId])->fetchAllAssociative();
        if (empty($rows)) {
            return new JsonResponse(['error' => 'Lagerplatz ist leer – keine Materialien gefunden'], 400);
        }

        try {
            $this->entityManager->beginTransaction();

            $comboMaterial = new MaterialItem();
            $comboMaterial->setId(IdGenerator::generate());
            $comboMaterial->setDepartment($department);
            $comboMaterial->setName($name);
            $comboMaterial->setMaterialType($materialType);
            $comboMaterial->setTrackingType('serialized');
            $comboMaterial->setIsTent(false);
            $comboMaterial->setReservationMode($data['reservation_mode'] ?? 'complete_only');

            if (!empty($data['category_id'])) {
                $category = $this->entityManager->getRepository(Category::class)->find($data['category_id']);
                if ($category) $comboMaterial->setCategory($category);
            }
            if (!empty($data['storage_address_id'])) {
                $addr = $this->entityManager->getRepository(Address::class)->find($data['storage_address_id']);
                if ($addr && $addr->getDepartmentId() === $departmentId) $comboMaterial->setStorageAddress($addr);
            }

            $this->entityManager->persist($comboMaterial);

            if ($materialType === 'physical_combo') {
                $comboBatch = new MaterialBatch();
                $comboBatch->setId(IdGenerator::generate13('ba'));
                $comboBatch->setMaterialItem($comboMaterial);
                $comboBatch->setQty(1);
                $comboBatch->setIsInitial(true);
                $comboBatch->setBatchType('initial');
                $comboBatch->setAcquiredOn(new \DateTime($data['purchase_date'] ?? 'now'));
                $this->entityManager->persist($comboBatch);
            }

            $sortOrder = 0;
            foreach ($rows as $row) {
                $componentMaterial = $this->entityManager->getRepository(MaterialItem::class)->find($row['material_id']);
                if (!$componentMaterial) continue;

                $tracking = $row['tracking_type'] ?? 'bulk';
                $qty = (int) $row['qty'];
                $assignmentMode = ($tracking === 'serialized') ? 'on_issue' : 'bulk';

                $comp = new MaterialComboComponent();
                $comp->setId(IdGenerator::generate13('cc'));
                $comp->setParentMaterial($comboMaterial);
                $comp->setComponentMaterial($componentMaterial);
                $comp->setQty($qty);
                $comp->setAssignmentMode($assignmentMode);
                $comp->setComponentRole($componentMaterial->getName());
                $comp->setSortOrder($sortOrder++);
                $this->entityManager->persist($comp);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return new JsonResponse($this->serializeMaterial($comboMaterial, true), 201);
        } catch (\Exception $e) {
            $this->entityManager->rollBack();
            return new JsonResponse(['error' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Material aktualisieren
     */
    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $id, Request $request): JsonResponse
    {
        $material = $this->entityManager->getRepository(MaterialItem::class)->find($id);
        
        if (!$material) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }
        $accessCheck = $this->assertDepartmentAccess($material->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $data = json_decode($request->getContent(), true);

        // J&S-Flags nur durch Superadmin änderbar
        if (array_key_exists('is_js_material', $data) || array_key_exists('external_source', $data)) {
            if (!$this->isGranted('ROLE_SUPERADMIN')) {
                return new JsonResponse(['error' => 'Nur Superadmin darf J&S-Material oder externe Quelle ändern'], 403);
            }
        }

        try {
            // Snapshot VOR der Änderung erstellen
            $oldSnapshot = $this->buildSnapshot($material);

            // Grunddaten
            if (isset($data['name'])) $material->setName($data['name']);
            if (isset($data['description'])) $material->setDescription($data['description']);
            if (isset($data['location'])) $material->setLocation($data['location']);
            if (isset($data['condition'])) $material->setCondition($data['condition']);
            
            // Kategorie
            if (array_key_exists('category_id', $data)) {
                if ($data['category_id']) {
                    $category = $this->entityManager->getRepository(Category::class)
                        ->find($data['category_id']);
                    $material->setCategory($category);
                } else {
                    $material->setCategory(null);
                }
            }
            
            // Lagerort (Address mit type='storage')
            if (array_key_exists('storage_address_id', $data)) {
                if ($data['storage_address_id']) {
                    $storageAddress = $this->entityManager->getRepository(Address::class)
                        ->find($data['storage_address_id']);
                    $material->setStorageAddress($storageAddress);
                } else {
                    $material->setStorageAddress(null);
                }
            }
            
            // Details
            if (isset($data['is_tent'])) $material->setIsTent((bool)$data['is_tent']);
            if (array_key_exists('reservation_mode', $data)) $material->setReservationMode($data['reservation_mode']);
            if (isset($data['color'])) $material->setColor($data['color']);
            if (isset($data['material'])) $material->setMaterial($data['material']);
            if (isset($data['size_length'])) $material->setSizeLength($data['size_length']);
            if (isset($data['size_width'])) $material->setSizeWidth($data['size_width']);
            if (isset($data['size_height'])) $material->setSizeHeight($data['size_height']);
            if (isset($data['weight'])) $material->setWeight($data['weight']);
            
            // Identifikation
            if (isset($data['ean'])) $material->setEan($data['ean']);
            if (isset($data['barcode_tag'])) $material->setBarcodeTag($data['barcode_tag']);
            if (isset($data['manufacturer'])) $material->setManufacturer($data['manufacturer']);
            if (isset($data['model'])) $material->setModel($data['model']);
            if (array_key_exists('warranty_until', $data)) {
                $material->setWarrantyUntil($data['warranty_until'] ? new \DateTime($data['warranty_until']) : null);
            }
            
            // Verleih
            if (isset($data['rental_external_allowed'])) $material->setRentalExternalAllowed((bool)$data['rental_external_allowed']);
            if (isset($data['rental_scope'])) $material->setRentalScope($data['rental_scope']);
            if (isset($data['rental_requires_approval'])) $material->setRentalRequiresApproval((bool)$data['rental_requires_approval']);
            if (isset($data['rental_price_day'])) $material->setRentalPriceDay($data['rental_price_day']);
            if (isset($data['rental_price_week'])) $material->setRentalPriceWeek($data['rental_price_week']);
            if (isset($data['rental_price_month'])) $material->setRentalPriceMonth($data['rental_price_month']);
            if (isset($data['rental_deposit'])) $material->setRentalDeposit($data['rental_deposit']);
            if (isset($data['rental_lead_days'])) $material->setRentalLeadDays((int)$data['rental_lead_days']);
            if (isset($data['rental_max_days'])) $material->setRentalMaxDays((int)$data['rental_max_days']);
            if (isset($data['rental_notes'])) $material->setRentalNotes($data['rental_notes']);
            if (isset($data['is_js_material'])) $material->setIsJsMaterial((bool)$data['is_js_material']);
            if (array_key_exists('external_source', $data)) $material->setExternalSource($data['external_source'] ?: null);

            // Verbrauchsmaterial / Esswaren
            if (isset($data['is_consumable'])) $material->setIsConsumable((bool)$data['is_consumable']);
            if (isset($data['is_food'])) $material->setIsFood((bool)$data['is_food']);
            if ($material->getIsFood()) {
                $material->setTrackingType('bulk');
            } elseif (isset($data['tracking_type'])) {
                $material->setTrackingType($data['tracking_type']);
            }
            if (array_key_exists('sale_price', $data)) $material->setSalePrice($data['sale_price']);
            if (array_key_exists('min_stock', $data)) $material->setMinStock($data['min_stock'] !== null ? (int)$data['min_stock'] : null);

            // Verpackungseinheit (Bündel)
            if (array_key_exists('pack_size', $data)) $material->setPackSize($data['pack_size'] ? (int)$data['pack_size'] : null);
            if (array_key_exists('pack_unit', $data)) $material->setPackUnit($data['pack_unit'] ?: null);

            $material->updateTimestamps();

            // History-Eintrag: Änderungen berechnen und speichern
            $newSnapshot = $this->buildSnapshot($material);
            $changes = $this->computeChanges($oldSnapshot, $newSnapshot);
            if (!empty($changes)) {
                $this->createHistoryEntry($material, 'updated', $changes);
            }

            $this->entityManager->flush();

            return new JsonResponse($this->serializeMaterial($material, true));

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Aktualisieren des Materials: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch zu bestehendem Material hinzufügen
     */
    #[Route('/{id}/batches', name: 'add_batch', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addBatch(string $id, Request $request): JsonResponse
    {
        $material = $this->entityManager->getRepository(MaterialItem::class)->find($id);
        
        if (!$material) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }
        $accessCheck = $this->assertDepartmentAccess($material->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['qty']) || $data['qty'] <= 0) {
            return new JsonResponse(['error' => 'Menge muss größer als 0 sein'], 400);
        }

        if (!$material->getIsFood() && empty($data['acquired_on'])) {
            return new JsonResponse(['error' => 'Einkaufsdatum (acquired_on) ist Pflicht'], 400);
        }
        if ($material->getIsFood() && empty($data['expiry_date'])) {
            return new JsonResponse(['error' => 'Ablaufdatum (expiry_date) ist für Esswaren Pflicht'], 400);
        }

        // Serialisiert + qty > 1: Mehrere Batches mit Seriennummern erstellen
        if ($material->getTrackingType() === 'serialized' && (int) $data['qty'] > 1) {
            $serialEntries = $this->buildSerialEntriesForAddBatch($data);
            if ($serialEntries !== null) {
                return $this->addSerializedBatches($material, $data, $serialEntries);
            }
        }

        try {
            $acquiredOnDate = !empty($data['acquired_on'])
                ? new \DateTime($data['acquired_on'])
                : (!empty($data['expiry_date']) ? new \DateTime($data['expiry_date']) : new \DateTime());
            $expiryDate = !empty($data['expiry_date']) ? new \DateTime($data['expiry_date']) : null;

            $batch = new MaterialBatch();
            $batch->setId(IdGenerator::generate13('ba', $acquiredOnDate->format('Y')));
            $batch->setMaterialItem($material);
            $batch->setQty((int)$data['qty']);
            $batch->setIsInitial(false);
            $batch->setBatchType('purchase');
            $batch->setAcquiredOn($acquiredOnDate);
            if ($expiryDate) {
                $batch->setExpiryDate($expiryDate);
            }
            
            if (isset($data['unit_price'])) {
                $batch->setUnitPrice($data['unit_price']);
            }
            
            if (isset($data['supplier_id']) && $data['supplier_id']) {
                $supplier = $this->entityManager->getRepository(Address::class)
                    ->find($data['supplier_id']);
                if ($supplier) {
                    $batch->setSupplier($supplier);
                }
            }
            
            if (isset($data['notes'])) {
                $batch->setNotes($data['notes']);
            }

            if (isset($data['serial_number'])) {
                $batch->setSerialNumber($data['serial_number']);
            } elseif (!empty($data['serial_numbers']) && is_array($data['serial_numbers'])) {
                $first = trim((string) ($data['serial_numbers'][0] ?? ''));
                if ($first !== '') {
                    $batch->setSerialNumber($first);
                }
            }

            $allocations = $data['allocations'] ?? null;
            if (is_array($allocations) && count($allocations) > 0) {
                // Auf mehrere Lagerplätze aufteilen: Batch ohne rack/slot, Allokationen erstellen
                $allocSum = 0;
                foreach ($allocations as $alloc) {
                    $allocResult = $this->createAllocationFromPayload($alloc, $material->getDepartmentId());
                    if ($allocResult instanceof JsonResponse) return $allocResult;
                    $allocation = $allocResult;
                    $allocation->setId(IdGenerator::generate13Unique($this->entityManager, BatchStorageAllocation::class, 'al'));
                    $allocation->setBatch($batch);
                    $allocation->setDepartmentId($material->getDepartmentId());
                    $batch->addAllocation($allocation);
                    $this->entityManager->persist($allocation);
                    $allocSum += $allocation->getQty();
                }
                if ($allocSum !== (int)$data['qty']) {
                    return new JsonResponse(['error' => 'Summe der Allokationen muss qty entsprechen (' . (int)$data['qty'] . ')'], 400);
                }
            } else {
                // Einzelner Lagerplatz (bisheriges Verhalten)
                if (array_key_exists('rack_id', $data)) {
                    if ($data['rack_id']) {
                        $rack = $this->entityManager->getRepository(StorageRack::class)->find($data['rack_id']);
                        if ($rack && $rack->getDepartmentId() === $material->getDepartmentId()) {
                            $batch->setRack($rack);
                        }
                    } else {
                        $batch->setRack(null);
                    }
                }
                if (array_key_exists('slot_id', $data)) {
                    if ($data['slot_id']) {
                        $slot = $this->entityManager->getRepository(StorageSlot::class)->find($data['slot_id']);
                        if ($slot && $slot->getRack()->getDepartmentId() === $material->getDepartmentId()) {
                            $batch->setSlot($slot);
                        }
                    } else {
                        $batch->setSlot(null);
                    }
                }
            }

            $this->entityManager->persist($batch);

            // History-Eintrag für Batch-Hinzufügung
            $this->createHistoryEntry($material, 'batch_added', [
                'batch_id' => ['old' => null, 'new' => $batch->getId()],
                'qty' => ['old' => null, 'new' => $batch->getQty()],
                'acquired_on' => ['old' => null, 'new' => $acquiredOnDate->format('Y-m-d')],
                'expiry_date' => ['old' => null, 'new' => $batch->getExpiryDate()?->format('Y-m-d')],
                'unit_price' => ['old' => null, 'new' => $batch->getUnitPrice()],
                'rack_id' => ['old' => null, 'new' => $batch->getRackId()],
                'slot_id' => ['old' => null, 'new' => $batch->getSlotId()],
            ]);

            $this->entityManager->flush();

            $response = [
                'id' => $batch->getId(),
                'qty' => $batch->getQty(),
                'unit_price' => $batch->getUnitPrice(),
                'acquired_on' => $batch->getAcquiredOn()->format('Y-m-d'),
                'expiry_date' => $batch->getExpiryDate()?->format('Y-m-d'),
                'status' => $batch->getStatus(),
                'batch_type' => $batch->getBatchType(),
                'is_initial' => $batch->getIsInitial(),
                'label' => $batch->getLabel(),
                'notes' => $batch->getNotes(),
                'serial_number' => $batch->getSerialNumber(),
                'rack_id' => $batch->getRackId(),
                'slot_id' => $batch->getSlotId(),
            ];
            $allocations = $batch->getAllocations();
            if ($allocations->count() > 0) {
                $response['allocations'] = $this->serializeAllocations($allocations);
            }
            return new JsonResponse($response, 201);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Hinzufügen des Batches: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Baut serial_entries aus Request-Daten für addBatch (serialisiert, qty > 1).
     * @return array<int, array{serial_number: string, label: ?string}>|null wenn nicht anwendbar
     */
    private function buildSerialEntriesForAddBatch(array $data): ?array
    {
        $qty = (int) $data['qty'];
        $entries = [];

        if (!empty($data['serial_entries']) && is_array($data['serial_entries'])) {
            foreach ($data['serial_entries'] as $entry) {
                $sn = trim((string) ($entry['serial_number'] ?? ''));
                if ($sn !== '') {
                    $label = trim((string) ($entry['label'] ?? ''));
                    $entries[] = ['serial_number' => $sn, 'label' => $label !== '' ? $label : null];
                }
            }
        }
        if (count($entries) === 0 && !empty($data['serial_numbers']) && is_array($data['serial_numbers'])) {
            foreach ($data['serial_numbers'] as $sn) {
                $sn = trim((string) $sn);
                if ($sn !== '') {
                    $entries[] = ['serial_number' => $sn, 'label' => null];
                }
            }
        }
        if (count($entries) === 0 && (!empty($data['serial_prefix']) || isset($data['start_number']))) {
            $prefix = trim((string) ($data['serial_prefix'] ?? 'SER-'));
            $start = (int) ($data['start_number'] ?? 1);
            $pad = (int) ($data['pad_length'] ?? 3);
            for ($i = 0; $i < $qty; $i++) {
                $entries[] = [
                    'serial_number' => $prefix . str_pad((string) ($start + $i), max(1, $pad), '0', STR_PAD_LEFT),
                    'label' => null,
                ];
            }
        }

        if (count($entries) !== $qty) {
            return null;
        }
        return $entries;
    }

    /**
     * Erstellt mehrere serialisierte Batches (Charge hinzufügen mit qty > 1).
     */
    private function addSerializedBatches(MaterialItem $material, array $data, array $serialEntries): JsonResponse
    {
        $serialNumbers = array_column($serialEntries, 'serial_number');
        if (count(array_unique($serialNumbers)) !== count($serialNumbers)) {
            return new JsonResponse(['error' => 'serial_numbers enthalten Duplikate'], 400);
        }

        $existingCount = $this->entityManager->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(MaterialBatch::class, 'b')
            ->where('b.materialItemId = :materialId')
            ->andWhere('b.serialNumber IN (:serials)')
            ->setParameter('materialId', $material->getId())
            ->setParameter('serials', $serialNumbers)
            ->getQuery()
            ->getSingleScalarResult();
        if ((int) $existingCount > 0) {
            return new JsonResponse(['error' => 'Mindestens eine Seriennummer existiert bereits für dieses Material'], 400);
        }

        $acquiredOnDate = !empty($data['acquired_on'])
            ? new \DateTime($data['acquired_on'])
            : (!empty($data['expiry_date']) ? new \DateTime($data['expiry_date']) : new \DateTime());
        $expiryDate = !empty($data['expiry_date']) ? new \DateTime($data['expiry_date']) : null;

        $rack = null;
        if (!empty($data['rack_id'])) {
            $rack = $this->entityManager->getRepository(StorageRack::class)->find((string) $data['rack_id']);
            if (!$rack || $rack->getDepartmentId() !== $material->getDepartmentId()) {
                return new JsonResponse(['error' => 'rack_id ist ungültig'], 400);
            }
        }
        $defaultContainerBatch = null;
        if (!empty($data['container_batch_id'])) {
            $defaultContainerBatch = $this->entityManager->getRepository(MaterialBatch::class)->find((string) $data['container_batch_id']);
            if (!$defaultContainerBatch || $defaultContainerBatch->getMaterialItem()->getDepartmentId() !== $material->getDepartmentId()) {
                return new JsonResponse(['error' => 'container_batch_id ist ungültig'], 400);
            }
        }

        $serialAllocations = $data['serial_allocations'] ?? [];
        $allocMap = [];
        foreach ($serialAllocations as $a) {
            $sn = trim((string) ($a['serial_number'] ?? ''));
            if ($sn !== '') {
                $allocMap[$sn] = $a;
            }
        }

        $createSlotPerSerial = !empty($data['create_slot_per_serial']) && $rack !== null;
        $repo = $this->entityManager->getRepository(StorageSlot::class);

        $supplier = null;
        if (!empty($data['supplier_id'])) {
            $supplier = $this->entityManager->getRepository(Address::class)->find($data['supplier_id']);
        }

        $created = [];
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            foreach ($serialEntries as $entry) {
                $sn = $entry['serial_number'];
                $label = $entry['label'] ?? null;

                $batch = new MaterialBatch();
                $batch->setId(IdGenerator::generate13Unique($this->entityManager, MaterialBatch::class, 'ba'));
                $batch->setMaterialItem($material);
                $batch->setQty(1);
                $batch->setIsInitial(false);
                $batch->setBatchType('purchase');
                $batch->setAcquiredOn($acquiredOnDate);
                $batch->setSerialNumber($sn);
                if ($label !== null) {
                    $batch->setLabel($label);
                }
                if ($expiryDate) {
                    $batch->setExpiryDate($expiryDate);
                }
                if (isset($data['unit_price'])) {
                    $batch->setUnitPrice($data['unit_price']);
                }
                if ($supplier) {
                    $batch->setSupplier($supplier);
                }
                if ($data['notes'] ?? '') {
                    $batch->setNotes($data['notes']);
                }

                $batchRack = $rack;
                $batchSlot = null;
                $useContainerBatch = $defaultContainerBatch;

                if (isset($allocMap[$sn])) {
                    $allocEntry = $allocMap[$sn];
                    $containerBatchId = $allocEntry['container_batch_id'] ?? null;
                    if ($containerBatchId) {
                        $containerBatch = $this->entityManager->getRepository(MaterialBatch::class)->find((string) $containerBatchId);
                        if (!$containerBatch || $containerBatch->getMaterialItem()->getDepartmentId() !== $material->getDepartmentId()) {
                            $connection->rollBack();
                            return new JsonResponse(['error' => 'container_batch_id ungültig für Seriennummer ' . $sn], 400);
                        }
                        $useContainerBatch = $containerBatch;
                    } else {
                        $useContainerBatch = null;
                        $rackId = $allocEntry['rack_id'] ?? null;
                        $slotId = $allocEntry['slot_id'] ?? null;
                        if ($rackId) {
                            $r = $this->entityManager->getRepository(StorageRack::class)->find($rackId);
                            if ($r && $r->getDepartmentId() === $material->getDepartmentId()) {
                                $batchRack = $r;
                                if ($slotId) {
                                    $s = $repo->find($slotId);
                                    if ($s && $s->getRackId() === $r->getId()) {
                                        $batchSlot = $s;
                                    }
                                }
                            }
                        }
                    }
                } elseif ($createSlotPerSerial && $batchRack && !$useContainerBatch) {
                    $slotName = $label ?? $sn;
                    $existing = $repo->findOneBy(['rackId' => $batchRack->getId(), 'name' => $slotName]);
                    if ($existing) {
                        $idx = 2;
                        while ($repo->findOneBy(['rackId' => $batchRack->getId(), 'name' => $slotName . ' (' . $idx . ')'])) {
                            $idx++;
                        }
                        $slotName = $slotName . ' (' . $idx . ')';
                    }
                    $slot = new StorageSlot();
                    $slot->setId(IdGenerator::generate());
                    $slot->setRack($batchRack);
                    $slot->setName($slotName);
                    $this->entityManager->persist($slot);
                    $batchSlot = $slot;
                } elseif ($rack && empty($data['serial_allocations']) && !$useContainerBatch) {
                    if (array_key_exists('rack_id', $data) && $data['rack_id']) {
                        $batchRack = $rack;
                    }
                    if (array_key_exists('slot_id', $data) && $data['slot_id']) {
                        $batchSlot = $repo->find($data['slot_id']);
                        if ($batchSlot && $batchSlot->getRackId() !== $batchRack?->getId()) {
                            $batchSlot = null;
                        }
                    }
                }

                if ($useContainerBatch) {
                    $batch->setRack(null);
                    $batch->setSlot(null);
                    $this->entityManager->persist($batch);
                    $allocation = new BatchStorageAllocation();
                    $allocation->setId(IdGenerator::generate13Unique($this->entityManager, BatchStorageAllocation::class, 'al'));
                    $allocation->setBatch($batch);
                    $allocation->setContainerBatch($useContainerBatch);
                    $allocation->setQty(1);
                    $allocation->setDepartmentId($material->getDepartmentId());
                    $batch->addAllocation($allocation);
                    $this->entityManager->persist($allocation);
                } else {
                    if ($batchRack) {
                        $batch->setRack($batchRack);
                    }
                    if ($batchSlot) {
                        $batch->setSlot($batchSlot);
                    }
                    $this->entityManager->persist($batch);
                }

                $this->createHistoryEntry($material, 'batch_added', [
                    'batch_id' => ['old' => null, 'new' => $batch->getId()],
                    'qty' => ['old' => null, 'new' => 1],
                    'serial_number' => ['old' => null, 'new' => $sn],
                ]);

                $created[] = [
                    'id' => $batch->getId(),
                    'qty' => 1,
                    'serial_number' => $sn,
                    'label' => $batch->getLabel(),
                    'rack_id' => $useContainerBatch ? null : $batch->getRackId(),
                    'slot_id' => $useContainerBatch ? null : $batch->getSlotId(),
                    'container_batch_id' => $useContainerBatch ? $useContainerBatch->getId() : null,
                ];
            }

            $this->entityManager->flush();
            $connection->commit();

            return new JsonResponse([
                'created_count' => count($created),
                'created_batches' => $created,
            ], 201);
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            return new JsonResponse(['error' => 'Fehler beim Hinzufügen der Chargen: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Batch aktualisieren (acquired_on ist NICHT änderbar - Jahr steckt in der ID!)
     */
    #[Route('/{materialId}/batches/{batchId}', name: 'update_batch', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateBatch(string $materialId, string $batchId, Request $request): JsonResponse
    {
        $batch = $this->entityManager->getRepository(MaterialBatch::class)->find($batchId);
        
        if (!$batch || $batch->getMaterialItemId() !== $materialId) {
            return new JsonResponse(['error' => 'Batch nicht gefunden'], 404);
        }

        $material = $batch->getMaterialItem();
        $data = json_decode($request->getContent(), true);

        try {
            // Alten Zustand merken für History
            $oldValues = [
                'qty' => $batch->getQty(),
                'unit_price' => $batch->getUnitPrice(),
                'status' => $batch->getStatus(),
                'notes' => $batch->getNotes(),
                'label' => $batch->getLabel(),
                'serial_number' => $batch->getSerialNumber(),
                'rack_id' => $batch->getRackId(),
                'slot_id' => $batch->getSlotId(),
                'supplier' => $batch->getSupplier() ? ($batch->getSupplier()->getName() ?: $batch->getSupplier()->getCompany()) : null,
            ];

            if (isset($data['qty'])) {
                $qty = (int)$data['qty'];
                if ($qty <= 0) {
                    return new JsonResponse(['error' => 'Menge muss größer als 0 sein'], 400);
                }
                $batch->setQty($qty);
            }

            if (isset($data['unit_price'])) {
                $batch->setUnitPrice($data['unit_price']);
            }

            if (isset($data['status'])) {
                $allowedStatuses = ['active', 'defect', 'repair', 'lost', 'disposed'];
                if (!in_array($data['status'], $allowedStatuses)) {
                    return new JsonResponse(['error' => 'Ungültiger Status. Erlaubt: ' . implode(', ', $allowedStatuses)], 400);
                }
                $batch->setStatus($data['status']);
            }

            if (array_key_exists('notes', $data)) {
                $batch->setNotes($data['notes']);
            }

            if (array_key_exists('label', $data)) {
                $batch->setLabel($data['label']);
            }

            if (array_key_exists('serial_number', $data)) {
                $batch->setSerialNumber($data['serial_number']);
            }

            if (array_key_exists('rack_id', $data)) {
                if ($data['rack_id']) {
                    $rack = $this->entityManager->getRepository(StorageRack::class)->find($data['rack_id']);
                    if ($rack && $rack->getDepartmentId() === $material->getDepartmentId()) {
                        $batch->setRack($rack);
                    }
                } else {
                    $batch->setRack(null);
                }
            }

            if (array_key_exists('slot_id', $data)) {
                if ($data['slot_id']) {
                    $slot = $this->entityManager->getRepository(StorageSlot::class)->find($data['slot_id']);
                    if ($slot && $slot->getRack()->getDepartmentId() === $material->getDepartmentId()) {
                        $batch->setSlot($slot);
                    }
                } else {
                    $batch->setSlot(null);
                }
            }

            if (isset($data['supplier_id'])) {
                if ($data['supplier_id']) {
                    $supplier = $this->entityManager->getRepository(Address::class)
                        ->find($data['supplier_id']);
                    if ($supplier) {
                        $batch->setSupplier($supplier);
                    }
                } else {
                    $batch->setSupplier(null);
                }
            }

            // Neuen Zustand erfassen und Diff berechnen
            $newValues = [
                'qty' => $batch->getQty(),
                'unit_price' => $batch->getUnitPrice(),
                'status' => $batch->getStatus(),
                'notes' => $batch->getNotes(),
                'label' => $batch->getLabel(),
                'serial_number' => $batch->getSerialNumber(),
                'rack_id' => $batch->getRackId(),
                'slot_id' => $batch->getSlotId(),
                'supplier' => $batch->getSupplier() ? ($batch->getSupplier()->getName() ?: $batch->getSupplier()->getCompany()) : null,
            ];

            $batchChanges = [];
            foreach ($newValues as $key => $newVal) {
                if ($oldValues[$key] !== $newVal) {
                    $batchChanges['batch.' . $key] = [
                        'old' => $oldValues[$key],
                        'new' => $newVal,
                    ];
                }
            }

            // History-Eintrag nur wenn sich etwas geändert hat
            if (!empty($batchChanges)) {
                $batchChanges['batch_id'] = ['old' => $batch->getId(), 'new' => $batch->getId()];
                $this->createHistoryEntry($material, 'batch_updated', $batchChanges);
            }

            $this->entityManager->flush();

            return new JsonResponse([
                'id' => $batch->getId(),
                'qty' => $batch->getQty(),
                'unit_price' => $batch->getUnitPrice(),
                'acquired_on' => $batch->getAcquiredOn()->format('Y-m-d'),
                'expiry_date' => $batch->getExpiryDate()?->format('Y-m-d'),
                'status' => $batch->getStatus(),
                'batch_type' => $batch->getBatchType(),
                'is_initial' => $batch->getIsInitial(),
                'label' => $batch->getLabel(),
                'notes' => $batch->getNotes(),
                'serial_number' => $batch->getSerialNumber(),
                'rack_id' => $batch->getRackId(),
                'slot_id' => $batch->getSlotId(),
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Aktualisieren des Batches: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menge eines Batches von einem Lagerplatz zu einem anderen verschieben.
     * Body: { from_allocation_id?: string, to_rack_id?: string, to_slot_id?: string, to_container_batch_id?: string, qty: number }
     */
    #[Route('/{materialId}/batches/{batchId}/move', name: 'move_batch', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function moveBatch(string $materialId, string $batchId, Request $request): JsonResponse
    {
        $batch = $this->entityManager->getRepository(MaterialBatch::class)->find($batchId);
        if (!$batch || $batch->getMaterialItemId() !== $materialId) {
            return new JsonResponse(['error' => 'Batch nicht gefunden'], 404);
        }

        $material = $batch->getMaterialItem();
        $accessCheck = $this->assertDepartmentAccess($material->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        if ($batch->getStatus() !== 'active') {
            return new JsonResponse(['error' => 'Nur aktive Batches können verschoben werden'], 400);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $moveQty = (int) ($data['qty'] ?? 0);
        $toRackId = (string) ($data['to_rack_id'] ?? '');
        $toSlotId = isset($data['to_slot_id']) && $data['to_slot_id'] !== '' ? (string) $data['to_slot_id'] : null;
        $toContainerBatchId = isset($data['to_container_batch_id']) && $data['to_container_batch_id'] !== '' ? (string) $data['to_container_batch_id'] : null;
        $fromAllocationId = isset($data['from_allocation_id']) && $data['from_allocation_id'] !== '' ? (string) $data['from_allocation_id'] : null;

        if ($moveQty <= 0) {
            return new JsonResponse(['error' => 'Menge muss größer als 0 sein'], 400);
        }
        if ($toContainerBatchId === null && $toRackId === '') {
            return new JsonResponse(['error' => 'to_rack_id oder to_container_batch_id ist erforderlich'], 400);
        }

        $toContainerBatch = null;
        $toRack = null;
        $toSlot = null;
        $targetEffectiveRackId = null;
        $targetEffectiveSlotId = null;

        if ($toContainerBatchId !== null) {
            $toContainerBatch = $this->entityManager->getRepository(MaterialBatch::class)->find($toContainerBatchId);
            if (!$toContainerBatch || !$toContainerBatch->getMaterialItem() || $toContainerBatch->getMaterialItem()->getDepartmentId() !== $material->getDepartmentId()) {
                return new JsonResponse(['error' => 'Ziel-Kiste ungültig oder gehört nicht zum Material-Department'], 400);
            }
            if ($toContainerBatch->getRackId() === null) {
                return new JsonResponse(['error' => 'Ziel-Kiste hat keinen Lagerplatz'], 400);
            }
            $targetEffectiveRackId = $toContainerBatch->getRackId();
            $targetEffectiveSlotId = $toContainerBatch->getSlotId();
        } else {
            $toRack = $this->entityManager->getRepository(StorageRack::class)->find($toRackId);
            if (!$toRack || $toRack->getDepartmentId() !== $material->getDepartmentId()) {
                return new JsonResponse(['error' => 'Ziel-Gestell ungültig oder gehört nicht zum Material-Department'], 400);
            }

            if ($toSlotId !== null) {
                $toSlot = $this->entityManager->getRepository(StorageSlot::class)->find($toSlotId);
                if (!$toSlot || $toSlot->getRackId() !== $toRackId) {
                    return new JsonResponse(['error' => 'Ziel-Platz ungültig oder gehört nicht zum Gestell'], 400);
                }
            }
            $targetEffectiveRackId = $toRackId;
            $targetEffectiveSlotId = $toSlotId;
        }

        $allocations = $batch->getAllocations();
        $hasAllocations = $allocations->count() > 0;

        if ($hasAllocations && $fromAllocationId !== null) {
            // Batch mit Allokationen: aus spezifischer Allokation verschieben
            $fromAlloc = $this->entityManager->getRepository(BatchStorageAllocation::class)->find($fromAllocationId);
            if (!$fromAlloc || $fromAlloc->getBatchId() !== $batchId) {
                return new JsonResponse(['error' => 'Quell-Allokation nicht gefunden oder gehört nicht zu dieser Charge'], 404);
            }
            $sourceQty = $fromAlloc->getQty();
            if ($moveQty > $sourceQty) {
                return new JsonResponse(['error' => 'Menge darf nicht größer als verfügbare Menge an der Quelle sein (' . $sourceQty . ')'], 400);
            }
            $fromContainerBatchId = $fromAlloc->getContainerBatchId();
            $fromRackId = $fromAlloc->getEffectiveRackId();
            $fromSlotId = $fromAlloc->getEffectiveSlotId();
            $isSameTarget = $toContainerBatchId !== null
                ? ($fromContainerBatchId !== null && $fromContainerBatchId === $toContainerBatchId)
                : ($fromContainerBatchId === null && $fromRackId === $targetEffectiveRackId && ($fromSlotId ?? '') === ($targetEffectiveSlotId ?? ''));
            if ($isSameTarget) {
                return new JsonResponse(['error' => 'Quelle und Ziel dürfen nicht identisch sein'], 400);
            }

            $fromAlloc->setQty($sourceQty - $moveQty);
            if ($fromAlloc->getQty() <= 0) {
                $batch->removeAllocation($fromAlloc);
                $this->entityManager->remove($fromAlloc);
            }

            $existingTarget = null;
            foreach ($allocations as $a) {
                $matchesTarget = $toContainerBatchId !== null
                    ? ($a->getContainerBatchId() !== null && $a->getContainerBatchId() === $toContainerBatchId)
                    : ($a->getContainerBatchId() === null && $a->getEffectiveRackId() === $targetEffectiveRackId && ($a->getEffectiveSlotId() ?? '') === ($targetEffectiveSlotId ?? ''));
                if ($matchesTarget) {
                    $existingTarget = $a;
                    break;
                }
            }
            if ($existingTarget) {
                $existingTarget->setQty($existingTarget->getQty() + $moveQty);
            } else {
                $newAlloc = new BatchStorageAllocation();
                $newAlloc->setId(IdGenerator::generate13Unique($this->entityManager, BatchStorageAllocation::class, 'al'));
                $newAlloc->setBatch($batch);
                if ($toContainerBatch !== null) {
                    $newAlloc->setContainerBatch($toContainerBatch);
                    $newAlloc->setRack(null);
                    $newAlloc->setSlot(null);
                } else {
                    $newAlloc->setContainerBatch(null);
                    $newAlloc->setRack($toRack);
                    $newAlloc->setSlot($toSlot);
                }
                $newAlloc->setQty($moveQty);
                $newAlloc->setDepartmentId($material->getDepartmentId());
                $batch->addAllocation($newAlloc);
                $this->entityManager->persist($newAlloc);
            }
        } elseif (!$hasAllocations && $batch->getRackId() !== null) {
            // Batch mit direktem rack/slot: in Allokationen überführen
            $sourceQty = $batch->getQty();
            if ($moveQty > $sourceQty) {
                return new JsonResponse(['error' => 'Menge darf nicht größer als Batch-Menge sein (' . $sourceQty . ')'], 400);
            }
            $fromRack = $batch->getRack();
            $fromSlot = $batch->getSlot();
            if ($toContainerBatchId === null && $fromRack->getId() === $targetEffectiveRackId && (($fromSlot?->getId()) ?? '') === ($targetEffectiveSlotId ?? '')) {
                return new JsonResponse(['error' => 'Quelle und Ziel dürfen nicht identisch sein'], 400);
            }

            $remainQty = $sourceQty - $moveQty;
            if ($remainQty > 0) {
                $allocStay = new BatchStorageAllocation();
                $allocStay->setId(IdGenerator::generate13Unique($this->entityManager, BatchStorageAllocation::class, 'al'));
                $allocStay->setBatch($batch);
                $allocStay->setRack($fromRack);
                $allocStay->setSlot($fromSlot);
                $allocStay->setQty($remainQty);
                $allocStay->setDepartmentId($material->getDepartmentId());
                $batch->addAllocation($allocStay);
                $this->entityManager->persist($allocStay);
            }

            $allocNew = new BatchStorageAllocation();
            $allocNew->setId(IdGenerator::generate13Unique($this->entityManager, BatchStorageAllocation::class, 'al'));
            $allocNew->setBatch($batch);
            if ($toContainerBatch !== null) {
                $allocNew->setContainerBatch($toContainerBatch);
                $allocNew->setRack(null);
                $allocNew->setSlot(null);
            } else {
                $allocNew->setContainerBatch(null);
                $allocNew->setRack($toRack);
                $allocNew->setSlot($toSlot);
            }
            $allocNew->setQty($moveQty);
            $allocNew->setDepartmentId($material->getDepartmentId());
            $batch->addAllocation($allocNew);
            $this->entityManager->persist($allocNew);

            $batch->setRack(null);
            $batch->setSlot(null);
        } else {
            return new JsonResponse(['error' => 'Charge hat keinen Lagerplatz oder from_allocation_id fehlt bei Allokationen'], 400);
        }

        $this->createHistoryEntry($material, 'batch_moved', [
            'batch_id' => ['old' => $batchId, 'new' => $batchId],
            'qty_moved' => ['old' => null, 'new' => $moveQty],
            'to_rack_id' => ['old' => null, 'new' => $toRackId],
            'to_slot_id' => ['old' => null, 'new' => $toSlotId],
            'to_container_batch_id' => ['old' => null, 'new' => $toContainerBatchId],
        ]);

        $this->entityManager->flush();

        $response = [
            'id' => $batch->getId(),
            'qty' => $batch->getQty(),
            'rack_id' => $batch->getRackId(),
            'slot_id' => $batch->getSlotId(),
        ];
        $batchAllocs = $batch->getAllocations();
        if ($batchAllocs->count() > 0) {
            $response['allocations'] = array_map(function ($a) {
                $item = [
                    'id' => $a->getId(),
                    'container_batch_id' => $a->getContainerBatchId(),
                    'rack_id' => $a->getEffectiveRackId(),
                    'slot_id' => $a->getEffectiveSlotId(),
                    'qty' => $a->getQty(),
                ];
                $cb = $a->getContainerBatch();
                if ($cb) {
                    $item['container_batch'] = [
                        'id' => $cb->getId(),
                        'material_id' => $cb->getMaterialItemId(),
                        'material_name' => $cb->getMaterialItem()->getName(),
                        'serial_number' => $cb->getSerialNumber(),
                        'label' => $cb->getLabel(),
                        'rack' => $cb->getRack() ? ['id' => $cb->getRackId(), 'name' => $cb->getRack()->getName()] : null,
                        'slot' => $cb->getSlot() ? ['id' => $cb->getSlotId(), 'name' => $cb->getSlot()->getName()] : null,
                    ];
                }
                return $item;
            }, $batchAllocs->toArray());
        }
        return new JsonResponse($response);
    }

    /**
     * Bulk-Bestand in serialisierte Einzelinstanzen splitten/konvertieren.
     * Body:
     * {
     *   "quantity": 10,
     *   "source_batch_id": "ba...",
     *   "serial_numbers": ["RAKO-001", "..."],
     *   "serial_prefix": "RAKO-",
     *   "start_number": 1,
     *   "pad_length": 3,
     *   "rack_id": "...",
     *   "slot_id": "..."
     * }
     */
    #[Route('/{id}/split-to-serialized', name: 'split_to_serialized', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function splitToSerialized(string $id, Request $request): JsonResponse
    {
        $material = $this->entityManager->getRepository(MaterialItem::class)->find($id);
        if (!$material) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }
        $accessCheck = $this->assertDepartmentAccess($material->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $quantity = max(0, (int) ($data['quantity'] ?? 0));
        if ($quantity < 1) {
            return new JsonResponse(['error' => 'quantity muss groesser als 0 sein'], 400);
        }

        // Quelle bestimmen
        $sourceBatch = null;
        if (!empty($data['source_batch_id'])) {
            $sourceBatch = $this->entityManager->getRepository(MaterialBatch::class)->find((string) $data['source_batch_id']);
            if (!$sourceBatch || $sourceBatch->getMaterialItemId() !== $material->getId()) {
                return new JsonResponse(['error' => 'source_batch_id ist ungueltig'], 400);
            }
            if ($sourceBatch->getStatus() !== 'active' || $sourceBatch->getQty() < $quantity || $sourceBatch->getSerialNumber()) {
                return new JsonResponse(['error' => 'Source-Batch muss aktiv, nicht-serialisiert und ausreichend gross sein'], 400);
            }
        } else {
            $sourceBatch = $this->entityManager->getRepository(MaterialBatch::class)
                ->createQueryBuilder('b')
                ->where('b.materialItemId = :materialId')
                ->andWhere('b.status = :status')
                ->andWhere('b.serialNumber IS NULL')
                ->andWhere('b.qty >= :qty')
                ->setParameter('materialId', $material->getId())
                ->setParameter('status', 'active')
                ->setParameter('qty', $quantity)
                ->orderBy('b.acquiredOn', 'ASC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            if (!$sourceBatch) {
                return new JsonResponse(['error' => 'Kein passender Bulk-Batch fuer den Split gefunden'], 400);
            }
        }

        $serialEntries = [];
        if (!empty($data['serial_entries']) && is_array($data['serial_entries'])) {
            foreach ($data['serial_entries'] as $entry) {
                $sn = trim((string) ($entry['serial_number'] ?? ''));
                if ($sn !== '') {
                    $label = trim((string) ($entry['label'] ?? ''));
                    $createSlot = array_key_exists('create_slot', $entry) ? !empty($entry['create_slot']) : false;
                    $serialEntries[] = ['serial_number' => $sn, 'label' => $label !== '' ? $label : null, 'create_slot' => $createSlot];
                }
            }
        }
        if (count($serialEntries) === 0 && !empty($data['serial_numbers']) && is_array($data['serial_numbers'])) {
            foreach ($data['serial_numbers'] as $entry) {
                $sn = trim((string) $entry);
                if ($sn !== '') {
                    $serialEntries[] = ['serial_number' => $sn, 'label' => null, 'create_slot' => false];
                }
            }
        }
        if (count($serialEntries) === 0) {
            $prefix = trim((string) ($data['serial_prefix'] ?? 'SER-'));
            $start = (int) ($data['start_number'] ?? 1);
            $pad = (int) ($data['pad_length'] ?? 3);
            for ($i = 0; $i < $quantity; $i++) {
                $serialEntries[] = [
                    'serial_number' => $prefix . str_pad((string) ($start + $i), max(1, $pad), '0', STR_PAD_LEFT),
                    'label' => null,
                    'create_slot' => false,
                ];
            }
        }
        $serialNumbers = array_column($serialEntries, 'serial_number');
        if (count($serialNumbers) !== $quantity) {
            return new JsonResponse(['error' => 'Anzahl serial_numbers muss genau quantity entsprechen'], 400);
        }
        if (count(array_unique($serialNumbers)) !== $quantity) {
            return new JsonResponse(['error' => 'serial_numbers enthalten Duplikate'], 400);
        }

        $existingSerialRows = $this->entityManager->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(MaterialBatch::class, 'b')
            ->where('b.materialItemId = :materialId')
            ->andWhere('b.serialNumber IN (:serials)')
            ->setParameter('materialId', $material->getId())
            ->setParameter('serials', $serialNumbers)
            ->getQuery()
            ->getSingleScalarResult();
        if ((int) $existingSerialRows > 0) {
            return new JsonResponse(['error' => 'Mindestens eine Seriennummer existiert bereits fuer dieses Material'], 400);
        }

        $rack = null;
        if (!empty($data['rack_id'])) {
            $rack = $this->entityManager->getRepository(StorageRack::class)->find((string) $data['rack_id']);
            if (!$rack || $rack->getDepartmentId() !== $material->getDepartmentId()) {
                return new JsonResponse(['error' => 'rack_id ist ungueltig'], 400);
            }
        }
        $slot = null;
        if (!empty($data['slot_id'])) {
            $slot = $this->entityManager->getRepository(StorageSlot::class)->find((string) $data['slot_id']);
            if (!$slot || ($rack && $slot->getRackId() !== $rack->getId()) || $slot->getRack()->getDepartmentId() !== $material->getDepartmentId()) {
                return new JsonResponse(['error' => 'slot_id ist ungueltig'], 400);
            }
        }
        $defaultContainerBatch = null;
        if (!empty($data['container_batch_id'])) {
            $defaultContainerBatch = $this->entityManager->getRepository(MaterialBatch::class)->find((string) $data['container_batch_id']);
            if (!$defaultContainerBatch || $defaultContainerBatch->getMaterialItem()->getDepartmentId() !== $material->getDepartmentId()) {
                return new JsonResponse(['error' => 'container_batch_id ist ungueltig'], 400);
            }
        }

        $serialAllocations = $data['serial_allocations'] ?? [];
        $allocMap = [];
        foreach ($serialAllocations as $a) {
            $sn = trim((string) ($a['serial_number'] ?? ''));
            if ($sn !== '') {
                $allocMap[$sn] = $a;
            }
        }
        $createSlotPerSerial = !empty($data['create_slot_per_serial']) && $rack !== null;
        $slotRepo = $this->entityManager->getRepository(StorageSlot::class);
        // Per-entry create_slot: wenn entry['create_slot'] gesetzt, nutze das; sonst Fallback auf create_slot_per_serial

        $em = $this->entityManager;
        $connection = $em->getConnection();
        $connection->beginTransaction();

        try {
            $sourceBatch->setQty($sourceBatch->getQty() - $quantity);
            if ($sourceBatch->getQty() === 0) {
                $sourceBatch->setStatus('split_to_serial');
            }
            $conversionGroupId = 'split_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
            $created = [];

            foreach ($serialEntries as $entry) {
                $serial = $entry['serial_number'];
                $label = $entry['label'] ?? null;
                $entryCreateSlot = ($entry['create_slot'] ?? false) || ($createSlotPerSerial && !array_key_exists('create_slot', $entry));

                $newBatch = new MaterialBatch();
                $newBatch->setId(IdGenerator::generate13Unique($em, MaterialBatch::class, 'ba'));
                $newBatch->setMaterialItem($material);
                $newBatch->setQty(1);
                $newBatch->setIsInitial(false);
                $newBatch->setBatchType('split');
                $newBatch->setStatus('active');
                $newBatch->setAcquiredOn($sourceBatch->getAcquiredOn());
                $newBatch->setSerialNumber($serial);
                if ($label !== null) {
                    $newBatch->setLabel($label);
                }
                $newBatch->setSourceBatch($sourceBatch);
                $newBatch->setConversionGroupId($conversionGroupId);
                $newBatch->setUnitPrice($sourceBatch->getUnitPrice());
                $newBatch->setSupplier($sourceBatch->getSupplier());
                $newBatch->setExpiryDate($sourceBatch->getExpiryDate());

                $batchRack = $rack ?: $sourceBatch->getRack();
                $batchSlot = $slot ?: $sourceBatch->getSlot();
                $useContainerBatch = $defaultContainerBatch;

                if (isset($allocMap[$serial])) {
                    $allocEntry = $allocMap[$serial];
                    $containerBatchId = $allocEntry['container_batch_id'] ?? null;
                    if ($containerBatchId) {
                        $containerBatch = $em->getRepository(MaterialBatch::class)->find((string) $containerBatchId);
                        if (!$containerBatch || $containerBatch->getMaterialItem()->getDepartmentId() !== $material->getDepartmentId()) {
                            $connection->rollBack();
                            return new JsonResponse(['error' => 'container_batch_id ungültig für Seriennummer ' . $serial], 400);
                        }
                        $useContainerBatch = $containerBatch;
                        $batchRack = null;
                        $batchSlot = null;
                    } else {
                        $useContainerBatch = null;
                        $rackId = $allocEntry['rack_id'] ?? null;
                        $slotId = $allocEntry['slot_id'] ?? null;
                        if ($rackId) {
                            $r = $em->getRepository(StorageRack::class)->find($rackId);
                            if ($r && $r->getDepartmentId() === $material->getDepartmentId()) {
                                $batchRack = $r;
                                if ($slotId) {
                                    $s = $slotRepo->find($slotId);
                                    if ($s && $s->getRackId() === $r->getId()) {
                                        $batchSlot = $s;
                                    }
                                }
                            }
                        }
                    }
                } elseif (!$useContainerBatch && $entryCreateSlot && $batchRack) {
                    $slotName = $label ?? $serial;
                    $existing = $slotRepo->findOneBy(['rackId' => $batchRack->getId(), 'name' => $slotName]);
                    if ($existing) {
                        $idx = 2;
                        while ($slotRepo->findOneBy(['rackId' => $batchRack->getId(), 'name' => $slotName . ' (' . $idx . ')'])) {
                            $idx++;
                        }
                        $slotName = $slotName . ' (' . $idx . ')';
                    }
                    $newSlot = new StorageSlot();
                    $newSlot->setId(IdGenerator::generate());
                    $newSlot->setRack($batchRack);
                    $newSlot->setName($slotName);
                    $em->persist($newSlot);
                    $batchSlot = $newSlot;
                }

                if ($useContainerBatch) {
                    $newBatch->setRack(null);
                    $newBatch->setSlot(null);
                    $em->persist($newBatch);
                    $allocation = new BatchStorageAllocation();
                    $allocation->setId(IdGenerator::generate13Unique($em, BatchStorageAllocation::class, 'al'));
                    $allocation->setBatch($newBatch);
                    $allocation->setContainerBatch($useContainerBatch);
                    $allocation->setQty(1);
                    $allocation->setDepartmentId($material->getDepartmentId());
                    $newBatch->addAllocation($allocation);
                    $em->persist($allocation);
                } else {
                    $newBatch->setRack($batchRack);
                    $newBatch->setSlot($batchSlot);
                    $em->persist($newBatch);
                }

                $created[] = [
                    'id' => $newBatch->getId(),
                    'serial_number' => $newBatch->getSerialNumber(),
                    'label' => $newBatch->getLabel(),
                ];
            }

            if ($material->getTrackingType() !== 'serialized') {
                $material->setTrackingType('serialized');
                $material->updateTimestamps();
            }

            $this->createHistoryEntry($material, 'split_serialized', [
                'source_batch_id' => ['old' => null, 'new' => $sourceBatch->getId()],
                'split_quantity' => ['old' => 0, 'new' => $quantity],
                'conversion_group_id' => ['old' => null, 'new' => $conversionGroupId],
            ]);

            $em->flush();
            $connection->commit();

            return new JsonResponse([
                'success' => true,
                'material_id' => $material->getId(),
                'source_batch_id' => $sourceBatch->getId(),
                'source_batch_qty_remaining' => $sourceBatch->getQty(),
                'created_count' => count($created),
                'created_batches' => $created,
                'conversion_group_id' => $conversionGroupId,
            ], 201);
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $msg = $e->getMessage();
            return new JsonResponse(['error' => 'Split fehlgeschlagen: ' . $msg], 500);
        }
    }

    /**
     * Material löschen (Soft-Delete)
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $id): JsonResponse
    {
        $material = $this->entityManager->getRepository(MaterialItem::class)->find($id);
        
        if (!$material) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }
        $accessCheck = $this->assertDepartmentAccess($material->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        // Soft-Delete
        $material->setDeletedAt(new \DateTime());
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * History Log für ein Material abrufen
     */
    #[Route('/{id}/history', name: 'history', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function history(string $id): JsonResponse
    {
        $material = $this->entityManager->getRepository(MaterialItem::class)->find($id);
        
        if (!$material) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }
        $accessCheck = $this->assertDepartmentAccess($material->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $entries = $this->entityManager->getRepository(MaterialHistory::class)
            ->createQueryBuilder('h')
            ->leftJoin('h.user', 'u')
            ->leftJoin('u.profile', 'p')
            ->addSelect('u', 'p')
            ->where('h.materialItemId = :materialId')
            ->setParameter('materialId', $id)
            ->orderBy('h.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($entries as $entry) {
            $user = $entry->getUser();
            $profile = $user?->getProfile();
            
            $result[] = [
                'id' => $entry->getId(),
                'action' => $entry->getAction(),
                'snapshot' => $entry->getSnapshot(),
                'changes' => $entry->getChanges(),
                'created_at' => $entry->getCreatedAt()->format('c'),
                'user' => $user ? [
                    'id' => $user->getId(),
                    'name' => $profile ? trim($profile->getFirstName() . ' ' . $profile->getLastName()) : 'Unbekannt',
                ] : null,
            ];
        }

        return new JsonResponse($result);
    }

    // ==========================================
    // === Combo-Component Endpoints ===
    // ==========================================

    /**
     * Combo-Komponenten eines Materials laden
     */
    #[Route('/{id}/components', name: 'components_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listComponents(string $id): JsonResponse
    {
        $material = $this->entityManager->getRepository(MaterialItem::class)->find($id);
        if (!$material) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }

        $components = $this->entityManager->getRepository(MaterialComboComponent::class)
            ->createQueryBuilder('cc')
            ->leftJoin('cc.componentMaterial', 'cm')
            ->leftJoin('cc.componentBatch', 'cb')
            ->addSelect('cm', 'cb')
            ->where('cc.parentMaterialId = :parentId')
            ->setParameter('parentId', $id)
            ->orderBy('cc.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($components as $comp) {
            $result[] = $this->serializeComboComponent($comp);
        }

        return new JsonResponse($result);
    }

    /**
     * Reverse Lookup: In welchen Kombos wird dieses Material als Komponente verwendet?
     */
    #[Route('/{id}/used-in', name: 'used_in', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function usedIn(string $id): JsonResponse
    {
        $material = $this->entityManager->getRepository(MaterialItem::class)->find($id);
        if (!$material) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }

        $components = $this->entityManager->getRepository(MaterialComboComponent::class)
            ->createQueryBuilder('cc')
            ->leftJoin('cc.parentMaterial', 'pm')
            ->leftJoin('cc.componentBatch', 'cb')
            ->addSelect('pm', 'cb')
            ->where('cc.componentMaterialId = :materialId')
            ->setParameter('materialId', $id)
            ->orderBy('pm.name', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($components as $comp) {
            $parent = $comp->getParentMaterial();
            $batch = $comp->getComponentBatch();
            $result[] = [
                'combo_id' => $parent->getId(),
                'combo_name' => $parent->getName(),
                'material_type' => $parent->getMaterialType(),
                'assignment_mode' => $comp->getAssignmentMode(),
                'component_role' => $comp->getComponentRole(),
                'batch_id' => $batch?->getId(),
                'batch_serial' => $batch?->getSerialNumber(),
                'qty' => $comp->getQty(),
                'is_optional' => $comp->getIsOptional(),
            ];
        }

        return new JsonResponse($result);
    }

    /**
     * Komponente zu einem Combo-Material hinzufügen
     */
    #[Route('/{id}/components', name: 'components_add', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addComponent(string $id, Request $request): JsonResponse
    {
        $parentMaterial = $this->entityManager->getRepository(MaterialItem::class)->find($id);
        if (!$parentMaterial) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }

        if (!in_array($parentMaterial->getMaterialType(), ['physical_combo', 'virtual_combo'])) {
            return new JsonResponse(['error' => 'Material ist kein Combo-Typ'], 400);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['component_material_id'])) {
            return new JsonResponse(['error' => 'component_material_id ist erforderlich'], 400);
        }

        $componentMaterial = $this->entityManager->getRepository(MaterialItem::class)
            ->find($data['component_material_id']);
        if (!$componentMaterial) {
            return new JsonResponse(['error' => 'Komponenten-Material nicht gefunden'], 404);
        }

        try {
            $comp = new MaterialComboComponent();
            $comp->setId(IdGenerator::generate13('cc'));
            $comp->setParentMaterial($parentMaterial);
            $comp->setComponentMaterial($componentMaterial);
            $comp->setQty(isset($data['qty']) ? (int)$data['qty'] : 1);
            $comp->setComponentRole($data['component_role'] ?? null);
            $comp->setAssignmentMode($data['assignment_mode'] ?? 'bulk');
            $comp->setIsOptional($data['is_optional'] ?? false);
            $comp->setSortOrder($data['sort_order'] ?? 0);

            // Batch zuweisen (für serialized/fixed/assigned)
            if (isset($data['component_batch_id']) && $data['component_batch_id']) {
                $batch = $this->entityManager->getRepository(MaterialBatch::class)
                    ->find($data['component_batch_id']);
                if ($batch && $batch->getMaterialItemId() === $componentMaterial->getId()) {
                    $comp->setComponentBatch($batch);
                }
            }

            $this->entityManager->persist($comp);
            $this->entityManager->flush();

            return new JsonResponse($this->serializeComboComponent($comp), 201);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Hinzufügen der Komponente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Combo-Komponente bearbeiten (z.B. Batch zuweisen/ändern)
     */
    #[Route('/{materialId}/components/{compId}', name: 'components_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateComponent(string $materialId, string $compId, Request $request): JsonResponse
    {
        $comp = $this->entityManager->getRepository(MaterialComboComponent::class)->find($compId);
        if (!$comp || $comp->getParentMaterialId() !== $materialId) {
            return new JsonResponse(['error' => 'Komponente nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);

        try {
            if (isset($data['qty'])) {
                $comp->setQty((int)$data['qty']);
            }
            if (isset($data['assignment_mode'])) {
                $comp->setAssignmentMode($data['assignment_mode']);
            }
            if (isset($data['component_role'])) {
                $comp->setComponentRole($data['component_role']);
            }
            if (isset($data['is_optional'])) {
                $comp->setIsOptional((bool)$data['is_optional']);
            }
            if (isset($data['sort_order'])) {
                $comp->setSortOrder((int)$data['sort_order']);
            }

            // Batch zuweisen/ändern/entfernen
            if (array_key_exists('component_batch_id', $data)) {
                if ($data['component_batch_id']) {
                    $batch = $this->entityManager->getRepository(MaterialBatch::class)
                        ->find($data['component_batch_id']);
                    if ($batch && $batch->getMaterialItemId() === $comp->getComponentMaterialId()) {
                        $comp->setComponentBatch($batch);
                    }
                } else {
                    $comp->setComponentBatch(null);
                }
            }

            $this->entityManager->flush();

            return new JsonResponse($this->serializeComboComponent($comp));

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Aktualisieren der Komponente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Combo-Komponente entfernen
     */
    #[Route('/{materialId}/components/{compId}', name: 'components_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteComponent(string $materialId, string $compId): JsonResponse
    {
        $comp = $this->entityManager->getRepository(MaterialComboComponent::class)->find($compId);
        if (!$comp || $comp->getParentMaterialId() !== $materialId) {
            return new JsonResponse(['error' => 'Komponente nicht gefunden'], 404);
        }

        $this->entityManager->remove($comp);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    // ==========================================
    // === Private Helpers ===
    // ==========================================

    /**
     * Serialisiert eine ComboComponent für die API-Response
     */
    private function serializeComboComponent(MaterialComboComponent $comp): array
    {
        $componentMaterial = $comp->getComponentMaterial();
        $componentBatch = $comp->getComponentBatch();

        return [
            'id' => $comp->getId(),
            'parent_material_id' => $comp->getParentMaterialId(),
            'component_material' => [
                'id' => $componentMaterial->getId(),
                'name' => $componentMaterial->getName(),
                'material_type' => $componentMaterial->getMaterialType(),
                'tracking_type' => $componentMaterial->getTrackingType(),
                'total_stock' => $componentMaterial->getTotalStock(),
            ],
            'component_batch' => $componentBatch ? [
                'id' => $componentBatch->getId(),
                'serial_number' => $componentBatch->getSerialNumber(),
                'label' => $componentBatch->getLabel(),
                'status' => $componentBatch->getStatus(),
                'qty' => $componentBatch->getQty(),
            ] : null,
            'qty' => $comp->getQty(),
            'component_role' => $comp->getComponentRole(),
            'assignment_mode' => $comp->getAssignmentMode(),
            'is_optional' => $comp->getIsOptional(),
            'sort_order' => $comp->getSortOrder(),
            'is_assigned' => $comp->isAssignedToBatch(),
            'is_awaiting' => $comp->isAwaitingAssignment(),
            'created_at' => $comp->getCreatedAt()->format('c'),
        ];
    }

    /**
     * Erstellt einen History-Eintrag für ein Material
     */
    private function createHistoryEntry(MaterialItem $material, string $action, array $changes = []): void
    {
        $history = new MaterialHistory();
        $history->setId(IdGenerator::generate13('hi'));
        $history->setMaterialItem($material);
        $history->setAction($action);
        $history->setSnapshot($this->buildSnapshot($material));
        $history->setChanges($changes);

        // User aus Security-Context
        $user = $this->getUser();
        if ($user instanceof User) {
            $history->setUser($user);
        }

        $this->entityManager->persist($history);
    }

    /**
     * Erstellt einen Snapshot des aktuellen Material-Zustands
     */
    private function buildSnapshot(MaterialItem $material): array
    {
        return [
            'name' => $material->getName(),
            'description' => $material->getDescription(),
            'category' => $material->getCategory() ? $material->getCategory()->getName() : null,
            'category_id' => $material->getCategoryId(),
            'storage_address' => $material->getStorageAddress() ? $material->getStorageAddress()->getName() : null,
            'storage_address_id' => $material->getStorageAddressId(),
            'location' => $material->getLocation(),
            'condition' => $material->getCondition(),
            'material_type' => $material->getMaterialType(),
            'tracking_type' => $material->getTrackingType(),
            'is_tent' => $material->getIsTent(),
            'color' => $material->getColor(),
            'size_length' => $material->getSizeLength(),
            'size_width' => $material->getSizeWidth(),
            'size_height' => $material->getSizeHeight(),
            'weight' => $material->getWeight(),
            'ean' => $material->getEan(),
            'barcode_tag' => $material->getBarcodeTag(),
            'manufacturer' => $material->getManufacturer(),
            'model' => $material->getModel(),
            'warranty_until' => $material->getWarrantyUntil()?->format('Y-m-d'),
            'rental_external_allowed' => $material->getRentalExternalAllowed(),
            'rental_scope' => $material->getRentalScope(),
            'rental_requires_approval' => $material->getRentalRequiresApproval(),
            'rental_price_day' => $material->getRentalPriceDay(),
            'rental_price_week' => $material->getRentalPriceWeek(),
            'rental_price_month' => $material->getRentalPriceMonth(),
            'rental_deposit' => $material->getRentalDeposit(),
            'rental_lead_days' => $material->getRentalLeadDays(),
            'rental_max_days' => $material->getRentalMaxDays(),
            'rental_notes' => $material->getRentalNotes(),
            'is_js_material' => $material->getIsJsMaterial(),
            'external_source' => $material->getExternalSource(),
            'is_consumable' => $material->getIsConsumable(),
            'sale_price' => $material->getSalePrice(),
            'min_stock' => $material->getMinStock(),
            'pack_size' => $material->getPackSize(),
            'pack_unit' => $material->getPackUnit(),
        ];
    }

    /**
     * Berechnet die Änderungen zwischen dem alten und neuen Zustand
     */
    private function computeChanges(array $oldSnapshot, array $newSnapshot): array
    {
        $changes = [];
        foreach ($newSnapshot as $key => $newValue) {
            $oldValue = $oldSnapshot[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }
        return $changes;
    }

    /**
     * Serialisiert ein Material für die API-Response
     * Erweitert mit combo_allocated und free_stock
     */
    private function serializeMaterial(MaterialItem $material, bool $includeDetails = false, ?array $activityStockData = null, ?array $comboStockData = null, ?array $openLossData = null): array
    {
        // Bestand berechnen (nach Batch-Status aufschlüsseln)
        $totalStock = 0;
        $defectStock = 0;
        $repairStockFromBatches = 0;
        $batches = $material->getBatches();
        foreach ($batches as $batch) {
            $status = $batch->getStatus();
            if ($status === 'active') {
                $totalStock += $batch->getQty();
            } elseif ($status === 'defect') {
                $defectStock += $batch->getQty();
            } elseif ($status === 'repair') {
                $repairStockFromBatches += $batch->getQty();
            }
        }

        $repairStock = $repairStockFromBatches;
        $repairStockFromWorkshop = 0;

        // Offene Werkstatt-Tickets (Reparatur) nur wenn keine Batches in Reparatur – sonst Doppelzählung
        if ($repairStock === 0) {
            $openRepairTickets = $this->entityManager->getRepository(WorkshopTicket::class)
                ->createQueryBuilder('t')
                ->leftJoin('t.issueReport', 'ir')
                ->addSelect('ir')
                ->where('t.materialItemId = :materialId')
                ->andWhere('t.type = :type')
                ->andWhere('t.status NOT IN (:completedStatuses)')
                ->setParameter('materialId', $material->getId())
                ->setParameter('type', WorkshopTicket::TYPE_REPAIR)
                ->setParameter('completedStatuses', [WorkshopTicket::STATUS_COMPLETED, WorkshopTicket::STATUS_CANCELLED])
                ->getQuery()
                ->getResult();
            foreach ($openRepairTickets as $ticket) {
                $report = $ticket->getIssueReport();
                $qty = $report ? $report->getQuantity() : 1;
                $repairStock += $qty;
                $repairStockFromWorkshop += $qty;
            }
        }

        // Activity-basierte Zahlen (draussen / reserviert)
        $issuedOut = 0;
        $reserved = 0;
        if ($activityStockData !== null) {
            $mid = $material->getId();
            $issuedOut = $activityStockData[$mid]['issued'] ?? 0;
            $reserved = $activityStockData[$mid]['reserved'] ?? 0;
        }

        // Combo-Allokation: Wie viel ist in Combos gebunden?
        $comboAllocated = 0;
        if ($comboStockData !== null) {
            $mid = $material->getId();
            $comboAllocated = $comboStockData[$mid] ?? 0;
        }

        $freeStock = max(0, $totalStock - $comboAllocated);
        $inWarehouse = $totalStock - $issuedOut;
        // Verfügbar: Bei Reparatur aus Werkstatt-Tickets sind die Stk. noch in totalStock (active Batch) – müssen abgezogen werden
        $available = max(0, $freeStock - $issuedOut - $reserved - $repairStockFromWorkshop);
        $openLossReports = 0;
        $openLossQty = 0;
        if ($openLossData !== null) {
            $mid = $material->getId();
            $openLossReports = $openLossData[$mid]['count'] ?? 0;
            $openLossQty = $openLossData[$mid]['qty'] ?? 0;
        }

        $result = [
            'id' => $material->getId(),
            'department_id' => $material->getDepartmentId(),
            'name' => $material->getName(),
            'description' => $material->getDescription(),
            'category' => $material->getCategory() ? [
                'id' => $material->getCategory()->getId(),
                'name' => $material->getCategory()->getName(),
                'parent_id' => $material->getCategory()->getParentId()
            ] : null,
            'storage_address' => $material->getStorageAddress() ? [
                'id' => $material->getStorageAddress()->getId(),
                'name' => $material->getStorageAddress()->getName(),
                'city' => $material->getStorageAddress()->getCity()
            ] : null,
            'location' => $material->getLocation(),
            'condition' => $material->getCondition(),
            'material_type' => $material->getMaterialType(),
            'tracking_type' => $material->getTrackingType(),
            'total_stock' => $totalStock,
            'defect_stock' => $defectStock,
            'repair_stock' => $repairStock,
            'combo_allocated' => $comboAllocated,
            'free_stock' => $freeStock,
            'issued_out' => $issuedOut,
            'reserved' => $reserved,
            'in_warehouse' => max(0, $inWarehouse),
            'available' => $available,
            'open_loss_reports' => $openLossReports,
            'open_loss_qty' => $openLossQty,
            'batch_count' => count($batches),
            'is_tent' => $material->getIsTent(),
            'tent_type' => $material->getTentType(),
            'tent_capacity' => $material->getTentCapacity(),
            'reservation_mode' => $material->getReservationMode(),
            'is_consumable' => $material->getIsConsumable(),
            'is_food' => $material->getIsFood(),
            'is_js_material' => $material->getIsJsMaterial(),
            'external_source' => $material->getExternalSource(),
            'sale_price' => $material->getSalePrice(),
            'min_stock' => $material->getMinStock(),
            'pack_size' => $material->getPackSize(),
            'pack_unit' => $material->getPackUnit(),
            'barcode_tag' => $material->getBarcodeTag(),
            'created_at' => $material->getCreatedAt()->format('c'),
            'updated_at' => $material->getUpdatedAt()->format('c')
        ];

        if ($includeDetails) {
            $result['color'] = $material->getColor();
            $result['material'] = $material->getMaterial();
            $result['size_length'] = $material->getSizeLength();
            $result['size_width'] = $material->getSizeWidth();
            $result['size_height'] = $material->getSizeHeight();
            $result['weight'] = $material->getWeight();
            $result['ean'] = $material->getEan();
            $result['barcode_tag'] = $material->getBarcodeTag();
            $result['manufacturer'] = $material->getManufacturer();
            $result['model'] = $material->getModel();
            $result['warranty_until'] = $material->getWarrantyUntil()?->format('Y-m-d');
            
            // Verleih
            $result['rental_external_allowed'] = $material->getRentalExternalAllowed();
            $result['rental_scope'] = $material->getRentalScope();
            $result['rental_requires_approval'] = $material->getRentalRequiresApproval();
            $result['rental_price_day'] = $material->getRentalPriceDay();
            $result['rental_price_week'] = $material->getRentalPriceWeek();
            $result['rental_price_month'] = $material->getRentalPriceMonth();
            $result['rental_deposit'] = $material->getRentalDeposit();
            $result['rental_lead_days'] = $material->getRentalLeadDays();
            $result['rental_max_days'] = $material->getRentalMaxDays();
            $result['rental_notes'] = $material->getRentalNotes();
            
            // Batches
            $result['batches'] = [];
            foreach ($batches as $batch) {
                $batchData = [
                    'id' => $batch->getId(),
                    'qty' => $batch->getQty(),
                    'unit_price' => $batch->getUnitPrice(),
                    'acquired_on' => $batch->getAcquiredOn()->format('Y-m-d'),
                    'expiry_date' => $batch->getExpiryDate()?->format('Y-m-d'),
                    'status' => $batch->getStatus(),
                    'batch_type' => $batch->getBatchType(),
                    'is_initial' => $batch->getIsInitial(),
                    'label' => $batch->getLabel(),
                    'notes' => $batch->getNotes(),
                    'serial_number' => $batch->getSerialNumber(),
                    'rack_id' => $batch->getRackId(),
                    'slot_id' => $batch->getSlotId(),
                    'rack' => $batch->getRack() ? [
                        'id' => $batch->getRack()->getId(),
                        'name' => $batch->getRack()->getName(),
                    ] : null,
                    'slot' => $batch->getSlot() ? [
                        'id' => $batch->getSlot()->getId(),
                        'name' => $batch->getSlot()->getName(),
                    ] : null,
                    'source_batch_id' => $batch->getSourceBatchId(),
                    'conversion_group_id' => $batch->getConversionGroupId(),
                ];
                // Allokationen mitsenden, falls vorhanden (Batch auf mehrere Lagerplätze verteilt)
                $allocations = $batch->getAllocations();
                if ($allocations->count() > 0) {
                    $batchData['allocations'] = $this->serializeAllocations($allocations);
                }
                $result['batches'][] = $batchData;
            }

            // Combo-Komponenten (wenn Combo-Typ)
            if (in_array($material->getMaterialType(), ['physical_combo', 'virtual_combo'])) {
                $comboComponents = $this->entityManager->getRepository(MaterialComboComponent::class)
                    ->createQueryBuilder('cc')
                    ->leftJoin('cc.componentMaterial', 'cm')
                    ->leftJoin('cc.componentBatch', 'cb')
                    ->addSelect('cm', 'cb')
                    ->where('cc.parentMaterialId = :parentId')
                    ->setParameter('parentId', $material->getId())
                    ->orderBy('cc.sortOrder', 'ASC')
                    ->getQuery()
                    ->getResult();

                $result['combo_components'] = [];
                foreach ($comboComponents as $cc) {
                    $result['combo_components'][] = $this->serializeComboComponent($cc);
                }
                $result['combo_component_count'] = count($comboComponents);
            }
        }

        return $result;
    }

    /**
     * Erstellt eine BatchStorageAllocation aus API-Payload.
     * Entweder container_batch_id ODER (rack_id + optional slot_id) – XOR.
     * @return BatchStorageAllocation|JsonResponse
     */
    private function createAllocationFromPayload(array $alloc, string $departmentId): BatchStorageAllocation|JsonResponse
    {
        $qty = (int)($alloc['qty'] ?? 0);
        if ($qty <= 0) {
            return new JsonResponse(['error' => 'Allokationsmenge muss größer als 0 sein'], 400);
        }
        $containerBatchId = $alloc['container_batch_id'] ?? null;
        $rackId = $alloc['rack_id'] ?? null;
        $hasContainer = $containerBatchId !== null && $containerBatchId !== '';
        $hasSlot = $rackId !== null && $rackId !== '';
        if ($hasContainer === $hasSlot) {
            return new JsonResponse(['error' => 'Jede Allokation muss entweder container_batch_id ODER rack_id haben (nicht beides, nicht keins)'], 400);
        }
        $allocation = new BatchStorageAllocation();
        $allocation->setQty($qty);
        if ($hasContainer) {
            $containerBatch = $this->entityManager->getRepository(MaterialBatch::class)->find((string) $containerBatchId);
            if (!$containerBatch || $containerBatch->getMaterialItem()->getDepartmentId() !== $departmentId) {
                return new JsonResponse(['error' => 'Kisten-Batch ungültig oder gehört nicht zum Department'], 400);
            }
            $allocation->setContainerBatch($containerBatch);
            $allocation->setRack(null);
            $allocation->setSlot(null);
        } else {
            $rack = $this->entityManager->getRepository(StorageRack::class)->find((string) $rackId);
            if (!$rack || $rack->getDepartmentId() !== $departmentId) {
                return new JsonResponse(['error' => 'Gestell muss zum Material-Department gehören'], 400);
            }
            $slotId = $alloc['slot_id'] ?? null;
            $slot = null;
            if ($slotId) {
                $slot = $this->entityManager->getRepository(StorageSlot::class)->find((string) $slotId);
                if (!$slot || $slot->getRackId() !== $rack->getId()) {
                    return new JsonResponse(['error' => 'Platz/Fach muss zum angegebenen Gestell gehören'], 400);
                }
            }
            $allocation->setContainerBatch(null);
            $allocation->setRack($rack);
            $allocation->setSlot($slot);
        }
        return $allocation;
    }

    /**
     * @param \Doctrine\Common\Collections\Collection<int, BatchStorageAllocation> $allocations
     */
    private function serializeAllocations(\Doctrine\Common\Collections\Collection $allocations): array
    {
        $result = [];
        foreach ($allocations as $alloc) {
            $rack = $alloc->getRack();
            $slot = $alloc->getSlot();
            $cb = $alloc->getContainerBatch();
            $rackData = $rack ? ['id' => $rack->getId(), 'name' => $rack->getName()] : null;
            $slotData = $slot ? ['id' => $slot->getId(), 'name' => $slot->getName()] : null;
            if ($cb) {
                $rackData = $cb->getRack() ? ['id' => $cb->getRackId(), 'name' => $cb->getRack()->getName()] : null;
                $slotData = $cb->getSlot() ? ['id' => $cb->getSlotId(), 'name' => $cb->getSlot()->getName()] : null;
            }
            $result[] = [
                'id' => $alloc->getId(),
                'batch_id' => $alloc->getBatchId(),
                'container_batch_id' => $alloc->getContainerBatchId(),
                'rack_id' => $alloc->getEffectiveRackId(),
                'slot_id' => $alloc->getEffectiveSlotId(),
                'qty' => $alloc->getQty(),
                'container_batch' => $cb ? [
                    'id' => $cb->getId(),
                    'material_id' => $cb->getMaterialItemId(),
                    'serial_number' => $cb->getSerialNumber(),
                    'label' => $cb->getLabel(),
                    'material_name' => $cb->getMaterialItem()->getName(),
                    'rack' => $cb->getRack() ? ['id' => $cb->getRackId(), 'name' => $cb->getRack()->getName()] : null,
                    'slot' => $cb->getSlot() ? ['id' => $cb->getSlotId(), 'name' => $cb->getSlot()->getName()] : null,
                ] : null,
                'rack' => $rackData,
                'slot' => $slotData,
            ];
        }
        return $result;
    }

    private function assertDepartmentAccess(string $departmentId): true|JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $currentUser->getId(),
            'departmentId' => $departmentId,
        ]);

        if (!$membership) {
            return new JsonResponse(['error' => 'Keine Berechtigung fuer dieses Department'], 403);
        }

        return true;
    }

    /**
     * Lädt die Activity-basierte Bestandsaufschlüsselung für alle Materialien eines Departments.
     * Ein einziger Query für die gesamte Liste – kein N+1-Problem.
     *
     * Gibt ein Array zurück: [material_item_id => ['issued' => int, 'reserved' => int]]
     * - issued:   Menge in Aktivitäten mit Status 'issued' (Material ist draussen beim Kunden)
     * - reserved: Menge in Aktivitäten mit Status submitted/approved/packing/packed (noch im Lager, aber reserviert)
     */
    private function getActivityStockBreakdown(string $departmentId): array
    {
        $conn = $this->entityManager->getConnection();

        $sql = "
            SELECT 
                ai.material_item_id,
                COALESCE(SUM(CASE WHEN a.status = 'issued' THEN ai.quantity ELSE 0 END), 0) AS issued,
                COALESCE(SUM(CASE WHEN a.status IN ('submitted', 'approved', 'packing', 'packed') THEN ai.quantity ELSE 0 END), 0) AS reserved
            FROM activity_item ai
            INNER JOIN activity a ON a.id = ai.activity_id
            WHERE a.department_id = :department_id
              AND a.deleted_at IS NULL
              AND a.status NOT IN ('draft', 'cancelled', 'completed', 'returned')
            GROUP BY ai.material_item_id
        ";

        $rows = $conn->executeQuery($sql, ['department_id' => $departmentId])->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['material_item_id']] = [
                'issued' => (int) $row['issued'],
                'reserved' => (int) $row['reserved'],
            ];
        }

        return $result;
    }

    /**
     * Lädt die Combo-Allokation für alle Materialien eines Departments.
     * Berechnet, wie viel von jedem Artikel in Combos gebunden ist.
     *
     * Für Bulk-Komponenten: Summe der qty
     * Für serialisierte Komponenten (mit component_batch_id): Anzahl zugewiesene Batches
     *
     * Gibt ein Array zurück: [material_item_id => allocated_qty]
     */
    private function getComboStockBreakdown(string $departmentId): array
    {
        $conn = $this->entityManager->getConnection();

        $sql = "
            SELECT 
                cc.component_material_id,
                SUM(cc.qty) AS allocated
            FROM material_combo_component cc
            INNER JOIN material_item parent ON parent.id = cc.parent_material_id
            WHERE parent.department_id = :department_id
              AND parent.deleted_at IS NULL
            GROUP BY cc.component_material_id
        ";

        $rows = $conn->executeQuery($sql, ['department_id' => $departmentId])->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['component_material_id']] = (int) $row['allocated'];
        }

        return $result;
    }

    /**
     * Lädt offene Verlustmeldungen (Issue Reports vom Typ "loss") pro Material.
     *
     * Gibt ein Array zurück:
     * [material_item_id => ['count' => int, 'qty' => int]]
     */
    private function getOpenLossReportBreakdown(string $departmentId): array
    {
        $conn = $this->entityManager->getConnection();

        $sql = "
            SELECT
                ir.material_item_id,
                COUNT(ir.id) AS report_count,
                COALESCE(SUM(ir.quantity), 0) AS loss_qty
            FROM activity_issue_report ir
            INNER JOIN activity a ON a.id = ir.activity_id
            WHERE a.department_id = :department_id
              AND a.deleted_at IS NULL
              AND ir.type = :loss_type
              AND ir.resolved = FALSE
              AND ir.material_item_id IS NOT NULL
            GROUP BY ir.material_item_id
        ";

        $rows = $conn->executeQuery($sql, [
            'department_id' => $departmentId,
            'loss_type' => ActivityIssueReport::TYPE_LOSS,
        ])->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['material_item_id']] = [
                'count' => (int) $row['report_count'],
                'qty' => (int) $row['loss_qty'],
            ];
        }

        return $result;
    }
}
