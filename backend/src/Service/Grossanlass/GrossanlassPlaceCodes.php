<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * GA-Ort: Event-Standorte, nicht Lager und nicht Address-POI.
 */
final class GrossanlassPlaceCodes
{
    public const KIND_BAUPROJEKT = 'bauprojekt';
    public const KIND_UNTERLAGER = 'unterlager';
    public const KIND_MATPLATZ = 'matplatz';
    public const KIND_ANFAHRT = 'anfahrt';
    public const KIND_POI = 'poi';

    public const KINDS = [
        self::KIND_BAUPROJEKT,
        self::KIND_UNTERLAGER,
        self::KIND_MATPLATZ,
        self::KIND_ANFAHRT,
        self::KIND_POI,
    ];

    public const QR_SEGMENT = 'ga';
    public const QR_SEGMENT_LEGACY = 'p';

    public static function isKind(string $kind): bool
    {
        return in_array($kind, self::KINDS, true);
    }

    public static function normalizeKind(?string $kind): string
    {
        $value = trim((string) $kind);

        return self::isKind($value) ? $value : self::KIND_POI;
    }

    public static function inferKind(
        ?string $requestedKind,
        ?string $unterlagerId,
        bool $groupIsBauprojekt,
    ): string {
        if ($unterlagerId) {
            return self::KIND_UNTERLAGER;
        }
        $requested = trim((string) $requestedKind);
        if (self::isKind($requested)) {
            return $requested;
        }
        if ($groupIsBauprojekt) {
            return self::KIND_BAUPROJEKT;
        }

        return self::KIND_POI;
    }

    public static function qrPath(string $code, bool $legacy = false): string
    {
        $segment = $legacy ? self::QR_SEGMENT_LEGACY : self::QR_SEGMENT;

        return '/i/' . $segment . '/' . rawurlencode($code);
    }

    public static function qrUrl(string $base, string $code): string
    {
        return rtrim($base, '/') . self::qrPath($code);
    }

    public static function clampAxis(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }

        return max(0.0, min(1.0, (float) $value));
    }

    public static function optionalLatitude(mixed $value): ?float
    {
        return self::optionalDegree($value, -90.0, 90.0);
    }

    public static function optionalLongitude(mixed $value): ?float
    {
        return self::optionalDegree($value, -180.0, 180.0);
    }

    /**
     * Bildpunkt (0–1, y oben) → WGS84. Null wenn Overlay-Bounds oder Achse fehlen.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function latLngFromMapPoint(
        mixed $x,
        mixed $y,
        mixed $north,
        mixed $south,
        mixed $east,
        mixed $west,
    ): ?array {
        if (!is_numeric($x) || !is_numeric($y) || !self::validBounds($north, $south, $east, $west)) {
            return null;
        }
        $spanLng = (float) $east - (float) $west;
        $spanLat = (float) $north - (float) $south;

        return [
            'lat' => (float) $north - ((float) $y) * $spanLat,
            'lng' => (float) $west + ((float) $x) * $spanLng,
        ];
    }

    /**
     * WGS84 → Bildpunkt. Null wenn ausserhalb des Overlays (leichter Rand).
     *
     * @return array{x: float, y: float}|null
     */
    public static function mapPointFromLatLng(
        mixed $lat,
        mixed $lng,
        mixed $north,
        mixed $south,
        mixed $east,
        mixed $west,
    ): ?array {
        if (!is_numeric($lat) || !is_numeric($lng) || !self::validBounds($north, $south, $east, $west)) {
            return null;
        }
        $spanLng = (float) $east - (float) $west;
        $spanLat = (float) $north - (float) $south;
        if ($spanLng <= 0.0 || $spanLat <= 0.0) {
            return null;
        }
        $x = (((float) $lng) - (float) $west) / $spanLng;
        $y = (((float) $north) - (float) $lat) / $spanLat;
        if ($x < -0.02 || $x > 1.02 || $y < -0.02 || $y > 1.02) {
            return null;
        }

        return [
            'x' => max(0.0, min(1.0, $x)),
            'y' => max(0.0, min(1.0, $y)),
        ];
    }

    public static function validBounds(mixed $north, mixed $south, mixed $east, mixed $west): bool
    {
        if (!is_numeric($north) || !is_numeric($south) || !is_numeric($east) || !is_numeric($west)) {
            return false;
        }

        return (float) $north > (float) $south && (float) $east > (float) $west;
    }

    private static function optionalDegree(mixed $value, float $min, float $max): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        $number = (float) $value;
        if ($number < $min || $number > $max) {
            return null;
        }

        return $number;
    }
}
