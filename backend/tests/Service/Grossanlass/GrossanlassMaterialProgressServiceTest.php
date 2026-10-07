<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassMaterialProgressService;
use App\Service\Grossanlass\GrossanlassPackService;
use PHPUnit\Framework\TestCase;

class GrossanlassMaterialProgressServiceTest extends TestCase
{
    public function testFullFlowQuantities(): void
    {
        $row = $this->single(GrossanlassMaterialProgressService::compute(
            [$this->wish('w1', 100, 'L1')],
            ['L1' => ['w1']],
            [$this->charge('c1', 'L1', 100, 80)],
            [$this->einsatz('e1', 'w1', 'c1', 60, 'issued')],
            [
                ['einsatz_id' => 'e1', 'status' => 'trip_released', 'packed' => 20],
                ['einsatz_id' => 'e1', 'status' => 'in_transit', 'packed' => 20],
                ['einsatz_id' => 'e1', 'status' => 'at_place', 'packed' => 10],
            ],
        ));

        self::assertSame([
            'required' => 100,
            'covered' => 100,
            'open' => 0,
            'received' => 80,
            'allocated' => 60,
            'packed' => 50,
            'ready_for_transport' => 20,
            'in_transit' => 20,
            'at_place' => 10,
            'return_open' => 30,
            'returned' => 0,
            'disposed' => 0,
        ], $this->quantities($row));
    }

    public function testPartialCoverageLeavesOpen(): void
    {
        $row = $this->single(GrossanlassMaterialProgressService::compute(
            [$this->wish('w1', 100, 'L1')],
            ['L1' => ['w1']],
            [$this->charge('c1', 'L1', 70, 0)],
            [],
            [],
        ));

        self::assertSame(70, $row['covered']);
        self::assertSame(30, $row['open']);
        self::assertSame(0, $row['received']);
    }

    public function testBookingOnOwnLineChargeIsNotCountedTwice(): void
    {
        $row = $this->single(GrossanlassMaterialProgressService::compute(
            [$this->wish('w1', 100, 'L1')],
            ['L1' => ['w1']],
            [$this->charge('c1', 'L1', 60, 60), $this->charge('c2', 'L1', 50, 50)],
            [$this->einsatz('e1', 'w1', 'c1', 60, 'planned')],
            [
                ['einsatz_id' => 'e1', 'status' => 'staging', 'packed' => 30],
                ['einsatz_id' => 'e1', 'status' => 'staging', 'packed' => 30],
            ],
        ));

        self::assertSame(100, $row['covered']);
        self::assertSame(100, $row['received']);
        self::assertSame(60, $row['allocated']);
        self::assertSame(60, $row['packed']);
    }

    public function testReceivedChargeIsPreferredOverEmptyCommitmentOnSameLine(): void
    {
        $row = $this->single(GrossanlassMaterialProgressService::compute(
            [$this->wish('w1', 100, 'L1')],
            ['L1' => ['w1']],
            [$this->charge('loan1', 'L1', 150, 0), $this->charge('buy1', 'L1', 100, 80)],
            [],
            [],
        ));

        self::assertSame(100, $row['covered']);
        self::assertSame(80, $row['received']);
    }

    public function testBookedChargeIsPreferred(): void
    {
        $row = $this->single(GrossanlassMaterialProgressService::compute(
            [$this->wish('w1', 100, 'L1')],
            ['L1' => ['w1']],
            [$this->charge('a', 'L1', 100, 100), $this->charge('b', 'L1', 100, 40)],
            [$this->einsatz('e1', 'w1', 'b', 40, 'planned')],
            [],
        ));

        self::assertSame(40, $row['received']);
    }

    public function testBundledLineIsSplitPerWishAndReceiptNotDuplicated(): void
    {
        $rows = GrossanlassMaterialProgressService::compute(
            [$this->wish('w1', 40, 'L1', 'g1'), $this->wish('w2', 60, 'L1', 'g2')],
            ['L1' => ['w1', 'w2']],
            [$this->charge('c1', 'L1', 100, 50)],
            [],
            [],
        );

        self::assertSame(['w1' => 40, 'w2' => 60], array_column($rows, 'covered', 'id'));
        self::assertSame(['w1' => 40, 'w2' => 10], array_column($rows, 'received', 'id'));
        self::assertSame(50, array_sum(array_column($rows, 'received')));
    }

    public function testBookingFromForeignChargeCoversOtherWish(): void
    {
        $rows = GrossanlassMaterialProgressService::compute(
            [$this->wish('w1', 10, 'L1'), $this->wish('w2', 5, null)],
            ['L1' => ['w1']],
            [$this->charge('c1', 'L1', 10, 12)],
            [$this->einsatz('e2', 'w2', 'c1', 5, 'planned')],
            [],
        );

        self::assertSame(['w1' => 10, 'w2' => 5], array_column($rows, 'covered', 'id'));
        // 12 erhalten: 10 für die Position, 2 für den fremden Einsatz — nicht 10 + 5.
        self::assertSame(['w1' => 10, 'w2' => 2], array_column($rows, 'received', 'id'));
    }

