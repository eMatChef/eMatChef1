<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\Address;
use App\Entity\MaterialBatch;
use App\Entity\MaterialItem;
use App\Entity\WorkshopTicket;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

final class WorkshopPurchaseLineService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkshopTicketPhaseService $phaseService,
        private WorkshopOrderReminderService $orderReminderService,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed> updated line
     */
    public function markOrdered(WorkshopTicket $ticket, string $lineId, array $data): array
    {
        $line = $this->findLineOrFail($ticket, $lineId);
        $this->assertPurchaseLine($line);

        $line['status'] = WorkshopPartsUsedValidator::STATUS_ORDERED;
        $line['ordered_at'] = (new \DateTime())->format('c');
        $line = $this->applyPurchaseMeta($line, $data, false);

        $this->replaceLine($ticket, $lineId, $line);
        $this->phaseService->syncFromPartsUsed($ticket);
        $this->orderReminderService->syncForTicket($ticket);

        return $line;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed> updated line
     */
    public function receivePurchase(WorkshopTicket $ticket, string $lineId, array $data): array
    {
        $line = $this->findLineOrFail($ticket, $lineId);
        $this->assertPurchaseLine($line);

        $materialId = (string) ($line['material_item_id'] ?? '');
        $material = $this->entityManager->getRepository(MaterialItem::class)->find($materialId);
        if (!$material instanceof MaterialItem) {
            throw new WorkshopTicketCompletionException('Ersatzteil nicht gefunden', 'material_not_found');
        }

        $qty = (float) ($line['quantity'] ?? 0);
        if ($qty <= 0) {
            throw new WorkshopTicketCompletionException('Menge muss grösser als 0 sein', 'invalid_quantity');
        }

        $line = $this->applyPurchaseMeta($line, $data, true);
        $unitPrice = $line['unit_cost'] ?? null;
        $acquiredOn = $this->resolveAcquiredOn($line, $data);

        $batch = new MaterialBatch();
        $batch->setId(IdGenerator::generate13('ba'));
        $batch->setMaterialItem($material);
        $batch->setQty((int) ceil($qty));
        $batch->setBatchType('purchase');
        $batch->setStatus('active');
        $batch->setLabel('Werkstatt-Einkauf');
        $batch->setNotes('Einkauf Workshop-Ticket #' . $ticket->getId());
        $batch->setAcquiredOn($acquiredOn);
        if ($unitPrice !== null && $unitPrice !== '') {
            $batch->setUnitPrice((string) $unitPrice);
        }

        $supplierId = trim((string) ($line['supplier_id'] ?? ''));
        if ($supplierId !== '') {
            $supplier = $this->entityManager->getRepository(Address::class)->find($supplierId);
            if ($supplier instanceof Address) {
                $batch->setSupplier($supplier);
            }
        }

        $this->entityManager->persist($batch);

        $line['status'] = WorkshopPartsUsedValidator::STATUS_RECEIVED;
        $line['material_batch_id'] = $batch->getId();
        $line['received_at'] = (new \DateTime())->format('c');
        if (!isset($line['ordered_at'])) {
            $line['ordered_at'] = $line['received_at'];
        }

        $this->replaceLine($ticket, $lineId, $line);
        $this->phaseService->syncFromPartsUsed($ticket);
        $this->orderReminderService->syncForTicket($ticket);

        return $line;
    }

    /**
     * @param array<string, mixed> $line
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function applyPurchaseMeta(array $line, array $data, bool $deriveUnitCost): array
    {
        if (isset($data['supplier_id'])) {
            $line['supplier_id'] = trim((string) $data['supplier_id']) ?: null;
        }
        if (isset($data['purchase_location'])) {
            $line['purchase_location'] = trim((string) $data['purchase_location']) ?: null;
        }
        if (isset($data['document_date'])) {
            $doc = trim((string) $data['document_date']);
            $line['document_date'] = $doc !== '' ? $doc : null;
        }
        if (isset($data['purchase_total']) && is_numeric($data['purchase_total'])) {
            $line['purchase_total'] = number_format((float) $data['purchase_total'], 2, '.', '');
            if ($deriveUnitCost) {
                $qty = (float) ($line['quantity'] ?? 1);
                if ($qty > 0) {
                    $line['unit_cost'] = number_format((float) $line['purchase_total'] / $qty, 2, '.', '');
                }
            }
        }
        if (isset($data['receipt_url']) && \is_string($data['receipt_url'])) {
            $url = trim($data['receipt_url']);
            if ($url !== '') {
                $line['receipt_url'] = $url;
            }
        }

        return $line;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveAcquiredOn(array $line, array $data): \DateTime
    {
        $raw = $line['document_date'] ?? $data['document_date'] ?? null;
        if (\is_string($raw) && trim($raw) !== '') {
            $parsed = \DateTime::createFromFormat('Y-m-d', trim($raw));
            if ($parsed instanceof \DateTime) {
                return $parsed;
            }
        }

        return new \DateTime();
    }

    /**
     * @return array<string, mixed>
     */
    private function findLineOrFail(WorkshopTicket $ticket, string $lineId): array
    {
        $parts = $ticket->getPartsUsed();
        if (!\is_array($parts)) {
            throw new WorkshopTicketCompletionException('Keine Stückliste vorhanden', 'no_parts');
        }

        foreach ($parts as $line) {
            if (!\is_array($line)) {
                continue;
            }
            if (($line['id'] ?? '') === $lineId) {
                return $line;
            }
        }

        throw new WorkshopTicketCompletionException('Stücklisten-Zeile nicht gefunden', 'line_not_found');
    }

    /**
     * @param array<string, mixed> $line
     */
    private function assertPurchaseLine(array $line): void
    {
        if (($line['source'] ?? '') !== WorkshopPartsUsedValidator::SOURCE_PURCHASE) {
            throw new WorkshopTicketCompletionException('Nur Einkaufs-Zeilen sind erlaubt', 'not_purchase_line');
        }
    }

    /**
     * @param array<string, mixed> $line
     */
    private function replaceLine(WorkshopTicket $ticket, string $lineId, array $line): void
    {
        $parts = $ticket->getPartsUsed();
        if (!\is_array($parts)) {
            return;
        }

        $updated = [];
        foreach ($parts as $existing) {
            if (!\is_array($existing)) {
                continue;
            }
            $updated[] = ($existing['id'] ?? '') === $lineId ? $line : $existing;
        }

        $ticket->setPartsUsed($updated);
        $ticket->updateTimestamps();
    }
}
