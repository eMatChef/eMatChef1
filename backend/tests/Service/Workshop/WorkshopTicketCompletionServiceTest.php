<?php

declare(strict_types=1);

namespace App\Tests\Service\Workshop;

use App\Entity\MaterialItem;
use App\Entity\WorkshopTicket;
use App\Service\Workshop\WorkshopPartsUsedValidator;
use App\Service\Workshop\WorkshopTicketCompletionService;
use App\Service\Workshop\WorkshopWriteoffRepurposeService;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class WorkshopTicketCompletionServiceTest extends TestCase
{
    public function testValidateBeforeCompleteReturnsNullForWriteoff(): void
    {
        $ticket = $this->createMock(WorkshopTicket::class);
        $ticket->method('getStrategy')->willReturn(WorkshopTicket::STRATEGY_INTERNAL_REPAIR);
        $ticket->method('getPartsUsed')->willReturn([
            [
                'material_item_id' => 'ma1234567890',
                'material_name' => 'Schraube',
                'quantity' => 99,
                'source' => 'stock',
                'status' => 'planned',
            ],
        ]);

        $service = $this->createCompletionService($this->createMock(EntityManagerInterface::class));

        $this->assertNull($service->validateBeforeComplete($ticket, 'writeoff'));
    }

    public function testValidateBeforeCompleteDetectsInsufficientStock(): void
    {
        $ticket = $this->createMock(WorkshopTicket::class);
        $ticket->method('getStrategy')->willReturn(WorkshopTicket::STRATEGY_INTERNAL_REPAIR);
        $ticket->method('getPartsUsed')->willReturn([
            [
                'material_item_id' => 'ma1234567890',
                'material_name' => 'Schraube',
                'quantity' => 5,
                'source' => 'stock',
                'status' => 'planned',
            ],
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($this->stockQueryBuilder(2));

        $service = $this->createCompletionService($em);

        $error = $service->validateBeforeComplete($ticket, 'repaired');

        $this->assertNotNull($error);
        $this->assertStringContainsString('Schraube', $error);
    }

    public function testCalculatePartsMaterialCostSumsStockLines(): void
    {
        $service = $this->createCompletionService($this->createMock(EntityManagerInterface::class));

        $result = $service->calculatePartsMaterialCost([
            [
                'material_item_id' => 'ma1234567890',
                'quantity' => 2,
                'source' => 'stock',
                'status' => 'planned',
                'unit_cost' => '1.50',
            ],
            [
                'material_item_id' => 'ma1234567891',
                'quantity' => 1,
                'source' => 'purchase',
                'status' => 'ordered',
                'unit_cost' => '9.00',
            ],
        ]);

        $this->assertSame('3.00', $result['total']);
        $this->assertCount(1, $result['lines']);
    }

    public function testApplyCompletionMarksStockLinesConsumed(): void
    {
        $material = $this->createMock(MaterialItem::class);
        $material->method('getId')->willReturn('ma98765432109');
        $material->method('getCondition')->willReturn('repair');
        $material->method('getName')->willReturn('Hammer');

        $ticket = $this->createMock(WorkshopTicket::class);
        $ticket->method('getStrategy')->willReturn(WorkshopTicket::STRATEGY_INTERNAL_REPAIR);
        $ticket->method('getMaterialItem')->willReturn($material);
        $ticket->method('getMaterialBatch')->willReturn(null);
        $ticket->method('getId')->willReturn('wt12345678901');
        $ticket->method('getIssueReport')->willReturn(null);
        $partsUsed = [
            [
                'id' => 'line-1',
                'material_item_id' => 'ma1234567890',
                'material_name' => 'Schraube',
                'quantity' => 2,
                'source' => 'stock',
                'status' => 'planned',
                'unit_cost' => '1.00',
            ],
        ];
        $ticket->method('getPartsUsed')->willReturn($partsUsed);
        $ticket->expects($this->once())->method('setPartsUsed')->with($this->callback(
            static fn (array $parts): bool => ($parts[0]['status'] ?? '') === WorkshopPartsUsedValidator::STATUS_CONSUMED
        ));

        $spareMaterial = $this->createMock(MaterialItem::class);
        $spareMaterial->method('getId')->willReturn('ma1234567890');
        $spareMaterial->method('getCondition')->willReturn('ok');
        $spareMaterial->method('getName')->willReturn('Schraube');

        $materialRepo = $this->createMock(EntityRepository::class);
        $materialRepo->method('find')->with('ma1234567890')->willReturn($spareMaterial);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($this->stockQueryBuilder(5));
        $em->method('getRepository')->with(MaterialItem::class)->willReturn($materialRepo);
        $em->expects($this->atLeastOnce())->method('persist');

        $service = $this->createCompletionService($em);
        $changes = $service->applyCompletion($ticket, 'repaired', [], new \DateTime(), null);

        $this->assertArrayHasKey('parts_consumed', $changes);
        $this->assertSame('2.00', $changes['parts_material_cost']);
    }

    private function createCompletionService(EntityManagerInterface $em): WorkshopTicketCompletionService
    {
        return new WorkshopTicketCompletionService(
            $em,
            $this->createMock(WorkshopWriteoffRepurposeService::class),
        );
    }

    private function stockQueryBuilder(int $stock): QueryBuilder
    {
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn($stock);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }
}
