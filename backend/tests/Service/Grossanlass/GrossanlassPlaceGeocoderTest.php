<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassPlaceGeocoder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GrossanlassPlaceGeocoderTest extends TestCase
{
    public function testGeocodeReturnsLatLngFromGeoAdmin(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) json_encode([
                'results' => [
                    ['attrs' => ['lat' => 47.521, 'lon' => 8.536]],
                ],
            ])),
        ]);
        $geocoder = new GrossanlassPlaceGeocoder($client);
        self::assertSame(
            ['lat' => 47.521, 'lng' => 8.536],
            $geocoder->geocode('Bülach'),
        );
    }

    public function testGeocodeEmptyPlaceReturnsNull(): void
    {
        $client = new MockHttpClient();
        $geocoder = new GrossanlassPlaceGeocoder($client);
        self::assertNull($geocoder->geocode('  '));
    }

    public function testGeocodeMissingResultsReturnsNull(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) json_encode(['results' => []])),
        ]);
        $geocoder = new GrossanlassPlaceGeocoder($client);
        self::assertNull($geocoder->geocode('Unbekanntesdorfxyz'));
    }
}
