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
     *   known_weight_kg: float,
     *   unknown_weight_count: int,
     *   estimated_volume_m3: float|null,
     *   max_payload_kg: float|null,
     *   max_volume_m3: float|null,
     *   fit: 'ok'|'heavy'|'unknown'
     * }
     */
    public function computeLoadSummary(ActivityTransportTour $tour, array $items): array
    {
        $vehicle = $tour->getVehicle();
        $knownWeight = 0.0;
        $unknownCount = 0;
        $volume = 0.0;
        $hasVolume = false;

        foreach ($items as $item) {
            $measured = $this->effectiveMeasuredWeightKg($tour, $item);
            if ($measured !== null) {
                $knownWeight += $measured;
                continue;
            }

            $qty = max(1, (int) ($item->getQuantity() ?? 1));
            if ($item->getPackContainerId()) {
                $breakdown = $this->containerWeightBreakdown($item->getPackContainerId());
                $knownWeight += $breakdown['known_kg'] * $qty;
                $unknownCount += $breakdown['unknown_count'] * $qty;
                continue;
            }
            if ($item->getPackItemId()) {
                $pi = $this->entityManager->getRepository(ActivityPackItem::class)->find($item->getPackItemId());
                $mi = $pi?->getMaterialItem();
                $unitKg = $this->knownWeightKgFromMaterial($mi);
                if ($unitKg !== null) {
                    $knownWeight += $unitKg * $qty;
                } else {
                    $unknownCount += $qty;
                }
            }
        }

        $maxPayload = $this->parseDecimal($vehicle->getMaxPayloadKg());
        $maxVolume = $this->parseDecimal($vehicle->getMaxVolumeM3());

        $fit = 'unknown';
        if ($maxPayload !== null && $maxPayload > 0 && $unknownCount === 0) {
            $fit = $knownWeight > $maxPayload ? 'heavy' : 'ok';
        }

        return [
            'known_weight_kg' => round($knownWeight, 2),
            'unknown_weight_count' => $unknownCount,
            'estimated_volume_m3' => $hasVolume ? round($volume, 3) : null,
            'max_payload_kg' => $maxPayload,
            'max_volume_m3' => $maxVolume,
            'fit' => $fit,
        ];
    }

    /**
     * @return array{known_kg: float, unknown_count: int}
     */
    private function containerWeightBreakdown(string $containerId): array
    {
        $container = $this->entityManager->find(ActivityPackContainer::class, $containerId);
        if (!$container instanceof ActivityPackContainer) {
            return ['known_kg' => 0.0, 'unknown_count' => 1];
        }

        $known = 0.0;
        $unknown = 0;

        $shellMid = $this->kisteMaterialLinker->shellMaterialIdForPackContainer($container);
        if ($shellMid !== null && $shellMid !== '') {
            $shellMi = $this->entityManager->find(MaterialItem::class, $shellMid);
            $shellKg = $this->knownWeightKgFromMaterial($shellMi);
            if ($shellKg !== null) {
                $known += $shellKg;
            } else {
                ++$unknown;
            }
        }

        $items = $this->entityManager->getRepository(ActivityPackContainerItem::class)->findBy([
            'packContainerId' => $containerId,
        ]);
        foreach ($items as $ci) {
            $mi = $ci->getMaterialItem();
            if ($mi === null) {
                continue;
            }
            $qty = max(0, (int) ($ci->getQuantityPacked() ?? 0));
            if ($qty < 1) {
                continue;
            }
            $unitKg = $this->knownWeightKgFromMaterial($mi);
            if ($unitKg !== null) {
                $known += $unitKg * $qty;
            } else {
                $unknown += $qty;
            }
        }

        if ($known === 0.0 && $unknown === 0) {
            ++$unknown;
        }

        return ['known_kg' => $known, 'unknown_count' => $unknown];
    }

    public function isContainerWeightKnown(string $containerId): bool
    {
        return $this->containerWeightBreakdown($containerId)['unknown_count'] === 0;
    }

    public function isMaterialWeightKnown(?MaterialItem $material): bool
    {
        if ($material === null) {
            return false;
        }
        $raw = trim((string) ($material->getWeight() ?? ''));

        return $raw !== '';
    }

    /**
     * @return array{
     *   material_weight_known: bool,
     *   material_item_id: string|null
     * }
     */
    public function tourItemWeightMeta(ActivityTransportTourItem $item): array
    {
        if ($item->getPackContainerId()) {
            return [
                'material_weight_known' => $this->isContainerWeightKnown($item->getPackContainerId()),
                'material_item_id' => null,
            ];
        }
        if ($item->getPackItemId()) {
            $pi = $this->entityManager->getRepository(ActivityPackItem::class)->find($item->getPackItemId());
            $mi = $pi?->getMaterialItem();

            return [
                'material_weight_known' => $this->isMaterialWeightKnown($mi),
                'material_item_id' => $mi?->getId(),
            ];
        }

        return [
            'material_weight_known' => true,
            'material_item_id' => null,
        ];
    }

    public function findOutboundMeasuredWeightKg(
        string $activityId,
        ?string $packContainerId,
        ?string $packItemId,
    ): ?float {
        if (($packContainerId === null || $packContainerId === '')
            && ($packItemId === null || $packItemId === '')) {
            return null;
        }

        $qb = $this->entityManager->createQueryBuilder()
            ->select('i')
            ->from(ActivityTransportTourItem::class, 'i')
            ->join(ActivityTransportTour::class, 't', 'WITH', 'i.tourId = t.id')
            ->where('t.activityId = :activityId')
            ->andWhere('t.direction = :direction')
            ->andWhere('i.measuredWeightKg IS NOT NULL')
            ->setParameter('activityId', $activityId)
            ->setParameter('direction', ActivityTransportTour::DIRECTION_OUTBOUND);

        if ($packContainerId !== null && $packContainerId !== '') {
            $qb->andWhere('i.packContainerId = :containerId')
                ->setParameter('containerId', $packContainerId);
        } else {
            $qb->andWhere('i.packItemId = :packItemId')
                ->setParameter('packItemId', $packItemId);
        }

        $match = $qb->orderBy('i.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$match instanceof ActivityTransportTourItem) {
            return null;
        }

        return $this->parseMeasuredWeightKg($match->getMeasuredWeightKg());
    }

    public function effectiveMeasuredWeightKg(
        ActivityTransportTour $tour,
        ActivityTransportTourItem $item,
    ): ?float {
        $own = $this->parseMeasuredWeightKg($item->getMeasuredWeightKg());
        if ($own !== null) {
            return $own;
        }
        if ($tour->getDirection() !== ActivityTransportTour::DIRECTION_INBOUND) {
            return null;
        }

        return $this->findOutboundMeasuredWeightKg(
            $tour->getActivityId(),
            $item->getPackContainerId(),
            $item->getPackItemId(),
        );
    }

    public function isMeasuredWeightInherited(
        ActivityTransportTour $tour,
        ActivityTransportTourItem $item,
    ): bool {
        if ($this->parseMeasuredWeightKg($item->getMeasuredWeightKg()) !== null) {
            return false;
        }

        return $this->effectiveMeasuredWeightKg($tour, $item) !== null;
    }

    private function parseMeasuredWeightKg(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $n = (float) str_replace(',', '.', $value);

        return $n > 0 ? $n : null;
    }

    private function knownWeightKgFromMaterial(?MaterialItem $material): ?float
    {
        if (!$this->isMaterialWeightKnown($material)) {
            return null;
        }

        return $this->parseWeightKg($material);
    }

    private function parseWeightKg(?MaterialItem $material): float
    {
        if ($material === null) {
            return 0.0;
        }
        $raw = trim((string) ($material->getWeight() ?? ''));
        if ($raw === '') {
            return 0.0;
        }
        $normalized = str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $raw) ?? '');
        $n = (float) $normalized;

        return $n > 0 ? $n : 0.0;
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

    /**
     * Ankunft bucht die nächste Pipeline-Stufe:
     * - outbound (Am Anlass): transport_to → issued (STAGE_AT_EVENT)
     * - inbound (Retour da): transport_back → returned (STAGE_RETURNED)
     *
     * Laden auf die Tour (issued → transport_back) bleibt bei den Vorwärts-Pfeilen.
     */
    public function pipelineStageForDirection(string $direction): string
    {
        return $direction === ActivityTransportTour::DIRECTION_INBOUND
            ? PackPipelineService::STAGE_RETURNED
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

        // Eine Packkiste pro Container-Buchung — Shell gilt für alle Kisten derselben Charge.
        $apply = min($apply, 1);
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
