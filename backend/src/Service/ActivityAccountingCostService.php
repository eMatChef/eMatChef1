<?php

namespace App\Service;

use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\Activity;
use App\Entity\ActivityIssueReport;
use App\Entity\ActivityItem;
use App\Entity\Department;
use App\Entity\MaterialItem;
use App\Entity\WorkshopTicket;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Legt ausstehende Buchhaltungs-Aufträge für Aktivitätskosten an
 * (Verbrauch, Verlust, Nachkauf, Werkstatt).
 */
class ActivityAccountingCostService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InboxMessageService $inboxMessages,
    ) {
    }

    public function enqueueFromConsumption(Activity $activity, ActivityIssueReport $report): void
    {
        if ($report->getType() !== ActivityIssueReport::TYPE_CONSUMPTION) {
            return;
        }
        $material = $report->getMaterialItem();
        if ($material === null) {
            return;
        }

        $amount = $this->consumptionIssueCharge($activity->getId(), $material->getId(), $report->getQuantity());
        if ($amount === null || $amount <= 0) {
            return;
        }

        $label = sprintf(
            'Aktivität %s · Verbrauch %s (%d Stk.)',
            $activity->getName(),
            $material->getName(),
            $report->getQuantity()
        );

        $this->upsertPending(
            $activity,
            AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_CONSUMPTION,
            $report->getId(),
            $amount,
            $label,
            $material
        );
    }

    public function enqueueFromLoss(Activity $activity, ActivityIssueReport $report): void
    {
        if ($report->getType() !== ActivityIssueReport::TYPE_LOSS) {
            return;
        }
        $material = $report->getMaterialItem();
        if ($material === null) {
            return;
        }

        $unit = $this->parseMoney($material->getSalePrice());
        if ($unit === null || $unit <= 0) {
            return;
        }

        $amount = $unit * $report->getQuantity();
        $label = sprintf(
            'Aktivität %s · Verlust %s (%d Stk.)',
            $activity->getName(),
            $material->getName(),
            $report->getQuantity()
        );

        $this->upsertPending(
            $activity,
            AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_LOSS,
            $report->getId(),
            $amount,
            $label,
            $material
        );
    }

    public function enqueueFromReplenishment(Activity $activity, ActivityItem $item): void
    {
        if (!$item->getIsReplenishment()) {
            return;
        }

        $amount = $this->parseMoney($item->getLineTotal());
        if ($amount === null || $amount <= 0) {
            return;
        }

        $material = $item->getMaterialItem();
        $label = sprintf(
            'Aktivität %s · Nachkauf %s (%d Stk.)',
            $activity->getName(),
            $material->getName(),
            $item->getQuantity()
        );

        $this->upsertPending(
            $activity,
            AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_REPLENISHMENT,
            $item->getId(),
            $amount,
            $label,
            $material
        );
    }

    public function enqueueFromWorkshopTicket(WorkshopTicket $ticket): void
    {
        $activity = $ticket->getActivity();
        if ($activity === null) {
            return;
        }
        if ($ticket->getStatus() !== 'completed') {
            return;
        }

        $resolution = $ticket->getResolutionAction();
        if (!in_array($resolution, ['repaired', 'writeoff'], true)) {
            return;
        }

        $amount = $this->parseMoney($ticket->getActualCost());
        if ($amount === null || $amount <= 0) {
            return;
        }

        $material = $ticket->getMaterialItem();
        $kindLabel = $resolution === 'writeoff' ? 'Abschreibung' : 'Reparatur';
        $matName = $material !== null ? $material->getName() : ($ticket->getTitle() ?: 'Werkstatt');
        $label = sprintf('Aktivität %s · Werkstatt %s: %s', $activity->getName(), $kindLabel, $matName);

        $this->upsertPending(
            $activity,
            AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_WORKSHOP,
            $ticket->getId(),
            $amount,
            $label,
            $material
        );
    }

    private function upsertPending(
        Activity $activity,
        string $sourceKind,
        string $sourceRefId,
        float $amount,
        string $receiptLabel,
        ?MaterialItem $materialItem,
    ): void {
        try {
            $department = $activity->getDepartment();
            if (!$department instanceof Department) {
                return;
            }

            $amountStr = number_format($amount, 2, '.', '');

            $existing = $this->entityManager->createQueryBuilder()
                ->select('f')
                ->from(AccountingAcquisitionFollowUp::class, 'f')
                ->where('f.sourceKind = :sk')
                ->andWhere('f.sourceRefId = :ref')
                ->andWhere('f.status = :st')
                ->setParameter('sk', $sourceKind)
                ->setParameter('ref', $sourceRefId)
                ->setParameter('st', AccountingAcquisitionFollowUp::STATUS_PENDING)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existing instanceof AccountingAcquisitionFollowUp) {
                $existing->setAmount($amountStr);
                $existing->setReceiptLabel(mb_substr($receiptLabel, 0, 255));
                $existing->setSuggestedDate(new \DateTimeImmutable('today'));
                if ($materialItem !== null) {
                    $existing->setMaterialItem($materialItem);
                }
                $existing->touchUpdatedAt();
                $this->entityManager->flush();
                $this->inboxMessages->syncAccountingFollowUp($existing);

                return;
            }

            $followUp = new AccountingAcquisitionFollowUp();
            $followUp->setId(IdGenerator::generateUnique($this->entityManager, AccountingAcquisitionFollowUp::class));
            $followUp->setDepartment($department);
            $followUp->setActivity($activity);
            $followUp->setSourceKind($sourceKind);
            $followUp->setSourceRefId($sourceRefId);
            $followUp->setAmount($amountStr);
            $followUp->setSuggestedDate(new \DateTimeImmutable('today'));
            $followUp->setReceiptLabel(mb_substr($receiptLabel, 0, 255));
            $followUp->setStatus(AccountingAcquisitionFollowUp::STATUS_PENDING);
            if ($materialItem !== null) {
                $followUp->setMaterialItem($materialItem);
            }

            $this->entityManager->persist($followUp);
            $this->entityManager->flush();
            $this->inboxMessages->syncAccountingFollowUp($followUp);
        } catch (\Throwable) {
            // Schema/Migration fehlt oder DB-Fehler — Aktivitäts-Workflow darf nicht abbrechen
        }
    }

    /**
     * Kosten für eine einzelne Verbrauchsmeldung (FIFO Lager → Nachkauf).
     */
    private function consumptionIssueCharge(string $activityId, string $materialItemId, int $issueQty): ?float
    {
        if ($issueQty <= 0) {
            return null;
        }

        $items = $this->entityManager->getRepository(ActivityItem::class)->findBy([
            'activityId' => $activityId,
            'materialItemId' => $materialItemId,
        ]);

        $consumableLines = array_filter(
            $items,
            static fn (ActivityItem $i) => $i->getIsConsumable()
        );
        if ($consumableLines === []) {
            return null;
        }

        $salePrice = null;
        $warehouseQty = 0;
        $replenLines = [];

        foreach ($consumableLines as $line) {
            $mi = $line->getMaterialItem();
            if ($salePrice === null) {
                $salePrice = $this->parseMoney($mi->getSalePrice());
            }
            if ($line->getIsReplenishment()) {
                $lineTotal = $this->parseMoney($line->getLineTotal());
                $unit = $this->parseMoney($line->getUnitPrice());
                if ($unit === null && $lineTotal !== null && $line->getQuantity() > 0) {
                    $unit = $lineTotal / $line->getQuantity();
                }
                $replenLines[] = ['qty' => $line->getQuantity(), 'unit' => $unit];
            } else {
                $warehouseQty += $line->getQuantity();
            }
        }

        $issues = $this->entityManager->getRepository(ActivityIssueReport::class)->findBy([
            'activityId' => $activityId,
        ]);

        $totalUsed = 0;
        foreach ($issues as $iss) {
            if ($iss->getType() === ActivityIssueReport::TYPE_CONSUMPTION
                && $iss->getMaterialItemId() === $materialItemId
            ) {
                $totalUsed += $iss->getQuantity();
            }
        }

        $usedBefore = max(0, $totalUsed - $issueQty);
        $fromWarehouseTotal = min($totalUsed, $warehouseQty);
        $fromWarehouseBefore = min($usedBefore, $warehouseQty);
        $fromReplenTotal = max(0, $totalUsed - $warehouseQty);
        $fromReplenBefore = max(0, $usedBefore - $warehouseQty);

        $whThis = $fromWarehouseTotal - $fromWarehouseBefore;
        $replThis = $fromReplenTotal - $fromReplenBefore;

        $cost = $whThis * ($salePrice ?? 0.0);
        $remaining = $replThis;

        foreach ($replenLines as $line) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, $line['qty']);
            $remaining -= $take;
            if ($line['unit'] !== null) {
                $cost += $take * $line['unit'];
            } elseif ($salePrice !== null) {
                $cost += $take * $salePrice;
            }
        }

        return $cost > 0 ? $cost : null;
    }

    private function parseMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }
        if (!is_numeric($value)) {
            return null;
        }
        $n = (float) $value;

        return $n > 0 ? $n : null;
    }
}
