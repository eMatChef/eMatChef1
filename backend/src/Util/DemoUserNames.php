<?php

declare(strict_types=1);

namespace App\Util;

/**
 * Demo-User: deterministisch «zufälliger» Vorname pro E-Mail, Nachname = Rolle.
 */
final class DemoUserNames
{
    /** @var list<string> */
    private const FIRST_NAMES = [
        'Lara', 'Tim', 'Sandra', 'Marco', 'Anna', 'Luca', 'Nina', 'Jan',
        'Mia', 'Noah', 'Lea', 'Finn', 'Emma', 'Ben', 'Sofia', 'Elias',
        'Hannah', 'Leon', 'Clara', 'David', 'Julia', 'Samuel', 'Laura', 'Fabian',
    ];

    public static function firstNameForEmail(string $email): string
    {
        $normalized = strtolower(trim($email));
        $index = abs(crc32($normalized)) % count(self::FIRST_NAMES);

        return self::FIRST_NAMES[$index];
    }
}
