<?php

declare(strict_types=1);

namespace App\Service\Inventory;

use App\Entity\InventoryTask;
use App\Entity\WorkshopTicket;
use Doctrine\ORM\EntityManagerInterface;

final class InventoryTaskLinkService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Verknüpft eine Inventur-Aufgabe mit einem abgeschlossenen Inspektions-Ticket.
     *
     * @return array<string, mixed> history changes
     */
    public function linkOnInspectionComplete(WorkshopTicket $ticket, string $inventoryTaskId): array
    {
        if ($ticket->getStrategy() !== WorkshopTicket::STRATEGY_INSPECTION) {
            throw new \InvalidArgumentException('inventory_task_id ist nur für Inspektions-Tickets erlaubt');
        }

        $task = $this->entityManager->getRepository(InventoryTask::class)->find($inventoryTaskId);
        if (!$task instanceof InventoryTask) {
            throw new \InvalidArgumentException('Inventur-Aufgabe nicht gefunden');
        }

        if ($task->getDepartmentId() !== $ticket->getDepartmentId()) {
            throw new \InvalidArgumentException('Inventur-Aufgabe gehört zu einem anderen Department');
        }

        $existingTicketId = $task->getWorkshopTicketId();
        if ($existingTicketId !== null && $existingTicketId !== $ticket->getId()) {
            throw new \InvalidArgumentException('Inventur-Aufgabe ist bereits mit einem anderen Ticket verknüpft');
        }

        $oldStatus = $task->getStatus();
        $task->setWorkshopTicket($ticket);
        $task->setStatus(InventoryTask::STATUS_COMPLETED);
        $task->updateTimestamps();

        return [
            'inventory_task_id' => $task->getId(),
            'inventory_task_status' => ['old' => $oldStatus, 'new' => InventoryTask::STATUS_COMPLETED],
            'workshop_ticket_id' => $ticket->getId(),
        ];
    }
}
