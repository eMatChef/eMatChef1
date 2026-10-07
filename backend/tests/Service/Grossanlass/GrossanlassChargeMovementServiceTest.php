<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Entity\DepartmentGrossanlassChargeMovement;
use App\Entity\DepartmentGrossanlassCommitment;
use App\Entity\DepartmentGrossanlassCost;
use App\Service\Grossanlass\GrossanlassChargeMovementService;
use App\Service\Grossanlass\GrossanlassCostCalculator;
use App\Util\GrossanlassIdGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GrossanlassChargeMovementServiceTest extends TestCase
{
    public function testPartialReceiptsSumUpAndShowMissing(): void
    {
        $expected = 100;
        $received = 0;
        foreach ([60, 30] as $qty) {
            GrossanlassChargeMovementService::assertReceivable($expected, $received, $qty);
            $received += $qty;
        }

        self::assertSame(
            ['expected' => 100, 'received' => 90, 'missing' => 10, 'state' => 'partial'],
            GrossanlassChargeMovementService::inboundSummary($expected, $received),
        );
    }

    public function testOverDeliveryIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Überlieferung');

        GrossanlassChargeMovementService::assertReceivable(100, 90, 15);
    }

    public function testExactRestIsAccepted(): void
    {
        GrossanlassChargeMovementService::assertReceivable(100, 90, 10);

        self::assertSame('complete', GrossanlassChargeMovementService::inboundSummary(100, 100)['state']);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function nonPositiveQuantities(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-5];
    }

    #[DataProvider('nonPositiveQuantities')]
    public function testNonPositiveQuantityIsRejected(int $qty): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GrossanlassChargeMovementService::assertReceivable(10, 0, $qty);
    }

    public function testSummaryStates(): void
    {
        self::assertSame('none', GrossanlassChargeMovementService::inboundSummary(20, 0)['state']);
        self::assertSame(20, GrossanlassChargeMovementService::inboundSummary(20, 0)['missing']);
        self::assertSame('complete', GrossanlassChargeMovementService::inboundSummary(20, 20)['state']);
        self::assertSame(0, GrossanlassChargeMovementService::inboundSummary(20, 20)['missing']);
    }

    public function testDistributeFillsWishesInOrderUpToWishQuantity(): void
    {
        $out = GrossanlassChargeMovementService::distributeReceived(
            ['w1' => 0, 'w2' => 0, 'w3' => 0],
            ['w1' => 40, 'w2' => 40, 'w3' => 20],
            60,
        );

        self::assertSame(['w1' => 40, 'w2' => 20, 'w3' => 0], $out);
    }

    public function testDistributeKeepsExistingAllocationAndAddsRest(): void
    {
        $out = GrossanlassChargeMovementService::distributeReceived(
            ['w1' => 0, 'w2' => 30],
            ['w1' => 40, 'w2' => 40],
            50,
        );

        self::assertSame(['w1' => 20, 'w2' => 30], $out);
    }

    public function testDistributeResetsWhenAllocationExceedsTotal(): void
    {
        $out = GrossanlassChargeMovementService::distributeReceived(
            ['w1' => 40, 'w2' => 40],
            ['w1' => 40, 'w2' => 40],
            50,
        );

        self::assertSame(['w1' => 40, 'w2' => 10], $out);
    }

    public function testOwnershipDefaultsFollowOrigin(): void
    {
        self::assertTrue(DepartmentGrossanlassCommitment::defaultReturnRequired('loan'));
        foreach (['buy', 'buy_resale', 'own', 'donation'] as $origin) {
            self::assertFalse(DepartmentGrossanlassCommitment::defaultReturnRequired($origin), $origin);
            self::assertSame('grossanlass', DepartmentGrossanlassCommitment::defaultOwnerKind($origin), $origin);
        }
        self::assertSame('external', DepartmentGrossanlassCommitment::defaultOwnerKind('loan'));
    }

    public function testNewOriginsAreKnown(): void
    {
        self::assertContains('own', DepartmentGrossanlassCommitment::ORIGINS);
        self::assertContains('donation', DepartmentGrossanlassCommitment::ORIGINS);
    }

    public function testDonationCountsAsInKindCost(): void
    {
        self::assertSame(DepartmentGrossanlassCost::KIND_LOAN, GrossanlassCostCalculator::kindFromOrigin('donation'));
        self::assertSame(
            DepartmentGrossanlassCost::KIND_LOAN,
            GrossanlassCostCalculator::kindFromOrigin('donation', DepartmentGrossanlassCost::KIND_PURCHASE),
        );
    }

    public function testOnlyReceivedIsBookableForNow(): void
    {
        self::assertSame(['received'], DepartmentGrossanlassChargeMovement::BOOKABLE_KINDS);
        self::assertContains('sold_handover', DepartmentGrossanlassChargeMovement::KINDS);
    }

    public function testMovementIdPrefix(): void
    {
        self::assertSame('cm', GrossanlassIdGenerator::prefix(GrossanlassIdGenerator::CHARGE_MOVEMENT));
        self::assertTrue(GrossanlassIdGenerator::matches('cm' . str_repeat('a', 10), GrossanlassIdGenerator::CHARGE_MOVEMENT));
    }
}
