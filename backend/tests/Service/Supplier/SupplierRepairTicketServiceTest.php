<?php

declare(strict_types=1);

namespace App\Tests\Service\Supplier;

use App\Entity\MaterialItem;
use App\Entity\SupplierCompany;
use App\Entity\User;
use App\Entity\WorkshopTicket;
use App\Repository\SupplierCompanyRepository;
use App\Service\ActivityAccountingCostService;
use App\Service\Media\MediaPhotoNormalizer;
use App\Service\Supplier\SupplierRepairTicketService;
use App\Service\Workshop\WorkshopPhotoStorageService;
use App\Service\Workshop\WorkshopTicketCompletionService;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class SupplierRepairTicketServiceTest extends TestCase
{
    public function testCompleteCallsCompletionServiceAndAccountingEnqueue(): void
    {
        $companyId = 'sc12345678901';
        $ticketId = 'wt12345678901';

        $material = $this->createMock(MaterialItem::class);
        $material->method('getId')->willReturn('ma12345678901');
        $material->method('getName')->willReturn('Zelt');
        $material->method('getCondition')->willReturn('defect');
        $material->method('getRepairTemplateKey')->willReturn(null);

        $department = $this->createMock(\App\Entity\Department::class);
        $department->method('getId')->willReturn('de12345678901');
        $department->method('getName')->willReturn('Pfadi');

        $ticket = $this->createMock(WorkshopTicket::class);
        $ticket->method('getId')->willReturn($ticketId);
        $ticket->method('getStatus')->willReturn(WorkshopTicket::STATUS_IN_PROGRESS);
        $ticket->method('canTransitionTo')->willReturnCallback(
            static fn (string $status): bool => $status === WorkshopTicket::STATUS_COMPLETED,
        );
        $ticket->method('getType')->willReturn(WorkshopTicket::TYPE_REPAIR);
        $ticket->method('getTypeLabel')->willReturn('Reparatur');
        $ticket->method('getPriority')->willReturn(WorkshopTicket::PRIORITY_NORMAL);
        $ticket->method('getPriorityLabel')->willReturn('Normal');
        $ticket->method('getStrategy')->willReturn(WorkshopTicket::STRATEGY_EXTERNAL_REPAIR);
        $ticket->method('getPhase')->willReturn(WorkshopTicket::PHASE_IN_PROGRESS);
        $ticket->method('getPhaseLabel')->willReturn('In Arbeit');
        $ticket->method('getStatusLabel')->willReturn('In Bearbeitung');
        $ticket->method('getTitle')->willReturn('Zelt reparieren');
        $ticket->method('getDescription')->willReturn(null);
        $ticket->method('getEstimatedCost')->willReturn('120.00');
        $ticket->method('getActualCost')->willReturn(null);
        $ticket->method('getResolutionAction')->willReturn(null);
        $ticket->method('getResolutionNotes')->willReturn(null);
        $ticket->method('getStartedAt')->willReturn(new \DateTime('2026-06-01'));
        $ticket->method('getCompletedAt')->willReturn(null);
        $ticket->method('getCreatedAt')->willReturn(new \DateTime('2026-05-30'));
        $ticket->method('getUpdatedAt')->willReturn(new \DateTime('2026-06-02'));
        $ticket->method('getMaterialItem')->willReturn($material);
        $ticket->method('getDepartment')->willReturn($department);
        $ticket->method('getIssueReport')->willReturn(null);
        $ticket->method('getRepairChecklist')->willReturn(null);
        $ticket->method('getPhotos')->willReturn([]);
        $ticket->method('getCreatedByUser')->willReturn(null);
        $ticket->method('getAssignedToUserId')->willReturn(null);

        $company = $this->createMock(SupplierCompany::class);

        $companyRepo = $this->createMock(SupplierCompanyRepository::class);
        $companyRepo->method('find')->with($companyId)->willReturn($company);

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getOneOrNullResult')->willReturn($ticket);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);
        $em->expects($this->once())->method('flush');
        $em->expects($this->once())->method('persist');

        $completionService = $this->createMock(WorkshopTicketCompletionService::class);
        $completionService->expects($this->once())
            ->method('validateBeforeComplete')
            ->with($ticket, 'repaired')
            ->willReturn(null);
        $completionService->expects($this->once())
            ->method('applyCompletion')
            ->with(
                $ticket,
                'repaired',
                $this->callback(static fn (array $data): bool => ($data['actual_cost'] ?? null) === '125.50'),
                $this->isInstanceOf(\DateTime::class),
                $this->isInstanceOf(User::class),
            )
            ->willReturn(['material_condition' => ['old' => 'defect', 'new' => 'ok']]);

        $accounting = $this->createMock(ActivityAccountingCostService::class);
        $accounting->expects($this->once())
            ->method('enqueueFromWorkshopTicket')
            ->with($ticket);

        $service = new SupplierRepairTicketService(
            $em,
            $companyRepo,
            $this->createMock(WorkshopPhotoStorageService::class),
            $this->createMock(MediaPhotoNormalizer::class),
            $completionService,
            $accounting,
        );

        $actor = $this->createMock(User::class);
        $result = $service->transitionTicket($companyId, $ticketId, [
            'status' => WorkshopTicket::STATUS_COMPLETED,
            'resolution_action' => 'repaired',
            'actual_cost' => '125.50',
        ], $actor);

        $this->assertSame(WorkshopTicket::STATUS_COMPLETED, $result['status']);
        $this->assertSame('125.50', $result['actual_cost']);
    }
}
