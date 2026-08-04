<?php

namespace App\Service\Accounting;

use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\Activity;
use App\Service\AccountingAcquisitionFollowUpSerializer;
use App\Service\ActivityAccountingCostService;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Aktivitäts-Rechnung v1: berechnetes Aggregat aus Follow-ups + offene Werkstatt.
 * Keine persistierte Rechnung — ersetzt keine AccountingBooking.
 */
final class AccountingActivityInvoiceService
{
    public const STATUS_EMPTY = 'empty';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_PAID = 'paid';
    public const STATUS_BLOCKED = 'blocked';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountingAcquisitionFollowUpSerializer $followUpSerializer,
        private AccountingExpectedCostsService $expectedCosts,
        private ActivityAccountingCostService $activityAccountingCost,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildForActivity(Activity $activity): array
    {
        $activityId = $activity->getId() ?? '';
        $followUps = $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(AccountingAcquisitionFollowUp::class, 'f')
            ->where('f.activity = :activity')
            ->andWhere('f.status IN (:statuses)')
            ->setParameter('activity', $activity)
            ->setParameter('statuses', [
                AccountingAcquisitionFollowUp::STATUS_PENDING,
                AccountingAcquisitionFollowUp::STATUS_RECORDED,
            ])
            ->orderBy('f.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $lines = [];
        $confirmed = '0.00';
        $pendingCount = 0;
        $recordedCount = 0;
        $consumptionExpanded = false;

        foreach ($followUps as $fu) {
            if (!$fu instanceof AccountingAcquisitionFollowUp) {
                continue;
            }
            $serialized = $this->followUpSerializer->serialize($fu);
            $amount = (string) ($serialized['amount'] ?? '0');
            $confirmed = bcadd($confirmed, $amount, 2);
            if ($fu->getStatus() === AccountingAcquisitionFollowUp::STATUS_PENDING) {
                ++$pendingCount;
            } else {
                ++$recordedCount;
            }

            $sourceKind = $serialized['source_kind'] ?? null;
            if (
                $sourceKind === AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_CONSUMPTION
                && !$consumptionExpanded
            ) {
                $usageLines = $this->activityAccountingCost->listConsumableUsageLinesForInvoice($activity);
                if ($usageLines !== []) {
                    $consumptionExpanded = true;
                    foreach ($usageLines as $usage) {
                        $lines[] = [
                            'kind' => 'consumption_item',
                            'expected' => false,
                            'follow_up_id' => $serialized['id'] ?? null,
                            'booking_id' => $serialized['accounting_booking_id'] ?? null,
                            'source_kind' => AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_CONSUMPTION,
                            'status' => $serialized['status'] ?? null,
                            'label' => sprintf(
                                '%s · %s Stk.',
                                $usage['material_name'],
                                $usage['quantity'],
                            ),
                            'material_item_id' => $usage['material_item_id'],
                            'material_name' => $usage['material_name'],
                            'quantity' => $usage['quantity'],
                            'amount_chf' => $usage['amount_chf'],
                            'estimated' => false,
                        ];
                    }
                    continue;
                }
            }

            $lines[] = [
                'kind' => 'follow_up',
                'expected' => false,
                'follow_up_id' => $serialized['id'] ?? null,
                'booking_id' => $serialized['accounting_booking_id'] ?? null,
                'source_kind' => $sourceKind,
                'status' => $serialized['status'] ?? null,
                'label' => $serialized['receipt_label'] ?? ($serialized['material_name'] ?? null),
                'amount_chf' => $amount,
                'estimated' => false,
            ];
        }

        // Verbrauch schon vor Follow-up sichtbar (z. B. noch nicht completed)?
        if (!$consumptionExpanded) {
            foreach ($this->activityAccountingCost->listConsumableUsageLinesForInvoice($activity) as $usage) {
                $lines[] = [
                    'kind' => 'consumption_item',
                    'expected' => false,
                    'source_kind' => AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_CONSUMPTION,
                    'status' => 'preview',
                    'label' => sprintf(
                        '%s · %s Stk.',
                        $usage['material_name'],
                        $usage['quantity'],
                    ),
                    'material_item_id' => $usage['material_item_id'],
                    'material_name' => $usage['material_name'],
                    'quantity' => $usage['quantity'],
                    'amount_chf' => $usage['amount_chf'],
                    'estimated' => false,
                ];
                $confirmed = bcadd($confirmed, $usage['amount_chf'], 2);
            }
        }

        $expectedItems = $this->expectedCosts->listForActivity($activityId);
        $estimated = '0.00';
        foreach ($expectedItems as $item) {
            $est = $item['estimated_cost_chf'] ?? null;
            if (is_string($est) && $est !== '') {
                $estimated = bcadd($estimated, $est, 2);
            }
            $labelParts = array_filter([
                $item['ticket_title'] ?? null,
                $item['material_name'] ?? null,
            ]);
            $lines[] = [
                'kind' => 'workshop_open',
                'expected' => true,
                'ticket_id' => $item['ticket_id'] ?? null,
                'ticket_status' => $item['ticket_status'] ?? null,
                'source_kind' => AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_WORKSHOP,
                'status' => 'expected',
                'label' => $labelParts !== [] ? implode(' · ', $labelParts) : 'Werkstatt offen',
                'amount_chf' => is_string($est) && $est !== '' ? $est : null,
                'estimated' => true,
            ];
        }

        $hasExpected = $expectedItems !== [];
        $hasLines = $lines !== [];
        $note = $activity->getCollectionNote();

        $status = self::STATUS_EMPTY;
        if ($hasExpected) {
            $status = self::STATUS_BLOCKED;
        } elseif ($note === 'cash' && $hasLines && $pendingCount === 0) {
            $status = self::STATUS_PAID;
        } elseif ($note === 'invoice' && $hasLines) {
            $status = self::STATUS_OPEN;
        } elseif ($hasLines) {
            $status = self::STATUS_DRAFT;
        }

        $customerLabel = $activity->isExternal() ? $activity->getName() : null;

        return [
            'activity_id' => $activityId,
            'activity_name' => $activity->getName(),
            'activity_type' => $activity->getType(),
            'activity_status' => $activity->getStatus(),
            'is_external' => $activity->isExternal(),
            'customer_label' => $customerLabel,
            'collection_note' => $note,
            'collection_note_amount' => $activity->getCollectionNoteAmount() !== null
                ? (float) $activity->getCollectionNoteAmount()
                : null,
            'status' => $status,
            'total_chf' => $confirmed,
            'estimated_open_chf' => $hasExpected ? $estimated : '0.00',
            'pending_followup_count' => $pendingCount,
            'recorded_followup_count' => $recordedCount,
            'expected_workshop_count' => count($expectedItems),
            'lines' => $lines,
        ];
    }

    /**
     * Zusammenfassung für Buchhaltung → Erfassen → Rechnungen.
     *
     * @return list<array<string, mixed>>
     */
    public function listSummariesForDepartment(string $departmentId): array
    {
        $conn = $this->entityManager->getConnection();

        // Aktivitäten mit Follow-up in diesem Department ODER offener Werkstatt (Billing-Dept).
        $sql = <<<'SQL'
            SELECT DISTINCT a.id AS activity_id
            FROM activity a
            WHERE a.deleted_at IS NULL
              AND (
                EXISTS (
                  SELECT 1 FROM accounting_acquisition_follow_up f
                  WHERE f.activity_id = a.id AND f.department_id = :d
                )
                OR EXISTS (
                  SELECT 1
                  FROM workshop_ticket wt
                  INNER JOIN material_item mi ON mi.id = wt.material_item_id
                  WHERE wt.activity_id = a.id
                    AND wt.status IN ('open', 'in_progress', 'waiting_parts')
                    AND COALESCE(mi.department_id, a.department_id) = :d
                )
              )
            ORDER BY a.id
            SQL;

        $ids = $conn->executeQuery(
            $sql,
            ['d' => $departmentId],
            ['d' => ParameterType::STRING],
        )->fetchFirstColumn();

        $out = [];
        foreach ($ids as $id) {
            if (!is_string($id) || $id === '') {
                continue;
            }
            $activity = $this->entityManager->find(Activity::class, $id);
            if (!$activity instanceof Activity || $activity->isDeleted()) {
                continue;
            }
            $invoice = $this->buildForActivity($activity);
            if ($invoice['status'] === self::STATUS_EMPTY) {
                continue;
            }
            $out[] = [
                'activity_id' => $invoice['activity_id'],
                'activity_name' => $invoice['activity_name'],
                'activity_type' => $invoice['activity_type'],
                'is_external' => $invoice['is_external'],
                'customer_label' => $invoice['customer_label'],
                'status' => $invoice['status'],
                'total_chf' => $invoice['total_chf'],
                'estimated_open_chf' => $invoice['estimated_open_chf'],
                'line_count' => count($invoice['lines']),
                'pending_followup_count' => $invoice['pending_followup_count'],
                'expected_workshop_count' => $invoice['expected_workshop_count'],
                'collection_note' => $invoice['collection_note'],
            ];
        }

        usort($out, static function (array $a, array $b): int {
            return strcmp((string) $a['activity_name'], (string) $b['activity_name']);
        });

        return $out;
    }
}
