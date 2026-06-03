<?php

namespace App\Controller;

use App\Entity\MaterialItem;
use App\Entity\MaterialBatch;
use App\Entity\BatchStorageAllocation;
use App\Entity\MaterialHistory;
use App\Entity\MaterialComboComponent;
use App\Entity\MaterialComboOption;
use App\Entity\MaterialComboOptionDelta;
use App\Entity\MaterialComboOptionGroup;
use App\Entity\MaterialRelatedAccessory;
use App\Entity\ActivityIssueReport;
use App\Entity\WorkshopTicket;
use App\Entity\Category;
use App\Entity\Department;
use App\Entity\Address;
use App\Entity\Membership;
use App\Entity\StorageRack;
use App\Entity\StorageSlot;
use App\Entity\User;
use App\Service\Material\MaterialItemPhotoService;
use App\Service\Media\MediaPhotoNormalizer;
use App\Service\Public\PublicCodeService;
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
        private EntityManagerInterface $entityManager,
        private PublicCodeService $publicCodeService,
        private MediaPhotoNormalizer $photoNormalizer,
        private MaterialItemPhotoService $materialItemPhotoService,
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

        // Suchfilter (Name, Beschreibung, Barcode, EAN, Chargen-Seriennummer/Label) – gross/klein egal
        $search = $request->query->get('search');
        if ($search !== null && $search !== '') {
            $searchLike = '%' . mb_strtolower((string) $search) . '%';
            $batchMaterialIdsDql = $this->entityManager->createQueryBuilder()
                ->select('IDENTITY(bSearch.materialItem)')
                ->from(MaterialBatch::class, 'bSearch')
                ->where('bSearch.deletedAt IS NULL')
                ->andWhere(
                    'LOWER(COALESCE(bSearch.serialNumber, \'\')) LIKE :search
                    OR LOWER(COALESCE(bSearch.label, \'\')) LIKE :search'
                )
                ->getDQL();
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(m.name) LIKE :search',
                    'LOWER(COALESCE(m.description, \'\')) LIKE :search',
                    'LOWER(COALESCE(m.barcodeTag, \'\')) LIKE :search',
                    'LOWER(COALESCE(m.ean, \'\')) LIKE :search',
                    $qb->expr()->in('m.id', $batchMaterialIdsDql)
                )
            )->setParameter('search', $searchLike);
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
     * Physische Kombi, die diese Kisten-Charge als Referenz nutzt (Warnung beim Befüllen der Kiste).
     */
    #[Route('/container-batch/{containerBatchId}/linked-physical-combo', name: 'linked_physical_combo_for_container', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getLinkedPhysicalComboForContainerBatch(string $containerBatchId): JsonResponse
    {
        $batch = $this->entityManager->getRepository(MaterialBatch::class)->find($containerBatchId);
        if (!$batch) {
            return new JsonResponse(['error' => 'Charge nicht gefunden'], 404);
        }
        $departmentId = $batch->getMaterialItem()->getDepartmentId();
        $accessCheck = $this->assertDepartmentAccess($departmentId);
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $combo = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(MaterialItem::class, 'm')
            ->where('m.linkedContainerBatchId = :bid')
            ->andWhere('m.materialType = :mt')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('bid', $containerBatchId)
            ->setParameter('mt', 'physical_combo')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$combo instanceof MaterialItem) {
            return new JsonResponse(['physical_combo' => null]);
        }

        return new JsonResponse([
            'physical_combo' => [
                'id' => $combo->getId(),
                'name' => $combo->getName(),
            ],
        ]);
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
            ->leftJoin('m.linkedContainerBatch', 'lcb')
            ->leftJoin('lcb.materialItem', 'lcbmi')
            ->addSelect('c', 's', 'lcb', 'lcbmi')
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
     * Erzeugt (Backfill) einen Public-QR-Code für ein Material, falls noch keiner vorhanden ist.
     */
    #[Route('/{id}/public-code', name: 'ensure_public_code', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function ensurePublicCode(string $id): JsonResponse
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
        if ($material->getDeletedAt() !== null) {
            return new JsonResponse(['error' => 'Material wurde archiviert'], 400);
        }

        $accessCheck = $this->assertDepartmentAccess($material->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $this->ensurePublicCodesForMaterial($material, $this->getActorUserId());

        $this->entityManager->flush();

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

            // Kombos werden als „Hülle“ (Entwurf) angelegt und erst im Detail fertiggestellt.
            if ($material->isCombo()) {
                $material->setComboStatus('draft');
            }

            // Details
            if (array_key_exists('is_container', $data)) {
                $material->setIsContainer((bool) $data['is_container']);
            }
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
            if (array_key_exists('rental_scope', $data)) {
                $rs = $data['rental_scope'];
                $material->setRentalScope($rs !== null && $rs !== '' ? (string) $rs : null);
            }
            if (isset($data['rental_requires_approval'])) $material->setRentalRequiresApproval((bool)$data['rental_requires_approval']);
            if (isset($data['rental_price_day'])) $material->setRentalPriceDay($data['rental_price_day']);
            if (isset($data['rental_price_week'])) $material->setRentalPriceWeek($data['rental_price_week']);
            if (isset($data['rental_price_month'])) $material->setRentalPriceMonth($data['rental_price_month']);
            if (isset($data['rental_deposit'])) $material->setRentalDeposit($data['rental_deposit']);
            if (isset($data['rental_lead_days'])) $material->setRentalLeadDays((int)$data['rental_lead_days']);
            if (isset($data['rental_max_days'])) $material->setRentalMaxDays((int)$data['rental_max_days']);
            if (isset($data['rental_notes'])) $material->setRentalNotes($data['rental_notes']);
            $this->applyRentalCalcParamsFromPayload($data, $material);
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
            if (array_key_exists('reference_purchase_unit_chf', $data)) {
                $rp = $data['reference_purchase_unit_chf'];
                $material->setReferencePurchaseUnitChf($rp !== null && $rp !== '' ? (string) $rp : null);
            }
            if (isset($data['min_stock'])) $material->setMinStock((int)$data['min_stock']);

            // Verpackungseinheit (Bündel)
            if (isset($data['pack_size'])) $material->setPackSize($data['pack_size'] ? (int)$data['pack_size'] : null);
            if (isset($data['pack_unit'])) $material->setPackUnit($data['pack_unit'] ?: null);
            if (array_key_exists('pack_sale_price_chf', $data)) {
                $pp = $data['pack_sale_price_chf'];
                $material->setPackSalePriceChf($pp !== null && $pp !== '' ? (string) $pp : null);
            }
            if (array_key_exists('pack_weight', $data)) {
                $material->setPackWeight($data['pack_weight'] ?: null);
            }
            if (array_key_exists('pack_size_length', $data)) {
                $material->setPackSizeLength($data['pack_size_length'] ?: null);
            }
            if (array_key_exists('pack_size_width', $data)) {
                $material->setPackSizeWidth($data['pack_size_width'] ?: null);
            }
            if (array_key_exists('pack_size_height', $data)) {
                $material->setPackSizeHeight($data['pack_size_height'] ?: null);
            }

            $consumableFoodErr = $this->validateConsumableFoodPrices($material);
            if ($consumableFoodErr !== null) {
                return $consumableFoodErr;
            }

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

                    $batchIsContainer = array_key_exists('is_container', $serialEntry)
                        ? (bool) $serialEntry['is_container']
                        : $material->getIsContainer();
                    $batch->setIsContainer($batchIsContainer);

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
                    $this->publicCodeService->ensureBatchPublicCode($batch, $this->getActorUserId());
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

                $batch->setIsContainer($material->getIsContainer());

                $this->entityManager->persist($batch);
                if (!$this->shouldSkipBatchPublicCode($material, $batch)) {
                    $this->publicCodeService->ensureBatchPublicCode($batch, $this->getActorUserId());
                }
                $this->entityManager->flush();
            }

            $this->ensurePublicCodesForMaterial($material, $this->getActorUserId());
            $this->entityManager->flush();

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
            $comboMaterial->setIsContainer(false);
            $comboMaterial->setComboStatus('draft');

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
     * Combo aus Kisten-Inhalt erstellen (ohne Vorlage)
     * POST /api/materials/create-combo-from-container-batch
     * Body: { container_batch_id, name, material_type?, department_id, ... }
     */
    #[Route('/create-combo-from-container-batch', name: 'create_combo_from_container_batch', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createComboFromContainerBatch(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $containerBatchId = (string) ($data['container_batch_id'] ?? '');
        $name = trim((string) ($data['name'] ?? ''));
        $departmentId = (string) ($data['department_id'] ?? '');
        $materialType = $data['material_type'] ?? 'physical_combo';

        if (!$containerBatchId || !$name || !$departmentId) {
            return new JsonResponse(['error' => 'container_batch_id, name und department_id sind erforderlich'], 400);
        }
        if (!in_array($materialType, ['physical_combo', 'virtual_combo'], true)) {
            $materialType = 'physical_combo';
        }

        $accessCheck = $this->assertDepartmentAccess($departmentId);
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $containerBatch = $this->entityManager->getRepository(MaterialBatch::class)->find($containerBatchId);
        if (!$containerBatch || $containerBatch->getMaterialItem()->getDepartmentId() !== $departmentId) {
            return new JsonResponse(['error' => 'Kiste nicht gefunden oder gehört nicht zum Department'], 404);
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $conn = $this->entityManager->getConnection();
        $sql = "
            SELECT mi.id AS material_id, mi.name AS material_name, mi.tracking_type,
                   SUM(a.qty) AS qty
            FROM batch_storage_allocation a
            INNER JOIN material_batch b ON a.batch_id = b.id
            INNER JOIN material_item mi ON b.material_item_id = mi.id
            WHERE a.container_batch_id = :containerBatchId
              AND (mi.deleted_at IS NULL)
              AND b.status = 'active'
            GROUP BY mi.id, mi.name, mi.tracking_type
            ORDER BY mi.name
        ";
        $rows = $conn->executeQuery($sql, ['containerBatchId' => $containerBatchId])->fetchAllAssociative();
        if (empty($rows)) {
            return new JsonResponse(['error' => 'Kiste ist leer – keine Materialien gefunden'], 400);
        }

        try {
            $this->entityManager->beginTransaction();

            $comboMaterial = new MaterialItem();
            $comboMaterial->setId(IdGenerator::generate());
            $comboMaterial->setDepartment($department);
            $comboMaterial->setName($name);
            $comboMaterial->setMaterialType($materialType);
            $comboMaterial->setTrackingType('serialized');
            $comboMaterial->setIsContainer(false);
            $comboMaterial->setComboStatus('draft');

            if (!empty($data['category_id'])) {
                $category = $this->entityManager->getRepository(Category::class)->find($data['category_id']);
                if ($category) {
                    $comboMaterial->setCategory($category);
                }
            }
            if (!empty($data['storage_address_id'])) {
                $addr = $this->entityManager->getRepository(Address::class)->find($data['storage_address_id']);
                if ($addr && $addr->getDepartmentId() === $departmentId) {
                    $comboMaterial->setStorageAddress($addr);
                }
            }

            if ($materialType === 'physical_combo') {
                $comboMaterial->setLinkedContainerBatch($containerBatch);
            }

            $this->entityManager->persist($comboMaterial);

            $comboBatch = null;
            if ($materialType === 'physical_combo') {
                $comboBatch = new MaterialBatch();
                $comboBatch->setId(IdGenerator::generate13('ba'));
                $comboBatch->setMaterialItem($comboMaterial);
                $comboBatch->setQty(1);
                $comboBatch->setIsInitial(true);
                $comboBatch->setBatchType('initial');
                $comboBatch->setAcquiredOn(new \DateTime($data['purchase_date'] ?? 'now'));
                $this->entityManager->persist($comboBatch);

                $allocRes = $this->allocateInitialPhysicalComboBatch($comboBatch, $departmentId, $data);
                if ($allocRes instanceof JsonResponse) {
                    $this->entityManager->rollBack();
                    return $allocRes;
                }
            }

            $sortOrder = 0;
            foreach ($rows as $row) {
                $componentMaterial = $this->entityManager->getRepository(MaterialItem::class)->find($row['material_id']);
                if (!$componentMaterial) {
                    continue;
                }

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

            if ($comboBatch !== null) {
                $this->publicCodeService->reassignBatchPublicCode(
                    (string) $containerBatch->getId(),
                    (string) $comboBatch->getId()
                );
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
     * Physische Kombi: initialen Batch im Gestell/Fach oder in einer Kiste verorten.
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
            if (array_key_exists('description', $data)) {
                $material->setDescription($data['description'] !== null && $data['description'] !== '' ? (string) $data['description'] : null);
            }
            if (array_key_exists('location', $data)) {
                $material->setLocation($data['location'] !== null && $data['location'] !== '' ? (string) $data['location'] : null);
            }
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
            if (array_key_exists('is_container', $data)) {
                $material->setIsContainer((bool) $data['is_container']);
            }
            if (array_key_exists('color', $data)) {
                $material->setColor($data['color'] !== null && $data['color'] !== '' ? (string) $data['color'] : null);
            }
            if (array_key_exists('material', $data)) {
                $material->setMaterial($data['material'] !== null && $data['material'] !== '' ? (string) $data['material'] : null);
            }
            if (array_key_exists('size_length', $data)) {
                $material->setSizeLength($data['size_length'] !== null && $data['size_length'] !== '' ? (string) $data['size_length'] : null);
            }
            if (array_key_exists('size_width', $data)) {
                $material->setSizeWidth($data['size_width'] !== null && $data['size_width'] !== '' ? (string) $data['size_width'] : null);
            }
            if (array_key_exists('size_height', $data)) {
                $material->setSizeHeight($data['size_height'] !== null && $data['size_height'] !== '' ? (string) $data['size_height'] : null);
            }
            if (array_key_exists('weight', $data)) {
                $material->setWeight($data['weight'] !== null && $data['weight'] !== '' ? (string) $data['weight'] : null);
            }
            
            // Identifikation
            if (array_key_exists('ean', $data)) {
                $material->setEan($data['ean'] !== null && $data['ean'] !== '' ? (string) $data['ean'] : null);
            }
            if (array_key_exists('barcode_tag', $data)) {
                $material->setBarcodeTag($data['barcode_tag'] !== null && $data['barcode_tag'] !== '' ? (string) $data['barcode_tag'] : null);
            }
            if (array_key_exists('manufacturer', $data)) {
                $material->setManufacturer($data['manufacturer'] !== null && $data['manufacturer'] !== '' ? (string) $data['manufacturer'] : null);
            }
            if (array_key_exists('model', $data)) {
                $material->setModel($data['model'] !== null && $data['model'] !== '' ? (string) $data['model'] : null);
            }
            if (array_key_exists('warranty_until', $data)) {
                $material->setWarrantyUntil($data['warranty_until'] ? new \DateTime($data['warranty_until']) : null);
            }
            
            // Verleih
            if (isset($data['rental_external_allowed'])) $material->setRentalExternalAllowed((bool)$data['rental_external_allowed']);
            if (array_key_exists('rental_scope', $data)) {
                $rs = $data['rental_scope'];
                $material->setRentalScope($rs !== null && $rs !== '' ? (string) $rs : null);
            }
            if (isset($data['rental_requires_approval'])) $material->setRentalRequiresApproval((bool)$data['rental_requires_approval']);
            if (array_key_exists('rental_price_day', $data)) {
                $material->setRentalPriceDay($data['rental_price_day'] !== null && $data['rental_price_day'] !== '' ? (string) $data['rental_price_day'] : null);
            }
            if (array_key_exists('rental_price_week', $data)) {
                $material->setRentalPriceWeek($data['rental_price_week'] !== null && $data['rental_price_week'] !== '' ? (string) $data['rental_price_week'] : null);
            }
            if (array_key_exists('rental_price_month', $data)) {
                $material->setRentalPriceMonth($data['rental_price_month'] !== null && $data['rental_price_month'] !== '' ? (string) $data['rental_price_month'] : null);
            }
            if (array_key_exists('rental_deposit', $data)) {
                $material->setRentalDeposit($data['rental_deposit'] !== null && $data['rental_deposit'] !== '' ? (string) $data['rental_deposit'] : null);
            }
            if (array_key_exists('rental_lead_days', $data)) {
                $material->setRentalLeadDays($data['rental_lead_days'] !== null && $data['rental_lead_days'] !== '' ? (int) $data['rental_lead_days'] : null);
            }
            if (array_key_exists('rental_max_days', $data)) {
                $material->setRentalMaxDays($data['rental_max_days'] !== null && $data['rental_max_days'] !== '' ? (int) $data['rental_max_days'] : null);
            }
            if (array_key_exists('rental_notes', $data)) {
                $material->setRentalNotes($data['rental_notes'] !== null && $data['rental_notes'] !== '' ? (string) $data['rental_notes'] : null);
            }
            $this->applyRentalCalcParamsFromPayload($data, $material);
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
            if (array_key_exists('reference_purchase_unit_chf', $data)) {
                $rp = $data['reference_purchase_unit_chf'];
                $material->setReferencePurchaseUnitChf($rp !== null && $rp !== '' ? (string) $rp : null);
            }
            if (array_key_exists('min_stock', $data)) $material->setMinStock($data['min_stock'] !== null ? (int)$data['min_stock'] : null);

            // Verpackungseinheit (Bündel)
            if (array_key_exists('pack_size', $data)) $material->setPackSize($data['pack_size'] ? (int)$data['pack_size'] : null);
            if (array_key_exists('pack_unit', $data)) $material->setPackUnit($data['pack_unit'] ?: null);
            if (array_key_exists('pack_sale_price_chf', $data)) {
                $pp = $data['pack_sale_price_chf'];
                $material->setPackSalePriceChf($pp !== null && $pp !== '' ? (string) $pp : null);
            }
            if (array_key_exists('pack_weight', $data)) {
                $material->setPackWeight($data['pack_weight'] ?: null);
            }
            if (array_key_exists('pack_size_length', $data)) {
                $material->setPackSizeLength($data['pack_size_length'] ?: null);
            }
            if (array_key_exists('pack_size_width', $data)) {
                $material->setPackSizeWidth($data['pack_size_width'] ?: null);
            }
            if (array_key_exists('pack_size_height', $data)) {
                $material->setPackSizeHeight($data['pack_size_height'] ?: null);
            }

            $consumableFoodErr = $this->validateConsumableFoodPrices($material);
            if ($consumableFoodErr !== null) {
                return $consumableFoodErr;
            }

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
     * Kombo fertigstellen: Status draft → ready.
     * Mindest-Validierung: ≥ 1 Pflichtteil (nicht-optionale Komponente).
     */
    #[Route('/{id}/finalize-combo', name: 'finalize_combo', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function finalizeCombo(string $id): JsonResponse
    {
        $material = $this->entityManager->getRepository(MaterialItem::class)->find($id);
        if (!$material) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }
        $accessCheck = $this->assertDepartmentAccess($material->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }
        if (!$material->isCombo()) {
            return new JsonResponse(['error' => 'Nur Kombos können fertiggestellt werden'], 400);
        }

        // Regel (Weg B): jede Kombo braucht ≥ 1 Pflicht-Stückteil aus dem Lager (component_source = stock).
        // self_provided-Teile (z. B. Mast) zählen nie als Pflichtteil – sie sind nur Checklisten-Hinweis.
        $requiredStockCount = (int) $this->entityManager->getRepository(MaterialComboComponent::class)
            ->createQueryBuilder('cc')
            ->select('COUNT(cc.id)')
            ->where('cc.parentMaterialId = :parentId')
            ->andWhere('cc.isOptional = false')
            ->andWhere('cc.componentSource = :stock')
            ->setParameter('parentId', $id)
            ->setParameter('stock', 'stock')
            ->getQuery()
            ->getSingleScalarResult();

        if ($requiredStockCount < 1) {
            return new JsonResponse(['error' => 'Mindestens ein Pflichtteil aus dem Lager ist erforderlich, bevor die Kombo fertiggestellt werden kann'], 400);
        }

        $material->setComboStatus('ready');
        $material->updateTimestamps();
        $this->createHistoryEntry($material, 'updated', ['combo_status' => ['old' => 'draft', 'new' => 'ready']]);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeMaterial($material, true));
    }

    // ==========================================
    // === Verwandtes Zubehör (Empfehlung) ===
    // ==========================================

    /**
     * Verwandtes Zubehör eines Materials laden.
     */
    #[Route('/{id}/related-accessories', name: 'related_accessories_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listRelatedAccessories(string $id): JsonResponse
    {
        $material = $this->entityManager->getRepository(MaterialItem::class)->find($id);
        if (!$material) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }
        $accessCheck = $this->assertDepartmentAccess($material->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $accessories = $this->entityManager->getRepository(MaterialRelatedAccessory::class)
            ->createQueryBuilder('ra')
            ->leftJoin('ra.accessoryMaterial', 'am')
            ->addSelect('am')
            ->where('ra.materialId = :materialId')
            ->setParameter('materialId', $id)
            ->orderBy('ra.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($accessories as $ra) {
            $result[] = $this->serializeRelatedAccessory($ra);
        }

        return new JsonResponse($result);
    }

    /**
     * Verwandtes Zubehör verknüpfen (eigene Empfehlung, kein Stücklisten-Teil).
     */
    #[Route('/{id}/related-accessories', name: 'related_accessories_add', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addRelatedAccessory(string $id, Request $request): JsonResponse
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
        $accessoryId = $data['accessory_material_id'] ?? null;
        if (!$accessoryId) {
            return new JsonResponse(['error' => 'accessory_material_id ist erforderlich'], 400);
        }

        $accessory = $this->entityManager->getRepository(MaterialItem::class)->find($accessoryId);
        if (!$accessory) {
            return new JsonResponse(['error' => 'Zubehör-Material nicht gefunden'], 404);
        }
        if ($accessory->getId() === $material->getId()) {
            return new JsonResponse(['error' => 'Ein Material kann nicht sich selbst als Zubehör haben'], 400);
        }
        if ($accessory->getDepartmentId() !== $material->getDepartmentId()) {
            return new JsonResponse(['error' => 'Zubehör-Artikel muss zum gleichen Team gehören'], 400);
        }

        $existing = $this->entityManager->getRepository(MaterialRelatedAccessory::class)
            ->findOneBy(['materialId' => $id, 'accessoryMaterialId' => $accessory->getId()]);
        if ($existing) {
            return new JsonResponse(['error' => 'Dieses Zubehör ist bereits verknüpft'], 409);
        }

        $maxSort = (int) $this->entityManager->getRepository(MaterialRelatedAccessory::class)
            ->createQueryBuilder('ra')
            ->select('COALESCE(MAX(ra.sortOrder), -1)')
            ->where('ra.materialId = :materialId')
            ->setParameter('materialId', $id)
            ->getQuery()
            ->getSingleScalarResult();

        $ra = new MaterialRelatedAccessory();
        $ra->setId(IdGenerator::generate13Unique($this->entityManager, MaterialRelatedAccessory::class, 'ra'));
        $ra->setMaterial($material);
        $ra->setAccessoryMaterial($accessory);
        $ra->setSortOrder(isset($data['sort_order']) ? (int) $data['sort_order'] : $maxSort + 1);
        $this->entityManager->persist($ra);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeRelatedAccessory($ra), 201);
    }

    /**
     * Verwandtes Zubehör entfernen.
     */
    #[Route('/{materialId}/related-accessories/{accessoryId}', name: 'related_accessories_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteRelatedAccessory(string $materialId, string $accessoryId): JsonResponse
    {
        $ra = $this->entityManager->getRepository(MaterialRelatedAccessory::class)->find($accessoryId);
        if (!$ra || $ra->getMaterialId() !== $materialId) {
            return new JsonResponse(['error' => 'Zubehör-Verknüpfung nicht gefunden'], 404);
        }
        $accessCheck = $this->assertDepartmentAccess($ra->getMaterial()->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $this->entityManager->remove($ra);
        $this->entityManager->flush();

        return new JsonResponse(null, 204);
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
            if (array_key_exists('label', $data)) {
                $batch->setLabel($data['label'] ? (string) $data['label'] : null);
            }

            if (array_key_exists('is_container', $data)) {
                $batch->setIsContainer((bool) $data['is_container']);
            } else {
                $batch->setIsContainer($material->getIsContainer());
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
            if (!$this->shouldSkipBatchPublicCode($material, $batch)) {
                $this->publicCodeService->ensureMaterialPublicCode($material, $this->getActorUserId());
                $this->publicCodeService->ensureBatchPublicCode($batch, $this->getActorUserId());
            }

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

            $batchPublicCodeEntry = $this->publicCodeService->getActiveBatchPublicCode((string) $batch->getId());
            $batchPublicCode = $batchPublicCodeEntry?->getPublicCode();
            $batchPublicUrl = $this->resolveBatchPublicUrlForApi($material, $batch);

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
                'public_code' => $batchPublicCode,
                'public_url' => $batchPublicUrl,
                'is_container' => $batch->getIsContainer(),
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
                    $row = ['serial_number' => $sn, 'label' => $label !== '' ? $label : null];
                    if (array_key_exists('is_container', $entry)) {
                        $row['is_container'] = (bool) $entry['is_container'];
                    }
                    $entries[] = $row;
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

                if (array_key_exists('is_container', $entry)) {
                    $batch->setIsContainer((bool) $entry['is_container']);
                } else {
                    $batch->setIsContainer($material->getIsContainer());
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

                if (!$this->shouldSkipBatchPublicCode($material, $batch)) {
                    $this->publicCodeService->ensureMaterialPublicCode($material, $this->getActorUserId());
                    $this->publicCodeService->ensureBatchPublicCode($batch, $this->getActorUserId());
                }

                $batchPublicCodeEntry = $this->publicCodeService->getActiveBatchPublicCode((string) $batch->getId());
                $batchPublicCode = $batchPublicCodeEntry?->getPublicCode();
                $batchPublicUrl = $this->resolveBatchPublicUrlForApi($material, $batch);

                $created[] = [
                    'id' => $batch->getId(),
                    'qty' => 1,
                    'serial_number' => $sn,
                    'label' => $batch->getLabel(),
                    'rack_id' => $useContainerBatch ? null : $batch->getRackId(),
                    'slot_id' => $useContainerBatch ? null : $batch->getSlotId(),
                    'container_batch_id' => $useContainerBatch ? $useContainerBatch->getId() : null,
                    'public_code' => $batchPublicCode,
                    'public_url' => $batchPublicUrl,
                    'is_container' => $batch->getIsContainer(),
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
                'is_container' => $batch->getIsContainer(),
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

            if (array_key_exists('is_container', $data)) {
                $batch->setIsContainer((bool) $data['is_container']);
            }

            if (!$this->shouldSkipBatchPublicCode($material, $batch)) {
                $this->publicCodeService->ensureMaterialPublicCode($material, $this->getActorUserId());
                $this->publicCodeService->ensureBatchPublicCode($batch, $this->getActorUserId());
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
                'is_container' => $batch->getIsContainer(),
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

            $batchPublicCodeEntry = $this->publicCodeService->getActiveBatchPublicCode((string) $batch->getId());
            $batchPublicCode = $batchPublicCodeEntry?->getPublicCode();
            $batchPublicUrl = $this->resolveBatchPublicUrlForApi($material, $batch);

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
                'public_code' => $batchPublicCode,
                'public_url' => $batchPublicUrl,
                'is_container' => $batch->getIsContainer(),
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

                $this->publicCodeService->ensureBatchPublicCode($newBatch, $this->getActorUserId());
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
        $this->materialItemPhotoService->deletePhotosForMaterial($material);
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
     * Aktivitäten (inkl. Entwurf), auf denen dieses Material gebucht ist (reserviert oder ausgeliehen).
     */
    #[Route('/{id}/activity-bookings', name: 'activity_bookings', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function activityBookings(string $id, Request $request): JsonResponse
    {
        $material = $this->entityManager->getRepository(MaterialItem::class)->find($id);
        if (!$material) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }

        $departmentId = (string) $request->query->get('department_id', '');
        if ($departmentId === '' || $material->getDepartmentId() !== $departmentId) {
            return new JsonResponse(['error' => 'department_id ist erforderlich bzw. passt nicht zum Material'], 400);
        }
        $accessCheck = $this->assertDepartmentAccess($departmentId);
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $conn = $this->entityManager->getConnection();
        // Direkt auf die Aktivität gebucht + indirekt über eine Kombo (Parent steht in activity_item).
        $sql = "
            WITH direct_lines AS (
                SELECT
                    a.id AS activity_id,
                    a.no AS activity_no,
                    a.name AS activity_name,
                    a.status AS activity_status,
                    a.type AS activity_type,
                    a.usage_start,
                    a.usage_end,
                    SUM(ai.quantity) AS contrib_qty,
                    CASE
                        WHEN a.status = 'at_event' THEN 'issued'
                        WHEN a.status = 'draft' THEN 'draft'
                        ELSE 'reserved'
                    END AS booking_kind
                FROM activity_item ai
                INNER JOIN activity a ON a.id = ai.activity_id
                WHERE ai.material_item_id = :materialId
                  AND a.department_id = :departmentId
                  AND a.deleted_at IS NULL
                  AND a.status NOT IN ('cancelled', 'completed')
                GROUP BY a.id, a.no, a.name, a.status, a.type, a.usage_start, a.usage_end
            ),
            combo_lines AS (
                SELECT
                    a.id AS activity_id,
                    a.no AS activity_no,
                    a.name AS activity_name,
                    a.status AS activity_status,
                    a.type AS activity_type,
                    a.usage_start,
                    a.usage_end,
                    SUM(ai.quantity * cc.qty) AS contrib_qty,
                    CASE
                        WHEN a.status = 'at_event' THEN 'issued'
                        WHEN a.status = 'draft' THEN 'draft'
                        ELSE 'reserved'
                    END AS booking_kind,
                    string_agg(DISTINCT combo_parent.name, ', ' ORDER BY combo_parent.name) AS via_combo_material_names
                FROM activity_item ai
                INNER JOIN activity a ON a.id = ai.activity_id
                INNER JOIN material_combo_component cc ON cc.parent_material_id = ai.material_item_id
                    AND cc.component_material_id = :materialId
                INNER JOIN material_item combo_parent ON combo_parent.id = cc.parent_material_id
                WHERE a.department_id = :departmentId
                  AND a.deleted_at IS NULL
                  AND a.status NOT IN ('cancelled', 'completed')
                GROUP BY a.id, a.no, a.name, a.status, a.type, a.usage_start, a.usage_end
            )
            SELECT
                COALESCE(d.activity_id, c.activity_id) AS activity_id,
                COALESCE(d.activity_no, c.activity_no) AS activity_no,
                COALESCE(d.activity_name, c.activity_name) AS activity_name,
                COALESCE(d.activity_status, c.activity_status) AS activity_status,
                COALESCE(d.activity_type, c.activity_type) AS activity_type,
                COALESCE(d.usage_start, c.usage_start) AS usage_start,
                COALESCE(d.usage_end, c.usage_end) AS usage_end,
                (COALESCE(d.contrib_qty, 0) + COALESCE(c.contrib_qty, 0))::int AS qty,
                COALESCE(d.booking_kind, c.booking_kind) AS booking_kind,
                c.via_combo_material_names AS via_combo_material_names
            FROM direct_lines d
            FULL OUTER JOIN combo_lines c ON d.activity_id = c.activity_id
            ORDER BY COALESCE(d.usage_start, c.usage_start) ASC NULLS LAST,
                     COALESCE(d.activity_name, c.activity_name) ASC
        ";

        $rows = $conn->executeQuery($sql, [
            'materialId' => $id,
            'departmentId' => $departmentId,
        ])->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $usageStart = $row['usage_start'] ?? null;
            $usageEnd = $row['usage_end'] ?? null;
            $viaCombo = $row['via_combo_material_names'] ?? null;
            $result[] = [
                'activity_id' => (string) $row['activity_id'],
                'activity_no' => $row['activity_no'] !== null ? (int) $row['activity_no'] : null,
                'activity_name' => (string) $row['activity_name'],
                'activity_status' => (string) $row['activity_status'],
                'activity_type' => (string) $row['activity_type'],
                'usage_start' => $usageStart instanceof \DateTimeInterface
                    ? $usageStart->format('c')
                    : ($usageStart ? (string) $usageStart : null),
                'usage_end' => $usageEnd instanceof \DateTimeInterface
                    ? $usageEnd->format('c')
                    : ($usageEnd ? (string) $usageEnd : null),
                'qty' => (int) $row['qty'],
                'booking_kind' => (string) $row['booking_kind'],
                'via_combo_material_names' => $viaCombo !== null && $viaCombo !== ''
                    ? (string) $viaCombo
                    : null,
            ];
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
     * Lagerorte eines Materials: direkt (Allokationen / Batch am Platz) sowie über physische Kombinationen
     * (gleicher physischer Ort wie die Kombi-Einheit – z. B. Heringe liegen beim Zelt-Set).
     */
    #[Route('/{id}/storage-locations', name: 'storage_locations', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function storageLocations(string $id, Request $request): JsonResponse
    {
        $material = $this->entityManager->getRepository(MaterialItem::class)->find($id);
        if (!$material) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }
        $departmentId = (string) $request->query->get('department_id', '');
        if ($departmentId === '' || $material->getDepartmentId() !== $departmentId) {
            return new JsonResponse(['error' => 'department_id ist erforderlich bzw. passt nicht zum Material'], 400);
        }
        $accessCheck = $this->assertDepartmentAccess($departmentId);
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $direct = $this->collectFlatStorageLocationsForMaterial($departmentId, $id);

        $viaPhysicalCombo = [];
        $conn = $this->entityManager->getConnection();
        /** Eine Zeile pro Stücklisten-Eintrag (nicht nach Eltern gruppieren), damit component_batch_id erhalten bleibt. */
        $parentSql = "
            SELECT cc.id AS combo_component_id,
                   cc.parent_material_id,
                   p.name AS parent_name,
                   cc.component_batch_id,
                   cc.qty AS component_qty,
                   cc.assignment_mode
            FROM material_combo_component cc
            INNER JOIN material_item p ON p.id = cc.parent_material_id
            WHERE cc.component_material_id = :cid
              AND p.material_type = 'physical_combo'
              AND p.deleted_at IS NULL
            ORDER BY p.name ASC, cc.sort_order ASC, cc.id ASC
        ";
        $parents = $conn->executeQuery($parentSql, ['cid' => $id])->fetchAllAssociative();
        foreach ($parents as $row) {
            $pid = (string) $row['parent_material_id'];
            $loc = $this->collectFlatStorageLocationsForMaterial($departmentId, $pid);
            if ($loc === []) {
                continue;
            }
            $cbid = $row['component_batch_id'] ?? null;
            $parentMaterial = $this->entityManager->getRepository(MaterialItem::class)->find($pid);
            $linkedContainerBatchId = $parentMaterial?->getLinkedContainerBatchId();
            $storedQtyInContainer = 0;
            if ($linkedContainerBatchId !== null && $linkedContainerBatchId !== '') {
                $storedQtyInContainer = (int) $conn->fetchOne(
                    'SELECT COALESCE(SUM(a.qty), 0)
                     FROM batch_storage_allocation a
                     INNER JOIN material_batch b ON a.batch_id = b.id
                     WHERE a.container_batch_id = :containerBatchId
                       AND b.material_item_id = :materialId
                       AND b.status = :status',
                    [
                        'containerBatchId' => $linkedContainerBatchId,
                        'materialId' => $id,
                        'status' => 'active',
                    ]
                );
            }
            $viaPhysicalCombo[] = [
                'combo_component_id' => (string) $row['combo_component_id'],
                'parent_material_id' => $pid,
                'parent_name' => (string) $row['parent_name'],
                'parent_linked_container_batch_id' => $linkedContainerBatchId,
                'component_batch_id' => $cbid !== null && $cbid !== '' ? (string) $cbid : null,
                'component_qty' => (int) $row['component_qty'],
                'stored_qty_in_container' => $storedQtyInContainer,
                'assignment_mode' => (string) $row['assignment_mode'],
                'locations' => $loc,
            ];
        }

        return new JsonResponse([
            'direct' => $direct,
            'via_physical_combo' => $viaPhysicalCombo,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectFlatStorageLocationsForMaterial(string $departmentId, string $materialId): array
    {
        $conn = $this->entityManager->getConnection();
        $out = [];

        $allocSql = "
            SELECT COALESCE(cb.rack_id, a.rack_id) AS rack_id,
                   COALESCE(cb.slot_id, a.slot_id) AS slot_id,
                   a.qty,
                   b.id AS batch_id,
                   NULLIF(TRIM(b.serial_number), '') AS serial_number,
                   NULLIF(TRIM(b.label), '') AS batch_label,
                   a.container_batch_id,
                   r.name AS rack_name,
                   s.name AS slot_name,
                   addr.name AS storage_address_name,
                   COALESCE(NULLIF(TRIM(cb.label), ''), NULLIF(TRIM(cb.serial_number), '')) AS container_caption
            FROM batch_storage_allocation a
            INNER JOIN material_batch b ON a.batch_id = b.id
            INNER JOIN material_item mi ON b.material_item_id = mi.id
            LEFT JOIN material_batch cb ON a.container_batch_id = cb.id
            LEFT JOIN storage_rack r ON r.id = COALESCE(cb.rack_id, a.rack_id)
            LEFT JOIN storage_slot s ON s.id = COALESCE(cb.slot_id, a.slot_id)
            LEFT JOIN address addr ON addr.id = r.storage_address_id
            WHERE a.department_id = :departmentId
              AND mi.id = :materialId
              AND mi.deleted_at IS NULL
              AND b.status = 'active'
        ";
        $allocRows = $conn->executeQuery($allocSql, [
            'departmentId' => $departmentId,
            'materialId' => $materialId,
        ])->fetchAllAssociative();
        foreach ($allocRows as $row) {
            $out[] = $this->normalizeFlatStorageRow($row);
        }

        $directSql = "
            SELECT b.rack_id, b.slot_id, b.qty, b.id AS batch_id,
                   NULLIF(TRIM(b.serial_number), '') AS serial_number,
                   NULLIF(TRIM(b.label), '') AS batch_label,
                   r.name AS rack_name, s.name AS slot_name,
                   addr.name AS storage_address_name,
                   NULL AS container_caption
            FROM material_batch b
            INNER JOIN material_item mi ON b.material_item_id = mi.id
            LEFT JOIN storage_rack r ON r.id = b.rack_id
            LEFT JOIN storage_slot s ON s.id = b.slot_id
            LEFT JOIN address addr ON addr.id = r.storage_address_id
            WHERE mi.department_id = :departmentId
              AND mi.id = :materialId
              AND mi.deleted_at IS NULL
              AND b.status = 'active'
              AND b.rack_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM batch_storage_allocation a2 WHERE a2.batch_id = b.id
              )
        ";
        $directRows = $conn->executeQuery($directSql, [
            'departmentId' => $departmentId,
            'materialId' => $materialId,
        ])->fetchAllAssociative();
        foreach ($directRows as $row) {
            $out[] = $this->normalizeFlatStorageRow($row);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeFlatStorageRow(array $row): array
    {
        $rackName = trim((string) ($row['rack_name'] ?? ''));
        $slotName = trim((string) ($row['slot_name'] ?? ''));
        $addr = trim((string) ($row['storage_address_name'] ?? ''));
        $locLine = $rackName !== '' && $slotName !== ''
            ? $rackName.' / '.$slotName
            : ($rackName !== '' ? $rackName : ($slotName !== '' ? $slotName : ''));

        return [
            'rack_id' => $row['rack_id'] ?? null,
            'slot_id' => $row['slot_id'] ?? null,
            'rack_name' => $rackName !== '' ? $rackName : null,
            'slot_name' => $slotName !== '' ? $slotName : null,
            'storage_address_name' => $addr !== '' ? $addr : null,
            'location_label' => $locLine !== '' ? $locLine : null,
            'qty' => (int) ($row['qty'] ?? 0),
            'batch_id' => (string) ($row['batch_id'] ?? ''),
            'serial_number' => isset($row['serial_number']) && trim((string) $row['serial_number']) !== ''
                ? trim((string) $row['serial_number'])
                : null,
            'batch_label' => isset($row['batch_label']) && trim((string) $row['batch_label']) !== ''
                ? trim((string) $row['batch_label'])
                : null,
            'container_batch_id' => isset($row['container_batch_id']) && $row['container_batch_id'] !== null && $row['container_batch_id'] !== ''
                ? (string) $row['container_batch_id']
                : null,
            'container_caption' => isset($row['container_caption']) && trim((string) $row['container_caption']) !== ''
                ? trim((string) $row['container_caption'])
                : null,
        ];
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

        $accessCheck = $this->assertDepartmentAccess($parentMaterial->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }
        if ($componentMaterial->getId() === $parentMaterial->getId()) {
            return new JsonResponse(['error' => 'Eine Kombination kann nicht sich selbst als Komponente haben'], 400);
        }
        if ($componentMaterial->getDepartmentId() !== $parentMaterial->getDepartmentId()) {
            return new JsonResponse(['error' => 'Komponenten-Artikel muss zum gleichen Team gehören'], 400);
        }

        $allocateToContainer = ($data['allocate_to_linked_container'] ?? true) !== false;

        $this->entityManager->beginTransaction();
        try {
            $comp = new MaterialComboComponent();
            $comp->setId(IdGenerator::generate13('cc'));
            $comp->setParentMaterial($parentMaterial);
            $comp->setComponentMaterial($componentMaterial);
            $comp->setQty(isset($data['qty']) ? (int) $data['qty'] : 1);
            $comp->setComponentRole($data['component_role'] ?? null);
            $comp->setAssignmentMode($data['assignment_mode'] ?? 'bulk');
            $comp->setIsOptional($data['is_optional'] ?? false);
            $comp->setComponentSource(
                ($data['component_source'] ?? null) === 'self_provided' ? 'self_provided' : 'stock'
            );
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

            if (
                $allocateToContainer
                && $parentMaterial->getMaterialType() === 'physical_combo'
                && $parentMaterial->getLinkedContainerBatchId()
            ) {
                $containerBatch = $this->entityManager->getRepository(MaterialBatch::class)
                    ->find($parentMaterial->getLinkedContainerBatchId());
                if (!$containerBatch) {
                    throw new \RuntimeException('Verknüpfte Kiste nicht gefunden');
                }
                $firstMovedBatchId = $this->allocateComponentStockToLinkedContainer(
                    $componentMaterial,
                    $containerBatch,
                    $comp->getQty(),
                    $parentMaterial->getDepartmentId(),
                );
                if ($firstMovedBatchId !== null && $comp->getComponentBatchId() === null) {
                    $movedBatch = $this->entityManager->getRepository(MaterialBatch::class)->find($firstMovedBatchId);
                    if ($movedBatch && $movedBatch->getMaterialItemId() === $componentMaterial->getId()) {
                        $comp->setComponentBatch($movedBatch);
                    }
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return new JsonResponse($this->serializeComboComponent($comp), 201);
        } catch (\RuntimeException $e) {
            $this->entityManager->rollback();
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            return new JsonResponse([
                'error' => 'Fehler beim Hinzufügen der Komponente: ' . $e->getMessage(),
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

        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Ungültiger JSON-Body'], 400);
        }

        $allocateToContainer = ($data['allocate_to_linked_container'] ?? true) !== false;
        $oldQty = $comp->getQty();

        $this->entityManager->beginTransaction();
        try {
            if (array_key_exists('qty', $data)) {
                $comp->setQty((int) $data['qty']);
            }
            if (array_key_exists('assignment_mode', $data)) {
                $comp->setAssignmentMode((string) $data['assignment_mode']);
            }
            if (array_key_exists('component_role', $data)) {
                $role = $data['component_role'];
                $comp->setComponentRole(
                    is_string($role) && trim($role) !== '' ? trim($role) : null
                );
            }
            if (array_key_exists('is_optional', $data)) {
                $comp->setIsOptional((bool) $data['is_optional']);
            }
            if (array_key_exists('component_source', $data)) {
                $comp->setComponentSource(
                    ((string) $data['component_source']) === 'self_provided' ? 'self_provided' : 'stock'
                );
            }
            if (array_key_exists('sort_order', $data)) {
                $comp->setSortOrder((int) $data['sort_order']);
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

            $parentMaterial = $comp->getParentMaterial();
            $qtyDelta = $comp->getQty() - $oldQty;
            if (
                $allocateToContainer
                && $qtyDelta > 0
                && $parentMaterial->getMaterialType() === 'physical_combo'
                && $parentMaterial->getLinkedContainerBatchId()
            ) {
                $containerBatch = $this->entityManager->getRepository(MaterialBatch::class)
                    ->find($parentMaterial->getLinkedContainerBatchId());
                if (!$containerBatch) {
                    throw new \RuntimeException('Verknüpfte Kiste nicht gefunden');
                }
                $this->allocateComponentStockToLinkedContainer(
                    $comp->getComponentMaterial(),
                    $containerBatch,
                    $qtyDelta,
                    $parentMaterial->getDepartmentId(),
                );
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return new JsonResponse($this->serializeComboComponent($comp));
        } catch (\RuntimeException $e) {
            $this->entityManager->rollback();
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            return new JsonResponse([
                'error' => 'Fehler beim Aktualisieren der Komponente: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Combo-Komponente entfernen
     */
    #[Route('/{materialId}/components/{compId}', name: 'components_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteComponent(string $materialId, string $compId, Request $request): JsonResponse
    {
        $comp = $this->entityManager->getRepository(MaterialComboComponent::class)->find($compId);
        if (!$comp || $comp->getParentMaterialId() !== $materialId) {
            return new JsonResponse(['error' => 'Komponente nicht gefunden'], 404);
        }

        $parentMaterial = $comp->getParentMaterial();
        $accessCheck = $this->assertDepartmentAccess($parentMaterial->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $toContainerBatchId = isset($data['release_to_container_batch_id']) && $data['release_to_container_batch_id'] !== ''
            ? (string) $data['release_to_container_batch_id']
            : null;
        $toRackId = isset($data['release_to_rack_id']) && $data['release_to_rack_id'] !== ''
            ? (string) $data['release_to_rack_id']
            : null;
        $toSlotId = isset($data['release_to_slot_id']) && $data['release_to_slot_id'] !== ''
            ? (string) $data['release_to_slot_id']
            : null;

        $this->entityManager->beginTransaction();
        try {
            if (
                $parentMaterial->getMaterialType() === 'physical_combo'
                && $parentMaterial->getLinkedContainerBatchId()
            ) {
                $containerBatch = $this->entityManager->getRepository(MaterialBatch::class)
                    ->find($parentMaterial->getLinkedContainerBatchId());
                if ($containerBatch) {
                    $qtyInContainer = $this->getQtyInContainer(
                        $comp->getComponentMaterialId(),
                        $containerBatch->getId(),
                    );
                    if ($qtyInContainer > 0) {
                        if ($toContainerBatchId === null && $toRackId === null) {
                            throw new \RuntimeException('Bitte Ziel-Lagerplatz oder Ziel-Kiste angeben.');
                        }
                        if ($toContainerBatchId === $containerBatch->getId()) {
                            throw new \RuntimeException('Ziel-Kiste darf nicht die verknüpfte Quell-Kiste sein.');
                        }
                        $this->deallocateComponentStockFromLinkedContainer(
                            $comp->getComponentMaterial(),
                            $containerBatch,
                            $comp->getQty(),
                            $parentMaterial->getDepartmentId(),
                            $toContainerBatchId,
                            $toRackId,
                            $toSlotId,
                        );
                    }
                }
            }

            $this->entityManager->remove($comp);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return new JsonResponse(['success' => true]);
        } catch (\RuntimeException $e) {
            $this->entityManager->rollback();
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            return new JsonResponse(['error' => 'Fehler beim Entfernen der Komponente: ' . $e->getMessage()], 500);
        }
    }

    // ==========================================
    // === Konfigurator: Options-Gruppen & Optionen (Weg B, Paket 6) ===
    // ==========================================

    /**
     * Options-Gruppe anlegen (nur virtuelle Kombo).
     */
    #[Route('/{id}/option-groups', name: 'option_groups_add', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addOptionGroup(string $id, Request $request): JsonResponse
    {
        $combo = $this->entityManager->getRepository(MaterialItem::class)->find($id);
        if (!$combo) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }
        if ($combo->getMaterialType() !== 'virtual_combo') {
            return new JsonResponse(['error' => 'Options-Gruppen gibt es nur bei virtuellen Kombos'], 400);
        }
        $accessCheck = $this->assertDepartmentAccess($combo->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $group = new MaterialComboOptionGroup();
        $group->setId(IdGenerator::generate13('og'));
        $group->setMaterialItem($combo);
        $group->setName('Gruppe');
        $this->applyOptionGroupData($group, $data);

        $this->entityManager->persist($group);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeComboOptionGroup($group), 201);
    }

    /**
     * Options-Gruppe bearbeiten.
     */
    #[Route('/{materialId}/option-groups/{groupId}', name: 'option_groups_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateOptionGroup(string $materialId, string $groupId, Request $request): JsonResponse
    {
        $group = $this->entityManager->getRepository(MaterialComboOptionGroup::class)->find($groupId);
        if (!$group || $group->getMaterialItemId() !== $materialId) {
            return new JsonResponse(['error' => 'Options-Gruppe nicht gefunden'], 404);
        }
        $accessCheck = $this->assertDepartmentAccess($group->getMaterialItem()->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $this->applyOptionGroupData($group, $data);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeComboOptionGroup($group));
    }

    /**
     * Options-Gruppe löschen (kaskadiert auf ihre Optionen/Deltas via FK ON DELETE CASCADE).
     */
    #[Route('/{materialId}/option-groups/{groupId}', name: 'option_groups_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteOptionGroup(string $materialId, string $groupId): JsonResponse
    {
        $group = $this->entityManager->getRepository(MaterialComboOptionGroup::class)->find($groupId);
        if (!$group || $group->getMaterialItemId() !== $materialId) {
            return new JsonResponse(['error' => 'Options-Gruppe nicht gefunden'], 404);
        }
        $accessCheck = $this->assertDepartmentAccess($group->getMaterialItem()->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        // Optionen der Gruppe explizit entfernen (DB-Kaskade greift, ORM-Konsistenz sicherstellen).
        $options = $this->entityManager->getRepository(MaterialComboOption::class)
            ->findBy(['optionGroupId' => $groupId]);
        foreach ($options as $opt) {
            $this->removeOptionWithDeltas($opt);
        }
        $this->entityManager->remove($group);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * Option anlegen (Toggle oder Gruppen-Option) inkl. Inline-Deltas.
     */
    #[Route('/{id}/options', name: 'options_add', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addOption(string $id, Request $request): JsonResponse
    {
        $combo = $this->entityManager->getRepository(MaterialItem::class)->find($id);
        if (!$combo) {
            return new JsonResponse(['error' => 'Material nicht gefunden'], 404);
        }
        if ($combo->getMaterialType() !== 'virtual_combo') {
            return new JsonResponse(['error' => 'Optionen gibt es nur bei virtuellen Kombos'], 400);
        }
        $accessCheck = $this->assertDepartmentAccess($combo->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $this->entityManager->beginTransaction();
        try {
            $option = new MaterialComboOption();
            $option->setId(IdGenerator::generate13('op'));
            $option->setMaterialItem($combo);
            $option->setName('Option');
            $groupResult = $this->applyOptionData($option, $combo, $data);
            if ($groupResult instanceof JsonResponse) {
                $this->entityManager->rollback();
                return $groupResult;
            }
            $this->entityManager->persist($option);

            $deltaResult = $this->replaceOptionDeltas($option, $combo, $data['deltas'] ?? []);
            if ($deltaResult instanceof JsonResponse) {
                $this->entityManager->rollback();
                return $deltaResult;
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return new JsonResponse($this->serializeComboOption($option), 201);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            return new JsonResponse(['error' => 'Fehler beim Anlegen der Option: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Option bearbeiten (inkl. Deltas, replace-all).
     */
    #[Route('/{materialId}/options/{optionId}', name: 'options_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateOption(string $materialId, string $optionId, Request $request): JsonResponse
    {
        $option = $this->entityManager->getRepository(MaterialComboOption::class)->find($optionId);
        if (!$option || $option->getMaterialItemId() !== $materialId) {
            return new JsonResponse(['error' => 'Option nicht gefunden'], 404);
        }
        $combo = $option->getMaterialItem();
        $accessCheck = $this->assertDepartmentAccess($combo->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $this->entityManager->beginTransaction();
        try {
            $groupResult = $this->applyOptionData($option, $combo, $data);
            if ($groupResult instanceof JsonResponse) {
                $this->entityManager->rollback();
                return $groupResult;
            }
            if (array_key_exists('deltas', $data)) {
                $deltaResult = $this->replaceOptionDeltas($option, $combo, $data['deltas'] ?? []);
                if ($deltaResult instanceof JsonResponse) {
                    $this->entityManager->rollback();
                    return $deltaResult;
                }
            }
            $this->entityManager->flush();
            $this->entityManager->commit();

            return new JsonResponse($this->serializeComboOption($option));
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            return new JsonResponse(['error' => 'Fehler beim Aktualisieren der Option: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Option löschen (kaskadiert auf ihre Deltas).
     */
    #[Route('/{materialId}/options/{optionId}', name: 'options_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteOption(string $materialId, string $optionId): JsonResponse
    {
        $option = $this->entityManager->getRepository(MaterialComboOption::class)->find($optionId);
        if (!$option || $option->getMaterialItemId() !== $materialId) {
            return new JsonResponse(['error' => 'Option nicht gefunden'], 404);
        }
        $accessCheck = $this->assertDepartmentAccess($option->getMaterialItem()->getDepartmentId());
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $this->removeOptionWithDeltas($option);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    // ==========================================
    // === Private Helpers ===
    // ==========================================

    /**
     * @param array<string, mixed> $data
     */
    private function applyOptionGroupData(MaterialComboOptionGroup $group, array $data): void
    {
        if (array_key_exists('name', $data)) {
            $group->setName(trim((string) $data['name']) !== '' ? trim((string) $data['name']) : 'Gruppe');
        }
        if (array_key_exists('selection_type', $data)) {
            $st = (string) $data['selection_type'];
            $group->setSelectionType(in_array($st, ['exclusive', 'multi', 'quantity'], true) ? $st : 'exclusive');
        }
        if (array_key_exists('min_select', $data)) {
            $group->setMinSelect(max(0, (int) $data['min_select']));
        }
        if (array_key_exists('max_select', $data)) {
            $group->setMaxSelect($data['max_select'] === null ? null : max(0, (int) $data['max_select']));
        }
        if (array_key_exists('sort_order', $data)) {
            $group->setSortOrder((int) $data['sort_order']);
        }
    }

    /**
     * Setzt die einfachen Felder einer Option + optionale Gruppen-Zuordnung.
     *
     * @param array<string, mixed> $data
     */
    private function applyOptionData(MaterialComboOption $option, MaterialItem $combo, array $data): ?JsonResponse
    {
        if (array_key_exists('name', $data)) {
            $option->setName(trim((string) $data['name']) !== '' ? trim((string) $data['name']) : 'Option');
        }
        if (array_key_exists('display_mode', $data)) {
            $dm = (string) $data['display_mode'];
            $option->setDisplayMode($dm === 'group' ? 'group' : 'toggle');
        }
        if (array_key_exists('default_selected', $data)) {
            $option->setDefaultSelected((bool) $data['default_selected']);
        }
        if (array_key_exists('sort_order', $data)) {
            $option->setSortOrder((int) $data['sort_order']);
        }
        if (array_key_exists('option_group_id', $data)) {
            $gid = $data['option_group_id'];
            if ($gid === null || $gid === '') {
                $option->setOptionGroup(null);
            } else {
                $group = $this->entityManager->getRepository(MaterialComboOptionGroup::class)->find((string) $gid);
                if (!$group || $group->getMaterialItemId() !== $combo->getId()) {
                    return new JsonResponse(['error' => 'Options-Gruppe nicht gefunden'], 400);
                }
                $option->setOptionGroup($group);
                $option->setDisplayMode('group');
            }
        }
        return null;
    }

    /**
     * Ersetzt alle Delta-Zeilen einer Option (replace-all). Referenziert BESTEHENDE material_item.
     *
     * @param list<array<string, mixed>> $deltas
     */
    private function replaceOptionDeltas(MaterialComboOption $option, MaterialItem $combo, array $deltas): ?JsonResponse
    {
        $existing = $this->entityManager->getRepository(MaterialComboOptionDelta::class)
            ->findBy(['optionId' => $option->getId()]);
        foreach ($existing as $d) {
            $this->entityManager->remove($d);
        }

        $sort = 0;
        foreach ($deltas as $row) {
            $mid = (string) ($row['component_material_id'] ?? '');
            if ($mid === '') {
                return new JsonResponse(['error' => 'component_material_id ist je Delta-Zeile erforderlich'], 400);
            }
            $componentMaterial = $this->entityManager->getRepository(MaterialItem::class)->find($mid);
            if (!$componentMaterial) {
                return new JsonResponse(['error' => 'Komponenten-Material nicht gefunden: ' . $mid], 404);
            }
            if ($componentMaterial->getDepartmentId() !== $combo->getDepartmentId()) {
                return new JsonResponse(['error' => 'Komponenten-Artikel muss zum gleichen Team gehören'], 400);
            }
            if ($componentMaterial->getId() === $combo->getId()) {
                return new JsonResponse(['error' => 'Eine Kombo kann sich nicht selbst als Komponente haben'], 400);
            }

            $delta = new MaterialComboOptionDelta();
            $delta->setId(IdGenerator::generate13('dt'));
            $delta->setOption($option);
            $delta->setComponentMaterial($componentMaterial);
            $delta->setQtyDelta((int) ($row['qty_delta'] ?? 0));
            $delta->setAssignmentMode(($row['assignment_mode'] ?? 'bulk') === 'on_issue' ? 'on_issue' : 'bulk');
            $tracking = $row['tracking'] ?? $componentMaterial->getTrackingType();
            $delta->setTracking(is_string($tracking) && $tracking !== '' ? $tracking : null);
            $delta->setComponentSource(($row['component_source'] ?? null) === 'self_provided' ? 'self_provided' : 'stock');
            $delta->setSortOrder((int) ($row['sort_order'] ?? $sort++));
            $this->entityManager->persist($delta);
        }
        return null;
    }

    private function removeOptionWithDeltas(MaterialComboOption $option): void
    {
        $deltas = $this->entityManager->getRepository(MaterialComboOptionDelta::class)
            ->findBy(['optionId' => $option->getId()]);
        foreach ($deltas as $d) {
            $this->entityManager->remove($d);
        }
        $this->entityManager->remove($option);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadComboOptionGroups(string $comboId): array
    {
        $groups = $this->entityManager->getRepository(MaterialComboOptionGroup::class)
            ->findBy(['materialItemId' => $comboId], ['sortOrder' => 'ASC']);
        return array_map(fn (MaterialComboOptionGroup $g) => $this->serializeComboOptionGroup($g), $groups);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadComboOptions(string $comboId): array
    {
        $options = $this->entityManager->getRepository(MaterialComboOption::class)
            ->findBy(['materialItemId' => $comboId], ['sortOrder' => 'ASC']);
        return array_map(fn (MaterialComboOption $o) => $this->serializeComboOption($o), $options);
    }

    private function serializeComboOptionGroup(MaterialComboOptionGroup $g): array
    {
        return [
            'id' => $g->getId(),
            'material_item_id' => $g->getMaterialItemId(),
            'name' => $g->getName(),
            'selection_type' => $g->getSelectionType(),
            'min_select' => $g->getMinSelect(),
            'max_select' => $g->getMaxSelect(),
            'sort_order' => $g->getSortOrder(),
        ];
    }

    private function serializeComboOption(MaterialComboOption $o): array
    {
        $deltas = $this->entityManager->getRepository(MaterialComboOptionDelta::class)
            ->createQueryBuilder('d')
            ->leftJoin('d.componentMaterial', 'cm')
            ->addSelect('cm')
            ->where('d.optionId = :oid')
            ->setParameter('oid', $o->getId())
            ->orderBy('d.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        return [
            'id' => $o->getId(),
            'material_item_id' => $o->getMaterialItemId(),
            'option_group_id' => $o->getOptionGroupId(),
            'name' => $o->getName(),
            'display_mode' => $o->getDisplayMode(),
            'default_selected' => $o->getDefaultSelected(),
            'sort_order' => $o->getSortOrder(),
            'deltas' => array_map(function (MaterialComboOptionDelta $d) {
                $cm = $d->getComponentMaterial();
                return [
                    'id' => $d->getId(),
                    'option_id' => $d->getOptionId(),
                    'component_material' => [
                        'id' => $cm->getId(),
                        'name' => $cm->getName(),
                        'material_type' => $cm->getMaterialType(),
                        'tracking_type' => $cm->getTrackingType(),
                        'total_stock' => $cm->getTotalStock(),
                    ],
                    'qty_delta' => $d->getQtyDelta(),
                    'assignment_mode' => $d->getAssignmentMode(),
                    'tracking' => $d->getTracking(),
                    'component_source' => $d->getComponentSource(),
                    'sort_order' => $d->getSortOrder(),
                ];
            }, $deltas),
        ];
    }

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
            'component_source' => $comp->getComponentSource(),
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
     * Verbrauchsmaterial und Esswaren: Verkaufs- und Referenz-Einkaufspreis (je Stück) sind Pflicht.
     */
    private function validateConsumableFoodPrices(MaterialItem $material): ?JsonResponse
    {
        if (!$material->getIsConsumable() && !$material->getIsFood()) {
            return null;
        }
        $sale = $material->getSalePrice();
        $ref = $material->getReferencePurchaseUnitChf();
        $saleOk = $sale !== null && $sale !== '' && (float) $sale > 0;
        $refOk = $ref !== null && $ref !== '' && (float) $ref > 0;
        if (!$saleOk) {
            return new JsonResponse(['error' => 'Für Verbrauchsmaterial und Esswaren ist der Verkaufspreis (CHF/Stk.) Pflicht'], 400);
        }
        if (!$refOk) {
            return new JsonResponse(['error' => 'Für Verbrauchsmaterial und Esswaren ist der Einkaufspreis Referenz (CHF/Stk.) Pflicht'], 400);
        }

        return null;
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
            'linked_container_batch_id' => $material->getLinkedContainerBatchId(),
            'is_container' => $material->getIsContainer(),
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
            'rental_calc_params' => $material->getRentalCalcParams(),
            'is_js_material' => $material->getIsJsMaterial(),
            'external_source' => $material->getExternalSource(),
            'is_consumable' => $material->getIsConsumable(),
            'sale_price' => $material->getSalePrice(),
            'reference_purchase_unit_chf' => $material->getReferencePurchaseUnitChf(),
            'min_stock' => $material->getMinStock(),
            'pack_size' => $material->getPackSize(),
            'pack_unit' => $material->getPackUnit(),
            'pack_sale_price_chf' => $material->getPackSalePriceChf(),
            'pack_weight' => $material->getPackWeight(),
            'pack_size_length' => $material->getPackSizeLength(),
            'pack_size_width' => $material->getPackSizeWidth(),
            'pack_size_height' => $material->getPackSizeHeight(),
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
     * Kisten-Batch für linked_container_batch (physische Combo ↔ Referenz-Kiste).
     */
    private function serializeLinkedContainerBatch(?MaterialBatch $batch): ?array
    {
        if ($batch === null) {
            return null;
        }
        $mi = $batch->getMaterialItem();
        $sn = trim((string) ($batch->getSerialNumber() ?: ''));
        $lb = trim((string) ($batch->getLabel() ?: $mi->getName()));
        $display = $sn !== '' ? ($sn . ' – ' . $lb) : $lb;

        return [
            'id' => $batch->getId(),
            'material_id' => $mi->getId(),
            'label' => $batch->getLabel(),
            'serial_number' => $batch->getSerialNumber(),
            'material_name' => $mi->getName(),
            'display_label' => $display,
        ];
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
        $stockOutsideContainers = 0;
        $stockInContainers = 0;
        $batches = $material->getBatches();
        foreach ($batches as $batch) {
            $status = $batch->getStatus();
            if ($status === 'active') {
                $totalStock += $batch->getQty();
                $allocs = $batch->getAllocations();
                if ($allocs->count() > 0) {
                    foreach ($allocs as $alloc) {
                        $aq = $alloc->getQty();
                        if ($alloc->getContainerBatchId()) {
                            $stockInContainers += $aq;
                        } else {
                            $stockOutsideContainers += $aq;
                        }
                    }
                } elseif ($batch->getRackId() !== null) {
                    $stockOutsideContainers += $batch->getQty();
                }
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

        // Combo-Allokation: Wie viel ist in Combos gebunden (Stückliste)?
        $comboAllocated = 0;
        $comboAllocations = [];
        if ($comboStockData !== null) {
            $mid = $material->getId();
            $comboAllocated = $comboStockData['totals'][$mid] ?? 0;
            $comboAllocations = $comboStockData['by_material'][$mid] ?? [];
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

        $publicCodeEntry = $this->publicCodeService->getActiveMaterialPublicCode((string) $material->getId());
        $publicCode = $publicCodeEntry?->getPublicCode();

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
            'linked_container_batch_id' => $material->getLinkedContainerBatchId(),
            'linked_container_batch' => $this->serializeLinkedContainerBatch($material->getLinkedContainerBatch()),
            'total_stock' => $totalStock,
            'defect_stock' => $defectStock,
            'repair_stock' => $repairStock,
            'combo_allocated' => $comboAllocated,
            'combo_allocations' => $comboAllocations,
            'free_stock' => $freeStock,
            'stock_outside_containers' => $stockOutsideContainers,
            'stock_in_containers' => $stockInContainers,
            'issued_out' => $issuedOut,
            'reserved' => $reserved,
            'in_warehouse' => max(0, $inWarehouse),
            'available' => $available,
            'open_loss_reports' => $openLossReports,
            'open_loss_qty' => $openLossQty,
            'batch_count' => count($batches),
            'is_container' => $material->getIsContainer(),
            'tent_type' => $material->getTentType(),
            'tent_capacity' => $material->getTentCapacity(),
            'combo_status' => $material->getComboStatus(),
            'is_consumable' => $material->getIsConsumable(),
            'is_food' => $material->getIsFood(),
            'is_js_material' => $material->getIsJsMaterial(),
            'external_source' => $material->getExternalSource(),
            'sale_price' => $material->getSalePrice(),
            'reference_purchase_unit_chf' => $material->getReferencePurchaseUnitChf(),
            'min_stock' => $material->getMinStock(),
            'pack_size' => $material->getPackSize(),
            'pack_unit' => $material->getPackUnit(),
            'pack_sale_price_chf' => $material->getPackSalePriceChf(),
            'barcode_tag' => $material->getBarcodeTag(),
            'image_url' => $material->getPrimaryPhotoUrl(),
            'public_code' => $publicCode,
            'public_url' => null,
            'created_at' => $material->getCreatedAt()->format('c'),
            'updated_at' => $material->getUpdatedAt()->format('c')
        ];

        if ($includeDetails) {
            $result['photos'] = $this->photoNormalizer->normalizeOutgoing($material->getPhotos());
            $result['color'] = $material->getColor();
            $result['material'] = $material->getMaterial();
            $result['size_length'] = $material->getSizeLength();
            $result['size_width'] = $material->getSizeWidth();
            $result['size_height'] = $material->getSizeHeight();
            $result['weight'] = $material->getWeight();
            $result['pack_weight'] = $material->getPackWeight();
            $result['pack_size_length'] = $material->getPackSizeLength();
            $result['pack_size_width'] = $material->getPackSizeWidth();
            $result['pack_size_height'] = $material->getPackSizeHeight();
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
            $result['rental_calc_params'] = $material->getRentalCalcParams();
            
            // Batches
            $result['batches'] = [];
            foreach ($batches as $batch) {
                $batchPublicCodeEntry = $this->publicCodeService->getActiveBatchPublicCode((string) $batch->getId());
                $batchPublicCode = $batchPublicCodeEntry?->getPublicCode();
                $batchPublicUrl = $this->resolveBatchPublicUrlForApi($material, $batch);

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
                    'storage_address_name' => $this->storageAddressNameFromRack($batch->getRack()),
                    'source_batch_id' => $batch->getSourceBatchId(),
                    'conversion_group_id' => $batch->getConversionGroupId(),
                    'public_code' => $batchPublicCode,
                    'public_url' => $batchPublicUrl,
                    'is_container' => $batch->getIsContainer(),
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

                // Options-Gruppen + Optionen (Weg B / Konfigurator, Paket 6) – nur virtuelle Kombo.
                if ($material->getMaterialType() === 'virtual_combo') {
                    $result['combo_option_groups'] = $this->loadComboOptionGroups($material->getId());
                    $result['combo_options'] = $this->loadComboOptions($material->getId());
                }
            }

            // Verwandtes Zubehör (Empfehlung, kein Stücklisten-Teil) – für alle Typen
            $relatedAccessories = $this->entityManager->getRepository(MaterialRelatedAccessory::class)
                ->createQueryBuilder('ra')
                ->leftJoin('ra.accessoryMaterial', 'am')
                ->addSelect('am')
                ->where('ra.materialId = :materialId')
                ->setParameter('materialId', $material->getId())
                ->orderBy('ra.sortOrder', 'ASC')
                ->getQuery()
                ->getResult();

            $result['related_accessories'] = [];
            foreach ($relatedAccessories as $ra) {
                $result['related_accessories'][] = $this->serializeRelatedAccessory($ra);
            }
        }

        return $result;
    }

    private function serializeRelatedAccessory(MaterialRelatedAccessory $ra): array
    {
        $accessory = $ra->getAccessoryMaterial();

        return [
            'id' => $ra->getId(),
            'material_id' => $ra->getMaterialId(),
            'accessory_material' => [
                'id' => $accessory->getId(),
                'name' => $accessory->getName(),
                'material_type' => $accessory->getMaterialType(),
                'tracking_type' => $accessory->getTrackingType(),
                'total_stock' => $accessory->getTotalStock(),
            ],
            'sort_order' => $ra->getSortOrder(),
            'created_at' => $ra->getCreatedAt()->format('c'),
        ];
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
    private function storageAddressNameFromRack(?StorageRack $rack): ?string
    {
        if (!$rack) {
            return null;
        }
        $addr = $rack->getStorageAddress();
        if (!$addr) {
            return null;
        }
        $name = $addr->getName();
        if ($name === null || trim((string) $name) === '') {
            return null;
        }

        return trim((string) $name);
    }

    private function serializeAllocations(\Doctrine\Common\Collections\Collection $allocations): array
    {
        $result = [];
        foreach ($allocations as $alloc) {
            $rack = $alloc->getRack();
            $slot = $alloc->getSlot();
            $cb = $alloc->getContainerBatch();
            $rackData = $rack ? ['id' => $rack->getId(), 'name' => $rack->getName()] : null;
            $slotData = $slot ? ['id' => $slot->getId(), 'name' => $slot->getName()] : null;
            $effectiveRack = $rack;
            if ($cb) {
                $rackData = $cb->getRack() ? ['id' => $cb->getRackId(), 'name' => $cb->getRack()->getName()] : null;
                $slotData = $cb->getSlot() ? ['id' => $cb->getSlotId(), 'name' => $cb->getSlot()->getName()] : null;
                $effectiveRack = $cb->getRack() ?: $rack;
            }
            $storageAddressName = $this->storageAddressNameFromRack($effectiveRack);
            $result[] = [
                'id' => $alloc->getId(),
                'batch_id' => $alloc->getBatchId(),
                'container_batch_id' => $alloc->getContainerBatchId(),
                'rack_id' => $alloc->getEffectiveRackId(),
                'slot_id' => $alloc->getEffectiveSlotId(),
                'qty' => $alloc->getQty(),
                'storage_address_name' => $storageAddressName,
                'container_batch' => $cb ? [
                    'id' => $cb->getId(),
                    'material_id' => $cb->getMaterialItemId(),
                    'serial_number' => $cb->getSerialNumber(),
                    'label' => $cb->getLabel(),
                    'material_name' => $cb->getMaterialItem()->getName(),
                    'rack' => $cb->getRack() ? ['id' => $cb->getRackId(), 'name' => $cb->getRack()->getName()] : null,
                    'slot' => $cb->getSlot() ? ['id' => $cb->getSlotId(), 'name' => $cb->getSlot()->getName()] : null,
                    'storage_address_name' => $this->storageAddressNameFromRack($cb->getRack()),
                ] : null,
                'rack' => $rackData,
                'slot' => $slotData,
            ];
        }
        return $result;
    }

    /** Aktueller Benutzer für Metadaten am Public-Code (created_by_user_id). */
    private function getActorUserId(): ?string
    {
        $user = $this->getUser();

        return $user instanceof User ? $user->getId() : null;
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
     * - issued:   Menge in Aktivitäten mit Status at_event (Material ist draussen beim Kunden)
     * - reserved: Menge in Aktivitäten mit Status submitted/approved/packing/packed/returned (noch gesperrt bis completed)
     */
    private function getActivityStockBreakdown(string $departmentId): array
    {
        $conn = $this->entityManager->getConnection();

        $sql = "
            SELECT 
                ai.material_item_id,
                COALESCE(SUM(CASE WHEN a.status = 'at_event' THEN ai.quantity ELSE 0 END), 0) AS issued,
                COALESCE(SUM(CASE WHEN a.status IN ('submitted', 'approved', 'packing', 'packed', 'returned') THEN ai.quantity ELSE 0 END), 0) AS reserved
            FROM activity_item ai
            INNER JOIN activity a ON a.id = ai.activity_id
            WHERE a.department_id = :department_id
              AND a.deleted_at IS NULL
              AND a.status NOT IN ('draft', 'cancelled', 'completed')
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
     * Stücklisten-Mengen pro Komponenten-Material und pro Kombi-Elternartikel.
     *
     * @return array{totals: array<string, int>, by_material: array<string, list<array{parent_material_id: string, parent_name: string, qty: int}>>}
     */
    private function getComboStockBreakdown(string $departmentId): array
    {
        $conn = $this->entityManager->getConnection();

        $sql = "
            SELECT
                cc.component_material_id,
                cc.parent_material_id,
                parent.name AS parent_name,
                SUM(cc.qty) AS qty
            FROM material_combo_component cc
            INNER JOIN material_item parent ON parent.id = cc.parent_material_id
            WHERE parent.department_id = :department_id
              AND parent.deleted_at IS NULL
            GROUP BY cc.component_material_id, cc.parent_material_id, parent.name
            ORDER BY parent.name ASC
        ";

        $rows = $conn->executeQuery($sql, ['department_id' => $departmentId])->fetchAllAssociative();

        $totals = [];
        $byMaterial = [];
        foreach ($rows as $row) {
            $componentId = (string) $row['component_material_id'];
            $qty = (int) $row['qty'];
            $totals[$componentId] = ($totals[$componentId] ?? 0) + $qty;
            if (!isset($byMaterial[$componentId])) {
                $byMaterial[$componentId] = [];
            }
            $byMaterial[$componentId][] = [
                'parent_material_id' => (string) $row['parent_material_id'],
                'parent_name' => (string) $row['parent_name'],
                'qty' => $qty,
            ];
        }

        return ['totals' => $totals, 'by_material' => $byMaterial];
    }

    /**
     * Lagert freien Bestand (nicht in einer Kiste) in die verknüpfte Referenz-Kiste der physischen Kombi.
     *
     * @return string|null Erste bewegte batch_id (für optionale Charge-Zuweisung)
     */
    private function getQtyInContainer(string $materialItemId, string $containerBatchId): int
    {
        $conn = $this->entityManager->getConnection();
        $sql = "
            SELECT COALESCE(SUM(a.qty), 0) AS qty
            FROM batch_storage_allocation a
            INNER JOIN material_batch b ON a.batch_id = b.id
            WHERE b.material_item_id = :materialId
              AND b.status = 'active'
              AND a.container_batch_id = :containerId
        ";

        return (int) $conn->executeQuery($sql, [
            'materialId' => $materialItemId,
            'containerId' => $containerBatchId,
        ])->fetchOne();
    }

    private function allocateComponentStockToLinkedContainer(
        MaterialItem $componentMaterial,
        MaterialBatch $containerBatch,
        int $qtyNeeded,
        string $departmentId,
    ): ?string {
        if ($qtyNeeded <= 0) {
            return null;
        }
        if ($containerBatch->getRackId() === null) {
            throw new \RuntimeException('Die verknüpfte Kiste hat keinen Lagerplatz – zuerst Kiste einlagern');
        }

        $containerId = $containerBatch->getId();
        $alreadyInContainer = $this->getQtyInContainer($componentMaterial->getId(), $containerId);
        $remaining = max(0, $qtyNeeded - $alreadyInContainer);
        if ($remaining === 0) {
            return null;
        }

        $firstBatchId = null;

        $batches = $this->entityManager->getRepository(MaterialBatch::class)
            ->createQueryBuilder('b')
            ->where('b.materialItemId = :materialId')
            ->andWhere('b.status = :status')
            ->andWhere('b.qty > 0')
            ->setParameter('materialId', $componentMaterial->getId())
            ->setParameter('status', 'active')
            ->orderBy('b.acquiredOn', 'ASC')
            ->addOrderBy('b.id', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $moved = $this->moveLooseStockFromBatchToContainer($batch, $containerBatch, $remaining, $departmentId);
            if ($moved > 0 && $firstBatchId === null) {
                $firstBatchId = $batch->getId();
            }
            $remaining -= $moved;
        }

        if ($remaining > 0) {
            $remaining -= $this->moveStockFromOtherContainersToContainer(
                $componentMaterial,
                $containerBatch,
                $remaining,
                $departmentId,
            );
        }

        if ($remaining > 0) {
            $bookedNow = $qtyNeeded - $alreadyInContainer - $remaining;
            throw new \RuntimeException(
                'Es konnten nur ' . $bookedNow . ' von ' . $qtyNeeded . ' Stk. in die verknüpfte Kiste gebucht werden'
                . ($alreadyInContainer > 0 ? ' (' . $alreadyInContainer . ' lagen bereits in der Kiste)' : '')
                . '. Fehlend: ' . $remaining . ' Stk. – Bestand zuerst ins Lager buchen oder aus anderem Fach/Kiste verschieben.'
            );
        }

        return $firstBatchId;
    }

    /**
     * Umbuchen aus anderen Kisten in die Ziel-Kiste (wenn kein loses Regal-Fach-Bestand reicht).
     */
    private function moveStockFromOtherContainersToContainer(
        MaterialItem $componentMaterial,
        MaterialBatch $containerBatch,
        int $maxQty,
        string $departmentId,
    ): int {
        if ($maxQty <= 0) {
            return 0;
        }

        $targetId = $containerBatch->getId();
        $moved = 0;
        $material = $componentMaterial;

        $batches = $this->entityManager->getRepository(MaterialBatch::class)
            ->createQueryBuilder('b')
            ->where('b.materialItemId = :materialId')
            ->andWhere('b.status = :status')
            ->andWhere('b.qty > 0')
            ->setParameter('materialId', $componentMaterial->getId())
            ->setParameter('status', 'active')
            ->orderBy('b.acquiredOn', 'ASC')
            ->addOrderBy('b.id', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($batches as $batch) {
            if ($moved >= $maxQty) {
                break;
            }
            foreach ($batch->getAllocations() as $fromAlloc) {
                if ($moved >= $maxQty) {
                    break;
                }
                $sourceContainerId = $fromAlloc->getContainerBatchId();
                if (!$sourceContainerId || $sourceContainerId === $targetId) {
                    continue;
                }
                $take = min($fromAlloc->getQty(), $maxQty - $moved);
                $this->transferAllocationQtyToContainer($fromAlloc, $containerBatch, $take, $material, $batch);
                $moved += $take;
            }
        }

        return $moved;
    }

    /**
     * Verschiebt Menge aus «losem» Lager (rack/slot, nicht in Kiste) in die Ziel-Kiste.
     */
    private function moveLooseStockFromBatchToContainer(
        MaterialBatch $batch,
        MaterialBatch $containerBatch,
        int $maxQty,
        string $departmentId,
    ): int {
        if ($maxQty <= 0 || $batch->getStatus() !== 'active') {
            return 0;
        }

        $moved = 0;
        $material = $batch->getMaterialItem();
        $allocations = $batch->getAllocations();
        $containerId = $containerBatch->getId();

        if ($allocations->count() > 0) {
            foreach ($allocations as $fromAlloc) {
                if ($moved >= $maxQty) {
                    break;
                }
                if ($fromAlloc->getContainerBatchId() !== null) {
                    continue;
                }
                $take = min($fromAlloc->getQty(), $maxQty - $moved);
                $this->transferAllocationQtyToContainer($fromAlloc, $containerBatch, $take, $material, $batch);
                $moved += $take;
            }

            return $moved;
        }

        if ($batch->getRackId() === null) {
            return 0;
        }

        $take = min($batch->getQty(), $maxQty);
        $fromRack = $batch->getRack();
        $fromSlot = $batch->getSlot();
        $remainQty = $batch->getQty() - $take;

        if ($remainQty > 0) {
            $allocStay = new BatchStorageAllocation();
            $allocStay->setId(IdGenerator::generate13Unique($this->entityManager, BatchStorageAllocation::class, 'al'));
            $allocStay->setBatch($batch);
            $allocStay->setRack($fromRack);
            $allocStay->setSlot($fromSlot);
            $allocStay->setQty($remainQty);
            $allocStay->setDepartmentId($departmentId);
            $batch->addAllocation($allocStay);
            $this->entityManager->persist($allocStay);
        }

        $allocNew = new BatchStorageAllocation();
        $allocNew->setId(IdGenerator::generate13Unique($this->entityManager, BatchStorageAllocation::class, 'al'));
        $allocNew->setBatch($batch);
        $allocNew->setContainerBatch($containerBatch);
        $allocNew->setRack(null);
        $allocNew->setSlot(null);
        $allocNew->setQty($take);
        $allocNew->setDepartmentId($departmentId);
        $batch->addAllocation($allocNew);
        $this->entityManager->persist($allocNew);

        $batch->setRack(null);
        $batch->setSlot(null);

        return $take;
    }

    /**
     * Entfernt Komponenten-Bestand aus der verknüpften Kiste (max. Stücklisten-Menge) ins gewählte Ziel.
     */
    private function deallocateComponentStockFromLinkedContainer(
        MaterialItem $componentMaterial,
        MaterialBatch $sourceContainerBatch,
        int $qtyLimit,
        string $departmentId,
        ?string $toContainerBatchId,
        ?string $toRackId,
        ?string $toSlotId,
    ): void {
        if ($qtyLimit <= 0) {
            return;
        }

        $containerId = $sourceContainerBatch->getId();
        $remaining = $qtyLimit;
        $material = $componentMaterial;

        $toContainerBatch = null;
        $toRack = null;
        $toSlot = null;

        if ($toContainerBatchId !== null) {
            $toContainerBatch = $this->entityManager->getRepository(MaterialBatch::class)->find($toContainerBatchId);
            if (!$toContainerBatch || !$toContainerBatch->getMaterialItem() || $toContainerBatch->getMaterialItem()->getDepartmentId() !== $departmentId) {
                throw new \RuntimeException('Ziel-Kiste ungültig oder gehört nicht zum Material-Department');
            }
            if ($toContainerBatch->getRackId() === null) {
                throw new \RuntimeException('Ziel-Kiste hat keinen Lagerplatz');
            }
        } else {
            if ($toRackId === null || $toRackId === '') {
                throw new \RuntimeException('Ziel-Regal ist erforderlich');
            }
            $toRack = $this->entityManager->getRepository(StorageRack::class)->find($toRackId);
            if (!$toRack || $toRack->getDepartmentId() !== $departmentId) {
                throw new \RuntimeException('Ziel-Gestell ungültig oder gehört nicht zum Material-Department');
            }
            if ($toSlotId !== null && $toSlotId !== '') {
                $toSlot = $this->entityManager->getRepository(StorageSlot::class)->find($toSlotId);
                if (!$toSlot || $toSlot->getRackId() !== $toRackId) {
                    throw new \RuntimeException('Ziel-Platz ungültig oder gehört nicht zum Gestell');
                }
            }
        }

        $batches = $this->entityManager->getRepository(MaterialBatch::class)
            ->createQueryBuilder('b')
            ->where('b.materialItemId = :materialId')
            ->andWhere('b.status = :status')
            ->andWhere('b.qty > 0')
            ->setParameter('materialId', $componentMaterial->getId())
            ->setParameter('status', 'active')
            ->orderBy('b.acquiredOn', 'ASC')
            ->addOrderBy('b.id', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            foreach ($batch->getAllocations() as $fromAlloc) {
                if ($remaining <= 0) {
                    break;
                }
                if ($fromAlloc->getContainerBatchId() !== $containerId) {
                    continue;
                }
                $take = min($fromAlloc->getQty(), $remaining);
                if ($toContainerBatch !== null) {
                    $this->transferAllocationQtyToContainer($fromAlloc, $toContainerBatch, $take, $material, $batch);
                } else {
                    $this->transferAllocationQtyFromContainerToRack($fromAlloc, $toRack, $toSlot, $take, $material, $batch);
                }
                $remaining -= $take;
            }
        }
    }

    private function transferAllocationQtyFromContainerToRack(
        BatchStorageAllocation $fromAlloc,
        StorageRack $rack,
        ?StorageSlot $slot,
        int $moveQty,
        MaterialItem $material,
        MaterialBatch $batch,
    ): void {
        if ($moveQty <= 0) {
            return;
        }

        $sourceQty = $fromAlloc->getQty();
        if ($moveQty > $sourceQty) {
            throw new \RuntimeException('Interner Fehler: Menge grösser als Kisten-Allokation');
        }

        $rackId = $rack->getId();
        $slotId = $slot?->getId();

        $fromAlloc->setQty($sourceQty - $moveQty);
        if ($fromAlloc->getQty() <= 0) {
            $batch->removeAllocation($fromAlloc);
            $this->entityManager->remove($fromAlloc);
        }

        $existingLoose = null;
        foreach ($batch->getAllocations() as $a) {
            if ($a->getContainerBatchId() !== null) {
                continue;
            }
            if ($a->getEffectiveRackId() === $rackId && ($a->getEffectiveSlotId() ?? '') === ($slotId ?? '')) {
                $existingLoose = $a;
                break;
            }
        }

        if ($existingLoose) {
            $existingLoose->setQty($existingLoose->getQty() + $moveQty);
        } else {
            $newAlloc = new BatchStorageAllocation();
            $newAlloc->setId(IdGenerator::generate13Unique($this->entityManager, BatchStorageAllocation::class, 'al'));
            $newAlloc->setBatch($batch);
            $newAlloc->setContainerBatch(null);
            $newAlloc->setRack($rack);
            $newAlloc->setSlot($slot);
            $newAlloc->setQty($moveQty);
            $newAlloc->setDepartmentId($material->getDepartmentId());
            $batch->addAllocation($newAlloc);
            $this->entityManager->persist($newAlloc);
        }
    }

    private function transferAllocationQtyToContainer(
        BatchStorageAllocation $fromAlloc,
        MaterialBatch $containerBatch,
        int $moveQty,
        MaterialItem $material,
        MaterialBatch $batch,
    ): void {
        if ($moveQty <= 0) {
            return;
        }

        $containerId = $containerBatch->getId();
        $sourceQty = $fromAlloc->getQty();
        if ($moveQty > $sourceQty) {
            throw new \RuntimeException('Interner Fehler: Menge grösser als Allokation');
        }

        $fromAlloc->setQty($sourceQty - $moveQty);
        if ($fromAlloc->getQty() <= 0) {
            $batch->removeAllocation($fromAlloc);
            $this->entityManager->remove($fromAlloc);
        }

        $existingTarget = null;
        foreach ($batch->getAllocations() as $a) {
            if ($a->getContainerBatchId() !== null && $a->getContainerBatchId() === $containerId) {
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
            $newAlloc->setContainerBatch($containerBatch);
            $newAlloc->setRack(null);
            $newAlloc->setSlot(null);
            $newAlloc->setQty($moveQty);
            $newAlloc->setDepartmentId($material->getDepartmentId());
            $batch->addAllocation($newAlloc);
            $this->entityManager->persist($newAlloc);
        }
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

    /**
     * @param array<string, mixed> $data
     */
    private function applyRentalCalcParamsFromPayload(array $data, MaterialItem $material): void
    {
        if (!array_key_exists('rental_calc_params', $data)) {
            return;
        }
        $v = $data['rental_calc_params'];
        if ($v === null || $v === '') {
            $material->setRentalCalcParams(null);

            return;
        }
        if (!is_array($v)) {
            return;
        }
        $allowed = ['basis_override', 'price_increase_percent_per_year', 'years_to_replacement', 'internal_days_per_year', 'external_days_per_year', 'markup_percent'];
        $out = [];
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $v)) {
                continue;
            }
            $val = $v[$k];
            if ('basis_override' === $k) {
                if (null === $val || '' === $val) {
                    $out[$k] = null;
                } else {
                    $out[$k] = trim((string) $val);
                }
            } elseif (is_numeric($val)) {
                $f = (float) $val;
                if (\in_array($k, ['years_to_replacement', 'internal_days_per_year', 'external_days_per_year'], true)) {
                    $out[$k] = (int) round($f);
                } else {
                    $out[$k] = $f;
                }
            }
        }
        $material->setRentalCalcParams([] === $out ? null : $out);
    }

    /**
     * Öffentliche Codes für Etiketten: Material-Code (Segment {mat}) + pro Charge Batch-Code.
     * Physische Combo aus Kiste: nur Batch-QR (von Kiste übernommen), kein separates Material-QR.
     */
    private function ensurePublicCodesForMaterial(MaterialItem $material, ?string $actorUserId): void
    {
        if ($material->getMaterialType() === 'physical_combo' && $material->getLinkedContainerBatchId()) {
            foreach ($material->getBatches() as $batch) {
                if ($this->shouldSkipBatchPublicCode($material, $batch)) {
                    continue;
                }
                $this->publicCodeService->ensureBatchPublicCode($batch, $actorUserId);
            }

            return;
        }

        $this->publicCodeService->ensureMaterialPublicCode($material, $actorUserId);
        foreach ($material->getBatches() as $batch) {
            if ($this->shouldSkipBatchPublicCode($material, $batch)) {
                continue;
            }
            $this->publicCodeService->ensureBatchPublicCode($batch, $actorUserId);
        }
    }

    private function shouldSkipBatchPublicCode(MaterialItem $material, MaterialBatch $batch): bool
    {
        return $material->getTrackingType() === 'serialized'
            && trim((string) $batch->getSerialNumber()) === '';
    }

    /** Kanonische Etiketten-URL (/i/m/…/b/…) — nur mit Material- und Batch-public_code. */
    private function resolveBatchPublicUrlForApi(MaterialItem $material, MaterialBatch $batch): ?string
    {
        return $this->publicCodeService->buildCanonicalMaterialBatchPublicUrlForIds(
            (string) $material->getId(),
            (string) $batch->getId(),
        );
    }
}
