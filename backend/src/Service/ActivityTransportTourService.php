<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityPackContainer;
use App\Entity\ActivityPackContainerItem;
use App\Entity\ActivityPackItem;
use App\Entity\ActivityTransportTour;
use App\Entity\ActivityTransportTourItem;
use App\Entity\DepartmentVehicle;
use App\Entity\MaterialItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ActivityTransportTourService
{
    private const TOUR_LETTERS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PackPipelineService $packPipeline,
        private ActivityItemPipelineStatusService $activityItemPipelineStatus,
        private ActivityPackEventHistoryService $packEventHistory,
        private ActivityKisteMaterialLinker $kisteMaterialLinker,
    ) {}

    public function suggestTourLabel(Activity $activity, string $direction, DepartmentVehicle $vehicle): string
    {
        $existing = $this->entityManager->getRepository(ActivityTransportTour::class)->findBy(
            ['activityId' => $activity->getId(), 'direction' => $direction],
            ['sortOrder' => 'ASC', 'createdAt' => 'ASC'],
        );

        $letterIndex = \count($existing);
        $letter = self::TOUR_LETTERS[$letterIndex] ?? ('T' . ($letterIndex + 1));
        $base = 'Tour ' . $letter;

        $sameVehicleCount = 0;
        foreach ($existing as $tour) {
            if ($tour->getVehicleId() === $vehicle->getId()) {
                ++$sameVehicleCount;
            }
        }

        if ($sameVehicleCount > 0) {
            return sprintf('%s (%s — %d. Fahrt)', $base, $vehicle->getName(), $sameVehicleCount + 1);
        }

        return $base;
    }

    /**
     * @param ActivityTransportTourItem[] $items
     *
     * @return array{
     *   estimated_weight_kg: float,
     *   estimated_volume_m3: float|null,
     *   max_payload_kg: float|null,
     *   max_volume_m3: float|null,
     *   fit: 'ok'|'heavy'|'unknown'
     * }
     */
    public function computeLoadSummary(ActivityTransportTour $tour, array $items): array
    {
        $vehicle = $tour->getVehicle();
        $weight = 0.0;
        $volume = 0.0;
        $hasVolume = false;

        foreach ($items as $item) {
            $qty = max(1, (int) ($item->getQuantity() ?? 1));
            if ($item->getPackContainerId()) {
                $weight += $this->estimateContainerWeightKg($item->getPackContainerId()) * $qty;
                continue;
            }
            if ($item->getPackItemId()) {
                $pi = $this->entityManager->getRepository(ActivityPackItem::class)->find($item->getPackItemId());
                if ($pi?->getMaterialItem()) {
                    $weight += $this->parseWeightKg($pi->getMaterialItem()) * $qty;
                }
            }
        }

        $maxPayload = $this->parseDecimal($vehicle->getMaxPayloadKg());
        $maxVolume = $this->parseDecimal($vehicle->getMaxVolumeM3());

        $fit = 'unknown';
        if ($maxPayload !== null && $maxPayload > 0) {
            $fit = $weight > $maxPayload ? 'heavy' : 'ok';
        }

        return [
            'estimated_weight_kg' => round($weight, 2),
            'estimated_volume_m3' => $hasVolume ? round($volume, 3) : null,
            'max_payload_kg' => $maxPayload,
            'max_volume_m3' => $maxVolume,
            'fit' => $fit,
        ];
    }

    private function estimateContainerWeightKg(string $containerId): float
    {
        $items = $this->entityManager->getRepository(ActivityPackContainerItem::class)->findBy([
            'packContainerId' => $containerId,
        ]);
        $sum = 0.0;
        foreach ($items as $ci) {
            $mi = $ci->getMaterialItem();
            if (!$mi) {
                continue;
            }
            $qty = max(0, (int) ($ci->getQuantityPacked() ?? 0));
            if ($qty < 1) {
                $qty = 1;
            }
            $sum += $this->parseWeightKg($mi) * $qty;
        }

        return $sum > 0 ? $sum : 15.0;
    }

    private function parseWeightKg(?MaterialItem $material): float
    {
        if ($material === null) {
            return 0.0;
        }
        $raw = trim((string) ($material->getWeight() ?? ''));
        if ($raw === '') {
            return 1.0;
        }
        $normalized = str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $raw) ?? '');
        $n = (float) $normalized;

        return $n > 0 ? $n : 1.0;
    }

    private function parseDecimal(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $n = (float) str_replace(',', '.', $value);

        return $n > 0 ? $n : null;
    }

    public function journeyDirectionFromStep(string $step): ?string
    {
        return match ($step) {
            'transport_out', 'issue' => ActivityTransportTour::DIRECTION_OUTBOUND,
            'transport_back' => ActivityTransportTour::DIRECTION_INBOUND,
            default => null,
        };
    }

    public function isValidStatus(string $status): bool
    {
        return \in_array($status, [
            ActivityTransportTour::STATUS_PLANNED,
            ActivityTransportTour::STATUS_IN_TRANSIT,
            ActivityTransportTour::STATUS_ARRIVED,
        ], true);
    }

    public function pipelineStageForDirection(string $direction): string
    {
        return $direction === ActivityTransportTour::DIRECTION_INBOUND
            ? PackPipelineService::STAGE_TRANSPORT_BACK
            : PackPipelineService::STAGE_AT_EVENT;
    }

    /**
     * @return array{applied_units: int, updated_lines: int}
     */
    public function arriveTour(ActivityTransportTour $tour, Activity $activity, ?User $user): array
    {
        if ($tour->getStatus() === ActivityTransportTour::STATUS_ARRIVED) {
            return ['applied_units' => 0, 'updated_lines' => 0];
        }

        $profile = $this->packPipeline->profileForActivityType($activity->getType());
        $stage = $this->pipelineStageForDirection($tour->getDirection());
        $tourItems = $this->entityManager->getRepository(ActivityTransportTourItem::class)->findBy([
            'tourId' => $tour->getId(),
        ]);

        $appliedUnits = 0;
        $updatedLines = 0;
        $processedContainers = [];

        foreach ($tourItems as $tourItem) {
            $containerId = $tourItem->getPackContainerId();
            if ($containerId !== null && $containerId !== '' && !isset($processedContainers[$containerId])) {
                $result = $this->arriveContainer($activity, $containerId, $stage, $profile, $user, 'tour_arrive');
                $appliedUnits += $result['applied_units'];
                $updatedLines += $result['updated_lines'];
                $processedContainers[$containerId] = true;
            }

            $packItemId = $tourItem->getPackItemId();
            if ($packItemId !== null && $packItemId !== '') {
                $qty = max(1, (int) ($tourItem->getQuantity() ?? 1));
                $result = $this->arrivePackItem($activity, $packItemId, $qty, $stage, $profile, $user, 'tour_arrive');
                $appliedUnits += $result['applied_units'];
                $updatedLines += $result['updated_lines'];
            }
        }

        $tour->setStatus(ActivityTransportTour::STATUS_ARRIVED);
        $tour->touch();
        $this->syncActivityPipeline($activity, $user);

        return ['applied_units' => $appliedUnits, 'updated_lines' => $updatedLines];
    }

    /**
     * @return array{applied_units: int, updated_lines: int, tours_marked: int}
     */
    public function arriveAllForDirection(Activity $activity, string $direction, ?User $user): array
    {
        $profile = $this->packPipeline->profileForActivityType($activity->getType());
        $stage = $this->pipelineStageForDirection($direction);

        $appliedUnits = 0;
        $updatedLines = 0;

        $packItems = $this->entityManager->getRepository(ActivityPackItem::class)->findBy([
            'activityId' => $activity->getId(),
        ]);
        foreach ($packItems as $packItem) {
            if (!$packItem instanceof ActivityPackItem) {
                continue;
            }
            $max = $this->packPipeline->maxForwardQty($packItem, $stage, $profile);
            if ($max < 1) {
                continue;
            }
            $this->packPipeline->applyForward($packItem, $stage, $max, $profile);
            $packItem->setUpdatedAt(new \DateTime());
            $this->packEventHistory->logPackMove(
                $activity,
                $packItem,
                $stage,
                $max,
                $user,
                'tour_arrive_all',
            );
            $appliedUnits += $max;
            ++$updatedLines;
        }

        $containers = $this->entityManager->getRepository(ActivityPackContainer::class)->findBy([
            'activityId' => $activity->getId(),
        ]);
        foreach ($containers as $container) {
            if (!$container instanceof ActivityPackContainer) {
                continue;
            }
            $result = $this->arriveContainer($activity, $container->getId(), $stage, $profile, $user, 'tour_arrive_all');
            $appliedUnits += $result['applied_units'];
            $updatedLines += $result['updated_lines'];
        }

        $tours = $this->entityManager->getRepository(ActivityTransportTour::class)->findBy([
            'activityId' => $activity->getId(),
            'direction' => $direction,
        ]);
        $toursMarked = 0;
        foreach ($tours as $tour) {
            if ($tour->getStatus() !== ActivityTransportTour::STATUS_ARRIVED) {
                $tour->setStatus(ActivityTransportTour::STATUS_ARRIVED);
                $tour->touch();
                ++$toursMarked;
            }
        }

        $this->syncActivityPipeline($activity, $user);

        return [
            'applied_units' => $appliedUnits,
            'updated_lines' => $updatedLines,
            'tours_marked' => $toursMarked,
        ];
    }

    /**
     * @return array{applied_units: int, updated_lines: int}
     */
    private function arrivePackItem(
        Activity $activity,
        string $packItemId,
        int $requestedQty,
        string $stage,
        string $profile,
        ?User $user,
        string $source,
    ): array {
        $packItem = $this->entityManager->getRepository(ActivityPackItem::class)->find($packItemId);
        if (!$packItem || $packItem->getActivityId() !== $activity->getId()) {
            return ['applied_units' => 0, 'updated_lines' => 0];
        }

        $max = $this->packPipeline->maxForwardQty($packItem, $stage, $profile);
        $apply = min(max(1, $requestedQty), $max);
        if ($apply < 1) {
            return ['applied_units' => 0, 'updated_lines' => 0];
        }

        $this->packPipeline->applyForward($packItem, $stage, $apply, $profile);
        $packItem->setUpdatedAt(new \DateTime());
        $this->packEventHistory->logPackMove($activity, $packItem, $stage, $apply, $user, $source);

        return ['applied_units' => $apply, 'updated_lines' => 1];
    }

    /**
     * @return array{applied_units: int, updated_lines: int}
     */
    private function arriveContainer(
        Activity $activity,
        string $containerId,
        string $stage,
        string $profile,
        ?User $user,
        string $source,
    ): array {
        $container = $this->entityManager->getRepository(ActivityPackContainer::class)->find($containerId);
        if (!$container || $container->getActivityId() !== $activity->getId()) {
            return ['applied_units' => 0, 'updated_lines' => 0];
        }

        $items = $this->entityManager->getRepository(ActivityPackContainerItem::class)->findBy([
            'packContainerId' => $containerId,
        ]);
        $appliedUnits = 0;
        $updatedLines = 0;

        foreach ($items as $ci) {
            if (!$ci instanceof ActivityPackContainerItem) {
                continue;
            }
            $packItem = $this->entityManager->getRepository(ActivityPackItem::class)->findOneBy([
                'activityId' => $activity->getId(),
                'materialItemId' => $ci->getMaterialItemId(),
            ]);
            if ($packItem === null) {
                continue;
            }

            $maxLine = $this->packPipeline->maxForwardContainerQty($ci, $stage, $profile);
            $maxPack = $this->packPipeline->maxForwardQty($packItem, $stage, $profile);
            $apply = min($maxLine, $maxPack);
            if ($apply < 1) {
                continue;
            }

            $this->packPipeline->applyForwardContainer($ci, $stage, $apply, $profile);
            $this->packPipeline->applyForward($packItem, $stage, $apply, $profile);
            $ci->touch();
            $packItem->setUpdatedAt(new \DateTime());
            $appliedUnits += $apply;
            ++$updatedLines;
        }

        $shellResult = $this->applyShellForContainerArrival($activity, $container, $stage, $profile);
        $appliedUnits += $shellResult['applied_units'];
        $updatedLines += $shellResult['updated_lines'];

        if ($updatedLines > 0) {
            $this->packEventHistory->logContainerBulk(
                $activity,
                $container,
                'issue_all',
                $stage,
                $appliedUnits,
                $updatedLines,
                $user,
                $source,
            );
        }

        return ['applied_units' => $appliedUnits, 'updated_lines' => $updatedLines];
    }

    /**
     * @return array{applied_units: int, updated_lines: int}
     */
    private function applyShellForContainerArrival(
        Activity $activity,
        ActivityPackContainer $container,
        string $stage,
        string $profile,
    ): array {
        $batch = $container->getContainerBatch();
        if ($batch === null) {
            return ['applied_units' => 0, 'updated_lines' => 0];
        }

        $packItem = $this->entityManager->getRepository(ActivityPackItem::class)->findOneBy([
            'activityId' => $activity->getId(),
            'materialItemId' => $batch->getMaterialItemId(),
        ]);
        if ($packItem === null) {
            return ['applied_units' => 0, 'updated_lines' => 0];
        }

        $apply = $this->packPipeline->maxForwardQty($packItem, $stage, $profile);
        if ($apply < 1 && $stage === PackPipelineService::STAGE_AT_EVENT) {
            $containerItems = $this->entityManager->getRepository(ActivityPackContainerItem::class)
                ->findBy(['packContainerId' => $container->getId()]);
            $contentsIssued = false;
            foreach ($containerItems as $ci) {
                if ($ci instanceof ActivityPackContainerItem && $ci->getQuantityIssued() > 0) {
                    $contentsIssued = true;
                    break;
                }
            }
            if ($contentsIssued && $packItem->getQuantityIssued() < 1) {
                if ($packItem->getQuantityPacked() < 1) {
                    $packItem->setQuantityPacked(1);
                }
                $apply = 1;
            }
        }

        if ($apply < 1) {
            return ['applied_units' => 0, 'updated_lines' => 0];
        }

        $this->packPipeline->applyForward($packItem, $stage, $apply, $profile);
        $packItem->setUpdatedAt(new \DateTime());

        return ['applied_units' => $apply, 'updated_lines' => 1];
    }

    private function syncActivityPipeline(Activity $activity, ?User $user): void
    {
        $this->activityItemPipelineStatus->syncForActivity($activity);
        $this->kisteMaterialLinker->reconcileShellPackItemsPackedFromContainers(
            $activity,
            $user,
        );
        $this->entityManager->flush();
    }
}
