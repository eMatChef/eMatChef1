<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Department;

final readonly class MiDataDepartmentProvisioningResult
{
    /**
     * @param list<string> $createdExternalGroupIds external group IDs that received a new eMatChef object and mapping
     */
    public function __construct(
        public Department $department,
        public bool $departmentCreated,
        public array $createdExternalGroupIds,
        public bool $membershipCreated,
    ) {}
}
