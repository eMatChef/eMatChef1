<?php

declare(strict_types=1);

namespace App\Service\Auth;

final readonly class MiDataDepartmentVerificationResult
{
    /**
     * @param list<HitobitoRole> $verifiedRoles
     */
    public function __construct(
        public MiDataDepartmentVerificationStatus $status,
        public ?HitobitoRole $role = null,
        public array $verifiedRoles = [],
        public ?string $externalDepartmentGroupId = null,
        public ?string $externalPersonId = null,
    ) {}
}
