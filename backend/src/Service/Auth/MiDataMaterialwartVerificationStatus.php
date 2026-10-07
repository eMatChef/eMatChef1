<?php

declare(strict_types=1);

namespace App\Service\Auth;

enum MiDataMaterialwartVerificationStatus: string
{
    case CONFIRMED = 'confirmed';
    case NOT_CONFIRMED = 'not_confirmed';
    case UNSUPPORTED_STRUCTURE = 'unsupported_structure';
    case UNAVAILABLE = 'unavailable';
}
