<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Wie eine UserSession entstanden ist.
 *
 * LEGACY: per Refresh-Token übernommene Sitzung aus der Zeit vor UserSession.
 */
enum AuthMethod: string
{
    case PASSWORD = 'password';
    case GOOGLE = 'google';
    case MIDATA = 'midata';
    case LEGACY = 'legacy';
}
