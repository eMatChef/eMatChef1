<?php

namespace App\Service;

use App\Entity\Organisation;

/**
 * J&S- und globale System-Organisationen nie in Selbstregistrierung / User-Dropdowns.
 */
final class OrganisationUserPickerFilter
{
    private const HIDDEN_IDS = ['org_js000000'];

    public static function isVisibleForUserPickers(Organisation $org): bool
    {
        if (in_array($org->getId(), self::HIDDEN_IDS, true)) {
            return false;
        }

        $n = mb_strtolower($org->getName());
        if (str_contains($n, 'j&s') || str_contains($n, 'j+s')) {
            return false;
        }
        if (str_contains($n, 'global system')) {
            return false;
        }

        return true;
    }
}
