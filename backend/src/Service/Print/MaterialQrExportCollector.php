<?php

declare(strict_types=1);

namespace App\Service\Print;

use App\Entity\Category;
use App\Entity\MaterialBatch;
use App\Entity\MaterialItem;
use App\Service\Public\PublicCodeService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Sammelt alle druckbaren Material-QR-Zeilen eines Departments
 * (Bulk, serialisiert, physische Kombi inkl. Referenz-Kiste).
 */
class MaterialQrExportCollector
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PublicCodeService $publicCodeService,
    ) {
    }

    /**
     * @return MaterialQrExportRow[]
     */
    public function collectForDepartment(string $departmentId, ?string $actorUserId, bool $ensureMissingCodes = true): array
    {
        /** @var MaterialItem[] $materials */
        $materials = $this->entityManager->getRepository(MaterialItem::class)
            ->createQueryBuilder('m')
            ->leftJoin('m.batches', 'b')
            ->addSelect('b')
            ->leftJoin('m.linkedContainerBatch', 'lb')
            ->addSelect('lb')
            ->where('m.departmentId = :departmentId')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.materialType != :virtualCombo')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('virtualCombo', 'virtual_combo')
            ->orderBy('m.name', 'ASC')
            ->addOrderBy('b.serialNumber', 'ASC')
            ->addOrderBy('b.label', 'ASC')
            ->getQuery()
            ->getResult();

        if ($ensureMissingCodes) {
            foreach ($materials as $material) {
                $this->ensurePublicCodesForMaterial($material, $actorUserId);
            }
            $this->entityManager->flush();
        }

        $rows = [];
        /** @var array<string, true> $seenBatchIds */
        $seenBatchIds = [];

        foreach ($materials as $material) {
            if ($material->getMaterialType() === 'physical_combo' && $material->getLinkedContainerBatchId()) {
                $linked = $material->getLinkedContainerBatch();
                if ($linked !== null) {
                    $this->appendBatchRow($rows, $seenBatchIds, $material, $linked);
                }
                continue;
            }

            foreach ($material->getBatches() as $batch) {
                if ($batch->getStatus() !== 'active') {
                    continue;
                }
                $this->appendBatchRow($rows, $seenBatchIds, $material, $batch);
            }
        }

        return $rows;
    }

    /**
     * @param list<string> $batchIds
     *
     * @return MaterialQrExportRow[]
     */
    public function collectForBatchIds(
        string $departmentId,
        array $batchIds,
        ?string $actorUserId,
        bool $ensureMissingCodes = true,
    ): array {
        $wanted = [];
        foreach ($batchIds as $batchId) {
            $id = trim((string) $batchId);
            if ($id !== '') {
                $wanted[$id] = true;
            }
        }
        if ($wanted === []) {
            return [];
        }

        $allRows = $this->collectForDepartment($departmentId, $actorUserId, $ensureMissingCodes);

        return array_values(array_filter(
            $allRows,
            static fn (MaterialQrExportRow $row): bool => $row->batchId !== '' && isset($wanted[$row->batchId]),
        ));
    }

    /**
     * Kategorien + Materialien mit druckbaren Chargen für den Auswahl-Dialog.
     *
     * @return array{
     *     categories: list<array{id: string, name: string, parent_id: string|null, sort_order: int}>,
     *     materials: list<array{id: string, name: string, category_id: string|null, batches: list<array{id: string, line_label: string}>}>
     * }
     */
    public function buildTreeForDepartment(string $departmentId, ?string $actorUserId): array
    {
        /** @var Category[] $categories */
        $categories = $this->entityManager->getRepository(Category::class)
            ->createQueryBuilder('c')
            ->where('c.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var MaterialItem[] $materials */
        $materials = $this->entityManager->getRepository(MaterialItem::class)
            ->createQueryBuilder('m')
            ->leftJoin('m.batches', 'b')
            ->addSelect('b')
            ->leftJoin('m.linkedContainerBatch', 'lb')
            ->addSelect('lb')
            ->where('m.departmentId = :departmentId')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.materialType != :virtualCombo')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('virtualCombo', 'virtual_combo')
            ->orderBy('m.name', 'ASC')
            ->addOrderBy('b.serialNumber', 'ASC')
            ->addOrderBy('b.label', 'ASC')
            ->getQuery()
            ->getResult();

        if ($materials !== []) {
            foreach ($materials as $material) {
                $this->ensurePublicCodesForMaterial($material, $actorUserId);
            }
            $this->entityManager->flush();
        }

        $categoryPayload = array_map(static fn (Category $category): array => [
            'id' => (string) $category->getId(),
            'name' => $category->getName(),
            'parent_id' => $category->getParentId(),
            'sort_order' => $category->getSortOrder(),
        ], $categories);

        $materialPayload = [];
        /** @var array<string, true> $seenBatchIds */
        $seenBatchIds = [];

        foreach ($materials as $material) {
            $batches = [];

            if ($material->getMaterialType() === 'physical_combo' && $material->getLinkedContainerBatchId()) {
                $linked = $material->getLinkedContainerBatch();
                if ($linked !== null) {
                    $batchNode = $this->buildBatchTreeNode($material, $linked, $seenBatchIds);
                    if ($batchNode !== null) {
                        $batches[] = $batchNode;
                    }
                }
            } else {
                foreach ($material->getBatches() as $batch) {
                    if ($batch->getStatus() !== 'active') {
                        continue;
                    }
                    $batchNode = $this->buildBatchTreeNode($material, $batch, $seenBatchIds);
                    if ($batchNode !== null) {
                        $batches[] = $batchNode;
                    }
                }
            }

            if ($batches === []) {
                continue;
            }

            $materialPayload[] = [
                'id' => (string) $material->getId(),
                'name' => $material->getName(),
                'category_id' => $material->getCategoryId(),
                'batches' => $batches,
            ];
        }

        return [
            'categories' => $categoryPayload,
            'materials' => $materialPayload,
        ];
    }

    /**
     * @param array<string, true> $seenBatchIds
     *
     * @return array{id: string, line_label: string}|null
     */
    private function buildBatchTreeNode(
        MaterialItem $displayMaterial,
        MaterialBatch $batch,
        array &$seenBatchIds,
    ): ?array {
        $batchId = (string) $batch->getId();
        if ($batchId === '' || isset($seenBatchIds[$batchId])) {
            return null;
        }

        $batchMaterial = $batch->getMaterialItem();
        if ($batchMaterial === null) {
            return null;
        }

        if ($this->shouldSkipBatchPublicCode($batchMaterial, $batch)) {
            return null;
        }

        $publicUrl = $this->publicCodeService->buildCanonicalMaterialBatchPublicUrlForIds(
            (string) $batchMaterial->getId(),
            $batchId,
        );
        if ($publicUrl === null || $publicUrl === '') {
            return null;
        }

        $seenBatchIds[$batchId] = true;

        return [
            'id' => $batchId,
            'line_label' => $this->batchLineLabel($batch),
        ];
    }

    /**
     * @param MaterialQrExportRow[] $rows
     * @param array<string, true>   $seenBatchIds
     */
    private function appendBatchRow(
        array &$rows,
        array &$seenBatchIds,
        MaterialItem $displayMaterial,
        MaterialBatch $batch,
    ): void {
        $batchId = (string) $batch->getId();
        if ($batchId === '' || isset($seenBatchIds[$batchId])) {
            return;
        }

        $batchMaterial = $batch->getMaterialItem();
        if ($batchMaterial === null) {
            return;
        }

        if ($this->shouldSkipBatchPublicCode($batchMaterial, $batch)) {
            return;
        }

        $publicUrl = $this->publicCodeService->buildCanonicalMaterialBatchPublicUrlForIds(
            (string) $batchMaterial->getId(),
            $batchId,
        );
        if ($publicUrl === null || $publicUrl === '') {
            return;
        }

        $seenBatchIds[$batchId] = true;
        $rows[] = new MaterialQrExportRow(
            materialName: $displayMaterial->getName(),
            lineLabel: $this->batchLineLabel($batch),
            publicCode: (string) ($this->publicCodeService->getActiveBatchPublicCode($batchId)?->getPublicCode() ?? ''),
            publicUrl: $publicUrl,
            batchId: $batchId,
        );
    }

    private function shouldSkipBatchPublicCode(MaterialItem $material, MaterialBatch $batch): bool
    {
        return $material->getTrackingType() === 'serialized'
            && trim((string) $batch->getSerialNumber()) === '';
    }

    private function batchLineLabel(MaterialBatch $batch): string
    {
        $serial = trim((string) $batch->getSerialNumber());
        if ($serial !== '') {
            return 'S/N: ' . $serial;
        }

        $label = trim((string) ($batch->getLabel() ?? ''));
        if ($label !== '') {
            return 'Charge: ' . $label;
        }

        $id = (string) $batch->getId();

        return 'Charge: …' . substr($id, -4);
    }

    private function ensurePublicCodesForMaterial(MaterialItem $material, ?string $actorUserId): void
    {
        if ($material->getMaterialType() === 'physical_combo' && $material->getLinkedContainerBatchId()) {
            $linkedBatch = $material->getLinkedContainerBatch();
            if ($linkedBatch !== null) {
                $this->ensureLinkedContainerBatchPublicCodes($linkedBatch, $actorUserId);
            }

            return;
        }

        if ($material->getMaterialType() === 'virtual_combo') {
            return;
        }

        $this->publicCodeService->ensureMaterialPublicCode($material, $actorUserId);
        foreach ($material->getBatches() as $batch) {
            if ($batch->getStatus() !== 'active') {
                continue;
            }
            if ($this->shouldSkipBatchPublicCode($material, $batch)) {
                continue;
            }
            try {
                $this->publicCodeService->ensureBatchPublicCode($batch, $actorUserId);
            } catch (\InvalidArgumentException) {
                // serialisiert ohne S/N — kein Batch-QR
            }
        }
    }

    private function ensureLinkedContainerBatchPublicCodes(MaterialBatch $linkedBatch, ?string $actorUserId): void
    {
        $sackMaterial = $linkedBatch->getMaterialItem();
        if ($sackMaterial === null) {
            return;
        }

        $this->publicCodeService->ensureMaterialPublicCode($sackMaterial, $actorUserId);
        if (!$this->shouldSkipBatchPublicCode($sackMaterial, $linkedBatch)) {
            try {
                $this->publicCodeService->ensureBatchPublicCode($linkedBatch, $actorUserId);
            } catch (\InvalidArgumentException) {
                // skip
            }
        }
    }
}
