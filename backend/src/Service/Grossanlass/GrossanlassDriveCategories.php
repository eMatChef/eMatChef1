<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * Fahr- und Bedienklassen für Grossanlass-User-Karten (Führerausweis, Stapler, Kran).
 */
final class GrossanlassDriveCategories
{
    public const PROOF_NONE = 'none';
    public const PROOF_IN_PERSON = 'in_person';
    public const PROOF_DOCUMENT = 'document';

    /** @var list<string> */
    public const LICENSE = ['b', 'be', 'c', 'c1', 'c1e', 'ce', 'd', 'd1', 'd1e', 'de', 'f', 'g'];

    /** @var list<string> */
    public const FORKLIFT_R = ['r1', 'r2', 'r3', 'r4'];

    /** @var list<string> */
    public const FORKLIFT_S = ['s1', 's2', 's3'];

    /** @var list<string> */
    public const CRANE = ['crane_a', 'crane_b', 'crane_c'];

    /** Krane A/B: zusätzliche Regelungen (SUVA). */
    /** @var list<string> */
    public const EXTRA_REGULATION = ['crane_a', 'crane_b'];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [...self::LICENSE, ...self::FORKLIFT_R, ...self::FORKLIFT_S, ...self::CRANE];
    }

    /**
     * @param list<mixed> $codes
     * @return list<string>
     */
    public static function sanitize(array $codes): array
    {
        $allowed = array_flip(self::all());
        $clean = [];
        foreach ($codes as $code) {
            if (!is_string($code)) {
                continue;
            }
            $key = strtolower(trim($code));
            if ($key === '' || !isset($allowed[$key]) || in_array($key, $clean, true)) {
                continue;
            }
            $clean[] = $key;
        }

        return $clean;
    }

    public static function hasExtraRegulation(array $codes): bool
    {
        return array_intersect(self::EXTRA_REGULATION, $codes) !== [];
    }

    public static function isProofKind(string $kind): bool
    {
        return in_array($kind, [self::PROOF_NONE, self::PROOF_IN_PERSON, self::PROOF_DOCUMENT], true);
    }
}
