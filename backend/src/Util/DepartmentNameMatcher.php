<?php

namespace App\Util;

/**
 * Erkennt identische oder sehr ähnliche Department-Namen innerhalb einer Organisation
 * (z. B. «PFF 2027» vs «PFF27», «PFF-2027» vs «pff2027»).
 */
final class DepartmentNameMatcher
{
    public static function conflict(string $a, string $b): bool
    {
        $keysA = self::matchKeys($a);
        $keysB = self::matchKeys($b);
        if ($keysA === [] || $keysB === []) {
            return false;
        }

        foreach ($keysB as $key) {
            if (in_array($key, $keysA, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function matchKeys(string $name): array
    {
        $compact = self::compactKey($name);
        if ($compact === '') {
            return [];
        }

        $keys = [$compact];

        if (preg_match('/^(\p{L}+)(\d+)$/u', $compact, $matches)) {
            $letters = $matches[1];
            $digits = $matches[2];
            $keys[] = $letters . $digits;
            if (strlen($digits) === 4 && preg_match('/^(19|20)\d{2}$/', $digits)) {
                $keys[] = $letters . substr($digits, -2);
            }
        }

        return array_values(array_unique($keys));
    }

    private static function compactKey(string $name): string
    {
        $lower = mb_strtolower(trim($name));

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $lower) ?? '';
    }
}
