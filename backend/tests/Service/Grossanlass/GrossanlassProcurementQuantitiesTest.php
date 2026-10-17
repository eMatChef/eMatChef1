<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\ActivityGrossanlassProcurementLineWish;
use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\DepartmentGrossanlassCommitment as C;
use App\Service\Grossanlass\GrossanlassOvercoverageConfirmationRequiredException;
use App\Service\Grossanlass\GrossanlassProcurementQuantities;
use App\Service\Grossanlass\GrossanlassProcurementService;
use PHPUnit\Framework\TestCase;

/**
 * Einheitliche Mengenrechnung, bewusste Überdeckung und Auswirkung geänderter Wünsche.
 */
final class GrossanlassProcurementQuantitiesTest extends TestCase
{
    public function testLoanAndBuyAreCountedTogetherAgainstTheNeed(): void
    {
        $q = GrossanlassProcurementQuantities::compute(100, [
            ['origin' => C::ORIGIN_LOAN, 'quantity' => 25, 'received' => 25],
            ['origin' => C::ORIGIN_BUY, 'quantity' => 15, 'received' => 5],
            ['origin' => C::ORIGIN_DONATION, 'quantity' => 10],
        ]);

        self::assertSame(25, $q['loaned']);
        self::assertSame(15, $q['ordered']);
        self::assertSame(10, $q['donated']);
        self::assertSame(50, $q['covered']);
        self::assertSame(30, $q['received']);
        self::assertSame(20, $q['awaiting']);
        self::assertSame(50, $q['open']);
        self::assertSame(0, $q['over']);
    }

    public function testFullPurchaseOnTopOfLoanShowsOvercoverage(): void
    {
        $q = GrossanlassProcurementQuantities::compute(10, [
            ['origin' => C::ORIGIN_LOAN, 'quantity' => 4],
            ['origin' => C::ORIGIN_BUY, 'quantity' => 10],
        ]);

        self::assertSame(0, $q['open']);
        self::assertSame(4, $q['over']);
    }

    public function testReceivedNeverExceedsChargeQuantity(): void
    {
        $q = GrossanlassProcurementQuantities::compute(10, [['origin' => C::ORIGIN_BUY, 'quantity' => 6, 'received' => 9]]);

        self::assertSame(6, $q['received']);
    }

    public function testOrderableQuantityIsNeedMinusOtherSources(): void
    {
        self::assertSame(6, GrossanlassProcurementQuantities::orderableQuantity(10, [['origin' => C::ORIGIN_LOAN, 'quantity' => 4]]));
        self::assertSame(0, GrossanlassProcurementQuantities::orderableQuantity(10, [['origin' => C::ORIGIN_LOAN, 'quantity' => 12]]));
    }

    public function testOvercoverageNeedsConfirmation(): void
    {
        try {
            GrossanlassOvercoverageConfirmationRequiredException::assertWithin(10, 4, 10, false);
            self::fail('Überdeckung ohne Bestätigung muss abgelehnt werden');
        } catch (GrossanlassOvercoverageConfirmationRequiredException $e) {
            self::assertSame('overcoverage_confirmation_required', $e->toPayload()['code']);
            self::assertSame(6, $e->toPayload()['open']);
            self::assertSame(4, $e->toPayload()['over']);
        }

        GrossanlassOvercoverageConfirmationRequiredException::assertWithin(10, 4, 10, true);
        GrossanlassOvercoverageConfirmationRequiredException::assertWithin(10, 4, 6, false);
        $this->addToAssertionCount(1);
    }

    private function link(int $wishQty, string $unit = 'Stk'): ActivityGrossanlassProcurementLineWish
    {
        $wish = new ActivityGrossanlassWishLine();
        $wish->setId('W00000000001');
        $wish->setLabel('Tisch')->setQuantity($wishQty)->setQuantityUnit($unit);
        $link = new ActivityGrossanlassProcurementLineWish();
        $link->setProcurementLine($this->line(1, null));
        $link->setWishLine($wish);

        return $link;
    }

    private function line(int $qty, ?int $ack, string $status = ActivityGrossanlassProcurementLine::STATUS_BEDARF): ActivityGrossanlassProcurementLine
    {
        $line = new ActivityGrossanlassProcurementLine();
        $line->setId('L00000000001');
        $line->setQuantity($qty)->setSourceQuantityAck($ack)->setStatus($status);

        return $line;
    }

    public function testUnchangedWishesNeedNoDecision(): void
    {
        self::assertNull(GrossanlassProcurementService::sourceChange($this->line(10, 10), 10, [$this->link(10)], []));
    }

    public function testChangedWishOnOpenLineMayBeAdopted(): void
    {
        $change = GrossanlassProcurementService::sourceChange($this->line(10, 10), 14, [$this->link(14)], []);

        self::assertTrue($change['decision_required']);
        self::assertSame(4, $change['delta']);
        self::assertFalse($change['binding']);
        self::assertTrue($change['can_adopt']);
    }

    public function testChangedWishOnOrderedLineIsNeverAdoptedAutomatically(): void
    {
        $line = $this->line(10, 10, ActivityGrossanlassProcurementLine::STATUS_BESTELLT);
        $change = GrossanlassProcurementService::sourceChange($line, 7, [$this->link(7)], [['origin' => C::ORIGIN_BUY, 'quantity' => 10]]);

        self::assertTrue($change['decision_required']);
        self::assertTrue($change['binding']);
        self::assertFalse($change['can_adopt']);
        self::assertSame(10, $change['line_quantity']);
    }

    public function testKeptDecisionSuppressesTheHintUntilWishesChangeAgain(): void
    {
        $line = $this->line(10, 14);

        self::assertNull(GrossanlassProcurementService::sourceChange($line, 14, [$this->link(14)], []));
        self::assertNotNull(GrossanlassProcurementService::sourceChange($line, 15, [$this->link(15)], []));
    }

    public function testUnitMismatchIsReportedWithoutForcingADecision(): void
    {
        $line = $this->line(10, 10);
        $line->setQuantityUnit('m');
        $change = GrossanlassProcurementService::sourceChange($line, 10, [$this->link(10, 'Stk')], []);

        self::assertFalse($change['decision_required']);
        self::assertSame(['unit'], $change['reasons']);
    }
}
