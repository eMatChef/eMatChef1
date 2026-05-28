<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Address;
use App\Entity\Department;
use App\Entity\MaterialBatch;
use App\Entity\MaterialHistory;
use App\Entity\MaterialItem;
use App\Entity\User;
use App\Service\Bootstrap\GlobalSystemSeedDefaults;
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
        $localSuppliers = $this->loadSuppliers($departmentId);
        $globalSuppliers = $this->loadSuppliers(GlobalSystemSeedDefaults::DEPARTMENT_ID);

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

            if ($dryRun) {
                if ($existing !== null) {
                    $outcome['existing_material_id'] = $existing->getId();
                    $outcome['existing_material_name'] = $existing->getName();
                    $outcome['action'] = $duplicateAction === 'create' ? 'create' : 'add_batch';
                    $outcome['warnings'][] = 'Artikel existiert bereits im Department';
                } else {
                    $outcome['action'] = 'create';
                }
                $resultRows[] = $outcome;
                continue;
            }

            try {
                if ($existing !== null && $duplicateAction !== 'create') {
                    $material = $existing;
                    $this->addPurchaseBatch($material, $parsed, $supplierResult['supplier_entity'], $actor);
                    $outcome['action'] = 'add_batch';
                    $outcome['existing_material_id'] = $material->getId();
                    $outcome['existing_material_name'] = $material->getName();
                    ++$stats['batches_added'];
                } else {
                    $material = $this->createMaterialWithBatch(
                        $department,
                        $parsed,
                        $supplierResult['supplier_entity'],
                        $actor,
                    );
                    $outcome['action'] = 'create';
                    $outcome['existing_material_id'] = $material->getId();
                    $existingByName[$normName] = $material;
                    ++$stats['created'];
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

    private function createMaterialWithBatch(
        Department $department,
        array $parsed,
        ?Address $supplier,
        ?User $actor,
    ): MaterialItem {
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
        $this->publicCodeService->ensureBatchPublicCode($batch, $actor?->getId());

        return $material;
    }

    private function addPurchaseBatch(
        MaterialItem $material,
        array $parsed,
        ?Address $supplier,
        ?User $actor,
    ): void {
        if ($parsed['manufacturer'] !== '' && !$material->getManufacturer()) {
            $material->setManufacturer($parsed['manufacturer']);
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
        $this->publicCodeService->ensureBatchPublicCode($batch, $actor?->getId());
    }

    private function copySupplierToDepartment(Address $global, string $departmentId): Address
    {
        $local = new Address();
        $local->setId(IdGenerator::generate());
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
    private function loadSuppliers(string $departmentId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Address::class, 'a')
            ->where('a.departmentId = :departmentId')
            ->andWhere('a.type = :type')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('departmentId', $departmentId)
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
