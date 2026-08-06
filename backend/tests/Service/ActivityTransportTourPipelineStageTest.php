<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ActivityTransportTour;
use App\Service\ActivityItemPipelineStatusService;
use App\Service\ActivityKisteMaterialLinker;
use App\Service\ActivityPackEventHistoryService;
use App\Service\ActivityTransportTourService;
use App\Service\PackPipelineService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * D4: Inbound-Ankunft bucht Retour (transport_back → returned), nicht nochmals transport_back.
 */
final class ActivityTransportTourPipelineStageTest extends TestCase
{
    private function service(): ActivityTransportTourService
    {
        return new ActivityTransportTourService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(PackPipelineService::class),
            $this->createMock(ActivityItemPipelineStatusService::class),
            $this->createMock(ActivityPackEventHistoryService::class),
            $this->createMock(ActivityKisteMaterialLinker::class),
        );
    }

    public function testOutboundArrivalUsesAtEventStage(): void
    {
        $this->assertSame(
            PackPipelineService::STAGE_AT_EVENT,
            $this->service()->pipelineStageForDirection(ActivityTransportTour::DIRECTION_OUTBOUND),
        );
    }

    public function testInboundArrivalUsesReturnedStage(): void
    {
        $this->assertSame(
            PackPipelineService::STAGE_RETURNED,
            $this->service()->pipelineStageForDirection(ActivityTransportTour::DIRECTION_INBOUND),
        );
    }
}
