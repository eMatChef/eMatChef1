<?php

declare(strict_types=1);

namespace App\Service\Auth;

final readonly class HitobitoOAuthSession
{
    public function __construct(
        public string $provider,
        public string $accessToken,
        public MiDataOAuthUserInfo $userInfo,
    ) {}
}
