<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassInquiry;
use App\Service\Grossanlass\GrossanlassChargeMovementService;
use App\Service\Grossanlass\GrossanlassInboundService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class GrossanlassInboundBindingTest extends TestCase
{
    public function testOwnOriginIsNeverInboundRelevant(): void
    {
        $row = $this->createMock(DepartmentGrossanlassCommitment::class);
        $row->method('getOrigin')->willReturn(DepartmentGrossanlassCommitment::ORIGIN_OWN);
        $row->method('getQuantity')->willReturn(10);
        $row->method('isReturnedToFirm')->willReturn(false);

        self::assertFalse($this->isRelevant($row, ['received_quantity' => 0, 'missing_quantity' => 10, 'inbound_state' => 'none']));
        self::assertFalse($this->isRelevant($row, ['received_quantity' => 5, 'missing_quantity' => 5, 'inbound_state' => GrossanlassChargeMovementService::STATE_PARTIAL]));
    }

    public function testBuyWithoutConfirmedOrderIsHiddenUntilReceived(): void
    {
        $row = $this->createMock(DepartmentGrossanlassCommitment::class);
        $row->method('getOrigin')->willReturn(DepartmentGrossanlassCommitment::ORIGIN_BUY);
        $row->method('getQuantity')->willReturn(5);
        $row->method('isReturnedToFirm')->willReturn(false);
        $row->method('isPacked')->willReturn(false);
        $row->method('getOrder')->willReturn(null);
        $row->method('getItemDetails')->willReturn([]);

        self::assertFalse($this->isRelevant($row, ['received_quantity' => 0, 'missing_quantity' => 5, 'inbound_state' => 'none']));
    }

    public function testLogisticsVehiclePickupIsExcludedFromInbound(): void
    {
        $row = $this->createMock(DepartmentGrossanlassCommitment::class);
        $row->method('getOrigin')->willReturn(DepartmentGrossanlassCommitment::ORIGIN_LOAN);
        $row->method('getFamily')->willReturn(DepartmentGrossanlassCommitment::FAMILY_VEHICLE);
        $row->method('getQuantity')->willReturn(1);
        $row->method('isReturnedToFirm')->willReturn(false);
        $row->method('isPacked')->willReturn(false);
        $row->method('getItemDetails')->willReturn(['inbound_mode' => 'pickup']);
        $row->method('getPresentFrom')->willReturn(null);

        self::assertFalse($this->isRelevant($row, ['received_quantity' => 0, 'missing_quantity' => 1, 'inbound_state' => 'none']));
    }

    public function testRentalVehicleDeliveryStaysInboundRelevant(): void
    {
        $inquiry = $this->createMock(DepartmentGrossanlassInquiry::class);
        $inquiry->method('getStatus')->willReturn(DepartmentGrossanlassInquiry::STATUS_ZUSAGE);

        $row = $this->createMock(DepartmentGrossanlassCommitment::class);
        $row->method('getOrigin')->willReturn(DepartmentGrossanlassCommitment::ORIGIN_LOAN);
        $row->method('getFamily')->willReturn(DepartmentGrossanlassCommitment::FAMILY_VEHICLE);
        $row->method('getQuantity')->willReturn(1);
        $row->method('isReturnedToFirm')->willReturn(false);
        $row->method('isPacked')->willReturn(false);
        $row->method('getInquiry')->willReturn($inquiry);
        $row->method('getProcurementLine')->willReturn($this->createMock(\App\Entity\ActivityGrossanlassProcurementLine::class));
        $row->method('getItemDetails')->willReturn(['inbound_mode' => 'delivery']);
        $row->method('getPresentFrom')->willReturn(null);

        self::assertTrue($this->isRelevant($row, ['received_quantity' => 0, 'missing_quantity' => 1, 'inbound_state' => 'none']));
    }

    public function testLoanWithZusageInquiryIsInboundRelevant(): void
    {
        $inquiry = $this->createMock(DepartmentGrossanlassInquiry::class);
        $inquiry->method('getStatus')->willReturn(DepartmentGrossanlassInquiry::STATUS_ZUSAGE);

        $row = $this->createMock(DepartmentGrossanlassCommitment::class);
        $row->method('getOrigin')->willReturn(DepartmentGrossanlassCommitment::ORIGIN_LOAN);
        $row->method('getQuantity')->willReturn(3);
        $row->method('isReturnedToFirm')->willReturn(false);
        $row->method('isPacked')->willReturn(false);
        $row->method('getInquiry')->willReturn($inquiry);
        $row->method('getProcurementLine')->willReturn(null);
        $row->method('getItemDetails')->willReturn([]);

        self::assertTrue($this->isRelevant($row, ['received_quantity' => 0, 'missing_quantity' => 3, 'inbound_state' => 'none']));
    }

    /**
     * @param array<string, mixed> $serialized
     */
    private function isRelevant(DepartmentGrossanlassCommitment $row, array $serialized): bool
    {
        $service = new GrossanlassInboundService(
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class),
            $this->createMock(\App\Service\Grossanlass\GrossanlassAccessService::class),
            $this->createMock(\App\Service\Grossanlass\GrossanlassCommitmentService::class),
            $this->createMock(GrossanlassChargeMovementService::class),
            $this->createMock(\App\Service\Grossanlass\GrossanlassDispatchService::class),
            $this->createMock(\App\Service\Grossanlass\GrossanlassTourService::class),
            $this->createMock(\App\Service\Grossanlass\GrossanlassPlaceService::class),
        );
        $method = new ReflectionMethod(GrossanlassInboundService::class, 'isWareneingangRelevant');
        $method->setAccessible(true);

        return $method->invoke($service, $row, $serialized);
    }
}
