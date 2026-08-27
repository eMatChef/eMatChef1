<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassRound;
use App\Entity\ActivityGrossanlassWishLine;

/**
 * Welche Sammler-Aktion für welchen Formularzweck erlaubt ist.
 */
final class GrossanlassCollectorDecision
{
    public static function canDiscard(string $formPurpose, string $status): bool
    {
        return self::isPending($status)
            && in_array($formPurpose, [
                ActivityGrossanlassRound::PURPOSE_COMPANY_TIP,
                ActivityGrossanlassRound::PURPOSE_FREE,
            ], true);
    }

    public static function canToInquiry(string $formPurpose, string $status): bool
    {
        return self::isPending($status)
            && in_array($formPurpose, [
                ActivityGrossanlassRound::PURPOSE_COMPANY_TIP,
                ActivityGrossanlassRound::PURPOSE_FREE,
            ], true);
    }

    public static function canToMaterial(string $formPurpose, string $status): bool
    {
        return self::isPending($status)
            && $formPurpose === ActivityGrossanlassRound::PURPOSE_FREE;
    }

    private static function isPending(string $status): bool
    {
        return $status === ActivityGrossanlassWishLine::STATUS_REQUESTED;
    }
}
