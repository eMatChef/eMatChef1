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
    public function linkKisteOnContainerBatchAssigned(Activity $activity, MaterialBatch $batch, User $user): void
    {
        $materialItem = $batch->getMaterialItem();
        $mid = $materialItem->getId();

        $existing = $this->entityManager->getRepository(ActivityItem::class)
            ->findOneBy(['activityId' => $activity->getId(), 'materialItemId' => $mid]);
        if ($existing !== null) {
            $existing->setQuantity($existing->getQuantity() + 1);
            $existing->setUpdatedAt(new \DateTime());
        } else {
            $activityItem = new ActivityItem();
            $activityItem->setId(IdGenerator::generate13('ai'));
            $activityItem->setActivity($activity);
            $activityItem->setMaterialItem($materialItem);
            $activityItem->setQuantity(1);
            $activityItem->setPriority('normal');
            $activityItem->setIsConsumable($materialItem->getIsConsumable());

            $this->entityManager->persist($activityItem);

            $activity->setItemCount($activity->getItemCount() + 1);
        }

        $activity->setUpdatedAt(new \DateTime());

        $this->recalculateTotalPrice($activity);

        if (in_array($activity->getStatus(), [
            Activity::STATUS_PACKING,
            Activity::STATUS_PACKED,
            Activity::STATUS_ISSUED,
        ], true)) {
            $row = $this->entityManager->getRepository(ActivityItem::class)
                ->findOneBy(['activityId' => $activity->getId(), 'materialItemId' => $mid]);
            $qty = $row !== null ? $row->getQuantity() : 1;
            $this->syncPackItemForMaterial($activity, $materialItem, $qty, $user);
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
            $existing = $this->entityManager->getRepository(ActivityItem::class)
                ->findOneBy(['activityId' => $activity->getId(), 'materialItemId' => $mid]);
            if ($existing !== null) {
                continue;
            }

            $activityItem = new ActivityItem();
            $activityItem->setId(IdGenerator::generate13('ai'));
            $activityItem->setActivity($activity);
            $activityItem->setMaterialItem($materialItem);
            $activityItem->setQuantity(max(1, $needContainers));
            $activityItem->setPriority('normal');
            $activityItem->setIsConsumable($materialItem->getIsConsumable());
            $this->entityManager->persist($activityItem);
            $activity->setItemCount($activity->getItemCount() + 1);
            $changed = true;

            $activity->setUpdatedAt(new \DateTime());
            $this->recalculateTotalPrice($activity);

            if (in_array($activity->getStatus(), [
                Activity::STATUS_PACKING,
                Activity::STATUS_PACKED,
                Activity::STATUS_ISSUED,
            ], true)) {
                $this->syncPackItemForMaterial($activity, $materialItem, max(1, $needContainers), $user);
            }
        }

        if ($changed) {
            $this->entityManager->flush();
        }
    }

    private function syncPackItemForMaterial(Activity $activity, MaterialItem $materialItem, int $newQuantity, User $user): void
    {
        $existingPackItem = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findOneBy([
                'activityId' => $activity->getId(),
                'materialItemId' => $materialItem->getId(),
            ]);

        if ($existingPackItem) {
            $existingPackItem->setQuantityOrdered($newQuantity);
            if ($existingPackItem->getQuantityPacked() > $newQuantity) {
                $existingPackItem->setQuantityPacked($newQuantity);
            }
            if ($existingPackItem->getQuantityIssued() > $newQuantity) {
                $existingPackItem->setQuantityIssued($newQuantity);
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
