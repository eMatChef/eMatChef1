<?php

declare(strict_types=1);

namespace App\Service\Auth;

final readonly class MiDataOAuthUserInfo
{
    /**
     * @param list<HitobitoRole> $roles
     */
    public function __construct(
        public string $subject,
        public ?string $email,
        public bool $emailVerified,
        public ?string $firstName,
        public ?string $lastName,
        public array $roles = [],
        public ?string $nickname = null,
        public ?string $primaryGroupId = null,
    ) {}
}
