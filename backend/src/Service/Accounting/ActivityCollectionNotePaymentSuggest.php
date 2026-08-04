<?php

namespace App\Service\Accounting;

use App\Entity\AccountingBooking;
use App\Entity\Activity;

/**
 * #7 Phase 4: Einnahme-Vermerk (cash/invoice) → Vorbelegung Zahlungsmethode/Status.
 */
final class ActivityCollectionNotePaymentSuggest
{
    /**
     * @return array{payment_method: ?string, payment_status: ?string}
     */
    public static function fromNote(?string $note): array
    {
        $n = $note !== null ? strtolower(trim($note)) : '';

        return match ($n) {
            'cash' => [
                'payment_method' => AccountingBooking::PAYMENT_CASH_GROUP,
                'payment_status' => AccountingBooking::PAYMENT_STATUS_PAID,
            ],
            'invoice' => [
                'payment_method' => AccountingBooking::PAYMENT_SUPPLIER,
                'payment_status' => AccountingBooking::PAYMENT_STATUS_OPEN,
            ],
            default => [
                'payment_method' => null,
                'payment_status' => null,
            ],
        };
    }

    /**
     * @return array{payment_method: ?string, payment_status: ?string}
     */
    public static function fromActivity(?Activity $activity): array
    {
        return self::fromNote($activity?->getCollectionNote());
    }
}
