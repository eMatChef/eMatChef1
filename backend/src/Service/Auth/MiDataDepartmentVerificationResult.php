<?php

declare(strict_types=1);

namespace App\Service\Auth;

final readonly class MiDataDepartmentVerificationResult
{
    public function __construct(
        public MiDataDepartmentVerificationStatus $status,
        public ?HitobitoRole $role = null,
    ) {}
}
