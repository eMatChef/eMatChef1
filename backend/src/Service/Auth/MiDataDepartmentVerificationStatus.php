<?php

declare(strict_types=1);

namespace App\Service\Auth;

enum MiDataDepartmentVerificationStatus: string
{
    case CONFIRMED = 'confirmed';
    case NOT_CONFIRMED = 'not_confirmed';
    case UNAVAILABLE = 'unavailable';
    case NOT_APPLICABLE = 'not_applicable';
}
