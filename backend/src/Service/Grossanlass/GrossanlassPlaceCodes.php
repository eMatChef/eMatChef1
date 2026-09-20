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
    public const KIND_AREA = 'area';

    public const KINDS = [
        self::KIND_BAUPROJEKT,
        self::KIND_UNTERLAGER,
        self::KIND_MATPLATZ,
        self::KIND_ANFAHRT,
        self::KIND_POI,
        self::KIND_AREA,
    ];

    public const POLYGON_MIN_POINTS = 3;
    public const POLYGON_MAX_POINTS = 32;

    /** Neu anlegbar. Matplatz ist der Lagerstandort, kein GA-Ort. */
    public const CREATE_KINDS = [
        self::KIND_BAUPROJEKT,
        self::KIND_UNTERLAGER,
        self::KIND_ANFAHRT,
        self::KIND_POI,
    ];

    public const QR_SEGMENT = 'ga';
    public const QR_SEGMENT_LEGACY = 'p';

    public static function isKind(string $kind): bool
    {
        return in_array($kind, self::KINDS, true);
    }

    public static function isCreateKind(string $kind): bool
    {
        return in_array($kind, self::CREATE_KINDS, true);
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

    /**
     * @return list<array{lat: float, lng: float}>|null
     */
    public static function normalizePolygon(mixed $value): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }
        if (!is_array($value)) {
            return null;
        }
        $points = [];
        foreach ($value as $item) {
            $lat = null;
            $lng = null;
            if (is_array($item)) {
                if (array_key_exists('lat', $item) || array_key_exists('lng', $item)) {
                    $lat = self::optionalLatitude($item['lat'] ?? null);
                    $lng = self::optionalLongitude($item['lng'] ?? null);
                } elseif (array_is_list($item) && count($item) >= 2) {
                    $lat = self::optionalLatitude($item[0] ?? null);
                    $lng = self::optionalLongitude($item[1] ?? null);
                }
            }
            if ($lat === null || $lng === null) {
                continue;
            }
            $points[] = ['lat' => $lat, 'lng' => $lng];
            if (count($points) >= self::POLYGON_MAX_POINTS) {
                break;
            }
        }
        if (count($points) < self::POLYGON_MIN_POINTS) {
            return null;
        }

        return $points;
    }

    /**
     * @param list<array{lat: float, lng: float}> $points
     * @return array{lat: float, lng: float}|null
     */
    public static function centroidFromPolygon(array $points): ?array
    {
        if ($points === []) {
            return null;
        }
        $lat = 0.0;
        $lng = 0.0;
        foreach ($points as $point) {
            $lat += $point['lat'];
            $lng += $point['lng'];
        }
        $count = count($points);

        return [
            'lat' => $lat / $count,
            'lng' => $lng / $count,
        ];
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
