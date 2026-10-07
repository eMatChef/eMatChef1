<?php

declare(strict_types=1);

namespace App\Service\Auth;

interface HitobitoGroupLookup
{
    public function getGroup(string $provider, string $accessToken, string $groupId): ?HitobitoGroup;
}
