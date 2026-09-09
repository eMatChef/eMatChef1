<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Entity\DepartmentGrossanlassCost;
use App\Service\Grossanlass\GrossanlassCostCalculator;
use PHPUnit\Framework\TestCase;

class GrossanlassCostCalculatorTest extends TestCase
{
    public function testPurchaseExpenseCountsAsNetto(): void
    {
        $this->assertSame(100.0, GrossanlassCostCalculator::netto(
            DepartmentGrossanlassCost::KIND_PURCHASE,
            DepartmentGrossanlassCost::ASSET_EXPENSE,
            '100.00',
            null,
            null,
            DepartmentGrossanlassCost::STATUS_PAID,
        ));
    }

    public function testPurchaseInventoryHasZeroNetto(): void
    {
        $this->assertSame(0.0, GrossanlassCostCalculator::netto(
            DepartmentGrossanlassCost::KIND_PURCHASE,
            DepartmentGrossanlassCost::ASSET_INVENTORY,
            '800.00',
            null,
            null,
            DepartmentGrossanlassCost::STATUS_PAID,
        ));
        $this->assertSame(800.0, GrossanlassCostCalculator::cash('800.00', DepartmentGrossanlassCost::STATUS_PAID));
    }

    public function testRentalSubtractsReturnedDeposit(): void
    {
        $this->assertSame(4000.0, GrossanlassCostCalculator::netto(
            DepartmentGrossanlassCost::KIND_RENTAL,
            null,
            '5000.00',
            '1000.00',
            null,
            DepartmentGrossanlassCost::STATUS_RETURNED,
        ));
    }

    public function testLoanIsAlwaysZeroNetto(): void
    {
        $this->assertSame(0.0, GrossanlassCostCalculator::netto(
            DepartmentGrossanlassCost::KIND_LOAN,
            null,
            '150.00',
            null,
            null,
            DepartmentGrossanlassCost::STATUS_COMMITTED,
        ));
    }

    public function testBuyResaleSubtractsProceeds(): void
    {
        $this->assertSame(1800.0, GrossanlassCostCalculator::netto(
            DepartmentGrossanlassCost::KIND_BUY_RESALE,
            null,
            '12000.00',
            null,
            '10200.00',
            DepartmentGrossanlassCost::STATUS_SOLD,
        ));
    }

    public function testCancelledIgnoresAmounts(): void
    {
        $this->assertSame(0.0, GrossanlassCostCalculator::cash('99.00', DepartmentGrossanlassCost::STATUS_CANCELLED));
        $this->assertSame(0.0, GrossanlassCostCalculator::netto(
            DepartmentGrossanlassCost::KIND_ANCILLARY,
            null,
            '99.00',
            null,
            null,
            DepartmentGrossanlassCost::STATUS_CANCELLED,
        ));
    }

    public function testNullAmountsCountAsZero(): void
    {
        $this->assertSame(0.0, GrossanlassCostCalculator::netto(
            DepartmentGrossanlassCost::KIND_PURCHASE,
            DepartmentGrossanlassCost::ASSET_EXPENSE,
            null,
            null,
            null,
            DepartmentGrossanlassCost::STATUS_PLANNED,
        ));
    }

    public function testKindFromOrigin(): void
    {
        $this->assertSame(DepartmentGrossanlassCost::KIND_LOAN, GrossanlassCostCalculator::kindFromOrigin('loan'));
        $this->assertSame(DepartmentGrossanlassCost::KIND_RENTAL, GrossanlassCostCalculator::kindFromOrigin('loan', 'rental'));
        $this->assertSame(DepartmentGrossanlassCost::KIND_PURCHASE, GrossanlassCostCalculator::kindFromOrigin('buy'));
        $this->assertSame(DepartmentGrossanlassCost::KIND_BUY_RESALE, GrossanlassCostCalculator::kindFromOrigin('buy_resale'));
        $this->assertSame(DepartmentGrossanlassCost::KIND_LOAN, GrossanlassCostCalculator::kindFromOrigin('loan', 'purchase'));
    }
}
