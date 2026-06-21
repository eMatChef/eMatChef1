<?php

namespace App\Service;

/**
 * Anzeigename mit Einheits-Suffix (Spiegel der Frontend-Logik in materialStockUnit.ts).
 */
final class MaterialDisplayName
{
    /** @var list<string> */
    private const PACKAGING_UNITS = [
        'Bündel',
        'Kiste',
        'Karton',
        'Sack',
        'Rolle',
        'Palette',
        'Set',
        'Paket',
    ];

    /** @var list<string> */
    private const PACKAGING_LEGACY_ALIASES = ['Bund'];

    public static function isPackagingUnit(?string $packUnit): bool
    {
        $raw = trim((string) ($packUnit ?? ''));
        if ($raw === '') {
            return false;
        }
        if (in_array($raw, self::PACKAGING_UNITS, true)) {
            return true;
        }

        return in_array($raw, self::PACKAGING_LEGACY_ALIASES, true);
    }

    public static function getStockUnitKind(?string $packUnit): string
    {
        $u = strtolower(trim((string) ($packUnit ?? '')));
        if (in_array($u, ['m', 'meter', 'metre'], true)) {
            return 'length';
        }
        if (in_array($u, ['m2', 'm²', 'qm'], true)) {
            return 'area';
        }

        return 'piece';
    }

    public static function isMeterStockUnit(?string $packUnit): bool
    {
        return self::getStockUnitKind($packUnit) === 'length';
    }

    public static function hasContentPerPiece(?string $packUnit, ?int $packSize): bool
    {
        if ($packSize === null || $packSize < 2) {
            return false;
        }
        $raw = trim((string) ($packUnit ?? ''));
        if (self::isPackagingUnit($raw)) {
            return false;
        }
        if ($raw !== '' && $raw !== 'Stk') {
            return false;
        }

        return true;
    }

    public static function parseSizeLengthCm(string|int|float|null $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $n = (float) str_replace(',', '.', trim((string) $value));
        if (!is_finite($n) || $n <= 0) {
            return null;
        }

        return $n;
    }

    public static function parseLengthMetersFromMaterialName(?string $name): ?float
    {
        if (!preg_match('/\((\d+(?:[.,]\d+)?)\s*m\)\s*$/i', trim((string) ($name ?? '')), $m)) {
            return null;
        }
        $n = (float) str_replace(',', '.', $m[1]);
        if (!is_finite($n) || $n <= 0) {
            return null;
        }

        return $n;
    }

    public static function resolveMaterialSizeLengthCm(
        string|int|float|null $sizeLengthCm,
        ?string $materialName,
    ): ?float {
        $fromField = self::parseSizeLengthCm($sizeLengthCm);
        if ($fromField !== null) {
            return $fromField;
        }
        $meters = self::parseLengthMetersFromMaterialName($materialName);
        if ($meters === null) {
            return null;
        }

        return round($meters * 100);
    }

    public static function sizeLengthCmToMeters(string|int|float|null $cm): ?float
    {
        $n = self::parseSizeLengthCm($cm);
        if ($n === null) {
            return null;
        }

        return $n / 100;
    }

    public static function formatMetersForDisplay(float $meters): string
    {
        $rounded = round($meters * 100) / 100;
        if (abs($rounded - round($rounded)) < 0.001) {
            return (string) (int) round($rounded);
        }

        return rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');
    }

    public static function getMaterialUnitSuffix(
        ?string $packUnit,
        ?int $packSize,
        string|int|float|null $sizeLengthCm = null,
        ?string $materialName = null,
    ): ?string {
        $kind = self::getStockUnitKind($packUnit);
        if ($kind === 'length') {
            $resolvedCm = self::resolveMaterialSizeLengthCm($sizeLengthCm, $materialName);
            $m = self::sizeLengthCmToMeters($resolvedCm);
            if ($m !== null) {
                return self::formatMetersForDisplay($m) . ' m';
            }

            return null;
        }
        if ($kind === 'area') {
            return 'm²';
        }
        if (self::hasContentPerPiece($packUnit, $packSize)) {
            return $packSize . ' m';
        }

        return null;
    }

    /** Nur Einheits-Suffixe am Ende — nicht beliebige Klammern wie «(Netz + 6 Schläger)». */
    private const UNIT_SUFFIX_PATTERN = '/\s*(?:\(\d+(?:[.,]\d+)?\s*m\)|\(m²\))\s*$/iu';

    public static function stripMaterialUnitSuffix(string $name): string
    {
        return trim(preg_replace(self::UNIT_SUFFIX_PATTERN, '', trim($name)) ?? '');
    }

    public static function formatDisplayName(
        string $name,
        ?string $packUnit = null,
        ?int $packSize = null,
        string|int|float|null $sizeLengthCm = null,
    ): string {
        $base = self::stripMaterialUnitSuffix($name);
        if ($base === '') {
            return '';
        }
        $suffix = self::getMaterialUnitSuffix($packUnit, $packSize, $sizeLengthCm, $name);
        if ($suffix === null) {
            return $base;
        }

        return $base . ' (' . $suffix . ')';
    }
}
