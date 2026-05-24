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
 * Buchhaltung für Aktivitätskosten: eine Endabrechnung pro abgeschlossener Aktivität.
 */
class ActivityAccountingCostService
{
  /** @var list<string> */
  private const LINE_SOURCE_KINDS = [
    AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_CONSUMPTION,
    AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_REPLENISHMENT,
    AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_LOSS,
    AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_WORKSHOP,
  ];

  public function __construct(
    private EntityManagerInterface $entityManager,
    private InboxMessageService $inboxMessages,
  ) {
  }

  /** @deprecated Einzelpositionen — Endabrechnung erfolgt beim Abschluss. */
  public function enqueueFromConsumption(Activity $activity, ActivityIssueReport $report): void
  {
  }

  /**
   * Beim Einlagern keine Einzel-Buchhaltungs-Aufträge mehr anlegen.
   */
  public function enqueueAccountingForMaterialOnStore(Activity $activity, string $materialItemId): void
  {
  }

  /** @deprecated Einzelpositionen — Endabrechnung erfolgt beim Abschluss. */
  public function finalizeConsumptionAccountingForActivity(Activity $activity): void
  {
    $this->enqueueFinalActivityBilling($activity);
  }

  /**
   * Beim Abschluss: alte Einzel-Aufträge entfernen, einen Endabrechnungs-Auftrag anlegen.
   */
  public function enqueueFinalActivityBilling(Activity $activity): void
  {
    $this->removePendingLineFollowUpsForActivity($activity);
    $this->upsertFinalActivityBilling($activity);
  }

  /**
   * Nach Werkstatt-Abschluss o. ä.: Endabrechnung aktualisieren (solange noch pending).
   */
  public function refreshFinalActivityBilling(Activity $activity): void
  {
    if ($activity->getStatus() !== Activity::STATUS_COMPLETED) {
      return;
    }
    $this->upsertFinalActivityBilling($activity);
  }

  /** @deprecated Einzelpositionen — Endabrechnung wird aktualisiert. */
  public function enqueueFromWorkshopTicket(WorkshopTicket $ticket): void
  {
    $activity = $ticket->getActivity();
    if ($activity === null) {
      return;
    }
    $this->refreshFinalActivityBilling($activity);
  }

  public function computeActivityBillingTotal(Activity $activity): float
  {
    $activityId = $activity->getId();
    $total = 0.0;

    $materialIds = $this->consumableMaterialIdsForActivity($activityId);
    foreach ($materialIds as $materialItemId) {
      $total += $this->computeConsumableMaterialTotalCost($activityId, $materialItemId);
    }

    $workshopTickets = $this->entityManager->getRepository(WorkshopTicket::class)->findBy([
      'activityId' => $activityId,
      'status' => 'completed',
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
      $items = $this->entityManager->getRepository(ActivityItem::class)->findBy([
        'activityId' => $activityId,
      ]);
      foreach ($items as $item) {
        if ($item->getIsConsumable()) {
          continue;
        }
        $lineTotal = $this->parseMoney($item->getLineTotal());
        if ($lineTotal !== null && $lineTotal > 0) {
          $total += $lineTotal;
        }
      }
    }

    return round($total, 2);
  }

  private function upsertFinalActivityBilling(Activity $activity): void
  {
    $amount = $this->computeActivityBillingTotal($activity);
    if ($amount <= 0) {
      return;
    }

    $label = sprintf(
      'Aktivität %s · Endabrechnung',
      $activity->getName()
    );

    $this->upsertPending(
      $activity,
      AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_FINAL,
      $activity->getId(),
      $amount,
      $label,
      null,
      onlyIfPending: true,
    );
  }

  private function removePendingLineFollowUpsForActivity(Activity $activity): void
  {
    try {
      $pending = $this->entityManager->createQueryBuilder()
        ->select('f')
        ->from(AccountingAcquisitionFollowUp::class, 'f')
        ->where('f.activity = :activity')
        ->andWhere('f.status = :st')
        ->andWhere('f.sourceKind IN (:kinds)')
        ->setParameter('activity', $activity)
        ->setParameter('st', AccountingAcquisitionFollowUp::STATUS_PENDING)
        ->setParameter('kinds', self::LINE_SOURCE_KINDS)
        ->getQuery()
        ->getResult();

      foreach ($pending as $followUp) {
        if (!$followUp instanceof AccountingAcquisitionFollowUp) {
          continue;
        }
        $this->entityManager->remove($followUp);
      }
      if ($pending !== []) {
        $this->entityManager->flush();
      }
    } catch (\Throwable) {
      // Workflow darf nicht abbrechen
    }
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

  private function upsertPending(
    Activity $activity,
    string $sourceKind,
    string $sourceRefId,
    float $amount,
    string $receiptLabel,
    ?MaterialItem $materialItem,
    bool $onlyIfPending = false,
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
   * Kosten für Verbrauch einer Materialposition (FIFO Lager → Nachkauf).
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
