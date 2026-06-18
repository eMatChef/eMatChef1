<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\SupplierCompany;
use App\Entity\WorkshopTicket;
final class WorkshopSendToSupplierService
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed> history changes
     */
    public function send(WorkshopTicket $ticket, array $data = []): array
    {
        $strategy = $ticket->getStrategy();
        if (!\in_array($strategy, [
            WorkshopTicket::STRATEGY_EXTERNAL_REPAIR,
            WorkshopTicket::STRATEGY_EXTERNAL_CLEANING,
        ], true)) {
            throw new WorkshopTicketCompletionException(
                'Nur externe Reparatur- oder Reinigungs-Tickets können an den Lieferanten gesendet werden',
                'not_external_supplier_ticket',
            );
        }

        if ($ticket->getAssignedToSupplierCompanyId() === null) {
            throw new WorkshopTicketCompletionException(
                'Kein Lieferant zugewiesen',
                'supplier_required',
            );
        }

        $supplier = $ticket->getAssignedToSupplierCompany();
        if (!$supplier instanceof SupplierCompany) {
            throw new WorkshopTicketCompletionException('Lieferant nicht gefunden', 'supplier_not_found');
        }

        if (!\in_array(SupplierCompany::CAPABILITY_REPAIRS, $supplier->getCapabilities(), true)) {
            throw new WorkshopTicketCompletionException(
                'Lieferant hat keine Reparatur-Capability',
                'supplier_no_repairs',
            );
        }

        if (\in_array($ticket->getStatus(), [WorkshopTicket::STATUS_COMPLETED, WorkshopTicket::STATUS_CANCELLED], true)) {
            throw new WorkshopTicketCompletionException(
                'Abgeschlossene oder stornierte Tickets können nicht gesendet werden',
                'ticket_closed',
            );
        }

        $allowedPhases = [
            WorkshopTicket::PHASE_PLANNING,
            WorkshopTicket::PHASE_READY,
            null,
        ];
        if ($ticket->getPhase() !== null && !\in_array($ticket->getPhase(), $allowedPhases, true)) {
            throw new WorkshopTicketCompletionException(
                'Ticket wurde bereits an den Lieferanten gesendet',
                'already_sent',
            );
        }

        $oldPhase = $ticket->getPhase();
        $oldStatus = $ticket->getStatus();
        $oldEstimated = $ticket->getEstimatedCost();

        $ticket->setPhase(WorkshopTicket::PHASE_AWAITING_QUOTE);
        $ticket->syncStatusFromPhase();

        if (\array_key_exists('estimated_cost', $data)) {
            $raw = $data['estimated_cost'];
            if ($raw !== null && $raw !== '') {
                $ticket->setEstimatedCost(number_format((float) $raw, 2, '.', ''));
            }
        }

        if (!$ticket->getStartedAt()) {
            $ticket->setStartedAt(new \DateTime());
        }

        $ticket->updateTimestamps();

        return [
            'phase' => ['old' => $oldPhase, 'new' => WorkshopTicket::PHASE_AWAITING_QUOTE],
            'status' => $oldStatus !== $ticket->getStatus()
                ? ['old' => $oldStatus, 'new' => $ticket->getStatus()]
                : null,
            'estimated_cost' => $oldEstimated !== $ticket->getEstimatedCost()
                ? ['old' => $oldEstimated, 'new' => $ticket->getEstimatedCost()]
                : null,
            'assigned_to_supplier_company_id' => $ticket->getAssignedToSupplierCompanyId(),
            'sent_to_supplier' => true,
        ];
    }
}
