<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\WorkshopTicket;

final class WorkshopTicketPhaseService
{
    public function syncFromPartsUsed(WorkshopTicket $ticket): void
    {
        if ($ticket->getStrategy() !== WorkshopTicket::STRATEGY_INTERNAL_REPAIR) {
            return;
        }

        if (\in_array($ticket->getPhase(), [WorkshopTicket::PHASE_COMPLETED, WorkshopTicket::PHASE_CANCELLED], true)) {
            return;
        }

        $parts = $ticket->getPartsUsed();
        if (!\is_array($parts) || $parts === []) {
            return;
        }

        $hasOrdered = false;
        $hasOpenPurchase = false;

        foreach ($parts as $line) {
            if (!\is_array($line)) {
                continue;
            }
            if (($line['source'] ?? '') !== WorkshopPartsUsedValidator::SOURCE_PURCHASE) {
                continue;
            }
            $status = (string) ($line['status'] ?? '');
            if ($status === WorkshopPartsUsedValidator::STATUS_ORDERED) {
                $hasOrdered = true;
            }
            if (\in_array($status, [
                WorkshopPartsUsedValidator::STATUS_PLANNED,
                WorkshopPartsUsedValidator::STATUS_ORDERED,
            ], true)) {
                $hasOpenPurchase = true;
            }
        }

        if ($hasOrdered) {
            $ticket->setPhase(WorkshopTicket::PHASE_ORDERED);
            $ticket->syncStatusFromPhase();

            return;
        }

        if (!$hasOpenPurchase && \in_array($ticket->getPhase(), [
            WorkshopTicket::PHASE_ORDERED,
            WorkshopTicket::PHASE_PLANNING,
        ], true)) {
            $ticket->setPhase(WorkshopTicket::PHASE_READY);
            $ticket->syncStatusFromPhase();

            return;
        }

        if (!$hasOpenPurchase && $ticket->getPhase() === WorkshopTicket::PHASE_ORDERED) {
            $ticket->setPhase(WorkshopTicket::PHASE_PLANNING);
            $ticket->syncStatusFromPhase();
        }
    }

    /**
     * @return string|null Fehlermeldung oder null wenn erlaubt
     */
    public function validateAdvanceTo(WorkshopTicket $ticket, string $targetPhase): ?string
    {
        if (\in_array($ticket->getPhase(), [WorkshopTicket::PHASE_COMPLETED, WorkshopTicket::PHASE_CANCELLED], true)) {
            return 'Ticket ist bereits abgeschlossen oder storniert.';
        }

        if ($ticket->getStrategy() !== WorkshopTicket::STRATEGY_INTERNAL_REPAIR) {
            return 'Phasenwechsel ist nur für interne Reparaturen verfügbar.';
        }

        $current = $ticket->getPhase() ?? WorkshopTicket::PHASE_PLANNING;

        if ($targetPhase === WorkshopTicket::PHASE_READY) {
            if (!\in_array($current, [WorkshopTicket::PHASE_PLANNING, WorkshopTicket::PHASE_ORDERED], true)) {
                return 'Von dieser Phase aus kann nicht auf «Bereit» gewechselt werden.';
            }

            return $this->validatePartsReady($ticket);
        }

        if ($targetPhase === WorkshopTicket::PHASE_IN_PROGRESS) {
            if ($current === WorkshopTicket::PHASE_IN_PROGRESS) {
                return 'Reparatur läuft bereits.';
            }
            if (!\in_array($current, [
                WorkshopTicket::PHASE_PLANNING,
                WorkshopTicket::PHASE_READY,
            ], true)) {
                return 'Von dieser Phase aus kann die Reparatur nicht gestartet werden.';
            }

            return $this->validatePartsReady($ticket);
        }

        return 'Ungültiger Phasenwechsel.';
    }

    public function advanceTo(WorkshopTicket $ticket, string $targetPhase): void
    {
        $ticket->setPhase($targetPhase);
        $ticket->syncStatusFromPhase();

        if ($targetPhase === WorkshopTicket::PHASE_IN_PROGRESS && !$ticket->getStartedAt()) {
            $ticket->setStartedAt(new \DateTime());
        }
    }

    private function validatePartsReady(WorkshopTicket $ticket): ?string
    {
        $parts = $ticket->getPartsUsed();
        if (!\is_array($parts) || $parts === []) {
            return null;
        }

        foreach ($parts as $line) {
            if (!\is_array($line)) {
                continue;
            }
            if (($line['source'] ?? '') !== WorkshopPartsUsedValidator::SOURCE_PURCHASE) {
                continue;
            }
            $status = (string) ($line['status'] ?? '');
            if ($status === WorkshopPartsUsedValidator::STATUS_ORDERED) {
                return 'Es warten noch bestellte Teile auf Ankunft.';
            }
            if ($status === WorkshopPartsUsedValidator::STATUS_PLANNED) {
                return 'Einkaufspositionen müssen zuerst bestellt oder entfernt werden.';
            }
        }

        return null;
    }
}
