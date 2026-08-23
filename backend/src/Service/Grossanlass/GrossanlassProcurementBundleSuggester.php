<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * Vorschläge zum Bündeln ähnlicher Wunsch-Bezeichnungen — nur Vorschlag, keine automatische Aktion.
 */
final class GrossanlassProcurementBundleSuggester
{
    /**
     * @param list<array<string, mixed>> $pool
     *
     * @return list<array{key: string, suggested_label: string, wish_ids: list<string>, quantity_sum: int, wish_count: int}>
     */
    public static function suggest(array $pool): array
    {
        /** @var array<string, list<array<string, mixed>>> $buckets */
        $buckets = [];
        foreach ($pool as $wish) {
            $label = trim((string) ($wish['label'] ?? ''));
            $key = self::normalizeKey($label);
            if ($key === '' || empty($wish['id'])) {
                continue;
            }
            $buckets[$key][] = $wish;
        }

        $merged = self::mergeSimilarBuckets($buckets);
        $groups = [];
        foreach ($merged as $key => $wishes) {
            if (count($wishes) < 2) {
                continue;
            }
            $quantitySum = 0;
            $labelCounts = [];
            $ids = [];
            foreach ($wishes as $wish) {
                $ids[] = (string) $wish['id'];
                $quantitySum += (int) ($wish['quantity'] ?? 0);
                $lbl = trim((string) ($wish['label'] ?? ''));
                $labelCounts[$lbl] = ($labelCounts[$lbl] ?? 0) + 1;
            }
            uksort($labelCounts, static function (string $a, string $b) use ($labelCounts): int {
                $byCount = $labelCounts[$b] <=> $labelCounts[$a];
                if ($byCount !== 0) {
                    return $byCount;
                }
                $byLength = mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
                if ($byLength !== 0) {
                    return $byLength;
                }

                return strcmp($a, $b);
            });
            $suggested = (string) array_key_first($labelCounts);

            $groups[] = [
                'key' => $key,
                'suggested_label' => $suggested,
                'wish_ids' => $ids,
                'quantity_sum' => $quantitySum,
                'wish_count' => count($wishes),
            ];
        }

        usort($groups, static fn (array $a, array $b) => $b['wish_count'] <=> $a['wish_count'] ?: $b['quantity_sum'] <=> $a['quantity_sum']);

        return $groups;
    }

    public static function normalizeKey(string $label): string
    {
        $value = mb_strtolower(trim($label), 'UTF-8');
        $value = strtr($value, [
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ß' => 'ss',
        ]);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        if ($value === '') {
            return '';
        }

        $tokens = explode(' ', $value);
        $stemmed = array_map(self::stemToken(...), $tokens);
        sort($stemmed);

        return implode(' ', $stemmed);
    }

    private static function stemToken(string $token): string
    {
        if (mb_strlen($token) < 5) {
            return $token;
        }
        if (str_ends_with($token, 'en') && mb_strlen($token) > 5) {
            return substr($token, 0, -2);
        }
        if (str_ends_with($token, 'er') && mb_strlen($token) > 5) {
            return substr($token, 0, -2);
        }
        if (str_ends_with($token, 'e') && mb_strlen($token) > 4) {
            return substr($token, 0, -1);
        }
        if (str_ends_with($token, 'n') && mb_strlen($token) > 4) {
            return substr($token, 0, -1);
        }

        return $token;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $buckets
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function mergeSimilarBuckets(array $buckets): array
    {
        $keys = array_keys($buckets);
        $parent = [];
        foreach ($keys as $key) {
            $parent[$key] = $key;
        }

        $find = static function (string $k) use (&$parent): string {
            while ($parent[$k] !== $k) {
                $parent[$k] = $parent[$parent[$k]];
                $k = $parent[$k];
            }

            return $k;
        };

        $n = count($keys);
        for ($i = 0; $i < $n; ++$i) {
            for ($j = $i + 1; $j < $n; ++$j) {
                $a = $keys[$i];
                $b = $keys[$j];
                if (!self::keysAreSimilar($a, $b)) {
                    continue;
                }
                $ra = $find($a);
                $rb = $find($b);
                if ($ra !== $rb) {
                    $parent[$rb] = $ra;
                }
            }
        }

        /** @var array<string, list<array<string, mixed>>> $merged */
        $merged = [];
        foreach ($keys as $key) {
            $root = $find($key);
            foreach ($buckets[$key] as $wish) {
                $merged[$root][] = $wish;
            }
        }

        return $merged;
    }

    private static function keysAreSimilar(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        $lenA = strlen($a);
        $lenB = strlen($b);
        $min = min($lenA, $lenB);
        $max = max($lenA, $lenB);
        if ($min < 4) {
            return false;
        }
        if (str_contains($a, $b) || str_contains($b, $a)) {
            return abs($lenA - $lenB) <= 8;
        }
        $distance = levenshtein($a, $b);
        if ($max >= 8) {
            return $distance <= 2;
        }

        return $distance <= 1;
    }
}
