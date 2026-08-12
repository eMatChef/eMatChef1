<?php

declare(strict_types=1);

namespace App\Util;

use App\Command\EnsureE2eUserCommand;

/**
 * Dedizierter Playwright-Smoke-User — nicht in User-Suchen / Auto-Join anzeigen.
 */
final class E2eSmokeUser
{
    /**
     * @return list<string>
     */
    public static function emails(): array
    {
        return [strtolower(EnsureE2eUserCommand::DEFAULT_EMAIL)];
    }

    public static function isExcluded(?string $email): bool
    {
        if ($email === null || $email === '') {
            return false;
        }

        return \in_array(strtolower(trim($email)), self::emails(), true);
    }
}
