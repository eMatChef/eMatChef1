<?php

declare(strict_types=1);

namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Grossanlass-IDs: immer 12 Zeichen über den zentralen {@see IdGenerator},
 * mit festem Prefix pro Entitätstyp.
 */
final class GrossanlassIdGenerator
{
    public const ROUND = 'round';
    public const FORM = 'form';
    public const FORM_FIELD = 'form_field';
    public const WISH_RESPONSE = 'wish_response';
    public const WISH_LINE = 'wish_line';
    public const WISH_VALUE = 'wish_value';
    public const INQUIRY = 'inquiry';
    public const COMMITMENT = 'commitment';
    public const PROCUREMENT_LINE = 'procurement_line';
    public const PROCUREMENT_CATEGORY = 'procurement_category';
    public const PROCUREMENT_QUOTE = 'procurement_quote';
    public const PROCUREMENT_ORDER = 'procurement_order';
    public const USER_CARD = 'user_card';
    public const GROUP = 'group';
    public const WORKSHOP_CASE = 'workshop_case';
    public const GMAIL_UNMATCHED = 'gmail_unmatched';
    public const EINSATZ = 'einsatz';

    /** @var array<string, string> */
    public const PREFIXES = [
        self::ROUND => 'gr',
        self::FORM => 'gf',
        self::FORM_FIELD => 'ff',
        self::WISH_RESPONSE => 'wr',
        self::WISH_LINE => 'gw',
        self::WISH_VALUE => 'wv',
        self::INQUIRY => 'iq',
        self::COMMITMENT => 'zs',
        self::PROCUREMENT_LINE => 'gp',
        self::PROCUREMENT_CATEGORY => 'gc',
        self::PROCUREMENT_QUOTE => 'gq',
        self::PROCUREMENT_ORDER => 'go',
        self::USER_CARD => 'uc',
        self::GROUP => 'grp',
        self::WORKSHOP_CASE => 'wk',
        self::GMAIL_UNMATCHED => 'gu',
        self::EINSATZ => 'ei',
    ];

    public static function prefix(string $kind): string
    {
        if (!isset(self::PREFIXES[$kind])) {
            throw new \InvalidArgumentException('Unbekannter Grossanlass-ID-Typ: ' . $kind);
        }

        return self::PREFIXES[$kind];
    }

    public static function unique(
        EntityManagerInterface $em,
        string $kind,
        string $entityClass,
        string $field = 'id',
    ): string {
        return IdGenerator::generate12UniqueWithPrefix($em, $entityClass, self::prefix($kind), $field);
    }

    /** Nested JSON-IDs ohne eigene Tabelle (z. B. Zusage-Leistungen). */
    public static function hex(): string
    {
        return IdGenerator::generate();
    }

    public static function matches(string $id, string $kind): bool
    {
        $prefix = self::prefix($kind);
        $hexLen = 12 - strlen($prefix);
        $pattern = '/^' . preg_quote($prefix, '/') . '[0-9a-f]{' . $hexLen . '}$/i';

        return preg_match($pattern, $id) === 1;
    }
}
