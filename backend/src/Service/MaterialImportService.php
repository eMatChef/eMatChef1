<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Address;
use App\Entity\BatchStorageAllocation;
use App\Entity\Department;
use App\Entity\MaterialBatch;
use App\Entity\MaterialHistory;
use App\Entity\MaterialItem;
use App\Entity\StorageRack;
use App\Entity\StorageSlot;
use App\Entity\User;
use App\Service\Public\PublicCodeService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * CSV/XLSX-Materialimport: Vorschau (dry-run) und Commit.
 */
class MaterialImportService
{
    private const MANAGE_ROLES = ['mw', 'dc', 'matwart', 'depchef'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PublicCodeService $publicCodeService,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    public function process(
        string $departmentId,
        array $rows,
        bool $dryRun,
        string $defaultDuplicateAction = 'add_batch',
        ?User $actor = null,
    ): array {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return ['success' => false, 'error' => 'Department nicht gefunden'];
        }

        $existingByName = $this->loadExistingMaterialsByNormalizedName($departmentId);
        $localSuppliers = $this->loadDepartmentSuppliers($departmentId);
        $globalSuppliers = $this->loadGlobalSuppliers();
        $storageContext = $this->buildStorageContext($departmentId);

        $resultRows = [];
        $stats = [
            'created' => 0,
            'batches_added' => 0,
            'skipped' => 0,
            'errors' => 0,
            'suppliers_copied' => 0,
            'suppliers_created' => 0,
        ];

        foreach ($rows as $index => $row) {
            $parsed = $this->parseRow($row, $index);
            $outcome = [
                'row_index' => $parsed['row_index'],
                'status' => 'ok',
                'action' => null,
                'errors' => $parsed['errors'],
                'warnings' => $parsed['warnings'],
                'existing_material_id' => null,
                'existing_material_name' => null,
                'supplier_resolution' => null,
                'supplier_id' => null,
                'supplier_label' => null,
            ];

            if (count($parsed['errors']) > 0) {
                $outcome['status'] = 'error';
                ++$stats['errors'];
                $resultRows[] = $outcome;
                continue;
            }

            $normName = $this->normalizeName($parsed['name']);
            $existing = $existingByName[$normName] ?? null;
            $duplicateAction = (string) ($row['duplicate_action'] ?? $defaultDuplicateAction);
            if (!in_array($duplicateAction, ['add_batch', 'skip', 'create'], true)) {
                $duplicateAction = $defaultDuplicateAction;
            }

            if ($existing !== null && $duplicateAction === 'skip') {
                $outcome['status'] = 'skipped';
                $outcome['action'] = 'skip';
                $outcome['existing_material_id'] = $existing->getId();
                $outcome['existing_material_name'] = $existing->getName();
                ++$stats['skipped'];
                $resultRows[] = $outcome;
                continue;
            }

            $supplierResult = $this->resolveSupplier(
                $departmentId,
                $parsed['supplier_id'],
                $parsed['supplier_name'],
                $localSuppliers,
                $globalSuppliers,
                $dryRun,
                $actor,
            );
            $outcome['supplier_resolution'] = $supplierResult['resolution'];
            $outcome['supplier_id'] = $supplierResult['supplier_id'];
            $outcome['supplier_label'] = $supplierResult['label'];
            if ($supplierResult['copied']) {
                ++$stats['suppliers_copied'];
            }
            if ($supplierResult['created']) {
                ++$stats['suppliers_created'];
            }
            if (!empty($supplierResult['new_local_supplier'])) {
                $localSuppliers[] = $supplierResult['new_local_supplier'];
            }

            $storagePlan = $this->resolveStoragePlan($parsed, $storageContext);
            $outcome['warnings'] = array_merge($outcome['warnings'], $storagePlan['warnings']);

            if ($dryRun) {
                if ($existing !== null) {
                    $outcome['existing_material_id'] = $existing->getId();
                    $outcome['existing_material_name'] = $existing->getName();
                    $outcome['action'] = $duplicateAction === 'create' ? 'create' : 'add_batch';
                    $outcome['warnings'][] = 'Artikel existiert bereits im Department';
                } else {
                    $outcome['action'] = 'create';
                }
                $outcome['public_codes_planned'] = true;
                $resultRows[] = $outcome;
                continue;
            }

            try {
                $batch = null;
                if ($existing !== null && $duplicateAction !== 'create') {
                    $material = $existing;
                    $batch = $this->addPurchaseBatch($material, $parsed, $supplierResult['supplier_entity'], $actor, $storagePlan);
                    $outcome['action'] = 'add_batch';
                    $outcome['existing_material_id'] = $material->getId();
                    $outcome['existing_material_name'] = $material->getName();
                    ++$stats['batches_added'];
                } else {
                    [$material, $batch] = $this->createMaterialWithBatch(
                        $department,
                        $parsed,
                        $supplierResult['supplier_entity'],
                        $actor,
                        $storagePlan,
                    );
                    $outcome['action'] = 'create';
                    $outcome['existing_material_id'] = $material->getId();
                    $existingByName[$normName] = $material;
                    ++$stats['created'];
                }

                if ($batch instanceof MaterialBatch) {
                    $this->entityManager->flush();
                    $matCode = $this->publicCodeService->getActiveMaterialPublicCode((string) $material->getId());
                    $batchCode = $this->publicCodeService->getActiveBatchPublicCode((string) $batch->getId());
                    $outcome['material_public_code'] = $matCode?->getPublicCode();
                    $outcome['batch_public_code'] = $batchCode?->getPublicCode();
                }
            } catch (\Throwable $e) {
                $outcome['status'] = 'error';
                $outcome['errors'][] = $e->getMessage();
                ++$stats['errors'];
            }

            $resultRows[] = $outcome;
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return [
            'success' => $stats['errors'] === 0,
            'dry_run' => $dryRun,
            'rows' => $resultRows,
            'stats' => $stats,
        ];
    }

    public function assertCanImport(string $departmentId, ?User $user): ?string
    {
        if (!$user instanceof User) {
            return 'Nicht authentifiziert';
        }

        if ($this->isGrantedSuperadmin($user)) {
            return null;
        }

        $membership = $this->entityManager->getRepository(\App\Entity\Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$membership) {
            return 'Keine Berechtigung für dieses Department';
        }

        $role = strtolower(trim($membership->getRole()));
        if (!in_array($role, self::MANAGE_ROLES, true)) {
            return 'Nur Materialchef (MW) oder Departmentchef (DC) dürfen Material importieren';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function parseRow(array $row, int $fallbackIndex): array
    {
        $errors = [];
        $warnings = [];

        $rowIndex = isset($row['row_index']) ? (int) $row['row_index'] : $fallbackIndex;
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            $errors[] = 'Artikelname fehlt';
        }

        $qtyRaw = $row['qty'] ?? $row['quantity'] ?? null;
        $qty = is_numeric($qtyRaw) ? (int) $qtyRaw : 0;
        if ($qty <= 0) {
            $errors[] = 'Menge muss grösser als 0 sein';
        }

        $acquiredOn = $this->parseAcquiredOn($row);
        if ($acquiredOn === null) {
            $errors[] = 'Beschaffungsdatum oder -jahr fehlt oder ungültig';
        }

        $unitPrice = $this->parsePrice($row['unit_price'] ?? $row['unitPrice'] ?? null);
        if ($unitPrice === null && ($row['unit_price'] ?? '') !== '' && ($row['unit_price'] ?? null) !== null) {
            $warnings[] = 'Stückpreis konnte nicht gelesen werden';
        }

        $storageFields = [
            'storage_name' => trim((string) ($row['storage_name'] ?? $row['storage'] ?? '')),
            'storage_address_id' => trim((string) ($row['storage_address_id'] ?? '')),
            'stock_location_mode' => $this->normalizeStockLocationMode($row['stock_location_mode'] ?? $row['lagerung'] ?? $row['lagerart'] ?? ''),
            'rack_id' => trim((string) ($row['rack_id'] ?? '')),
            'rack_name' => trim((string) ($row['rack_name'] ?? $row['rack'] ?? $row['gestell'] ?? $row['regal'] ?? '')),
            'slot_id' => trim((string) ($row['slot_id'] ?? '')),
            'slot_name' => trim((string) ($row['slot_name'] ?? $row['slot'] ?? $row['fach'] ?? '')),
            'container_name' => trim((string) ($row['container_name'] ?? $row['container'] ?? $row['kiste'] ?? $row['tasche'] ?? '')),
            'container_batch_id' => trim((string) ($row['container_batch_id'] ?? '')),
        ];
        foreach ($this->validateStoragePlacement($storageFields) as $storageError) {
            $errors[] = $storageError;
        }

        return [
            'row_index' => $rowIndex,
            'name' => $name,
            'qty' => $qty,
            'description' => $this->nullableString($row['notes'] ?? $row['description'] ?? null),
            'color' => $this->nullableString($row['color'] ?? null),
            'material' => $this->nullableString($row['material'] ?? null),
            'manufacturer' => trim((string) ($row['manufacturer'] ?? '')),
            'size_length' => $this->normalizeSize($row['size_length'] ?? null),
            'size_width' => $this->normalizeSize($row['size_width'] ?? null),
            'size_height' => $this->normalizeSize($row['size_height'] ?? null),
            'supplier_name' => trim((string) ($row['supplier_name'] ?? $row['supplier'] ?? '')),
            'supplier_id' => trim((string) ($row['supplier_id'] ?? '')),
            ...$storageFields,
            'acquired_on' => $acquiredOn,
            'unit_price' => $unitPrice,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<string, Address> $localByKey
     * @param array<string, Address> $globalByKey
     *
     * @return array{
     *   supplier_id: ?string,
     *   supplier_entity: ?Address,
     *   resolution: string,
     *   label: ?string,
     *   copied: bool,
     *   created: bool,
     *   new_local_supplier: ?Address
     * }
     */
    private function resolveSupplier(
        string $departmentId,
        string $explicitSupplierId,
        string $supplierName,
        array &$localSuppliers,
        array $globalSuppliers,
        bool $dryRun,
        ?User $actor,
    ): array {
        $empty = [
            'supplier_id' => null,
            'supplier_entity' => null,
            'resolution' => 'none',
            'label' => null,
            'copied' => false,
            'created' => false,
            'new_local_supplier' => null,
        ];

        if ($explicitSupplierId !== '') {
            $addr = $this->entityManager->getRepository(Address::class)->find($explicitSupplierId);
            if ($addr && !$addr->isDeleted()) {
                return [
                    'supplier_id' => $addr->getId(),
                    'supplier_entity' => $addr,
                    'resolution' => 'explicit',
                    'label' => $addr->getName() ?: $addr->getCompany(),
                    'copied' => false,
                    'created' => false,
                    'new_local_supplier' => null,
                ];
            }
        }

        if ($supplierName === '') {
            return $empty;
        }

        $key = $this->normalizeName($supplierName);
        foreach ($localSuppliers as $addr) {
            if ($this->supplierKey($addr) === $key) {
                return [
                    'supplier_id' => $addr->getId(),
                    'supplier_entity' => $addr,
                    'resolution' => 'local',
                    'label' => $addr->getName() ?: $addr->getCompany(),
                    'copied' => false,
                    'created' => false,
                    'new_local_supplier' => null,
                ];
            }
        }

        $globalMatch = null;
        foreach ($globalSuppliers as $addr) {
            if ($this->supplierKey($addr) === $key) {
                $globalMatch = $addr;
                break;
            }
        }

        if ($globalMatch !== null) {
            if ($this->addressHasContact($globalMatch)) {
                if ($dryRun) {
                    return [
                        'supplier_id' => null,
                        'supplier_entity' => null,
                        'resolution' => 'copy_from_global',
                        'label' => $globalMatch->getName() ?: $globalMatch->getCompany(),
                        'copied' => true,
                        'created' => false,
                        'new_local_supplier' => null,
                    ];
                }
                $local = $this->copySupplierToDepartment($globalMatch, $departmentId);
                $localSuppliers[] = $local;

                return [
                    'supplier_id' => $local->getId(),
                    'supplier_entity' => $local,
                    'resolution' => 'copy_from_global',
                    'label' => $local->getName() ?: $local->getCompany(),
                    'copied' => true,
                    'created' => false,
                    'new_local_supplier' => $local,
                ];
            }

            return [
                'supplier_id' => $globalMatch->getId(),
                'supplier_entity' => $globalMatch,
                'resolution' => 'global',
                'label' => $globalMatch->getName() ?: $globalMatch->getCompany(),
                'copied' => false,
                'created' => false,
                'new_local_supplier' => null,
            ];
        }

        if ($dryRun) {
            return [
                'supplier_id' => null,
                'supplier_entity' => null,
                'resolution' => 'create_local',
                'label' => $supplierName,
                'copied' => false,
                'created' => true,
                'new_local_supplier' => null,
            ];
        }

        $local = $this->createMinimalLocalSupplier($departmentId, $supplierName);
        $localSuppliers[] = $local;

        return [
            'supplier_id' => $local->getId(),
            'supplier_entity' => $local,
            'resolution' => 'create_local',
            'label' => $supplierName,
            'copied' => false,
            'created' => true,
            'new_local_supplier' => $local,
        ];
    }

    /**
     * @param array<string, mixed> $storagePlan
     *
     * @return array{0: MaterialItem, 1: MaterialBatch}
     */
    private function createMaterialWithBatch(
        Department $department,
        array $parsed,
        ?Address $supplier,
        ?User $actor,
        array $storagePlan,
    ): array {
        $material = new MaterialItem();
        $material->setId(IdGenerator::generate());
        $material->setDepartment($department);
        $material->setName($parsed['name']);
        $material->setTrackingType('bulk');
        $material->setMaterialType('physical');

        if ($parsed['description']) {
            $material->setDescription($parsed['description']);
        }
        if ($parsed['color']) {
            $material->setColor($parsed['color']);
        }
        if ($parsed['material']) {
            $material->setMaterial($parsed['material']);
        }
        if ($parsed['manufacturer'] !== '') {
            $material->setManufacturer($parsed['manufacturer']);
        }
        if ($parsed['size_length']) {
            $material->setSizeLength($parsed['size_length']);
        }
        if ($parsed['size_width']) {
            $material->setSizeWidth($parsed['size_width']);
        }
        if ($parsed['size_height']) {
            $material->setSizeHeight($parsed['size_height']);
        }

        if ($storagePlan['storage_address'] instanceof Address) {
            $material->setStorageAddress($storagePlan['storage_address']);
        }

        $this->entityManager->persist($material);
        $this->createHistoryEntry($material, 'created', [], $actor);

        $acquiredOn = new \DateTime($parsed['acquired_on']);
        $batch = new MaterialBatch();
        $batch->setId(IdGenerator::generate13('ba', $acquiredOn->format('Y')));
        $batch->setMaterialItem($material);
        $batch->setQty($parsed['qty']);
        $batch->setIsInitial(true);
        $batch->setBatchType('initial');
        $batch->setAcquiredOn($acquiredOn);
        if ($parsed['unit_price'] !== null) {
            $batch->setUnitPrice($parsed['unit_price']);
        }
        if ($supplier) {
            $batch->setSupplier($supplier);
        }
        if ($parsed['description']) {
            $batch->setNotes($parsed['description']);
        }

        $this->entityManager->persist($batch);
        $this->applyStoragePlanToBatch($material, $batch, $storagePlan);
        $this->ensureImportPublicCodes($material, $batch, $actor?->getId());

        return [$material, $batch];
    }

    private function addPurchaseBatch(
        MaterialItem $material,
        array $parsed,
        ?Address $supplier,
        ?User $actor,
        array $storagePlan,
    ): MaterialBatch {
        if ($parsed['manufacturer'] !== '' && !$material->getManufacturer()) {
            $material->setManufacturer($parsed['manufacturer']);
        }
        if ($storagePlan['storage_address'] instanceof Address && !$material->getStorageAddressId()) {
            $material->setStorageAddress($storagePlan['storage_address']);
        }

        $acquiredOn = new \DateTime($parsed['acquired_on']);
        $batch = new MaterialBatch();
        $batch->setId(IdGenerator::generate13('ba', $acquiredOn->format('Y')));
        $batch->setMaterialItem($material);
        $batch->setQty($parsed['qty']);
        $batch->setIsInitial(false);
        $batch->setBatchType('purchase');
        $batch->setAcquiredOn($acquiredOn);
        if ($parsed['unit_price'] !== null) {
            $batch->setUnitPrice($parsed['unit_price']);
        }
        if ($supplier) {
            $batch->setSupplier($supplier);
        }
        if ($parsed['description']) {
            $batch->setNotes($parsed['description']);
        }

        $this->entityManager->persist($batch);
        $this->applyStoragePlanToBatch($material, $batch, $storagePlan);
        $this->ensureImportPublicCodes($material, $batch, $actor?->getId());

        return $batch;
    }

    private function ensureImportPublicCodes(MaterialItem $material, MaterialBatch $batch, ?string $actorUserId): void
    {
        $this->publicCodeService->ensureMaterialPublicCode($material, $actorUserId);
        $this->publicCodeService->ensureBatchPublicCode($batch, $actorUserId);
    }

    /**
     * @param array<string, mixed> $storagePlan
     */
    private function applyStoragePlanToBatch(MaterialItem $material, MaterialBatch $batch, array $storagePlan): void
    {
        $mode = (string) ($storagePlan['mode'] ?? '');
        if ($mode === 'kiste' && $storagePlan['container_batch'] instanceof MaterialBatch) {
            $containerBatch = $storagePlan['container_batch'];
            $allocation = new BatchStorageAllocation();
            $allocation->setId(IdGenerator::generate13Unique($this->entityManager, BatchStorageAllocation::class, 'al'));
            $allocation->setBatch($batch);
            $allocation->setContainerBatch($containerBatch);
            $allocation->setQty($batch->getQty());
            $allocation->setDepartmentId($material->getDepartmentId());
            $batch->addAllocation($allocation);
            $this->entityManager->persist($allocation);

            return;
        }

        if ($mode !== 'slot') {
            return;
        }

        $rack = $storagePlan['rack'] ?? null;
        $slot = $storagePlan['slot'] ?? null;
        if ($rack instanceof StorageRack) {
            $batch->setRack($rack);
            if (!$material->getStorageAddressId() && $rack->getStorageAddress()) {
                $material->setStorageAddress($rack->getStorageAddress());
            }
        }
        if ($slot instanceof StorageSlot) {
            $batch->setSlot($slot);
            if (!$batch->getRack()) {
                $batch->setRack($slot->getRack());
            }
        }
    }

    /**
     * @return array{
     *   mode: string,
     *   storage_address: ?Address,
     *   rack: ?StorageRack,
     *   slot: ?StorageSlot,
     *   container_batch: ?MaterialBatch,
     *   warnings: array<int, string>
     * }
     */
    private function resolveStoragePlan(array $parsed, array $context): array
    {
        $warnings = [];
        $mode = (string) ($parsed['stock_location_mode'] ?? '');
        $containerName = (string) ($parsed['container_name'] ?? '');
        $containerBatchId = (string) ($parsed['container_batch_id'] ?? '');
        $rackName = (string) ($parsed['rack_name'] ?? '');
        $slotName = (string) ($parsed['slot_name'] ?? '');

        if ($mode === '') {
            if ($containerName !== '' || $containerBatchId !== '') {
                $mode = 'kiste';
            } elseif ($rackName !== '' || $slotName !== '') {
                $mode = 'slot';
            }
        }

        $storageAddress = $this->resolveStorageAddress(
            (string) ($parsed['storage_address_id'] ?? ''),
            (string) ($parsed['storage_name'] ?? ''),
            $context,
            $warnings,
        );

        $rack = null;
        $slot = null;
        $containerBatch = null;

        if ($mode === 'kiste') {
            $containerBatch = $this->resolveContainerBatch($containerBatchId, $containerName, $context, $warnings);
        } elseif ($mode === 'slot') {
            $rack = $this->resolveRack(
                (string) ($parsed['rack_id'] ?? ''),
                $rackName,
                $storageAddress,
                $context,
                $warnings,
            );
            if ($rack !== null) {
                $slot = $this->resolveSlot(
                    (string) ($parsed['slot_id'] ?? ''),
                    $slotName,
                    $rack,
                    $context,
                    $warnings,
                );
            } elseif ($rackName !== '' || (string) ($parsed['rack_id'] ?? '') !== '') {
                $warnings[] = 'Gestell nicht gefunden';
            } elseif ($slotName !== '' || (string) ($parsed['slot_id'] ?? '') !== '') {
                $warnings[] = 'Fach: zuerst Gestell zuordnen';
            }
        } elseif ($mode !== '' && !in_array($mode, ['slot', 'kiste'], true)) {
            $warnings[] = 'Lagerungsart «' . $mode . '» unbekannt (erwartet: gestell/slot oder kiste)';
        }

        if ($mode === 'kiste' && $containerBatch === null && ($containerName !== '' || $containerBatchId !== '')) {
            // warning already added in resolveContainerBatch
        } elseif ($mode === 'slot' && $rack === null && $slot === null && ($rackName !== '' || $slotName !== '')) {
            // warnings above
        }

        return [
            'mode' => $mode,
            'storage_address' => $storageAddress,
            'rack' => $rack,
            'slot' => $slot,
            'container_batch' => $containerBatch,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{
     *   storages_by_key: array<string, Address>,
     *   racks_by_key: array<string, StorageRack>,
     *   slots_by_rack_and_key: array<string, StorageSlot>,
     *   containers_by_key: array<string, MaterialBatch>
     * }
     */
    private function buildStorageContext(string $departmentId): array
    {
        $storagesByKey = [];
        /** @var Address[] $storages */
        $storages = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Address::class, 'a')
            ->where('a.departmentId = :departmentId')
            ->andWhere('a.type = :type')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('type', 'storage')
            ->getQuery()
            ->getResult();
        foreach ($storages as $addr) {
            if ($addr instanceof Address) {
                $storagesByKey[$this->normalizeName((string) ($addr->getName() ?: $addr->getCompany() ?: ''))] = $addr;
            }
        }

        $racksByKey = [];
        /** @var StorageRack[] $racks */
        $racks = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(StorageRack::class, 'r')
            ->where('r.departmentId = :departmentId')
            ->andWhere('r.isActive = true')
            ->setParameter('departmentId', $departmentId)
            ->getQuery()
            ->getResult();
        foreach ($racks as $rack) {
            if ($rack instanceof StorageRack) {
                $racksByKey[$this->normalizeName($rack->getName())] = $rack;
            }
        }

        $slotsByRackAndKey = [];
        /** @var StorageSlot[] $slots */
        $slots = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(StorageSlot::class, 's')
            ->join('s.rack', 'r')
            ->where('r.departmentId = :departmentId')
            ->andWhere('s.isActive = true')
            ->setParameter('departmentId', $departmentId)
            ->getQuery()
            ->getResult();
        foreach ($slots as $slot) {
            if ($slot instanceof StorageSlot) {
                $slotsByRackAndKey[$slot->getRackId() . '|' . $this->normalizeName($slot->getName())] = $slot;
            }
        }

        $containersByKey = [];
        /** @var MaterialBatch[] $containerBatches */
        $containerBatches = $this->entityManager->createQueryBuilder()
            ->select('b', 'm')
            ->from(MaterialBatch::class, 'b')
            ->join('b.materialItem', 'm')
            ->where('m.departmentId = :departmentId')
            ->andWhere('m.isContainer = true')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('b.status = :status')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getResult();
        foreach ($containerBatches as $cb) {
            if (!$cb instanceof MaterialBatch) {
                continue;
            }
            $material = $cb->getMaterialItem();
            $keys = array_filter([
                $this->normalizeName((string) ($cb->getLabel() ?? '')),
                $this->normalizeName((string) ($cb->getSerialNumber() ?? '')),
                $this->normalizeName($material->getName()),
            ]);
            foreach ($keys as $key) {
                if ($key !== '') {
                    $containersByKey[$key] = $cb;
                }
            }
        }

        return [
            'storages_by_key' => $storagesByKey,
            'racks_by_key' => $racksByKey,
            'slots_by_rack_and_key' => $slotsByRackAndKey,
            'containers_by_key' => $containersByKey,
        ];
    }

    /**
     * @param array<int, string> $warnings
     */
    private function resolveStorageAddress(string $explicitId, string $name, array $context, array &$warnings): ?Address
    {
        if ($explicitId !== '') {
            $addr = $this->entityManager->getRepository(Address::class)->find($explicitId);
            if ($addr && !$addr->isDeleted() && $addr->getType() === 'storage') {
                return $addr;
            }
            $warnings[] = 'Lager-Adresse (ID) ungültig';
        }

        if ($name === '') {
            return null;
        }

        $key = $this->normalizeName($name);
        $hit = $context['storages_by_key'][$key] ?? null;
        if ($hit instanceof Address) {
            return $hit;
        }

        $warnings[] = 'Lager «' . $name . '» nicht gefunden';

        return null;
    }

    /**
     * @param array<int, string> $warnings
     */
    private function resolveRack(string $explicitId, string $name, ?Address $storageAddress, array $context, array &$warnings): ?StorageRack
    {
        if ($explicitId !== '') {
            $rack = $this->entityManager->getRepository(StorageRack::class)->find($explicitId);
            if ($rack instanceof StorageRack && $rack->getIsActive()) {
                return $rack;
            }
            $warnings[] = 'Gestell (ID) ungültig';
        }

        if ($name === '') {
            return null;
        }

        $rack = $context['racks_by_key'][$this->normalizeName($name)] ?? null;
        if (!$rack instanceof StorageRack) {
            return null;
        }

        if ($storageAddress !== null && $rack->getStorageAddressId() !== $storageAddress->getId()) {
            $warnings[] = 'Gestell «' . $name . '» gehört nicht zum gewählten Lager';
        }

        return $rack;
    }

    /**
     * @param array<int, string> $warnings
     */
    private function resolveSlot(string $explicitId, string $name, StorageRack $rack, array $context, array &$warnings): ?StorageSlot
    {
        if ($explicitId !== '') {
            $slot = $this->entityManager->getRepository(StorageSlot::class)->find($explicitId);
            if ($slot instanceof StorageSlot && $slot->getRackId() === $rack->getId() && $slot->getIsActive()) {
                return $slot;
            }
            $warnings[] = 'Fach (ID) ungültig oder gehört nicht zum Gestell';
        }

        if ($name === '') {
            return null;
        }

        $slot = $context['slots_by_rack_and_key'][$rack->getId() . '|' . $this->normalizeName($name)] ?? null;
        if ($slot instanceof StorageSlot) {
            return $slot;
        }

        $warnings[] = 'Fach «' . $name . '» im Gestell «' . $rack->getName() . '» nicht gefunden';

        return null;
    }

    /**
     * @param array<int, string> $warnings
     */
    private function resolveContainerBatch(string $explicitId, string $name, array $context, array &$warnings): ?MaterialBatch
    {
        if ($explicitId !== '') {
            $batch = $this->entityManager->getRepository(MaterialBatch::class)->find($explicitId);
            if ($batch && $batch->getMaterialItem()->getIsContainer()) {
                return $batch;
            }
            $warnings[] = 'Kisten-Batch (ID) ungültig';
        }

        if ($name === '') {
            return null;
        }

        $hit = $context['containers_by_key'][$this->normalizeName($name)] ?? null;
        if ($hit instanceof MaterialBatch) {
            return $hit;
        }

        $warnings[] = 'Kiste «' . $name . '» nicht gefunden';

        return null;
    }

    /**
     * @param array<string, string> $fields
     *
     * @return array<int, string>
     */
    private function validateStoragePlacement(array $fields): array
    {
        $errors = [];
        if ($fields['storage_address_id'] === '' && $fields['storage_name'] === '') {
            $errors[] = 'Lager fehlt';
        }

        $mode = $fields['stock_location_mode'];
        if ($mode !== 'slot' && $mode !== 'kiste') {
            $errors[] = 'Lagerung fehlt (Gestell/Fach oder Kiste)';

            return $errors;
        }

        if ($mode === 'kiste') {
            if ($fields['container_batch_id'] === '' && $fields['container_name'] === '') {
                $errors[] = 'Kiste fehlt';
            }

            return $errors;
        }

        if ($fields['rack_id'] === '' && $fields['rack_name'] === '') {
            $errors[] = 'Gestell fehlt';
        }
        if ($fields['slot_id'] === '' && $fields['slot_name'] === '') {
            $errors[] = 'Fach fehlt';
        }

        return $errors;
    }

    private function normalizeStockLocationMode(mixed $raw): string
    {
        $s = $this->normalizeName((string) $raw);
        if ($s === '') {
            return '';
        }
        if (in_array($s, ['kiste', 'kisten', 'tasche', 'box', 'container', 'behaelter'], true)) {
            return 'kiste';
        }
        if (in_array($s, ['slot', 'gestell', 'rack', 'regal', 'fach', 'platz'], true)) {
            return 'slot';
        }

        return $s;
    }

    private function copySupplierToDepartment(Address $global, string $departmentId): Address
    {
        $local = new Address();
        $local->setId(IdGenerator::generate());
        $local->setScope(Address::SCOPE_DEPARTMENT);
        $local->setDepartmentId($departmentId);
        $local->setType('supplier');
        $local->setName($global->getName());
        $local->setCompany($global->getCompany());
        $local->setAddressLine2($global->getAddressLine2());
        $local->setStreet($global->getStreet());
        $local->setStreetNumber($global->getStreetNumber());
        $local->setPostalCode($global->getPostalCode());
        $local->setCity($global->getCity());
        $local->setCanton($global->getCanton());
        $local->setCountry($global->getCountry() ?: 'Schweiz');
        $local->setContactFirstName($global->getContactFirstName());
        $local->setContactLastName($global->getContactLastName());
        $local->setEmail($global->getEmail());
        $local->setPhone($global->getPhone());
        $local->setMobile($global->getMobile());
        $local->setAdditionalInfo($global->getAdditionalInfo());

        $this->entityManager->persist($local);
        $this->entityManager->flush();

        return $local;
    }

    private function createMinimalLocalSupplier(string $departmentId, string $name): Address
    {
        $local = new Address();
        $local->setId(IdGenerator::generate());
        $local->setScope(Address::SCOPE_DEPARTMENT);
        $local->setDepartmentId($departmentId);
        $local->setType('supplier');
        $local->setName($name);
        $local->setCountry('Schweiz');

        $this->entityManager->persist($local);
        $this->entityManager->flush();

        return $local;
    }

    /**
     * @return array<string, MaterialItem>
     */
    private function loadExistingMaterialsByNormalizedName(string $departmentId): array
    {
        $materials = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(MaterialItem::class, 'm')
            ->where('m.departmentId = :departmentId')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.isJsMaterial = false')
            ->setParameter('departmentId', $departmentId)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($materials as $material) {
            if ($material instanceof MaterialItem) {
                $map[$this->normalizeName($material->getName())] = $material;
            }
        }

        return $map;
    }

    /**
     * @return Address[]
     */
    private function loadDepartmentSuppliers(string $departmentId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Address::class, 'a')
            ->where('a.scope = :scope')
            ->andWhere('a.departmentId = :departmentId')
            ->andWhere('a.type = :type')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('scope', Address::SCOPE_DEPARTMENT)
            ->setParameter('departmentId', $departmentId)
            ->setParameter('type', 'supplier')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Address[]
     */
    private function loadGlobalSuppliers(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Address::class, 'a')
            ->where('a.scope = :scope')
            ->andWhere('a.type = :type')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('scope', Address::SCOPE_GLOBAL)
            ->setParameter('type', 'supplier')
            ->getQuery()
            ->getResult();
    }

    private function supplierKey(Address $addr): string
    {
        $label = trim((string) ($addr->getName() ?: $addr->getCompany() ?: ''));

        return $this->normalizeName($label);
    }

    private function addressHasContact(Address $address): bool
    {
        return trim((string) ($address->getContactFirstName() ?? '')) !== ''
            || trim((string) ($address->getContactLastName() ?? '')) !== ''
            || trim((string) ($address->getEmail() ?? '')) !== ''
            || trim((string) ($address->getPhone() ?? '')) !== ''
            || trim((string) ($address->getMobile() ?? '')) !== '';
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private function normalizeSize(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = trim(str_replace(',', '.', (string) $raw));
        if ($s === '') {
            return null;
        }

        if (preg_match('/^([\d.]+)\s*mm$/i', $s, $m)) {
            $n = (float) $m[1];

            return $this->trimNumericString($n / 10);
        }
        if (preg_match('/^([\d.]+)\s*m$/i', $s, $m)) {
            $n = (float) $m[1];

            return $this->trimNumericString($n * 100);
        }
        $s = preg_replace('/\s*cm$/i', '', $s) ?? $s;

        return $s;
    }

    private function trimNumericString(float $n): string
    {
        $rounded = round($n, 4);
        $s = (string) $rounded;
        if (!str_contains($s, '.')) {
            return $s;
        }

        return rtrim(rtrim($s, '0'), '.');
    }

    private function parseAcquiredOn(array $row): ?string
    {
        $date = trim((string) ($row['acquired_on'] ?? ''));
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        $year = trim((string) ($row['acquired_year'] ?? $row['beschaffung'] ?? ''));
        if ($year !== '' && preg_match('/^\d{4}$/', $year)) {
            return $this->acquiredDateFromYearOnly($year);
        }

        if ($date !== '' && preg_match('/^\d{4}$/', $date)) {
            return $this->acquiredDateFromYearOnly($date);
        }

        return null;
    }

    /** Nur Jahr: Monat/Tag vom Importzeitpunkt, Jahr aus den Daten. */
    private function acquiredDateFromYearOnly(string $yearStr): string
    {
        $year = (int) $yearStr;
        $now = new \DateTimeImmutable();
        $month = (int) $now->format('n');
        $day = (int) $now->format('j');
        if (!checkdate($month, $day, $year)) {
            $day = (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function parsePrice(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = trim((string) $raw);
        $s = preg_replace('/\s*\/.*$/', '', $s) ?? $s;
        $s = str_ireplace(['chf', 'fr', ' '], '', $s);
        $s = str_replace(',', '.', $s);
        $s = preg_replace('/[^0-9.]/', '', $s) ?? '';
        if ($s === '' || !is_numeric($s)) {
            return null;
        }

        return number_format((float) $s, 2, '.', '');
    }

    private function createHistoryEntry(MaterialItem $material, string $action, array $changes, ?User $user): void
    {
        $history = new MaterialHistory();
        $history->setId(IdGenerator::generate13('hi'));
        $history->setMaterialItem($material);
        $history->setAction($action);
        $history->setChanges($changes);
        if ($user) {
            $history->setUser($user);
        }
        $this->entityManager->persist($history);
    }

    private function isGrantedSuperadmin(User $user): bool
    {
        foreach ($user->getRoles() as $role) {
            if ($role === 'ROLE_SUPERADMIN') {
                return true;
            }
        }

        return false;
    }
}
