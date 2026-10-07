<?php

declare(strict_types=1);

namespace App\Service\Auth;

enum DepartmentJoinOutcomeStatus: string
{
    case MEMBERSHIP_CONFIRMED = 'membership_confirmed';
    case ALREADY_MEMBER = 'already_member';
    case MANUAL_REQUEST_REQUIRED = 'manual_request_required';
    case REQUEST_CREATED = 'request_created';
    case REQUEST_ALREADY_PENDING = 'request_already_pending';
    case VERIFICATION_UNAVAILABLE = 'verification_unavailable';
    case DEPARTMENT_NOT_FOUND = 'department_not_found';
}
