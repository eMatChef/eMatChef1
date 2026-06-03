<?php

namespace App\Service\Accounting;

use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\AccountingBooking;
use App\Entity\AccountingCostCenterRule;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Reichert Buchungen mit Quell-Links (Follow-up → Aktivität, Batch, Werkstatt) an.
 */
final class AccountingBookingSourceService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<AccountingBooking> $bookings
     *
     * @return array<string, array<string, mixed>>
     */
    public function sourceMapForBookings(array $bookings): array
    {
        $ids = [];
        foreach ($bookings as $b) {
            $id = $b->getId();
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('f', 'b')
            ->from(AccountingAcquisitionFollowUp::class, 'f')
            ->innerJoin('f.accountingBooking', 'b')
            ->where('b.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rows as $row) {
            if (!$row instanceof AccountingAcquisitionFollowUp) {
                continue;
            }
            $booking = $row->getAccountingBooking();
            if ($booking === null || $booking->getId() === null) {
                continue;
            }
            $out[$booking->getId()] = $this->serializeSource($row);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeSource(AccountingAcquisitionFollowUp $f): array
    {
        $activity = $f->getActivity();
        $batch = $f->getMaterialBatch();
        $sourceKind = $f->getSourceKind() ?? '';

        return [
            'follow_up_id' => $f->getId(),
            'source_kind' => $sourceKind,
            'activity_id' => $activity?->getId(),
            'activity_name' => $activity?->getName(),
            'material_batch_id' => $batch?->getId(),
            'workshop_ticket_id' => $sourceKind === AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_WORKSHOP
                ? $f->getSourceRefId()
                : null,
        ];
    }

    /**
     * @return array<string, AccountingCostCenterRule> keyed by source_kind
     */
    public function rulesBySourceKind(string $departmentId): array
    {
        $deptRef = $this->entityManager->getReference(\App\Entity\Department::class, $departmentId);
        $rules = $this->entityManager->getRepository(AccountingCostCenterRule::class)
            ->createQueryBuilder('r')
            ->innerJoin('r.costCenter', 'cc')
            ->where('r.department = :d')
            ->setParameter('d', $deptRef)
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rules as $rule) {
            if ($rule instanceof AccountingCostCenterRule) {
                $out[$rule->getSourceKind()] = $rule;
            }
        }

        return $out;
    }
}
