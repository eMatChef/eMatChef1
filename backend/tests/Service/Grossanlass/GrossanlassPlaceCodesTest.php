<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassPlaceCodes;
use PHPUnit\Framework\TestCase;

class GrossanlassPlaceCodesTest extends TestCase
{
    public function testQrPathIsNamespacedUnderGa(): void
    {
        self::assertSame('/i/ga/ga1a2b3c4d', GrossanlassPlaceCodes::qrPath('ga1a2b3c4d'));
        self::assertSame('/i/p/pl1a2b3c4d', GrossanlassPlaceCodes::qrPath('pl1a2b3c4d', true));
        self::assertSame(
            'https://qr.example/i/ga/ga1a2b3c4d',
            GrossanlassPlaceCodes::qrUrl('https://qr.example/', 'ga1a2b3c4d'),
        );
    }

    public function testInferKindPrefersUnterlagerThenRequestThenBauprojekt(): void
    {
        self::assertSame(
            GrossanlassPlaceCodes::KIND_UNTERLAGER,
            GrossanlassPlaceCodes::inferKind('poi', 'ul1', false),
        );
        self::assertSame(
            GrossanlassPlaceCodes::KIND_MATPLATZ,
            GrossanlassPlaceCodes::inferKind('matplatz', null, true),
        );
        self::assertSame(
            GrossanlassPlaceCodes::KIND_BAUPROJEKT,
            GrossanlassPlaceCodes::inferKind(null, null, true),
        );
        self::assertSame(
            GrossanlassPlaceCodes::KIND_POI,
            GrossanlassPlaceCodes::inferKind('nope', null, false),
        );
        self::assertSame(
            GrossanlassPlaceCodes::KIND_ANFAHRT,
            GrossanlassPlaceCodes::normalizeKind('anfahrt'),
        );
        self::assertFalse(GrossanlassPlaceCodes::isCreateKind(GrossanlassPlaceCodes::KIND_MATPLATZ));
        self::assertTrue(GrossanlassPlaceCodes::isCreateKind(GrossanlassPlaceCodes::KIND_ANFAHRT));
        self::assertTrue(GrossanlassPlaceCodes::isKind(GrossanlassPlaceCodes::KIND_AREA));
        self::assertFalse(GrossanlassPlaceCodes::isCreateKind(GrossanlassPlaceCodes::KIND_AREA));
        self::assertSame(
            GrossanlassPlaceCodes::KIND_AREA,
            GrossanlassPlaceCodes::inferKind('area', null, false),
        );
    }

    public function testNormalizePolygonKeepsValidPointsAndCentroid(): void
    {
        $points = GrossanlassPlaceCodes::normalizePolygon([
            ['lat' => 47.4, 'lng' => 8.5],
            ['lat' => 47.41, 'lng' => 8.52],
            [47.39, 8.51],
            ['lat' => 200, 'lng' => 8.5],
        ]);
        self::assertNotNull($points);
        self::assertCount(3, $points);
        $centroid = GrossanlassPlaceCodes::centroidFromPolygon($points);
        self::assertNotNull($centroid);
        self::assertEqualsWithDelta(47.4, $centroid['lat'], 0.02);
        self::assertNull(GrossanlassPlaceCodes::normalizePolygon([['lat' => 47.4, 'lng' => 8.5]]));
        self::assertNull(GrossanlassPlaceCodes::normalizePolygon(null));
    }

    public function testClampAxisKeepsPinsOnTheBoard(): void
    {
        self::assertSame(0.25, GrossanlassPlaceCodes::clampAxis(0.25));
        self::assertSame(0.0, GrossanlassPlaceCodes::clampAxis(-2));
        self::assertSame(1.0, GrossanlassPlaceCodes::clampAxis(9));
        self::assertNull(GrossanlassPlaceCodes::clampAxis(null));
        self::assertNull(GrossanlassPlaceCodes::clampAxis('x'));
    }

    public function testLatLngAndMapPointRoundTripOnOverlayBounds(): void
    {
        $north = 47.40;
        $south = 47.38;
        $east = 8.56;
        $west = 8.52;
        $geo = GrossanlassPlaceCodes::latLngFromMapPoint(0.25, 0.5, $north, $south, $east, $west);
        self::assertNotNull($geo);
        self::assertEqualsWithDelta(47.39, $geo['lat'], 0.00001);
        self::assertEqualsWithDelta(8.53, $geo['lng'], 0.00001);

        $point = GrossanlassPlaceCodes::mapPointFromLatLng($geo['lat'], $geo['lng'], $north, $south, $east, $west);
        self::assertNotNull($point);
        self::assertEqualsWithDelta(0.25, $point['x'], 0.00001);
        self::assertEqualsWithDelta(0.5, $point['y'], 0.00001);
        self::assertNull(GrossanlassPlaceCodes::mapPointFromLatLng(46.0, 6.0, $north, $south, $east, $west));
        self::assertNull(GrossanlassPlaceCodes::optionalLatitude(100));
        self::assertSame(47.5, GrossanlassPlaceCodes::optionalLatitude(47.5));
    }
}
