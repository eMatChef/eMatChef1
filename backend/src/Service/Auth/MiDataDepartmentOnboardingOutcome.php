<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Department;

final readonly class MiDataDepartmentOnboardingOutcome
{
    public function __construct(
        public MiDataDepartmentOnboardingOutcomeStatus $status,
        public ?Department $department = null,
        public ?string $reason = null,
    ) {}
}
