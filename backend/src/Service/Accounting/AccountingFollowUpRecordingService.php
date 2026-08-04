<?php

namespace App\Service\Accounting;

use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\AccountingBooking;
use App\Entity\AccountingCostCenter;
use App\Entity\AccountingCostCenterRule;
use App\Entity\Department;
use App\Entity\Group;
use App\Service\InboxMessageService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Erfasst Buchungen aus Follow-ups (einzeln oder Batch).
 */
final class AccountingFollowUpRecordingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InboxMessageService $inboxMessages,
        private AccountingBookingSourceService $bookingSource,
        private AccountingAcquisitionFollowUpReceiptService $followUpReceiptService,
    ) {
    }

    /**
     * @param array<string, mixed> $options cost_center_id, entry_type, payment_method?, payment_status?, group_id?
     *
     * @return array{booking: AccountingBooking, follow_up: AccountingAcquisitionFollowUp}|null error via exception
     */
    public function recordFollowUp(
        AccountingAcquisitionFollowUp $followUp,
        array $options,
    ): array {
        if ($followUp->getStatus() !== AccountingAcquisitionFollowUp::STATUS_PENDING) {
            throw new \InvalidArgumentException('Anschaffungs-Auftrag ist bereits erfasst');
        }

        $departmentId = $followUp->getDepartment()->getId() ?? '';
        $ccId = trim((string) ($options['cost_center_id'] ?? ''));
        $cc = $this->entityManager->find(AccountingCostCenter::class, $ccId);
        if (!$cc || $cc->getDepartment()->getId() !== $departmentId) {
            throw new \InvalidArgumentException('Kostenstelle nicht gefunden');
        }

        $entryType = (string) ($options['entry_type'] ?? '');
        if (!in_array($entryType, AccountingBooking::ENTRY_TYPES, true)) {
            throw new \InvalidArgumentException('Ungültiger Buchungstyp');
        }

        $paymentMethod = $this->optionalPaymentMethod($options['payment_method'] ?? null);
        if ($paymentMethod === null) {
            $fromNote = ActivityCollectionNotePaymentSuggest::fromActivity($followUp->getActivity());
            $paymentMethod = $this->optionalPaymentMethod($fromNote['payment_method']);
        }
        $paymentStatus = $this->resolvePaymentStatus($followUp, $options['payment_status'] ?? null);

        $group = $this->resolveGroup($options['group_id'] ?? null, $departmentId, $followUp);

        $bookedAt = $followUp->getSuggestedDate();
        $booking = new AccountingBooking();
        $booking->setId(IdGenerator::generate13UniqueForYear(
            $this->entityManager,
            AccountingBooking::class,
            'kb',
            $bookedAt->format('Y')
        ));
        $booking->setDepartment($followUp->getDepartment());
        $booking->setCostCenter($cc);
        $booking->setAmount($followUp->getAmount());
        $booking->setBookedAt($bookedAt);
        $booking->setEntryType($entryType);
        $booking->setPaymentMethod($paymentMethod);
        $booking->setPaymentStatus($paymentStatus);
        $booking->setGroup($group);
        $booking->setReceiptLabel($followUp->getReceiptLabel());

        $notes = trim((string) ($options['notes'] ?? ''));
        if ($notes !== '') {
            $booking->setNotes($notes);
        }

        if ($followUp->getMaterialItem() !== null) {
            $booking->setMaterialItem($followUp->getMaterialItem());
        } elseif ($followUp->getMaterialBatch() !== null) {
            $booking->setMaterialItem($followUp->getMaterialBatch()->getMaterialItem());
        }

        $this->entityManager->persist($booking);
        $this->followUpReceiptService->transferReceiptsToBooking($followUp, $booking);
        $followUp->setAccountingBooking($booking);
        $followUp->setStatus(AccountingAcquisitionFollowUp::STATUS_RECORDED);
        $followUp->touchUpdatedAt();
        $this->entityManager->flush();
        $this->inboxMessages->removeAccountingFollowUpInbox($followUp->getId());

        return ['booking' => $booking, 'follow_up' => $followUp];
    }

    /**
     * @param list<string> $followUpIds
     * @param array<string, mixed> $options
     *
     * @return list<array{booking_id: string, follow_up_id: string}>
     */
    public function recordBatch(array $followUpIds, string $departmentId, array $options): array
    {
        $recorded = [];
        foreach ($followUpIds as $fid) {
            $followUp = $this->entityManager->find(AccountingAcquisitionFollowUp::class, $fid);
            if (!$followUp instanceof AccountingAcquisitionFollowUp) {
                continue;
            }
            if ($followUp->getDepartment()->getId() !== $departmentId) {
                continue;
            }
            if ($followUp->getStatus() !== AccountingAcquisitionFollowUp::STATUS_PENDING) {
                continue;
            }

            $opts = $options;
            if (!isset($opts['group_id']) || $opts['group_id'] === '') {
                $activity = $followUp->getActivity();
                if ($activity !== null && $activity->getGroupId() !== null) {
                    $opts['group_id'] = $activity->getGroupId();
                }
            }

            $rules = $this->bookingSource->rulesBySourceKind($departmentId);
            $sk = $followUp->getSourceKind() ?? '';
            if (isset($rules[$sk])) {
                $rule = $rules[$sk];
                if (!isset($opts['cost_center_id']) || $opts['cost_center_id'] === '') {
                    $opts['cost_center_id'] = $rule->getCostCenter()->getId();
                }
                if (!isset($opts['entry_type']) || $opts['entry_type'] === '') {
                    $opts['entry_type'] = $rule->getDefaultEntryType() ?? $opts['entry_type'] ?? 'other';
                }
                if (!isset($opts['payment_method']) || $opts['payment_method'] === '') {
                    $opts['payment_method'] = $rule->getDefaultPaymentMethod();
                }
            }
            if (!isset($opts['payment_method']) || $opts['payment_method'] === '') {
                $fromNote = ActivityCollectionNotePaymentSuggest::fromActivity($followUp->getActivity());
                if ($fromNote['payment_method'] !== null) {
                    $opts['payment_method'] = $fromNote['payment_method'];
                }
            }
            if (!isset($opts['payment_status']) || $opts['payment_status'] === '') {
                $fromNote = ActivityCollectionNotePaymentSuggest::fromActivity($followUp->getActivity());
                if ($fromNote['payment_status'] !== null) {
                    $opts['payment_status'] = $fromNote['payment_status'];
                }
            }

            try {
                $result = $this->recordFollowUp($followUp, $opts);
                $recorded[] = [
                    'booking_id' => $result['booking']->getId() ?? '',
                    'follow_up_id' => $result['follow_up']->getId() ?? '',
                ];
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return $recorded;
    }

    private function resolvePaymentStatus(AccountingAcquisitionFollowUp $followUp, mixed $raw): string
    {
        $ps = $this->optionalPaymentStatus($raw);
        if ($ps !== null) {
            return $ps;
        }
        $fromNote = ActivityCollectionNotePaymentSuggest::fromActivity($followUp->getActivity());
        if ($fromNote['payment_status'] !== null) {
            return $fromNote['payment_status'];
        }
        $activity = $followUp->getActivity();
        if ($activity !== null && $activity->getType() === 'external') {
            return AccountingBooking::PAYMENT_STATUS_OPEN;
        }
        if ($followUp->getSourceKind() === AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_RENTAL) {
            return AccountingBooking::PAYMENT_STATUS_OPEN;
        }

        return AccountingBooking::PAYMENT_STATUS_PAID;
    }

    private function optionalPaymentMethod(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = (string) $value;

        return in_array($v, AccountingBooking::PAYMENT_METHODS, true) ? $v : null;
    }

    private function optionalPaymentStatus(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = (string) $value;

        return in_array($v, AccountingBooking::PAYMENT_STATUSES, true) ? $v : null;
    }

    private function resolveGroup(mixed $groupId, string $departmentId, AccountingAcquisitionFollowUp $followUp): ?Group
    {
        if ($groupId !== null && $groupId !== '') {
            $g = $this->entityManager->find(Group::class, (string) $groupId);
            if ($g && $g->getDepartmentId() === $departmentId) {
                return $g;
            }
        }
        $activity = $followUp->getActivity();
        if ($activity !== null && $activity->getGroup() !== null) {
            return $activity->getGroup();
        }

        return null;
    }
}
