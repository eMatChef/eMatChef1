<?php

declare(strict_types=1);

namespace App\Service\Supplier;

use App\Entity\Address;
use App\Entity\Category;
use App\Entity\Department;
use App\Entity\MaterialBatch;
use App\Entity\MaterialItem;
use App\Entity\SupplierCatalogItem;
use App\Entity\SupplierDelivery;
use App\Entity\SupplierDeliveryLine;
use App\Repository\SupplierDeliveryRepository;
use App\Service\Public\PublicCodeService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Importiert Supplier-Übergaben und Katalog-Artikel ins Department-Bestand.
 */
class SupplierImportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierDeliveryRepository $deliveryRepository,
        private PublicCodeService $publicCodeService,
    ) {
    }

    /**
     * @param array<string, mixed> $options category_id, storage_address_id, purchase_date, lines (serial overrides)
     *
     * @return array{delivery: array<string, mixed>, materials: list<array<string, mixed>>}
     */
    public function importDelivery(string $departmentId, string $deliveryId, array $options = []): array
    {
        $delivery = $this->deliveryRepository->find($deliveryId);
        if (
            !$delivery instanceof SupplierDelivery
            || $delivery->getDepartmentId() !== $departmentId
            || $delivery->getStatus() !== SupplierDelivery::STATUS_SUBMITTED
        ) {
            throw new \InvalidArgumentException('Übergabe nicht gefunden oder nicht importierbar');
        }

        $department = $delivery->getDepartment();
        $supplierAddress = $delivery->getSupplierCompany()->getSupplierAddress();
        $lineOverrides = $this->indexLineOverrides($options['lines'] ?? []);

        $createdMaterials = [];
        $this->entityManager->beginTransaction();

        try {
            foreach ($delivery->getLines() as $line) {
                if (!$line instanceof SupplierDeliveryLine) {
                    continue;
                }
                $override = $lineOverrides[$line->getId()] ?? [];
                $createdMaterials[] = $this->importCatalogLine(
                    $department,
                    $line->getCatalogItem(),
                    $line->getQty(),
                    $line->getUnitPrice(),
                    $this->resolveSerialNumbers($line, $override),
                    $supplierAddress,
                    $options,
                    $delivery
                );
            }

            $delivery->setStatus(SupplierDelivery::STATUS_IMPORTED);
            $delivery->touch();
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        return [
            'delivery' => $delivery->toArray(),
            'materials' => $createdMaterials,
        ];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function importCatalogItem(
        string $departmentId,
        string $catalogItemId,
        int $qty,
        array $options = [],
    ): array {
        if ($qty < 1) {
            throw new \InvalidArgumentException('qty muss mindestens 1 sein');
        }

        $catalogItem = $this->entityManager->find(SupplierCatalogItem::class, $catalogItemId);
        if (!$catalogItem instanceof SupplierCatalogItem) {
            throw new \InvalidArgumentException('Katalog-Artikel nicht gefunden');
        }
        if (
            !$catalogItem->isActive()
            || $catalogItem->getStatus() !== SupplierCatalogItem::STATUS_PUBLISHED
            || $catalogItem->getVisibility() === SupplierCatalogItem::VISIBILITY_PRIVATE
        ) {
            throw new \InvalidArgumentException('Katalog-Artikel ist im Shop nicht verfügbar');
        }

        $department = $this->entityManager->find(Department::class, $departmentId);
        if (!$department instanceof Department) {
            throw new \InvalidArgumentException('Department nicht gefunden');
        }

        $company = $catalogItem->getSupplierCompany();
        $supplierAddress = $company->getSupplierAddress();
        $serials = $this->normalizeSerialList($options['serial_numbers'] ?? []);

        if ($catalogItem->getTrackingType() === SupplierCatalogItem::TRACKING_SERIALIZED) {
            if (\count($serials) !== $qty) {
                throw new \InvalidArgumentException('Bei serialisierten Artikeln muss die Anzahl Seriennummern der Menge entsprechen');
            }
        }

        $this->entityManager->beginTransaction();
        try {
            $result = $this->importCatalogLine(
                $department,
                $catalogItem,
                $qty,
                $catalogItem->getUnitPrice(),
                $serials,
                $supplierAddress,
                $options,
                null
            );
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * @param list<string> $serialNumbers
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function importCatalogLine(
        Department $department,
        SupplierCatalogItem $catalogItem,
        int $qty,
        ?string $unitPrice,
        array $serialNumbers,
        ?Address $supplierAddress,
        array $options,
        ?SupplierDelivery $delivery,
    ): array {
        if ($catalogItem->getTrackingType() === SupplierCatalogItem::TRACKING_SERIALIZED) {
            if (\count($serialNumbers) !== $qty) {
                throw new \InvalidArgumentException(sprintf(
                    'Artikel "%s": %d Seriennummern erforderlich, %d angegeben',
                    $catalogItem->getName(),
                    $qty,
                    \count($serialNumbers)
                ));
            }
            $this->assertNoDuplicateSerials($department->getId(), $serialNumbers);
        }

        $material = new MaterialItem();
        $material->setId(IdGenerator::generate());
        $material->setDepartment($department);
        $material->setName($catalogItem->getName());
        $material->setDescription($catalogItem->getDescription());
        $material->setManufacturer($catalogItem->getManufacturer());
        $material->setMaterialType('physical');
        $material->setTrackingType(
            $catalogItem->getTrackingType() === SupplierCatalogItem::TRACKING_SERIALIZED ? 'serialized' : 'bulk'
        );

        if (!empty($options['category_id'])) {
            $category = $this->entityManager->find(Category::class, (string) $options['category_id']);
            if ($category && $category->getDepartmentId() === $department->getId()) {
                $material->setCategory($category);
            }
        }

        if (!empty($options['storage_address_id'])) {
            $storage = $this->entityManager->find(Address::class, (string) $options['storage_address_id']);
            if ($storage && $storage->getDepartmentId() === $department->getId()) {
                $material->setStorageAddress($storage);
            }
        }

        $this->entityManager->persist($material);

        $acquiredOn = !empty($options['purchase_date'])
            ? new \DateTime((string) $options['purchase_date'])
            : ($delivery?->getDeliveredAt() ?? new \DateTime());

        $invoiceRef = $delivery?->getInvoiceRef();
        $notes = $delivery !== null
            ? trim('Import aus Lieferanten-Übergabe ' . ($delivery->getDeliveryRef() ?: $delivery->getId()))
            : 'Import aus Lieferanten-Shop';

        $batches = [];
        if ($catalogItem->getTrackingType() === SupplierCatalogItem::TRACKING_BULK) {
            $batch = $this->createBatch($material, $supplierAddress, $acquiredOn, $qty, null, $unitPrice, $invoiceRef, $notes);
            $batches[] = $this->serializeBatch($batch);
        } else {
            foreach ($serialNumbers as $sn) {
                $batch = $this->createBatch($material, $supplierAddress, $acquiredOn, 1, $sn, $unitPrice, $invoiceRef, $notes);
                $batches[] = $this->serializeBatch($batch);
            }
        }

        $this->publicCodeService->ensureMaterialPublicCode($material, null);
        foreach ($material->getBatches() as $batch) {
            if ($batch instanceof MaterialBatch && $batch->getSerialNumber()) {
                $this->publicCodeService->ensureBatchPublicCode($batch, null);
            }
        }

        return [
            'material_id' => $material->getId(),
            'material_name' => $material->getName(),
            'tracking_type' => $material->getTrackingType(),
            'batches' => $batches,
        ];
    }

    private function createBatch(
        MaterialItem $material,
        ?Address $supplier,
        \DateTime $acquiredOn,
        int $qty,
        ?string $serialNumber,
        ?string $unitPrice,
        ?string $invoiceNumber,
        string $notes,
    ): MaterialBatch {
        $batch = new MaterialBatch();
        $batch->setId(IdGenerator::generate13Unique($this->entityManager, MaterialBatch::class, 'ba'));
        $batch->setMaterialItem($material);
        $batch->setQty($qty);
        $batch->setIsInitial(true);
        $batch->setBatchType('purchase');
        $batch->setAcquiredOn($acquiredOn);
        if ($serialNumber !== null) {
            $batch->setSerialNumber($serialNumber);
        }
        if ($unitPrice !== null && $unitPrice !== '') {
            $batch->setUnitPrice($unitPrice);
        }
        if ($supplier instanceof Address) {
            $batch->setSupplier($supplier);
        }
        if ($invoiceNumber) {
            $batch->setInvoiceNumber($invoiceNumber);
        }
        $batch->setNotes($notes);
        $this->entityManager->persist($batch);
        $material->addBatch($batch);

        return $batch;
    }

    /** @return array{id: string|null, qty: int, serial_number: string|null} */
    private function serializeBatch(MaterialBatch $batch): array
    {
        return [
            'id' => $batch->getId(),
            'qty' => $batch->getQty(),
            'serial_number' => $batch->getSerialNumber(),
        ];
    }

    /**
     * @param mixed $lines
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexLineOverrides(mixed $lines): array
    {
        if (!\is_array($lines)) {
            return [];
        }
        $map = [];
        foreach ($lines as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            $lineId = trim((string) ($entry['line_id'] ?? ''));
            if ($lineId !== '') {
                $map[$lineId] = $entry;
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return list<string>
     */
    private function resolveSerialNumbers(SupplierDeliveryLine $line, array $override): array
    {
        if (\array_key_exists('serial_numbers', $override)) {
            return $this->normalizeSerialList($override['serial_numbers']);
        }

        return $line->getSerialNumbers();
    }

    /** @return list<string> */
    private function normalizeSerialList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($sn) => trim((string) $sn), $value),
            static fn (string $sn) => $sn !== ''
        ));
    }

    /** @param list<string> $serialNumbers */
    private function assertNoDuplicateSerials(string $departmentId, array $serialNumbers): void
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
