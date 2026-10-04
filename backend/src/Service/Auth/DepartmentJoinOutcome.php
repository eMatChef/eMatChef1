<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Department;
use App\Entity\JoinRequest;

final readonly class DepartmentJoinOutcome
{
    public function __construct(
        public DepartmentJoinOutcomeStatus $status,
        public ?Department $department = null,
        public ?JoinRequest $joinRequest = null,
    ) {}
}
