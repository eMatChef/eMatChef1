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
 * Bucht Reste eines abgeschriebenen Hauptmaterials als Ersatzteil/Übungsmaterial ins Lager.
 */
final class WorkshopWriteoffRepurposeService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkshopSparePartsCategoryBootstrapService $sparePartsCategoryBootstrap,
    ) {
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed> History-Änderungen
     */
    public function apply(WorkshopTicket $ticket, array $raw, \DateTime $now, ?User $actor): array
    {
        if ($raw === []) {
            return [];
        }

        $targetMaterialId = trim((string) ($raw['material_item_id'] ?? ''));
        if ($targetMaterialId === '') {
            throw new WorkshopTicketCompletionException(
                'writeoff_repurpose.material_item_id ist erforderlich',
                'repurpose_material_required',
            );
        }

        $stockAlreadyBooked = (bool) ($raw['stock_already_booked'] ?? false);
        $quantity = (float) ($raw['quantity'] ?? 0);
        if (!$stockAlreadyBooked && $quantity <= 0) {
            throw new WorkshopTicketCompletionException(
                'writeoff_repurpose.quantity muss grösser als 0 sein',
                'invalid_repurpose_qty',
            );
        }

        $quantityUnit = trim((string) ($raw['quantity_unit'] ?? 'Stk'));
        if ($quantityUnit === '') {
            $quantityUnit = 'Stk';
        }

        $departmentId = $ticket->getDepartmentId();
        $spareCategoryId = $this->sparePartsCategoryBootstrap->ensure($departmentId);
        $sourceMaterial = $ticket->getMaterialItem();

        $targetMaterial = $this->entityManager->getRepository(MaterialItem::class)->find($targetMaterialId);
        if (!$targetMaterial instanceof MaterialItem || $targetMaterial->getDepartmentId() !== $departmentId) {
            throw new WorkshopTicketCompletionException('Ziel-Material nicht gefunden', 'repurpose_material_not_found');
        }
        if ($targetMaterial->getCategoryId() !== $spareCategoryId) {
            throw new WorkshopTicketCompletionException(
                'Ziel-Material muss in der Ersatzteile-Kategorie liegen',
                'repurpose_category_mismatch',
            );
        }

        $repurposeBatchId = null;
        if (!$stockAlreadyBooked) {
            $repurposeBatch = new MaterialBatch();
            $repurposeBatch->setId(IdGenerator::generate13('ba'));
            $repurposeBatch->setMaterialItem($targetMaterial);
            $repurposeBatch->setQty((int) round($quantity));
            $repurposeBatch->setBatchType('purchase');
            $repurposeBatch->setStatus('active');
            $repurposeBatch->setLabel('Werkstatt-Umlagerung');
            $repurposeBatch->setNotes(sprintf(
                'Umlagerung aus Workshop-Ticket #%s (%s)',
                $ticket->getId(),
                $sourceMaterial->getName(),
            ));
            $repurposeBatch->setAcquiredOn($now);

            $unitCost = $raw['unit_cost'] ?? $sourceMaterial->getReferencePurchaseUnitChf();
            if ($unitCost !== null && $unitCost !== '' && is_numeric($unitCost)) {
                $repurposeBatch->setUnitPrice(number_format((float) $unitCost, 2, '.', ''));
            }

            $this->entityManager->persist($repurposeBatch);
            $repurposeBatchId = $repurposeBatch->getId();

            $this->createMaterialHistoryEntry($targetMaterial, 'stock_adjustment', [
                'batch_id' => $repurposeBatchId,
                'qty_added' => (int) round($quantity),
                'quantity_unit' => $quantityUnit,
                'source_material_id' => $sourceMaterial->getId(),
                'source_ticket_id' => $ticket->getId(),
            ], $actor);
        } else {
            $this->createMaterialHistoryEntry($targetMaterial, 'stock_adjustment', [
                'source_material_id' => $sourceMaterial->getId(),
                'source_ticket_id' => $ticket->getId(),
                'stock_already_booked' => true,
            ], $actor);
        }

        $ticketBatch = $ticket->getMaterialBatch();
        if ($ticketBatch instanceof MaterialBatch) {
            $ticketBatch->setStatus('disposed');
            $ticketBatch->setNotes(trim(($ticketBatch->getNotes() ?? '') . ' Abgeschrieben via Ticket #' . $ticket->getId()));
        }

        return [
            'writeoff_repurpose' => [
                'quantity' => $stockAlreadyBooked ? null : $quantity,
                'quantity_unit' => $quantityUnit,
                'target_material_id' => $targetMaterial->getId(),
                'target_material_name' => $targetMaterial->getName(),
                'batch_id' => $repurposeBatchId,
                'stock_already_booked' => $stockAlreadyBooked,
            ],
        ];
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
