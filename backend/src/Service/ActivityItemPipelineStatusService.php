<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityItem;
use App\Entity\ActivityPackItem;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Synchronisiert activity_item.status aus der Pack-Pipeline (activity_pack_item).
 *
 * Zielwerte: ordered, confirmed, packed, at_event, returned
 * Legacy-Alias beim Lesen: requested = ordered
 */
class ActivityItemPipelineStatusService
{
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PACKED = 'packed';
    public const STATUS_AT_EVENT = 'at_event';
    public const STATUS_RETURNED = 'returned';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function syncForActivity(Activity $activity): void
    {
        $activityId = $activity->getId();
        if (!$activityId) {
            return;
        }

        $packByMaterial = $this->aggregatePackQuantitiesByMaterial($activityId);

        $activityItems = $this->entityManager->getRepository(ActivityItem::class)
            ->findBy(['activityId' => $activityId]);

        foreach ($activityItems as $activityItem) {
            $mid = $activityItem->getMaterialItemId();
            $newStatus = $this->deriveStatusForMaterial(
                $activity,
                $packByMaterial[$mid] ?? null,
            );
            if ($activityItem->getStatus() !== $newStatus) {
                $activityItem->setStatus($newStatus);
                $activityItem->setUpdatedAt(new \DateTime());
            }
        }
    }

    /**
     * @return array<string, array{packed: int, issued: int, returned: int}>
     */
    private function aggregatePackQuantitiesByMaterial(string $activityId): array
    {
        $packItems = $this->entityManager->getRepository(ActivityPackItem::class)
            ->findBy(['activityId' => $activityId]);

        $out = [];
        foreach ($packItems as $packItem) {
            if (!$packItem instanceof ActivityPackItem) {
                continue;
            }
            $mid = $packItem->getMaterialItemId();
            if (!isset($out[$mid])) {
                $out[$mid] = ['packed' => 0, 'issued' => 0, 'returned' => 0];
            }
            $out[$mid]['packed'] += $packItem->getQuantityPacked();
            $out[$mid]['issued'] += $packItem->getQuantityIssued();
            $out[$mid]['returned'] += $packItem->getQuantityReturned();
        }

        return $out;
    }

    /**
     * @param array{packed: int, issued: int, returned: int}|null $agg
     */
    private function deriveStatusForMaterial(Activity $activity, ?array $agg): string
    {
        $activityStatus = $activity->getStatus();

        if (in_array($activityStatus, [
            Activity::STATUS_DRAFT,
            Activity::STATUS_SUBMITTED,
            Activity::STATUS_CANCELLED,
        ], true)) {
            return self::STATUS_ORDERED;
        }

        if ($activityStatus === Activity::STATUS_APPROVED) {
            return self::STATUS_ORDERED;
        }

        $packed = $agg['packed'] ?? 0;
        $issued = $agg['issued'] ?? 0;
        $returned = $agg['returned'] ?? 0;

        if ($returned > 0) {
            return self::STATUS_RETURNED;
        }
        if ($issued > 0) {
            return self::STATUS_AT_EVENT;
        }
        if ($packed > 0) {
            return self::STATUS_PACKED;
        }

        if (in_array($activityStatus, [
            Activity::STATUS_PACKING,
            Activity::STATUS_PACKED,
            Activity::STATUS_AT_EVENT,
            Activity::STATUS_RETURNED,
            Activity::STATUS_COMPLETED,
        ], true)) {
            return self::STATUS_CONFIRMED;
        }

        return self::STATUS_ORDERED;
    }
}
