<?php

declare(strict_types=1);

namespace App\Service\Auth;

final class GoogleOAuthUserInfo
{
    public function __construct(
        public readonly string $googleId,
        public readonly string $email,
        public readonly bool $emailVerified,
        public readonly string $firstName,
        public readonly string $lastName,
    ) {}
}
