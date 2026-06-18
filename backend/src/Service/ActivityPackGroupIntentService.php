<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityItem;
use App\Entity\ActivityPackContainer;
use App\Entity\ActivityPackContainerItem;
use App\Entity\ActivityPackGroupIntent;
use App\Entity\ActivityPackItem;
use App\Entity\MaterialItem;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

class ActivityPackGroupIntentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param list<string> $packItemIds
     */
    public function createIntent(
        Activity $activity,
        User $user,
        array $packItemIds,
        ?string $label = null,
    ): ActivityPackGroupIntent {
        $packItems = $this->loadPackItems($activity->getId(), $packItemIds);
        if (count($packItems) < 2) {
            throw new \InvalidArgumentException('Mindestens zwei Pack-Zeilen erforderlich');
        }

        $intent = new ActivityPackGroupIntent();
        $intent->setId(IdGenerator::generate13('gi'));
        $intent->setActivity($activity);
        $intent->setCreatedByUserId($user->getId());
        $intent->setLabel($label !== null && trim($label) !== '' ? trim($label) : null);
        $this->entityManager->persist($intent);

        foreach ($packItems as $packItem) {
            $packItem->setIntentId($intent->getId());
        }

        $this->entityManager->flush();

        return $intent;
    }

    /**
     * @return list<ActivityPackGroupIntent>
     */
    public function listOpenIntents(string $activityId): array
    {
        return $this->entityManager->getRepository(ActivityPackGroupIntent::class)->createQueryBuilder('i')
            ->where('i.activityId = :activityId')
            ->andWhere('i.resolvedAt IS NULL')
            ->setParameter('activityId', $activityId)
            ->orderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function resolveIntent(
        Activity $activity,
        ActivityPackGroupIntent $intent,
        ActivityPackContainer $container,
    ): void {
        if ($intent->getActivityId() !== $activity->getId()) {
            throw new \InvalidArgumentException('Intent gehört nicht zur Aktivität');
        }
        if ($intent->isResolved()) {
            throw new \InvalidArgumentException('Intent bereits aufgelöst');
        }

        $packItems = $this->entityManager->getRepository(ActivityPackItem::class)->findBy([
            'activityId' => $activity->getId(),
            'intentId' => $intent->getId(),
        ]);

        foreach ($packItems as $packItem) {
            $this->ensureContainerItem($container, $packItem);
            $packItem->setIntentId(null);
        }

        $intent->setResolvedAt(new \DateTime());
        $intent->setResolvedContainerId($container->getId());
        $this->entityManager->flush();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeIntent(ActivityPackGroupIntent $intent): array
    {
        $packItems = $this->entityManager->getRepository(ActivityPackItem::class)->findBy([
            'intentId' => $intent->getId(),
        ]);

        return [
            'id' => $intent->getId(),
            'activity_id' => $intent->getActivityId(),
            'label' => $intent->getLabel(),
            'created_by_user_id' => $intent->getCreatedByUserId(),
            'created_at' => $intent->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'resolved_at' => $intent->getResolvedAt()?->format(\DateTimeInterface::ATOM),
            'resolved_container_id' => $intent->getResolvedContainerId(),
            'pack_item_ids' => array_map(static fn (ActivityPackItem $pi) => $pi->getId(), $packItems),
            'member_count' => count($packItems),
        ];
    }

    /**
     * @param list<string> $packItemIds
     * @return list<ActivityPackItem>
     */
    private function loadPackItems(string $activityId, array $packItemIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $packItemIds))));
        if ($ids === []) {
            return [];
        }

        $items = $this->entityManager->getRepository(ActivityPackItem::class)->createQueryBuilder('pi')
            ->where('pi.activityId = :activityId')
            ->andWhere('pi.id IN (:ids)')
            ->andWhere('pi.intentId IS NULL')
            ->setParameter('activityId', $activityId)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        return $items;
    }

    private function ensureContainerItem(ActivityPackContainer $container, ActivityPackItem $packItem): void
    {
        $existing = $this->entityManager->getRepository(ActivityPackContainerItem::class)->findOneBy([
            'packContainerId' => $container->getId(),
            'materialItemId' => $packItem->getMaterialItemId(),
        ]);

        $qty = max(1, $packItem->getQuantityOrdered());

        if ($existing !== null) {
            $existing->setQuantityPacked(max($existing->getQuantityPacked(), $qty));
            $existing->touch();
            return;
        }

        $material = $packItem->getMaterialItem();
        $item = new ActivityPackContainerItem();
        $item->setId(IdGenerator::generate13Unique($this->entityManager, ActivityPackContainerItem::class, 'pi'));
        $item->setPackContainer($container);
        $item->setMaterialItem($material);
        $item->setQuantityPacked($qty);
        $this->entityManager->persist($item);
    }
}
