<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ort → WGS84 über geo.admin.ch (gleiche Quelle wie Kontakt-Karte).
 */
final class GrossanlassPlaceGeocoder
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $place): ?array
    {
        $query = trim($place);
        if ($query === '') {
            return null;
        }
        try {
            $response = $this->httpClient->request(
                'GET',
                'https://api3.geo.admin.ch/rest/services/api/SearchServer',
                [
                    'query' => [
                        'searchText' => $query,
                        'type' => 'locations',
                        'limit' => 1,
                    ],
                    'timeout' => 8,
                ],
            );
            $data = $response->toArray(false);
        } catch (\Throwable) {
            return null;
        }
        $results = $data['results'] ?? null;
        if (!is_array($results) || $results === []) {
            return null;
        }
        $first = $results[0];
        $attrs = is_array($first) ? ($first['attrs'] ?? null) : null;
        if (!is_array($attrs)) {
            return null;
        }
        $lat = $attrs['lat'] ?? null;
        $lng = $attrs['lon'] ?? $attrs['lng'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return null;
        }

        return [
            'lat' => (float) $lat,
            'lng' => (float) $lng,
        ];
    }
}
