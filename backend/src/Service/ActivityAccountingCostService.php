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
 * Buchhaltung für Aktivitätskosten: mehrere Anschaffungs-Aufträge pro Aktivität
 * (Verbrauch gesammelt, Miete extern, Werkstatt pro Ticket, optional Nachlieferung).
 */
class ActivityAccountingCostService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InboxMessageService $inboxMessages,
    ) {
    }

    /**
     * Alle Buchhaltungs-Aufträge zur Aktivität anlegen/aktualisieren.
     * Verbrauch/Miete/Nachlieferung: erst bei Status «abgeschlossen» — vorher nur Tab «Kosten».
     */
    public function syncActivityAccountingFollowUps(Activity $activity): void
    {
        if (!in_array($activity->getStatus(), [
            Activity::STATUS_RETURNED,
            Activity::STATUS_STORING,
            Activity::STATUS_AT_EVENT,
            Activity::STATUS_PACKED,
            Activity::STATUS_PACKING,
            Activity::STATUS_COMPLETED,
        ], true)) {
            return;
        }

        $this->removePendingFinalBillingForActivity($activity);
        $this->syncConsumptionFollowUp($activity);
        $this->syncReplenishmentFollowUp($activity);
        $this->syncRentalFollowUp($activity);
        $this->syncWorkshopFollowUps($activity);
    }

    /** @deprecated */
    public function enqueueFromConsumption(Activity $activity, ActivityIssueReport $report): void
    {
        $this->syncActivityAccountingFollowUps($activity);
    }

    public function enqueueAccountingForMaterialOnStore(Activity $activity, string $materialItemId): void
    {
        $this->syncActivityAccountingFollowUps($activity);
    }

    /** @deprecated */
    public function finalizeConsumptionAccountingForActivity(Activity $activity): void
    {
        $this->syncActivityAccountingFollowUps($activity);
    }

    /** @deprecated Keine Sammel-Endabrechnung mehr. */
    public function enqueueFinalActivityBilling(Activity $activity): void
    {
        $this->syncActivityAccountingFollowUps($activity);
    }

    /** @deprecated */
    public function ensurePendingFinalBilling(Activity $activity): void
    {
        $this->syncActivityAccountingFollowUps($activity);
    }

    public function enqueueFromWorkshopTicket(WorkshopTicket $ticket): void
    {
        $activity = $ticket->getActivity();
        if ($activity === null) {
            return;
        }
        $this->syncWorkshopFollowUps($activity);
    }

    /** @deprecated Nur noch für Tests/Vergleich — Summe aller Follow-up-Beträge. */
    public function computeActivityBillingTotal(Activity $activity): float
    {
        $activityId = $activity->getId();
        $total = 0.0;

        foreach ($this->consumableMaterialIdsForActivity($activityId) as $materialItemId) {
            $total += $this->computeConsumableMaterialTotalCost($activityId, $materialItemId);
        }

        $total += $this->computeReplenishmentPurchaseTotal($activityId);

        $workshopTickets = $this->entityManager->getRepository(WorkshopTicket::class)->findBy([
            'activityId' => $activityId,
            'status' => WorkshopTicket::STATUS_COMPLETED,
        ]);
        foreach ($workshopTickets as $ticket) {
            $resolution = $ticket->getResolutionAction();
            if (!in_array($resolution, ['repaired', 'writeoff'], true)) {
                continue;
            }
            $amount = $this->parseMoney($ticket->getActualCost());
            if ($amount !== null && $amount > 0) {
                $total += $amount;
            }
        }

        if ($activity->getType() === 'external') {
            $total += $this->computeExternalRentalTotal($activityId);
        }

        return round($total, 2);
    }

    private function syncConsumptionFollowUp(Activity $activity): void
    {
        $activityId = $activity->getId();
        if (!$activityId) {
            return;
        }

        /** Verbrauch: erst bei «abgeschlossen» Buchhaltungs-Auftrag — vorher nur Tab «Kosten». */
        if ($activity->getStatus() !== Activity::STATUS_COMPLETED) {
            $this->removePendingFollowUp(
                $activity,
                AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_CONSUMPTION,
                $activityId,
            );

            return;
        }

        $amount = 0.0;
        foreach ($this->consumableMaterialIdsForActivity($activityId) as $materialItemId) {
            $amount += $this->computeConsumableMaterialTotalCost($activityId, $materialItemId);
        }
        $amount = round($amount, 2);

        if ($amount <= 0) {
            $this->removePendingFollowUp(
                $activity,
                AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_CONSUMPTION,
                $activityId,
            );

            return;
        }

        $label = sprintf(
            'Aktivität %s · Verbrauchsmaterial',
            $activity->getName()
        );

        $this->upsertPending(
            $activity,
            AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_CONSUMPTION,
            $activityId,
            $amount,
            $label,
            null,
            onlyIfPending: true,
        );
    }

    private function syncReplenishmentFollowUp(Activity $activity): void
    {
        $activityId = $activity->getId();
        if (!$activityId) {
            return;
        }

        $this->removePendingFollowUp(
            $activity,
            AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_REPLENISHMENT,
            $activityId . ':replenishment',
        );

        /** Nachkäufe: erst bei «abgeschlossen» Buchhaltungs-Auftrag — vorher nur Tab «Kosten». */
        if ($activity->getStatus() !== Activity::STATUS_COMPLETED) {
            $this->removeOrphanReplenishmentFollowUps($activity, []);

            return;
        }

        $bySubmitterDept = $this->replenishmentTotalsBySubmitterDepartment($activity);
        $activeRefIds = [];
        $hostDept = $activity->getDepartment();

        foreach ($bySubmitterDept as $deptId => $row) {
            $amount = round($row['amount'], 2);
            $refId = $activityId . ':replenishment:' . $deptId;
            $activeRefIds[] = $refId;

            if ($amount <= 0) {
                $this->removePendingFollowUp(
                    $activity,
                    AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_REPLENISHMENT,
                    $refId,
                );
                continue;
            }

            $label = sprintf(
                'Aktivität %s · Nachlieferungen — %s',
                $activity->getName(),
                $row['department_name'],
            );

            $billingDept = $this->entityManager->find(Department::class, $deptId) ?? $hostDept;
            if (!$billingDept instanceof Department) {
                continue;
            }

            $this->upsertPending(
                $activity,
                AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_REPLENISHMENT,
                $refId,
                $amount,
                $label,
                null,
                onlyIfPending: true,
                billingDepartment: $billingDept,
            );
        }

        $this->removeOrphanReplenishmentFollowUps($activity, $activeRefIds);
    }

    /**
     * @return array<string, array{amount: float, department_name: string}>
     */
    private function replenishmentTotalsBySubmitterDepartment(Activity $activity): array
    {
        $activityId = $activity->getId();
        if (!$activityId) {
            return [];
        }

        $hostDept = $activity->getDepartment();
        $hostName = $hostDept?->getName() ?? 'Department';
        $hostId = $activity->getDepartmentId();

        $items = $this->entityManager->getRepository(ActivityItem::class)->findBy([
            'activityId' => $activityId,
            'isReplenishment' => true,
        ]);

        $out = [];
        foreach ($items as $item) {
            if (!$item instanceof ActivityItem) {
                continue;
            }
            $lineTotal = $this->parseMoney($item->getLineTotal());
            if ($lineTotal === null || $lineTotal <= 0) {
                $unit = $this->parseMoney($item->getUnitPrice());
                if ($unit !== null && $item->getQuantity() > 0) {
                    $lineTotal = $unit * $item->getQuantity();
                } else {
                    continue;
                }
            }

            $deptId = $item->getSubmitterDepartmentId() ?? $hostId;
            if ($deptId === null || $deptId === '') {
                continue;
            }

            if (!isset($out[$deptId])) {
                $dept = $item->getSubmitterDepartment();
                $out[$deptId] = [
                    'amount' => 0.0,
                    'department_name' => $dept?->getName() ?? $hostName,
                ];
            }
            $out[$deptId]['amount'] += $lineTotal;
        }

        return $out;
    }

    /**
     * @param list<string> $activeRefIds
     */
    private function removeOrphanReplenishmentFollowUps(Activity $activity, array $activeRefIds): void
    {
        try {
            $qb = $this->entityManager->createQueryBuilder()
                ->select('f')
                ->from(AccountingAcquisitionFollowUp::class, 'f')
                ->where('f.activity = :activity')
                ->andWhere('f.status = :pending')
                ->andWhere('f.sourceKind = :kind')
                ->setParameter('activity', $activity)
                ->setParameter('pending', AccountingAcquisitionFollowUp::STATUS_PENDING)
                ->setParameter('kind', AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_REPLENISHMENT);

            if ($activeRefIds !== []) {
                $qb->andWhere('f.sourceRefId NOT IN (:refs)')
                    ->setParameter('refs', $activeRefIds);
            }

            foreach ($qb->getQuery()->getResult() as $followUp) {
                if ($followUp instanceof AccountingAcquisitionFollowUp) {
                    $this->entityManager->remove($followUp);
                    $this->inboxMessages->removeAccountingFollowUpInbox($followUp->getId());
                }
            }
            $this->entityManager->flush();
        } catch (\Throwable) {
        }
    }

    private function syncRentalFollowUp(Activity $activity): void
    {
        $activityId = $activity->getId();
        if (!$activityId || $activity->getType() !== 'external') {
            if ($activityId) {
                $this->removePendingFollowUp(
                    $activity,
                    AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_RENTAL,
                    $activityId,
                );
            }

            return;
        }

        /** Externe Miete: Buchhaltungs-Auftrag erst bei Aktivitäts-Abschluss. */
        if ($activity->getStatus() !== Activity::STATUS_COMPLETED) {
            $this->removePendingFollowUp(
                $activity,
                AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_RENTAL,
                $activityId,
            );

            return;
        }

        $amount = round($this->computeExternalRentalTotal($activityId), 2);

        if ($amount <= 0) {
            $this->removePendingFollowUp(
                $activity,
                AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_RENTAL,
                $activityId,
            );

            return;
        }

        $label = sprintf(
            'Aktivität %s · Ausleihmaterial (extern)',
            $activity->getName()
        );

        $this->upsertPending(
            $activity,
            AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_RENTAL,
            $activityId,
            $amount,
            $label,
            null,
            onlyIfPending: true,
        );
    }

    private function syncWorkshopFollowUps(Activity $activity): void
    {
        $activityId = $activity->getId();
        if (!$activityId) {
            return;
        }

        $tickets = $this->entityManager->getRepository(WorkshopTicket::class)->findBy([
            'activityId' => $activityId,
        ]);

        $activeTicketIds = [];
        foreach ($tickets as $ticket) {
            $ticketId = $ticket->getId();
            if (!$ticketId) {
                continue;
            }
            $activeTicketIds[$ticketId] = true;

            if ($ticket->getStatus() !== WorkshopTicket::STATUS_COMPLETED) {
                $this->removePendingFollowUp(
                    $activity,
                    AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_WORKSHOP,
                    $ticketId,
                );
                continue;
            }

            $resolution = $ticket->getResolutionAction();
            if (!in_array($resolution, ['repaired', 'writeoff'], true)) {
                $this->removePendingFollowUp(
                    $activity,
                    AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_WORKSHOP,
                    $ticketId,
                );
                continue;
            }

            $amount = $this->parseMoney($ticket->getActualCost());
            if ($amount === null || $amount <= 0) {
                $this->removePendingFollowUp(
                    $activity,
                    AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_WORKSHOP,
                    $ticketId,
                );
                continue;
            }

            $material = $ticket->getMaterialItem();
            $billingDept = $material?->getDepartment() ?? $activity->getDepartment();
            if (!$billingDept instanceof Department) {
                continue;
            }

            $issue = $ticket->getIssueReport();
            $reporterName = null;
            if ($issue !== null) {
                $reporter = $issue->getReportedByUser();
                if ($reporter !== null) {
                    $profile = $reporter->getProfile();
                    if ($profile !== null) {
                        $reporterName = trim($profile->getFirstName() . ' ' . $profile->getLastName());
                        if ($reporterName === '') {
                            $reporterName = trim((string) ($profile->getNickname() ?? '')) ?: null;
                        }
                    }
                }
            }

            $kindLabel = $ticket->getResolutionAction() === 'writeoff' ? 'Abschreibung' : 'Reparatur';
            $matLabel = $material?->getName() ?? $ticket->getTitle();
            $label = sprintf(
                'Aktivität %s · Werkstatt %s · %s',
                $activity->getName(),
                $kindLabel,
                $matLabel,
            );
            if ($reporterName !== null && $reporterName !== '') {
                $label .= ' · gemeldet von ' . $reporterName;
            }

            $this->upsertPending(
                $activity,
                AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_WORKSHOP,
                $ticketId,
                $amount,
                $label,
                $material,
                onlyIfPending: true,
                billingDepartment: $billingDept,
            );
        }

        $this->removeOrphanWorkshopFollowUps($activity, array_keys($activeTicketIds));
    }

    /**
     * @param list<string> $validTicketIds
     */
    private function removeOrphanWorkshopFollowUps(Activity $activity, array $validTicketIds): void
    {
        try {
            $qb = $this->entityManager->createQueryBuilder()
                ->select('f')
                ->from(AccountingAcquisitionFollowUp::class, 'f')
                ->where('f.activity = :activity')
                ->andWhere('f.status = :pending')
                ->andWhere('f.sourceKind = :kind')
                ->setParameter('activity', $activity)
                ->setParameter('pending', AccountingAcquisitionFollowUp::STATUS_PENDING)
                ->setParameter('kind', AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_WORKSHOP);

            if ($validTicketIds !== []) {
                $qb->andWhere('f.sourceRefId NOT IN (:ids)')
                    ->setParameter('ids', $validTicketIds);
            }

            foreach ($qb->getQuery()->getResult() as $followUp) {
                if ($followUp instanceof AccountingAcquisitionFollowUp) {
                    $this->entityManager->remove($followUp);
                    $this->inboxMessages->removeAccountingFollowUpInbox($followUp->getId());
                }
            }
            $this->entityManager->flush();
        } catch (\Throwable) {
        }
    }

    private function removePendingFinalBillingForActivity(Activity $activity): void
    {
        try {
            $pending = $this->entityManager->createQueryBuilder()
                ->select('f')
                ->from(AccountingAcquisitionFollowUp::class, 'f')
                ->where('f.activity = :activity')
                ->andWhere('f.status = :st')
                ->andWhere('f.sourceKind = :kind')
                ->setParameter('activity', $activity)
                ->setParameter('st', AccountingAcquisitionFollowUp::STATUS_PENDING)
                ->setParameter('kind', AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_FINAL)
                ->getQuery()
                ->getResult();

            foreach ($pending as $followUp) {
                if ($followUp instanceof AccountingAcquisitionFollowUp) {
                    $this->entityManager->remove($followUp);
                    $this->inboxMessages->removeAccountingFollowUpInbox($followUp->getId());
                }
            }
            if ($pending !== []) {
                $this->entityManager->flush();
            }
        } catch (\Throwable) {
        }
    }

    private function removePendingFollowUp(Activity $activity, string $sourceKind, string $sourceRefId): void
    {
        try {
            $existing = $this->entityManager->createQueryBuilder()
                ->select('f')
                ->from(AccountingAcquisitionFollowUp::class, 'f')
                ->where('f.sourceKind = :sk')
                ->andWhere('f.sourceRefId = :ref')
                ->andWhere('f.activity = :activity')
                ->andWhere('f.status = :st')
                ->setParameter('sk', $sourceKind)
                ->setParameter('ref', $sourceRefId)
                ->setParameter('activity', $activity)
                ->setParameter('st', AccountingAcquisitionFollowUp::STATUS_PENDING)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existing instanceof AccountingAcquisitionFollowUp) {
                $this->entityManager->remove($existing);
                $this->entityManager->flush();
                $this->inboxMessages->removeAccountingFollowUpInbox($existing->getId());
            }
        } catch (\Throwable) {
        }
    }

    private function computeExternalRentalTotal(string $activityId): float
    {
        $items = $this->entityManager->getRepository(ActivityItem::class)->findBy([
            'activityId' => $activityId,
        ]);
        $total = 0.0;
        foreach ($items as $item) {
            if ($item->getIsConsumable()) {
                continue;
            }
            $lineTotal = $this->parseMoney($item->getLineTotal());
            if ($lineTotal !== null && $lineTotal > 0) {
                $total += $lineTotal;
            }
        }

        return $total;
    }

    private function computeReplenishmentPurchaseTotal(string $activityId): float
    {
        $items = $this->entityManager->getRepository(ActivityItem::class)->findBy([
            'activityId' => $activityId,
        ]);
        $total = 0.0;
        foreach ($items as $item) {
            if (!$item->getIsConsumable() || !$item->getIsReplenishment()) {
                continue;
            }
            $lineTotal = $this->parseMoney($item->getLineTotal());
            if ($lineTotal !== null && $lineTotal > 0) {
                $total += $lineTotal;
                continue;
            }
            $unit = $this->parseMoney($item->getUnitPrice());
            if ($unit !== null && $item->getQuantity() > 0) {
                $total += $unit * $item->getQuantity();
            }
        }

        return $total;
    }

    /** @return list<string> */
    private function consumableMaterialIdsForActivity(string $activityId): array
    {
        $items = $this->entityManager->getRepository(ActivityItem::class)->findBy([
            'activityId' => $activityId,
        ]);
        $ids = [];
        foreach ($items as $item) {
            if ($item->getIsConsumable()) {
                $ids[$item->getMaterialItemId()] = true;
            }
        }

        return array_keys($ids);
    }

    private function computeConsumableMaterialTotalCost(string $activityId, string $materialItemId): float
    {
        $issues = $this->entityManager->getRepository(ActivityIssueReport::class)->findBy([
            'activityId' => $activityId,
            'type' => ActivityIssueReport::TYPE_CONSUMPTION,
            'materialItemId' => $materialItemId,
        ]);
        $totalUsed = 0;
        foreach ($issues as $iss) {
            $totalUsed += $iss->getQuantity();
        }
        if ($totalUsed <= 0) {
            return 0.0;
        }

        return $this->consumptionIssueCharge($activityId, $materialItemId, $totalUsed) ?? 0.0;
    }

    /**
     * Normaler Stückpreis; bei externen Aktivitäten optional + Zusatz (external_sale_price_chf).
     */
    private function effectiveConsumableUnitSalePrice(MaterialItem $mi, bool $preferExternal = false): ?float
    {
        $base = $this->baseConsumableUnitSalePrice($mi);
        if (!$preferExternal) {
            return $base;
        }

        $extra = $this->parseMoney($mi->getExternalSalePriceChf());
        if ($extra === null || $extra <= 0) {
            return $base;
        }
        if ($base === null) {
            return $extra;
        }

        return round($base + $extra, 2);
    }

    private function baseConsumableUnitSalePrice(MaterialItem $mi): ?float
    {
        $direct = $this->parseMoney($mi->getSalePrice());
        if ($direct !== null) {
            return $direct;
        }

        $packPrice = $this->parseMoney($mi->getPackSalePriceChf());
        $packSize = $mi->getPackSize();
        if ($packPrice === null || $packSize === null || $packSize < 1 || $packPrice <= 0) {
            return null;
        }
        if ($packSize === 1) {
            return $packPrice;
        }

        return round($packPrice / $packSize, 2);
    }

    private function upsertPending(
        Activity $activity,
        string $sourceKind,
        string $sourceRefId,
        float $amount,
        string $receiptLabel,
        ?MaterialItem $materialItem,
        bool $onlyIfPending = false,
        ?Department $billingDepartment = null,
    ): void {
        try {
            $department = $billingDepartment ?? $activity->getDepartment();
            if (!$department instanceof Department) {
                return;
            }

            $amountStr = number_format($amount, 2, '.', '');

            $existing = $this->entityManager->createQueryBuilder()
                ->select('f')
                ->from(AccountingAcquisitionFollowUp::class, 'f')
                ->where('f.sourceKind = :sk')
                ->andWhere('f.sourceRefId = :ref')
                ->setParameter('sk', $sourceKind)
                ->setParameter('ref', $sourceRefId)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existing instanceof AccountingAcquisitionFollowUp) {
                if ($onlyIfPending && $existing->getStatus() !== AccountingAcquisitionFollowUp::STATUS_PENDING) {
                    return;
                }
                $existing->setAmount($amountStr);
                $existing->setReceiptLabel(mb_substr($receiptLabel, 0, 255));
                $existing->setSuggestedDate(new \DateTimeImmutable('today'));
                if ($materialItem !== null) {
                    $existing->setMaterialItem($materialItem);
                }
                if ($existing->getActivity() === null) {
                    $existing->setActivity($activity);
                }
                if (
                    $existing->getStatus() === AccountingAcquisitionFollowUp::STATUS_PENDING
                    && $existing->getDepartment()->getId() !== $department->getId()
                ) {
                    $existing->setDepartment($department);
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
        }
    }

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

        $activity = $this->entityManager->find(Activity::class, $activityId);
        $preferExternal = $activity instanceof Activity && $activity->getType() === 'external';

        foreach ($consumableLines as $line) {
            $mi = $line->getMaterialItem();
            if ($salePrice === null && $mi instanceof MaterialItem) {
                $salePrice = $this->effectiveConsumableUnitSalePrice($mi, $preferExternal);
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

        $fromWarehouse = min($issueQty, $warehouseQty);
        $fromReplen = $issueQty - $fromWarehouse;
        $cost = $fromWarehouse * ($salePrice ?? 0.0);

        foreach ($replenLines as $line) {
            if ($fromReplen <= 0) {
                break;
            }
            $take = min($fromReplen, $line['qty']);
            $fromReplen -= $take;
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
