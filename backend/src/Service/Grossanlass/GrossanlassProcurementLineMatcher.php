<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * Deterministische Suche nach ähnlichen bestehenden Beschaffungspositionen für einen Wunsch.
 * Liefert nur Vorschläge (Stufe + Begründung); Kategorie und Einheit beeinflussen die Bewertung,
 * identifizieren einen Artikel aber nie allein.
 */
final class GrossanlassProcurementLineMatcher
{
    public const TIER_EXACT = 'exact';
    public const TIER_SIMILAR = 'similar';
    public const TIER_POSSIBLE = 'possible';

    private const BASE_SCORE = [
        self::TIER_EXACT => 100,
        self::TIER_SIMILAR => 70,
        self::TIER_POSSIBLE => 40,
    ];

    /**
     * @param array<string, mixed>       $wish  pool wish array (label, category_id, quantity_unit, notes)
     * @param list<array<string, mixed>> $lines line arrays (label, category_id, quantity_unit, notes, source_wishes)
     *
     * @return list<array{line_id: string, tier: string, score: int, reasons: list<string>, unit_compatible: bool}>
     */
    public static function match(array $wish, array $lines): array
    {
        $result = [];
        $wishProfile = self::profile((string) ($wish['label'] ?? ''));
        $wishCategory = self::str($wish['category_id'] ?? null);
        $wishUnit = self::unit($wish['quantity_unit'] ?? null);
        $wishNotes = self::noteTokens($wish['notes'] ?? null);

        foreach ($lines as $line) {
            $lineId = (string) ($line['id'] ?? '');
            if ($lineId === '') {
                continue;
            }
            $labels = [(string) ($line['label'] ?? '')];
            $originalLabels = [];
            foreach ((array) ($line['source_wishes'] ?? []) as $source) {
                $originalLabels[] = (string) ($source['label'] ?? '');
            }

            $best = null;
            $viaOriginal = false;
            foreach ([...$labels, ...$originalLabels] as $index => $candidateLabel) {
                $tier = self::nameTier($wishProfile, self::profile($candidateLabel));
                if ($tier !== null && ($best === null || self::BASE_SCORE[$tier] > self::BASE_SCORE[$best])) {
                    $best = $tier;
                    $viaOriginal = $index >= count($labels);
                }
            }

            $sameCategory = $wishCategory !== null && $wishCategory === self::str($line['category_id'] ?? null);
            $lineUnit = self::unit($line['quantity_unit'] ?? null);
            $unitCompatible = $wishUnit === $lineUnit;
            $reasons = [];

            if ($best === null) {
                $lineProfile = self::profile($labels[0]);
                if ($wishProfile['dims'] !== '' && $lineProfile['dims'] !== '' && $wishProfile['dims'] !== $lineProfile['dims']) {
                    continue;
                }
                $dimsEqual = $wishProfile['dims'] !== '' && $wishProfile['dims'] === $lineProfile['dims'];
                $sharedWord = array_intersect($wishProfile['words'], $lineProfile['words']) !== [];
                $descriptionOverlap = $wishNotes !== [] && array_intersect($wishNotes, self::noteTokens($line['notes'] ?? null)) !== [];
                if ($sameCategory && ($dimsEqual || $sharedWord || $descriptionOverlap)) {
                    $best = self::TIER_POSSIBLE;
                    if ($dimsEqual) {
                        $reasons[] = 'dimensions_equal';
                    }
                    if ($sharedWord) {
                        $reasons[] = 'name_overlap';
                    }
                    if ($descriptionOverlap) {
                        $reasons[] = 'description_overlap';
                    }
                }
            } else {
                $reasons[] = match ($best) {
                    self::TIER_EXACT => 'name_equal',
                    self::TIER_SIMILAR => 'name_similar',
                    default => 'name_overlap',
                };
                if ($viaOriginal) {
                    $reasons[] = 'original_wish';
                }
            }

            if ($best === null) {
                continue;
            }
            if ($sameCategory) {
                $reasons[] = 'same_category';
            }
            $reasons[] = $unitCompatible ? 'same_unit' : 'unit_mismatch';

            $score = self::BASE_SCORE[$best] + ($sameCategory ? 10 : 0) + ($unitCompatible ? 5 : -15);
            $result[] = [
                'line_id' => $lineId,
                'tier' => $best,
                'score' => $score,
                'reasons' => array_values(array_unique($reasons)),
                'unit_compatible' => $unitCompatible,
            ];
        }

        usort($result, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $result;
    }

    /**
     * @param array{words: list<string>, key: string, dims: string, raw: string} $a
     * @param array{words: list<string>, key: string, dims: string, raw: string} $b
     */
    private static function nameTier(array $a, array $b): ?string
    {
        if ($a['key'] === '' || $b['key'] === '') {
            return null;
        }
        // Zwei Artikel mit unterschiedlichen Massen sind nicht derselbe Artikel.
        if ($a['dims'] !== '' && $b['dims'] !== '' && $a['dims'] !== $b['dims']) {
            return null;
        }
        $dimsOk = $a['dims'] === $b['dims'];
        if ($a['raw'] !== '' && $a['raw'] === $b['raw']) {
            return self::TIER_EXACT;
        }
        if ($a['key'] === $b['key'] && $dimsOk) {
            return self::TIER_SIMILAR;
        }
        if (GrossanlassProcurementBundleSuggester::keysAreSimilar($a['key'], $b['key'])) {
            return $dimsOk ? self::TIER_SIMILAR : self::TIER_POSSIBLE;
        }

        return null;
    }

    /**
     * @return array{words: list<string>, key: string, dims: string, raw: string}
     */
    private static function profile(string $label): array
    {
        $value = mb_strtolower(trim($label), 'UTF-8');
        // 30 × 50 mm / 30x50 / 4 x 4 m  →  Massfolge «30x50»
        $dims = '';
        $value = preg_replace_callback(
            '/\d+(?:[.,]\d+)?(?:\s*(?:mm|cm|m)\b)?(?:\s*[x×*]\s*\d+(?:[.,]\d+)?(?:\s*(?:mm|cm|m)\b)?)*/u',
            static function (array $m) use (&$dims): string {
                $numbers = [];
                preg_match_all('/\d+(?:[.,]\d+)?/', $m[0], $found);
                foreach ($found[0] as $n) {
                    $numbers[] = str_replace(',', '.', $n);
                }
                $dims .= ($dims === '' ? '' : ' ') . implode('x', $numbers);

                return ' ';
            },
            $value,
        ) ?? $value;
        $key = GrossanlassProcurementBundleSuggester::normalizeKey($value);

        return [
            'words' => $key === '' ? [] : explode(' ', $key),
            'key' => $key,
            'dims' => $dims,
            'raw' => trim(preg_replace('/\s+/u', ' ', mb_strtolower(trim($label), 'UTF-8')) ?? ''),
        ];
    }

    /**
     * @return list<string>
     */
    private static function noteTokens(mixed $notes): array
    {
        $key = GrossanlassProcurementBundleSuggester::normalizeKey((string) $notes);
        if ($key === '') {
            return [];
        }

        return array_values(array_filter(explode(' ', $key), static fn (string $t) => mb_strlen($t) >= 5));
    }

    private static function str(mixed $value): ?string
    {
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private static function unit(mixed $value): string
    {
        $s = mb_strtolower(trim((string) $value), 'UTF-8');

        return $s === '' ? 'stk' : $s;
    }
}
