<?php

declare(strict_types=1);

namespace App\Service\Auth;

final readonly class MiDataGroupMembershipSyncResult
{
    /**
     * @param list<string> $created
     * @param list<string> $existing
     * @param list<string> $unmapped
     * @param list<string> $conflicts
     * @param list<string> $errors
     */
    public function __construct(
        public array $created = [],
        public array $existing = [],
        public array $unmapped = [],
        public array $conflicts = [],
        public array $errors = [],
    ) {}
}
