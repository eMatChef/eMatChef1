<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityHistory;
use App\Entity\ActivityPackContainer;
use App\Entity\ActivityPackItem;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Pack-Pipeline-Buchungen in activity_history (Phase 8 — §20.2).
 */
class ActivityPackEventHistoryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PackPipelineService $packPipeline,
    ) {}

    public function logPackMove(
        Activity $activity,
        ActivityPackItem $packItem,
        string $stage,
        int $quantity,
        ?User $user = null,
        ?string $source = null,
    ): void {
        if ($quantity <= 0) {
            return;
        }

        $profile = $this->packPipeline->profileForActivityType($activity->getType());
        $material = $packItem->getMaterialItem();

        $this->persist($activity, 'pack_move', [
            'pack_item_id' => $packItem->getId(),
            'material_item_id' => $packItem->getMaterialItemId(),
            'material_name' => $material?->getName(),
            'stage_to' => $stage,
            'journey_step' => $this->journeyStepForStage($stage, $profile),
            'quantity' => $quantity,
            'source' => $this->normalizeSource($source),
        ], $user);
    }

    public function logPackMoveBack(
        Activity $activity,
        ActivityPackItem $packItem,
        string $stage,
        int $quantity,
        ?User $user = null,
        ?string $source = null,
    ): void {
        if ($quantity <= 0) {
            return;
        }

        $profile = $this->packPipeline->profileForActivityType($activity->getType());
        $material = $packItem->getMaterialItem();

        $this->persist($activity, 'pack_moveback', [
            'pack_item_id' => $packItem->getId(),
            'material_item_id' => $packItem->getMaterialItemId(),
            'material_name' => $material?->getName(),
            'stage_from' => $stage,
            'journey_step' => $this->journeyStepForStage($stage, $profile),
            'quantity' => $quantity,
            'source' => $this->normalizeSource($source),
        ], $user);
    }

    public function logContainerBulk(
        Activity $activity,
        ActivityPackContainer $container,
        string $mode,
        string $stage,
        int $appliedUnits,
        int $updatedLines,
        ?User $user = null,
        ?string $source = null,
    ): void {
        if ($appliedUnits <= 0 && $updatedLines <= 0) {
            return;
        }

        $profile = $this->packPipeline->profileForActivityType($activity->getType());

        $this->persist($activity, 'pack_container_bulk', [
            'pack_container_id' => $container->getId(),
            'container_label' => $container->getLabel(),
            'mode' => $mode,
            'stage_to' => $stage,
            'journey_step' => $this->journeyStepForStage($stage, $profile),
            'applied_units' => $appliedUnits,
            'updated_lines' => $updatedLines,
            'source' => $this->normalizeSource($source ?? 'bulk'),
        ], $user);
    }

    private function journeyStepForStage(string $stage, string $profile): ?string
    {
        if ($profile === PackPipelineService::PROFILE_LOGISTICS) {
            return match ($stage) {
                PackPipelineService::STAGE_PACKED => 'pack',
                PackPipelineService::STAGE_TRANSPORT_TO => 'transport_out',
                PackPipelineService::STAGE_AT_EVENT => 'issue',
                PackPipelineService::STAGE_TRANSPORT_BACK => 'transport_back',
                PackPipelineService::STAGE_RETURNED => 'return',
                PackPipelineService::STAGE_STORED => 'store',
                default => null,
            };
        }

        return match ($stage) {
            PackPipelineService::STAGE_PACKED => 'pack',
            PackPipelineService::STAGE_AT_EVENT => 'issue',
            PackPipelineService::STAGE_RETURNED => 'return',
            PackPipelineService::STAGE_STORED => 'store',
            default => null,
        };
    }

    private function normalizeSource(?string $source): string
    {
        return \in_array($source, ['scan', 'tap', 'bulk'], true) ? $source : 'tap';
    }

    private function persist(Activity $activity, string $action, array $changes, ?User $user): void
    {
        $history = new ActivityHistory();
        $history->setId(IdGenerator::generate13('ah'));
        $history->setActivity($activity);
        $history->setAction($action);
        $history->setSnapshot([]);
        $history->setChanges($changes);

        if ($user !== null) {
            $history->setUser($user);
        }

        $this->entityManager->persist($history);
    }
}
