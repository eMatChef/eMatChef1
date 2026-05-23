<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityItem;
use App\Entity\ActivityPackContainer;
use App\Entity\ActivityPackItem;
use App\Entity\MaterialBatch;
use App\Entity\MaterialItem;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Kiste (MaterialBatch) als Behälter zugeordnet → ActivityItem + ggf. ActivityPackItem.
 */
class ActivityKisteMaterialLinker
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Beim Zuordnen einer Kiste zu einem Pack-Behälter: Zeile in der Materialliste, ggf. Menge +1 bei zweiter gleicher Kiste.
     */
    public function linkKisteOnContainerBatchAssigned(
        Activity $activity,
        MaterialBatch $batch,
        User $user,
        ?string $excludePackContainerId = null,
    ): void
    {
        $materialItem = $batch->getMaterialItem();
        $mid = $materialItem->getId();

        $existingList = $this->entityManager->getRepository(ActivityItem::class)->findBy(
            ['activityId' => $activity->getId(), 'materialItemId' => $mid],
            ['isReplenishment' => 'ASC', 'createdAt' => 'ASC'],
        );
        $existing = $existingList[0] ?? null;
        $otherContainersWithBatch = $this->countPackContainersWithBatch(
            $activity,
            $batch->getId(),
            $excludePackContainerId,
        );
        if ($existing !== null) {
            // Erste Pack-Kiste zu bereits geplanter Materialliste: nicht nochmals +1 (sonst bleibt nach Löschen eine Zeile links).
            if ($otherContainersWithBatch > 0) {
                $existing->setQuantity($existing->getQuantity() + 1);
                $existing->setUpdatedAt(new \DateTime());
            }
        } else {
            $activityItem = new ActivityItem();
            $activityItem->setId(IdGenerator::generate13('ai'));
            $activityItem->setActivity($activity);
            $activityItem->setMaterialItem($materialItem);
            $activityItem->setQuantity(1);
            $activityItem->setPriority('normal');
            $activityItem->setIsConsumable($materialItem->getIsConsumable());
            $activityItem->setIsReplenishment(false);

            $this->entityManager->persist($activityItem);

            $activity->setItemCount($activity->getItemCount() + 1);
        }

        $activity->setUpdatedAt(new \DateTime());

        $this->recalculateTotalPrice($activity);

        if (in_array($activity->getStatus(), [
            Activity::STATUS_PACKING,
            Activity::STATUS_PACKED,
            Activity::STATUS_AT_EVENT,
            Activity::STATUS_RETURNED,
            Activity::STATUS_COMPLETED,
        ], true)) {
            $sumQty = (int) $this->entityManager->createQueryBuilder()
                ->select('COALESCE(SUM(ai.quantity), 0)')
                ->from(ActivityItem::class, 'ai')
                ->where('ai.activityId = :aid')
                ->andWhere('ai.materialItemId = :mid')
                ->setParameter('aid', $activity->getId())
                ->setParameter('mid', $mid)
                ->getQuery()
                ->getSingleScalarResult();
            $this->syncPackItemForMaterial($activity, $materialItem, max(1, $sumQty), $user);
        }
    }

    /**
     * Gegenstück zu linkKisteOnContainerBatchAssigned: eine Pack-Kiste entfernt → Materialliste −1, Packliste anpassen.
     */
    public function unlinkKisteOnContainerRemoved(
        Activity $activity,
        MaterialBatch $batch,
        User $user,
        ?string $removedPackContainerId = null,
    ): void {
        $materialItem = $batch->getMaterialItem();
        $mid = $materialItem->getId();

        $existingList = $this->entityManager->getRepository(ActivityItem::class)->findBy(
            ['activityId' => $activity->getId(), 'materialItemId' => $mid],
            ['isReplenishment' => 'ASC', 'createdAt' => 'ASC'],
        );
        $existing = $existingList[0] ?? null;
        $otherContainersWithBatch = $this->countPackContainersWithBatch(
            $activity,
            $batch->getId(),
            $removedPackContainerId,
        );
        if ($existing !== null) {
            if ($otherContainersWithBatch > 0) {
                $newQty = max(0, $existing->getQuantity() - 1);
                if ($newQty === 0) {
                    $this->entityManager->remove($existing);
                    $activity->setItemCount(max(0, $activity->getItemCount() - 1));
                } else {
                    $existing->setQuantity($newQty);
                    $existing->setUpdatedAt(new \DateTime());
                }
            } elseif ($existing->getQuantity() <= 1) {
                $this->entityManager->remove($existing);
                $activity->setItemCount(max(0, $activity->getItemCount() - 1));
            } else {
                $existing->setQuantity($existing->getQuantity() - 1);
                $existing->setUpdatedAt(new \DateTime());
            }

            $activity->setUpdatedAt(new \DateTime());
            $this->recalculateTotalPrice($activity);
        }

        if (!in_array($activity->getStatus(), [
            Activity::STATUS_PACKING,
            Activity::STATUS_PACKED,
            Activity::STATUS_AT_EVENT,
            Activity::STATUS_RETURNED,
            Activity::STATUS_COMPLETED,
        ], true)) {
            return;
        }

        $sumQty = (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(ai.quantity), 0)')
            ->from(ActivityItem::class, 'ai')
            ->where('ai.activityId = :aid')
            ->andWhere('ai.materialItemId = :mid')
            ->setParameter('aid', $activity->getId())
            ->setParameter('mid', $mid)
            ->getQuery()
            ->getSingleScalarResult();

        $this->syncPackItemQuantityAfterActivityItemChange($activity, $materialItem, $sumQty, $user);
    }

    /**
     * Nach Kisten-Löschen: verwaiste Pack-Zeilen entfernen (kein ActivityItem, kein Pack-Behälter mehr).
     * Behebt u. a. den Fall «Behälter weg in DB, Kiste erscheint links in Bestätigt».
     */
    public function reconcileOrphanPackItemsWithoutMaterialLine(
        Activity $activity,
        ?string $excludePackContainerId = null,
    ): void {
        if (!in_array($activity->getStatus(), [
            Activity::STATUS_PACKING,
            Activity::STATUS_PACKED,
            Activity::STATUS_AT_EVENT,
            Activity::STATUS_RETURNED,
            Activity::STATUS_COMPLETED,
        ], true)) {
            return;
        }

        $containerCountByMaterialId = $this->packContainerCountByMaterialId($activity, $excludePackContainerId);
        $packItems = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findBy(['activityId' => $activity->getId()]);

        $removed = false;
        foreach ($packItems as $packItem) {
            if (!$packItem instanceof ActivityPackItem) {
                continue;
            }
            $mid = $packItem->getMaterialItemId();
            if (($containerCountByMaterialId[$mid] ?? 0) > 0) {
                continue;
            }
            if ($this->activityItemSumQty($activity, $mid) > 0) {
                continue;
            }
            $this->entityManager->remove($packItem);
            $removed = true;
        }

        if ($removed) {
            $activity->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();
        }
    }

    /**
     * Fehlende Kisten-Zeilen nachziehen (einmalig nachziehbar, idempotent: keine doppelte Mengenerhöhung bei jedem GET).
     */
    public function syncMissingActivityLinesFromPackContainers(Activity $activity, User $user): void
    {
        $containers = $this->entityManager->getRepository(ActivityPackContainer::class)
            ->findBy(['activityId' => $activity->getId()]);

        /** @var array<string, int> Material-ID → Anzahl Behälter mit dieser Kiste */
        $containerCountByMaterialId = [];
        foreach ($containers as $pc) {
            $bid = $pc->getContainerBatchId();
            if ($bid === null || $bid === '') {
                continue;
            }
            $batch = $this->entityManager->getRepository(MaterialBatch::class)->find($bid);
            if ($batch === null) {
                continue;
            }
            $mid = $batch->getMaterialItemId();
            $containerCountByMaterialId[$mid] = ($containerCountByMaterialId[$mid] ?? 0) + 1;
        }

        $changed = false;
        foreach ($containerCountByMaterialId as $mid => $needContainers) {
            $materialItem = $this->entityManager->getRepository(MaterialItem::class)->find($mid);
            if ($materialItem === null) {
                continue;
            }
            $anyLine = $this->entityManager->getRepository(ActivityItem::class)->count([
                'activityId' => $activity->getId(),
                'materialItemId' => $mid,
            ]);
            if ($anyLine > 0) {
                continue;
            }

            $activityItem = new ActivityItem();
            $activityItem->setId(IdGenerator::generate13('ai'));
            $activityItem->setActivity($activity);
            $activityItem->setMaterialItem($materialItem);
            $activityItem->setQuantity(max(1, $needContainers));
            $activityItem->setPriority('normal');
            $activityItem->setIsConsumable($materialItem->getIsConsumable());
            $activityItem->setIsReplenishment(false);
            $this->entityManager->persist($activityItem);
            $activity->setItemCount($activity->getItemCount() + 1);
            $changed = true;

            $activity->setUpdatedAt(new \DateTime());
            $this->recalculateTotalPrice($activity);

            if (in_array($activity->getStatus(), [
                Activity::STATUS_PACKING,
                Activity::STATUS_PACKED,
                Activity::STATUS_AT_EVENT,
                Activity::STATUS_RETURNED,
                Activity::STATUS_COMPLETED,
            ], true)) {
                $this->syncPackItemForMaterial($activity, $materialItem, max(1, $needContainers), $user);
            }
        }

        if ($changed) {
            $this->entityManager->flush();
        }
    }

    private function syncPackItemQuantityAfterActivityItemChange(
        Activity $activity,
        MaterialItem $materialItem,
        int $sumQty,
        User $user,
    ): void {
        $existingPackItem = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findOneBy([
                'activityId' => $activity->getId(),
                'materialItemId' => $materialItem->getId(),
            ]);

        if ($sumQty < 1) {
            if ($existingPackItem instanceof ActivityPackItem) {
                $this->entityManager->remove($existingPackItem);
            }
            return;
        }

        $this->syncPackItemForMaterial($activity, $materialItem, $sumQty, $user);
    }

    private function syncPackItemForMaterial(Activity $activity, MaterialItem $materialItem, int $newQuantity, User $user): void
    {
        $existingPackItem = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findOneBy([
                'activityId' => $activity->getId(),
                'materialItemId' => $materialItem->getId(),
            ]);

        if ($existingPackItem) {
            $oldOrdered = $existingPackItem->getQuantityOrdered();
            $delta = max(0, $newQuantity - $oldOrdered);

            $existingPackItem->setQuantityOrdered($newQuantity);
            if ($existingPackItem->getQuantityPacked() > $newQuantity) {
                $existingPackItem->setQuantityPacked($newQuantity);
            }
            if ($existingPackItem->getQuantityIssued() > $newQuantity) {
                $existingPackItem->setQuantityIssued($newQuantity);
            }

            if ($materialItem->getIsConsumable() && $delta > 0) {
                $st = $activity->getStatus();
                if ($st === Activity::STATUS_PACKED) {
                    $existingPackItem->setQuantityPacked(
                        min($newQuantity, $existingPackItem->getQuantityPacked() + $delta)
                    );
                } elseif (in_array($st, [
                    Activity::STATUS_AT_EVENT,
                    Activity::STATUS_RETURNED,
                    Activity::STATUS_COMPLETED,
                ], true)) {
                    $existingPackItem->setQuantityPacked(
                        min($newQuantity, $existingPackItem->getQuantityPacked() + $delta)
                    );
                    $existingPackItem->setQuantityIssued(
                        min($newQuantity, $existingPackItem->getQuantityIssued() + $delta)
                    );
                }
            }

            $existingPackItem->setUpdatedAt(new \DateTime());
        } else {
            $packItem = new ActivityPackItem();
            $packItem->setId(IdGenerator::generate13('pk'));
            $packItem->setActivity($activity);
            $packItem->setMaterialItem($materialItem);
            $packItem->setQuantityOrdered($newQuantity);
            $packItem->setQuantityPacked(0);
            $packItem->setConditionOut('ok');
            $packItem->setPackedByUser($user);
            $this->entityManager->persist($packItem);
        }
    }

    /**
     * @return array<string, int> Material-ID → Anzahl Pack-Behälter mit Lager-Kiste
     */
    private function packContainerCountByMaterialId(
        Activity $activity,
        ?string $excludePackContainerId = null,
    ): array {
        $containers = $this->entityManager->getRepository(ActivityPackContainer::class)
            ->findBy(['activityId' => $activity->getId()]);
        $map = [];
        foreach ($containers as $pc) {
            if (!$pc instanceof ActivityPackContainer) {
                continue;
            }
            if ($excludePackContainerId !== null && $pc->getId() === $excludePackContainerId) {
                continue;
            }
            $batch = $pc->getContainerBatch();
            if ($batch === null) {
                continue;
            }
            $mid = $batch->getMaterialItemId();
            $map[$mid] = ($map[$mid] ?? 0) + 1;
        }

        return $map;
    }

    private function activityItemSumQty(Activity $activity, string $materialItemId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(ai.quantity), 0)')
            ->from(ActivityItem::class, 'ai')
            ->where('ai.activityId = :aid')
            ->andWhere('ai.materialItemId = :mid')
            ->setParameter('aid', $activity->getId())
            ->setParameter('mid', $materialItemId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countPackContainersWithBatch(
        Activity $activity,
        string $batchId,
        ?string $excludePackContainerId = null,
    ): int {
        $containers = $this->entityManager->getRepository(ActivityPackContainer::class)
            ->findBy(['activityId' => $activity->getId()]);
        $count = 0;
        foreach ($containers as $pc) {
            if (!$pc instanceof ActivityPackContainer) {
                continue;
            }
            if ($excludePackContainerId !== null && $pc->getId() === $excludePackContainerId) {
                continue;
            }
            if ($pc->getContainerBatchId() === $batchId) {
                ++$count;
            }
        }

        return $count;
    }

    private function recalculateTotalPrice(Activity $activity): void
    {
        if ($activity->getPricingMode() === 'set_price') {
            return;
        }

        $items = $this->entityManager->getRepository(ActivityItem::class)
            ->findBy(['activityId' => $activity->getId()]);

        $total = '0.00';
        foreach ($items as $item) {
            if ($item->getLineTotal() !== null) {
                $total = bcadd($total, $item->getLineTotal(), 2);
            }
        }

        $activity->setTotalPrice($total !== '0.00' ? $total : null);
    }
}
