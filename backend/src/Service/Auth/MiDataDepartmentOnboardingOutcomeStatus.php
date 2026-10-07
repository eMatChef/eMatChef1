<?php

declare(strict_types=1);

namespace App\Service\Auth;

enum MiDataDepartmentOnboardingOutcomeStatus: string
{
    /** Abteilung was newly created and the user became `mw`. */
    case CREATED = 'created';
    /** Abteilung already existed through its mapping and the user became `mw`. */
    case JOINED = 'joined';
    /** The user already had a membership; it was left unchanged. */
    case ALREADY_MEMBER = 'already_member';
    case DENIED = 'denied';
    case EXPIRED = 'expired';
    case CONFLICT = 'conflict';
    case UNSUPPORTED_STRUCTURE = 'unsupported_structure';
    case UNAVAILABLE = 'unavailable';
}
