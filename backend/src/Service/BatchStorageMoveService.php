<?php

namespace App\Service;

use App\Entity\BatchStorageAllocation;
use App\Entity\MaterialBatch;
use App\Entity\MaterialItem;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Verschiebt Bestand (Allokationen) in eine Kisten-Charge.
 */
class BatchStorageMoveService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Lose Allokation (ohne container_batch_id) → Ziel-Kiste.
     *
     * @return array{allocation_id: string, batch_id: string, qty_moved: int}
     */
    public function moveLooseQtyToContainer(
        string $materialItemId,
        string $departmentId,
        string $toContainerBatchId,
        int $qty,
    ): array {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Menge muss größer als 0 sein');
        }

        $material = $this->entityManager->getRepository(MaterialItem::class)->find($materialItemId);
        if (!$material || $material->getDepartmentId() !== $departmentId) {
            throw new \InvalidArgumentException('Material nicht gefunden');
        }

        $toContainerBatch = $this->entityManager->getRepository(MaterialBatch::class)->find($toContainerBatchId);
        if (!$toContainerBatch || !$toContainerBatch->getMaterialItem()
            || $toContainerBatch->getMaterialItem()->getDepartmentId() !== $departmentId) {
            throw new \InvalidArgumentException('Ziel-Kiste ungültig');
        }
        if ($toContainerBatch->getRackId() === null) {
            throw new \InvalidArgumentException('Ziel-Kiste hat keinen Lagerplatz');
        }

        $fromAlloc = $this->findLooseAllocation($materialItemId, $departmentId, $qty);
        if ($fromAlloc === null) {
            throw new \InvalidArgumentException('Kein loser Bestand im Lager verfügbar');
        }

        $batch = $fromAlloc->getBatch();
        $fromAllocId = $fromAlloc->getId() ?? '';
        $moveQty = min($qty, $fromAlloc->getQty());
        $sourceQty = $fromAlloc->getQty();

        $fromAlloc->setQty($sourceQty - $moveQty);
        if ($fromAlloc->getQty() <= 0) {
            $batch->removeAllocation($fromAlloc);
            $this->entityManager->remove($fromAlloc);
        }

        $existingTarget = null;
        foreach ($batch->getAllocations() as $a) {
            if ($a->getContainerBatchId() !== null && $a->getContainerBatchId() === $toContainerBatchId) {
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
            $newAlloc->setContainerBatch($toContainerBatch);
            $newAlloc->setRack(null);
            $newAlloc->setSlot(null);
            $newAlloc->setQty($moveQty);
            $newAlloc->setDepartmentId($departmentId);
            $batch->addAllocation($newAlloc);
            $this->entityManager->persist($newAlloc);
        }

        return [
            'allocation_id' => $fromAllocId,
            'batch_id' => $batch->getId(),
            'qty_moved' => $moveQty,
        ];
    }

    /** Summe lose (nicht in Kisten) für ein Material. */
    public function sumLooseQty(string $materialItemId, string $departmentId): int
    {
        $conn = $this->entityManager->getConnection();
        $sql = "
            SELECT COALESCE(SUM(a.qty), 0) AS loose_qty
            FROM batch_storage_allocation a
            INNER JOIN material_batch b ON a.batch_id = b.id
            INNER JOIN material_item mi ON b.material_item_id = mi.id
            WHERE a.department_id = :departmentId
              AND mi.id = :materialId
              AND mi.deleted_at IS NULL
              AND b.status = 'active'
              AND a.container_batch_id IS NULL
        ";
        $row = $conn->executeQuery($sql, [
            'departmentId' => $departmentId,
            'materialId' => $materialItemId,
        ])->fetchAssociative();

        return (int) ($row['loose_qty'] ?? 0);
    }

    private function findLooseAllocation(string $materialItemId, string $departmentId, int $minQty): ?BatchStorageAllocation
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(BatchStorageAllocation::class, 'a')
            ->innerJoin('a.batch', 'b')
            ->innerJoin('b.materialItem', 'mi')
            ->where('mi.id = :mid')
            ->andWhere('a.departmentId = :did')
            ->andWhere('a.containerBatchId IS NULL')
            ->andWhere('b.status = :status')
            ->andWhere('a.qty >= :minQty')
            ->setParameter('mid', $materialItemId)
            ->setParameter('did', $departmentId)
            ->setParameter('status', 'active')
            ->setParameter('minQty', max(1, $minQty))
            ->orderBy('a.qty', 'DESC')
            ->setMaxResults(1);

        $result = $qb->getQuery()->getOneOrNullResult();

        return $result instanceof BatchStorageAllocation ? $result : null;
    }
}
