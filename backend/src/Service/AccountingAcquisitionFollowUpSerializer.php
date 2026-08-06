<?php

namespace App\Service;

use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\Activity;
use App\Entity\Address;
use App\Entity\User;
use App\Entity\WorkshopTicket;
use App\Service\Accounting\ActivityCollectionNotePaymentSuggest;
use App\Service\Media\MediaPhotoNormalizer;
use Doctrine\ORM\EntityManagerInterface;

/**
 * API-Serialisierung inkl. Verrechnungsziel (Gruppe / Material-Dep. / externer Kunde).
 */
class AccountingAcquisitionFollowUpSerializer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MediaPhotoNormalizer $photoNormalizer,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(AccountingAcquisitionFollowUp $f): array
    {
        $batch = $f->getMaterialBatch();
        $booking = $f->getAccountingBooking();
        $activity = $f->getActivity();
        $material = $f->getMaterialItem();
        if ($material === null && $batch !== null) {
            $material = $batch->getMaterialItem();
        }
        $sourceKind = $f->getSourceKind() ?? '';

        $materialDeptId = $material?->getDepartmentId();
        $materialDeptName = $material?->getDepartment()?->getName();

        $chargeTarget = $this->resolveChargeTarget($activity, $sourceKind);
        $suggestedGroupId = $chargeTarget === 'group' ? $activity?->getGroupId() : null;

        $reportedByUserId = null;
        $reportedByDisplayName = null;
        $externalCustomerLabel = null;

        if ($activity !== null && $activity->getType() === 'external') {
            $externalCustomerLabel = $this->externalCustomerLabel($activity);
        }

        if ($sourceKind === AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_WORKSHOP) {
            $ticketId = $f->getSourceRefId();
            if ($ticketId !== null && $ticketId !== '') {
                $ticket = $this->entityManager->find(WorkshopTicket::class, $ticketId);
                if ($ticket instanceof WorkshopTicket) {
                    $issue = $ticket->getIssueReport();
                    if ($issue !== null) {
                        $reporter = $issue->getReportedByUser();
                        $reportedByUserId = $reporter?->getId();
                        $reportedByDisplayName = $this->userDisplayName($reporter);
                    }
                    $ticketMaterial = $ticket->getMaterialItem();
                    if ($ticketMaterial !== null) {
                        $materialDeptId = $ticketMaterial->getDepartmentId();
                        $materialDeptName = $ticketMaterial->getDepartment()->getName();
                    }
                }
            }
        }

        return [
            'id' => $f->getId(),
            'department_id' => $f->getDepartment()->getId(),
            'department_name' => $f->getDepartment()->getName(),
            'material_batch_id' => $batch?->getId(),
            'activity_id' => $activity?->getId(),
            'activity_name' => $activity?->getName(),
            'activity_group_id' => $activity?->getGroupId(),
            'activity_type' => $activity?->getType(),
            'source_kind' => $f->getSourceKind(),
            'source_ref_id' => $f->getSourceRefId(),
            'material_item_id' => $material?->getId(),
            'material_name' => $material?->getName(),
            'material_department_id' => $materialDeptId,
            'material_department_name' => $materialDeptName,
            'amount' => $f->getAmount(),
            'suggested_date' => $f->getSuggestedDate()->format('Y-m-d'),
            'receipt_label' => $f->getReceiptLabel(),
            'receipts' => $this->photoNormalizer->normalizeOutgoing($f->getReceipts()),
            'status' => $f->getStatus(),
            'accounting_booking_id' => $booking?->getId(),
            'created_at' => $f->getCreatedAt()->format('c'),
            'updated_at' => $f->getUpdatedAt()->format('c'),
            'charge_target' => $chargeTarget,
            'suggested_group_id' => $suggestedGroupId,
            'external_customer_label' => $externalCustomerLabel,
            'reported_by_user_id' => $reportedByUserId,
            'reported_by_display_name' => $reportedByDisplayName,
            'activity_collection_note' => $activity?->getCollectionNote(),
            'activity_collection_note_amount' => $activity?->getCollectionNoteAmount() !== null
                ? (float) $activity->getCollectionNoteAmount()
                : null,
            'suggested_payment_method' => ActivityCollectionNotePaymentSuggest::fromActivity($activity)['payment_method'],
            'suggested_payment_status' => ActivityCollectionNotePaymentSuggest::fromActivity($activity)['payment_status'],
        ];
    }

    private function resolveChargeTarget(?Activity $activity, string $sourceKind): string
    {
        if ($activity === null) {
            return 'department';
        }

        if ($activity->getType() === 'external') {
            if (in_array($sourceKind, [
                AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_RENTAL,
                AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_WORKSHOP,
                AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_CONSUMPTION,
            ], true)) {
                return 'external_customer';
            }
        }

        if ($sourceKind === AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_WORKSHOP) {
            return 'department';
        }

        if ($sourceKind === AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_REPLENISHMENT) {
            return 'department';
        }

        return 'group';
    }

    private function externalCustomerLabel(Activity $activity): ?string
    {
        $address = $activity->getAddress();
        if (!$address instanceof Address) {
            $name = trim($activity->getName());

            return $name !== '' ? $name : null;
        }

        $parts = array_filter([
            trim((string) ($address->getCompany() ?? '')),
            trim((string) ($address->getName() ?? '')),
        ]);

        if ($parts !== []) {
            return implode(' · ', $parts);
        }

        $city = trim((string) ($address->getCity() ?? ''));

        $fallback = trim($activity->getName());

        return $city !== '' ? $city : ($fallback !== '' ? $fallback : null);
    }

    private function userDisplayName(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }
        $profile = $user->getProfile();
        if ($profile === null) {
            return null;
        }
        $name = trim($profile->getFirstName() . ' ' . $profile->getLastName());
        if ($name !== '') {
            return $name;
        }
        $nick = trim((string) ($profile->getNickname() ?? ''));

        return $nick !== '' ? $nick : null;
    }
}
