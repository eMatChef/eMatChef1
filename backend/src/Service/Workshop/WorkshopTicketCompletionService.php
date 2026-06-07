<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\MaterialBatch;
use App\Entity\MaterialHistory;
use App\Entity\MaterialItem;
use App\Entity\User;
use App\Entity\WorkshopTicket;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Abschluss-Logik für Workshop-Tickets: Hauptmaterial vs. Ersatzteile (Stückliste).
 */
final class WorkshopTicketCompletionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function validateBeforeComplete(WorkshopTicket $ticket, string $resolutionAction): ?string
    {
        if ($resolutionAction !== 'repaired' && $resolutionAction !== 'ok') {
            return null;
        }

        if ($ticket->getStrategy() !== WorkshopTicket::STRATEGY_INTERNAL_REPAIR) {
            return null;
        }

        $partsUsed = $ticket->getPartsUsed();
        if (\is_array($partsUsed)) {
            foreach ($partsUsed as $line) {
                if (!\is_array($line)) {
                    continue;
                }
                if (($line['source'] ?? '') !== WorkshopPartsUsedValidator::SOURCE_PURCHASE) {
                    continue;
                }
                $status = (string) ($line['status'] ?? '');
                if (\in_array($status, [
                    WorkshopPartsUsedValidator::STATUS_PLANNED,
                    WorkshopPartsUsedValidator::STATUS_ORDERED,
                ], true)) {
                    $name = trim((string) ($line['material_name'] ?? 'Ersatzteil'));

                    return sprintf('Offene Einkaufs-Zeile «%s» muss zuerst eingebucht werden', $name);
                }
            }
        }

        foreach ($this->linesToConsumeAtCompletion($ticket) as $line) {
            $materialId = (string) ($line['material_item_id'] ?? '');
            $qty = $this->lineQuantity($line);
            if ($materialId === '' || $qty <= 0) {
                continue;
            }

            $source = (string) ($line['source'] ?? '');
            if ($source === WorkshopPartsUsedValidator::SOURCE_REST) {
                if (isset($line['available_qty']) && is_numeric($line['available_qty'])) {
                    $onHand = (float) $line['available_qty'];
                    if ($onHand < $qty) {
                        $name = trim((string) ($line['material_name'] ?? $materialId));
                        $unit = trim((string) ($line['quantity_unit'] ?? ''));

                        return sprintf(
                            'Vorrat für «%s» reicht nicht (benötigt: %s%s, vorhanden: %s%s)',
                            $name,
                            $this->formatQuantity($qty),
                            $unit !== '' ? ' '.$unit : '',
                            $this->formatQuantity($onHand),
                            $unit !== '' ? ' '.$unit : '',
                        );
                    }
                }
                continue;
            }

            $available = $this->resolveActiveStock($materialId);
            if ($available < $qty) {
                $name = trim((string) ($line['material_name'] ?? $materialId));

                return sprintf(
                    'Nicht genügend Bestand für «%s» (benötigt: %s, verfügbar: %d)',
                    $name,
                    $this->formatQuantity($qty),
                    $available,
                );
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data Transition-Payload (resolution_notes, writeoff_qty, …)
     *
     * @return array<string, mixed> History-Änderungen
     */
    public function applyCompletion(
        WorkshopTicket $ticket,
        string $resolutionAction,
        array $data,
        \DateTime $now,
        ?User $actor,
    ): array {
        $historyChanges = [];
        $material = $ticket->getMaterialItem();

        if ($resolutionAction === 'repaired' || $resolutionAction === 'ok') {
            $batchRepairHistory = $this->applyRepairedResolution($ticket, $material);
            if ($batchRepairHistory !== null) {
                $historyChanges = array_merge($historyChanges, $batchRepairHistory);
            } else {
                $oldCondition = $material->getCondition();
                $material->setCondition('ok');
                $material->updateTimestamps();
                $historyChanges['material_condition'] = ['old' => $oldCondition, 'new' => 'ok'];

                $this->createMaterialHistoryEntry($material, 'condition_changed', [
                    'condition' => ['old' => $oldCondition, 'new' => 'ok'],
                    'reason' => 'Workshop-Ticket #' . $ticket->getId() . ' abgeschlossen (repariert)',
                ], $actor);
            }

            if ($ticket->getStrategy() === WorkshopTicket::STRATEGY_INTERNAL_REPAIR) {
                $partsHistory = $this->consumePartsAtCompletion($ticket, $data, $now, $actor);
                if ($partsHistory !== []) {
                    $historyChanges = array_merge($historyChanges, $partsHistory);
                }
            }
        } elseif ($resolutionAction === 'writeoff') {
            $historyChanges = array_merge(
                $historyChanges,
                $this->applyWriteoffResolution($ticket, $material, $data, $now, $actor),
            );
        }

        $issueReport = $ticket->getIssueReport();
        if ($issueReport && !$issueReport->isResolved()) {
            $issueReport->setResolved(true);
            $issueReport->setResolvedAt($now);
            if ($actor instanceof User) {
                $issueReport->setResolvedByUser($actor);
            }
            $historyChanges['issue_report_resolved'] = true;
        }

        return $historyChanges;
    }

    /**
     * @param array<int, array<string, mixed>> $partsUsed
     *
     * @return array<int, array<string, mixed>>
     */
    public function calculatePartsMaterialCost(array $partsUsed): array
    {
        $lines = [];
        $total = 0.0;

        foreach ($partsUsed as $line) {
            if (!\is_array($line)) {
                continue;
            }
            if (($line['status'] ?? '') === WorkshopPartsUsedValidator::STATUS_CONSUMED) {
                continue;
            }
            $source = (string) ($line['source'] ?? '');
            if (!\in_array($source, [
                WorkshopPartsUsedValidator::SOURCE_STOCK,
                WorkshopPartsUsedValidator::SOURCE_PURCHASE,
                WorkshopPartsUsedValidator::SOURCE_REST,
            ], true)) {
                continue;
            }
            if ($source === WorkshopPartsUsedValidator::SOURCE_PURCHASE
                && ($line['status'] ?? '') !== WorkshopPartsUsedValidator::STATUS_RECEIVED) {
                continue;
            }

            $qty = $this->lineQuantity($line);
            $unitCost = is_numeric($line['unit_cost'] ?? null) ? (float) $line['unit_cost'] : 0.0;
            $lineCost = round($qty * $unitCost, 2);
            $total += $lineCost;
            $lines[] = [
                'material_item_id' => $line['material_item_id'] ?? null,
                'material_name' => $line['material_name'] ?? null,
                'quantity' => $qty,
                'unit_cost' => $line['unit_cost'] ?? null,
                'line_cost' => number_format($lineCost, 2, '.', ''),
            ];
        }

        return [
            'lines' => $lines,
            'total' => number_format(round($total, 2), 2, '.', ''),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function linesToConsumeAtCompletion(WorkshopTicket $ticket): array
    {
        $partsUsed = $ticket->getPartsUsed();
        if (!\is_array($partsUsed) || $partsUsed === []) {
            return [];
        }

        $lines = [];
        foreach ($partsUsed as $line) {
            if (!\is_array($line)) {
                continue;
            }
            if (($line['status'] ?? '') === WorkshopPartsUsedValidator::STATUS_CONSUMED) {
                continue;
            }
            $source = (string) ($line['source'] ?? '');
            if ($source === WorkshopPartsUsedValidator::SOURCE_STOCK
                || $source === WorkshopPartsUsedValidator::SOURCE_REST) {
                $lines[] = $line;
                continue;
            }
            if ($source === WorkshopPartsUsedValidator::SOURCE_PURCHASE
                && ($line['status'] ?? '') === WorkshopPartsUsedValidator::STATUS_RECEIVED) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function consumePartsAtCompletion(WorkshopTicket $ticket, array $data, \DateTime $now, ?User $actor): array
    {
        $partsUsed = $ticket->getPartsUsed();
        if (!\is_array($partsUsed) || $partsUsed === []) {
            return [];
        }

        $consumed = [];
        $updatedParts = [];
        $materialCost = $this->calculatePartsMaterialCost($partsUsed);
        $surplusByLineId = $this->parseSurplusQuantities($data);

        foreach ($partsUsed as $line) {
            if (!\is_array($line)) {
                continue;
            }

            $lineId = (string) ($line['id'] ?? '');
            $source = (string) ($line['source'] ?? '');
            $status = (string) ($line['status'] ?? '');
            $shouldConsume = $status !== WorkshopPartsUsedValidator::STATUS_CONSUMED
                && (
                    $source === WorkshopPartsUsedValidator::SOURCE_STOCK
                    || $source === WorkshopPartsUsedValidator::SOURCE_REST
                    || ($source === WorkshopPartsUsedValidator::SOURCE_PURCHASE && $status === WorkshopPartsUsedValidator::STATUS_RECEIVED)
                );

            if (!$shouldConsume) {
                $updatedParts[] = $line;
                continue;
            }

            $materialId = (string) ($line['material_item_id'] ?? '');
            $qty = $this->lineQuantity($line);
            if ($materialId === '' || $qty <= 0) {
                $updatedParts[] = $line;
                continue;
            }

            if ($source === WorkshopPartsUsedValidator::SOURCE_REST) {
                $remaining = null;
                if (isset($line['available_qty']) && is_numeric($line['available_qty'])) {
                    $remaining = max(0.0, (float) $line['available_qty'] - $qty);
                    $line['available_qty'] = $remaining;
                }
                $line['status'] = WorkshopPartsUsedValidator::STATUS_CONSUMED;
                $updatedParts[] = $line;
                $consumed[] = [
                    'material_item_id' => $materialId,
                    'material_name' => $line['material_name'] ?? null,
                    'quantity' => $qty,
                    'source' => $source,
                    'remaining_qty' => $remaining,
                ];
                continue;
            }

            $available = $this->resolveActiveStock($materialId);
            if ($available < $qty) {
                $name = trim((string) ($line['material_name'] ?? $materialId));
                throw new WorkshopTicketCompletionException(
                    sprintf(
                        'Nicht genügend Bestand für «%s» (benötigt: %s, verfügbar: %d)',
                        $name,
                        $this->formatQuantity($qty),
                        $available,
                    ),
                    'insufficient_stock',
                );
            }

            $spareMaterial = $this->entityManager->getRepository(MaterialItem::class)->find($materialId);
            if (!$spareMaterial instanceof MaterialItem) {
                throw new WorkshopTicketCompletionException('Ersatzteil nicht gefunden: ' . $materialId, 'material_not_found');
            }

            $consumptionBatch = new MaterialBatch();
            $consumptionBatch->setId(IdGenerator::generate13('ba'));
            $consumptionBatch->setMaterialItem($spareMaterial);
            $consumptionBatch->setQty(-(int) ceil($qty));
            $consumptionBatch->setBatchType('adjustment');
            $consumptionBatch->setStatus('active');
            $consumptionBatch->setLabel('Werkstatt-Verbrauch');
            $consumptionBatch->setNotes('Verbrauch via Workshop-Ticket #' . $ticket->getId());
            $consumptionBatch->setAcquiredOn($now);
            if (is_numeric($line['unit_cost'] ?? null)) {
                $consumptionBatch->setUnitPrice(number_format((float) $line['unit_cost'], 2, '.', ''));
            }

            $this->entityManager->persist($consumptionBatch);

            $surplusQty = $surplusByLineId[$lineId] ?? 0.0;
            if ($surplusQty > 0) {
                $surplusBatch = new MaterialBatch();
                $surplusBatch->setId(IdGenerator::generate13('ba'));
                $surplusBatch->setMaterialItem($spareMaterial);
                $surplusBatch->setQty((int) ceil($surplusQty));
                $surplusBatch->setBatchType('purchase');
                $surplusBatch->setStatus('active');
                $surplusBatch->setLabel('Werkstatt-Rest');
                $surplusBatch->setNotes('Rest Workshop-Ticket #' . $ticket->getId());
                $surplusBatch->setAcquiredOn($now);
                if (is_numeric($line['unit_cost'] ?? null)) {
                    $surplusBatch->setUnitPrice((string) $line['unit_cost']);
                }
                $this->entityManager->persist($surplusBatch);
                $line['surplus_qty'] = $surplusQty;
            }

            $this->createMaterialHistoryEntry($spareMaterial, 'stock_adjustment', [
                'qty' => ['old' => $available, 'new' => $available - (int) ceil($qty)],
                'batch_id' => $consumptionBatch->getId(),
                'reason' => 'Workshop-Ticket #' . $ticket->getId() . ' – Ersatzteil-Verbrauch',
            ], $actor);

            $line['status'] = WorkshopPartsUsedValidator::STATUS_CONSUMED;
            $updatedParts[] = $line;

            $consumed[] = [
                'material_item_id' => $materialId,
                'material_name' => $line['material_name'] ?? null,
                'quantity' => $qty,
                'batch_id' => $consumptionBatch->getId(),
            ];
        }

        if ($consumed === []) {
            return [];
        }

        $ticket->setPartsUsed($updatedParts);

        return [
            'parts_consumed' => $consumed,
            'parts_material_cost' => $materialCost['total'],
        ];
    }

    /** @return array<string, mixed> */
    private function applyWriteoffResolution(
        WorkshopTicket $ticket,
        MaterialItem $material,
        array $data,
        \DateTime $now,
        ?User $actor,
    ): array {
        $oldCondition = $material->getCondition();
        $material->setCondition('defect');
        $material->updateTimestamps();

        $writeoffQty = (int) ($data['writeoff_qty'] ?? 1);
        $writeoffBatch = new MaterialBatch();
        $writeoffBatch->setId(IdGenerator::generate13('ba'));
        $writeoffBatch->setMaterialItem($material);
        $writeoffBatch->setQty(-$writeoffQty);
        $writeoffBatch->setBatchType('writeoff');
        $writeoffBatch->setStatus('active');
        $writeoffBatch->setLabel('Abschreibung');
        $writeoffBatch->setNotes(
            'Abgeschrieben via Workshop-Ticket #' . $ticket->getId()
            . (!empty($data['resolution_notes']) ? ' – ' . $data['resolution_notes'] : '')
        );
        $writeoffBatch->setAcquiredOn($now);

        $this->entityManager->persist($writeoffBatch);

        $this->createMaterialHistoryEntry($material, 'writeoff', [
            'condition' => ['old' => $oldCondition, 'new' => 'defect'],
            'writeoff_qty' => $writeoffQty,
            'batch_id' => $writeoffBatch->getId(),
            'reason' => 'Workshop-Ticket #' . $ticket->getId() . ' – Abschreibung',
        ], $actor);

        return [
            'material_condition' => ['old' => $oldCondition, 'new' => 'defect'],
            'writeoff_batch_id' => $writeoffBatch->getId(),
            'writeoff_qty' => $writeoffQty,
        ];
    }

    /** @return array<string, mixed>|null */
    private function applyRepairedResolution(WorkshopTicket $ticket, MaterialItem $material): ?array
    {
        $batch = $ticket->getMaterialBatch();
        if (!$batch instanceof MaterialBatch) {
            return null;
        }

        $oldStatus = $batch->getStatus();
        $batch->setStatus('active');

        return [
            'material_batch_id' => $batch->getId(),
            'material_batch_status' => ['old' => $oldStatus, 'new' => 'active'],
        ];
    }

    private function resolveActiveStock(string $materialItemId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(b.qty), 0)')
            ->from(MaterialBatch::class, 'b')
            ->where('b.materialItemId = :mid')
            ->andWhere('b.status = :active')
            ->setParameter('mid', $materialItemId)
            ->setParameter('active', 'active')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function lineQuantity(array $line): float
    {
        $qty = (float) ($line['quantity'] ?? 0);

        return $qty > 0 ? $qty : 0.0;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, float>
     */
    private function parseSurplusQuantities(array $data): array
    {
        $result = [];
        $raw = $data['parts_surplus'] ?? null;
        if (!\is_array($raw)) {
            return $result;
        }

        foreach ($raw as $lineId => $qty) {
            if (!\is_string($lineId) && !\is_int($lineId)) {
                continue;
            }
            if (!is_numeric($qty)) {
                continue;
            }
            $value = (float) $qty;
            if ($value > 0) {
                $result[(string) $lineId] = $value;
            }
        }

        return $result;
    }

    private function formatQuantity(float $qty): string
    {
        if (abs($qty - round($qty)) < 0.00001) {
            return (string) (int) round($qty);
        }

        return rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
    }

    private function createMaterialHistoryEntry(
        MaterialItem $material,
        string $action,
        array $changes,
        ?User $actor,
    ): void {
        $history = new MaterialHistory();
        $history->setId(IdGenerator::generate13('hi'));
        $history->setMaterialItem($material);
        $history->setAction($action);
        $history->setSnapshot([
            'condition' => $material->getCondition(),
            'name' => $material->getName(),
        ]);
        $history->setChanges($changes);

        if ($actor instanceof User) {
            $history->setUser($actor);
        }

        $this->entityManager->persist($history);
    }
}
