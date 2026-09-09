<?php

declare(strict_types=1);

namespace App\Util;

final class GrossanlassContactName
{
    /**
     * @return array{0: string, 1: string}
     */
    public static function split(string $full): array
    {
        $full = trim(preg_replace('/\s+/u', ' ', $full) ?? $full);
        if ($full === '') {
            return ['', ''];
        }
        $pos = mb_strpos($full, ' ');
        if ($pos === false) {
            return [$full, ''];
        }

        return [
            mb_substr($full, 0, $pos),
            trim(mb_substr($full, $pos + 1)),
        ];
    }

    public static function join(string $first, string $last): string
    {
        return trim($first . ' ' . $last);
    }

    /** @return ''|'herr'|'frau' */
    public static function normalizeSalutation(string $raw): string
    {
        $key = mb_strtolower(trim($raw), 'UTF-8');
        $key = str_replace(['ä', 'ö', 'ü', 'ß', '.'], ['a', 'o', 'u', 'ss', ''], $key);
        $key = preg_replace('/[^a-z]/', '', $key) ?? '';
        if (in_array($key, ['herr', 'hr', 'mr', 'mister', 'monsieur'], true)) {
            return 'herr';
        }
        if (in_array($key, ['frau', 'fr', 'mrs', 'ms', 'miss', 'madame', 'madam'], true)) {
            return 'frau';
        }

        return '';
    }

    public static function salutationLabel(string $raw): string
    {
        return match (self::normalizeSalutation($raw)) {
            'herr' => 'Herr',
            'frau' => 'Frau',
            default => '',
        };
    }

    /**
     * @return array{VORNAME: string, NACHNAME: string, KONTAKT: string, ANREDE: string}
     */
    public static function mailParts(string $first, string $last, string $full = '', string $salutation = ''): array
    {
        $first = trim($first);
        $last = trim($last);
        if ($first === '' && $last === '' && trim($full) !== '') {
            [$first, $last] = self::split($full);
        }
        $contact = self::join($first, $last);
        if ($contact === '') {
            $contact = trim($full);
        }

        return [
            'VORNAME' => $first,
            'NACHNAME' => $last,
            'KONTAKT' => $contact,
            'ANREDE' => self::salutationLabel($salutation),
        ];
    }
}
