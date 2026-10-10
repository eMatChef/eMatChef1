<?php

declare(strict_types=1);

namespace App\Service\Auth;

/** Claim-Namen der JWTs (gleiche Werte wie JwtSessionSubscriber). */
final class JwtSessionClaims
{
    public const SESSION = 'sid';
    public const USER = 'username';
}