    public function testSelfOrganizedWishCountsAsCovered(): void
    {
        $wish = $this->wish('w1', 8, null);
        $wish['covered_by_requester'] = true;
        $row = $this->single(GrossanlassMaterialProgressService::compute([$wish], [], [], [], []));

        self::assertSame(8, $row['covered']);
        self::assertSame(0, $row['open']);
    }

    public function testPickupFlowAndReturnedEinsatz(): void
    {
        $rows = GrossanlassMaterialProgressService::compute(
            [$this->wish('w1', 30, null)],
            [],
            [$this->charge('c9', null, 30, 30)],
            [
                $this->einsatz('e1', 'w1', 'c9', 10, 'planned', 'pickup'),
                $this->einsatz('e2', 'w1', 'c9', 10, 'issued', 'pickup'),
                $this->einsatz('e3', 'w1', 'c9', 10, 'returned', 'pickup'),
            ],
            [
                ['einsatz_id' => 'e1', 'status' => 'staging', 'packed' => 10],
                ['einsatz_id' => 'e2', 'status' => 'staging', 'packed' => 10],
            ],
            ['e3' => 0],
        );
        $row = $this->single($rows);

        self::assertSame(20, $row['allocated']);
        self::assertSame(10, $row['ready_for_transport']);
        self::assertSame(10, $row['in_transit']);
        self::assertSame(10, $row['return_open']);
        self::assertSame(10, $row['returned']);
    }

    public function testGroupTotalsKeepUnitsApart(): void
    {
        $rows = GrossanlassMaterialProgressService::compute(
            [$this->wish('w1', 10, null, 'g1', 'Stk'), $this->wish('w2', 25, null, 'g1', 'm')],
            [],
            [],
            [],
            [],
        );
        foreach ($rows as &$row) {
            $row['group_name'] = 'Bühne';
        }
        unset($row);
        $groups = GrossanlassMaterialProgressService::groupTotals($rows);

        self::assertCount(1, $groups);
        self::assertSame(2, $groups[0]['open_items']);
        self::assertSame(10, $groups[0]['totals']['Stk']['required']);
        self::assertSame(25, $groups[0]['totals']['m']['required']);
    }

    public function testSecondPackMayTakeOnlyTheRest(): void
    {
        GrossanlassPackService::assertNeededFits(20, 0, 12);
        GrossanlassPackService::assertNeededFits(20, 12, 8);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('höchstens 8');
        GrossanlassPackService::assertNeededFits(20, 12, 9);
    }

    public function testPackedMayNotExceedPackQuantity(): void
    {
        GrossanlassPackService::assertPackedFits(8, 5);
        GrossanlassPackService::assertPackedFits(8, 8);

        $this->expectException(\InvalidArgumentException::class);
        GrossanlassPackService::assertPackedFits(8, 9);
    }

    public function testPackQuantityMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GrossanlassPackService::assertNeededFits(20, 0, 0);
    }

    /**
     * @return array{id: string, type: string, group_id: string, label: string, unit: string, required: int, line_id: ?string, covered_by_requester: bool}
     */
    private function wish(string $id, int $qty, ?string $lineId, string $groupId = 'g1', string $unit = 'Stk'): array
    {
        return [
            'id' => $id,
            'type' => 'wish',
            'group_id' => $groupId,
            'label' => 'Material ' . $id,
            'unit' => $unit,
            'required' => $qty,
            'line_id' => $lineId,
            'covered_by_requester' => false,
        ];
    }

    /**
     * @return array{id: string, line_id: ?string, quantity: int, received: int}
     */
    private function charge(string $id, ?string $lineId, int $qty, int $received): array
    {
        return ['id' => $id, 'line_id' => $lineId, 'quantity' => $qty, 'received' => $received];
    }

    /**
     * @return array{id: string, item_id: ?string, commitment_id: ?string, qty: int, status: string, delivery: string, starts_at: string}
     */
    private function einsatz(string $id, ?string $itemId, ?string $chargeId, int $qty, string $status, string $delivery = 'trip'): array
    {
        return [
            'id' => $id,
            'item_id' => $itemId,
            'commitment_id' => $chargeId,
            'qty' => $qty,
            'status' => $status,
            'delivery' => $delivery,
            'starts_at' => '2027-07-16T08:00:00+02:00',
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function single(array $rows): array
    {
        self::assertCount(1, $rows);

        return $rows[0];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function quantities(array $row): array
    {
        return array_intersect_key($row, array_flip(GrossanlassMaterialProgressService::QUANTITY_KEYS));
    }
}
