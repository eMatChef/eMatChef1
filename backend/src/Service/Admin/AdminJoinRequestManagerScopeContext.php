<?php

declare(strict_types=1);

namespace App\Service\Admin;

final readonly class AdminJoinRequestManagerScopeContext
{
    /**
     * @param list<string> $departmentIds the managed department and its sub-departments within the organisation
     */
    public function __construct(
        public string $departmentId,
        public string $organisationId,
        public string $role,
        public array $departmentIds,
    ) {}

    public function containsDepartment(string $departmentId): bool
    {
        return in_array($departmentId, $this->departmentIds, true);
    }
}
