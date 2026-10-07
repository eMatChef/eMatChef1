<?php

declare(strict_types=1);

namespace App\Service\Auth;

interface HitobitoRoleLookup
{
    /**
     * @return list<HitobitoRole>
     */
    public function getRolesForPerson(string $provider, string $accessToken, string $personId): array;
}
