<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\Service\Integration\IntegrationSettingsStore;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Client für feiertagskalender.ch / fcal (nur serverseitig).
 * API-Key: zentral in var/app/integration_settings.json (Superadmin) oder Fallback FCAL_API_KEY in .env.
 *
 * @see https://feiertagskalender.ch/api/openapi.php?hl=de
 */
final class FcalApiService
{
    private const BASE = 'https://feiertagskalender.ch/api/Data/GeoId/index.php';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly IntegrationSettingsStore $integrationSettings,
        #[Autowire('%env(string:FCAL_API_KEY)%')]
        private readonly string $envFcalApiKey = '',
    ) {
    }

    private function effectiveApiKey(): string
    {
        $fromStore = $this->integrationSettings->getFcalApiKey();
        if ($fromStore !== '') {
            return $fromStore;
        }

        return trim($this->envFcalApiKey);
    }

    /**
     * Schulferien-Marker für ein Jahr (API class=0).
     *
     * @return array{markers: list<array{date: string, label: string, kind: string}>, location: ?string}
     */
    public function fetchSchoolHolidayMarkers(int $geoId, int $year): array
    {
        $apiKey = $this->effectiveApiKey();
        if ($apiKey === '' || $geoId < 1 || $year < 2000 || $year > 2050) {
            return ['markers' => [], 'location' => null];
        }

        $keyHash = substr(hash('sha256', $apiKey), 0, 16);
        $cacheKey = sprintf('fcal.school.%s.%d.%d', $keyHash, $geoId, $year);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($geoId, $year, $apiKey): array {
            $item->expiresAfter(86400 * 7);

            try {
                $data = $this->fetchJsonGet(self::BASE, [
                    'api_key' => $apiKey,
                    'geoId' => $geoId,
                    'year' => $year,
                    'class' => 0,
                    'format' => 'json',
                    'hl' => 'de',
                ]);
            } catch (\Throwable $e) {
                $this->logger->warning('fcal Data/GeoId failed: {msg}', ['msg' => $e->getMessage()]);

                return ['markers' => [], 'location' => null];
            }

            if ($data === null) {
                return ['markers' => [], 'location' => null];
            }

            return [
                'markers' => $this->parseSchoolEventsToMarkers($data),
                'location' => $this->extractLocationDescription($data),
            ];
        });
    }

    /**
     * GET + JSON ohne Symfony HttpClient (funktioniert auch bei unvollständigem vendor/ im Docker-Volume).
     *
     * @param array<string, int|string> $query
     * @return array<string, mixed>|null
     */
    private function fetchJsonGet(string $baseUrl, array $query): ?array
    {
        $url = $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . http_build_query($query);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: eMatChef-fcal/1.0\r\n",
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') {
            $this->logger->warning('fcal Data/GeoId: empty or failed response');

            return null;
        }

        $status = 0;
        if (isset($http_response_header[0]) && \is_string($http_response_header[0])) {
            if (preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
                $status = (int) $m[1];
            }
        }
        if ($status !== 200) {
            $this->logger->warning('fcal Data/GeoId HTTP {code}', ['code' => $status]);

            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->warning('fcal Data/GeoId JSON: {msg}', ['msg' => $e->getMessage()]);

            return null;
        }

        return \is_array($decoded) ? $decoded : null;
    }

    private function extractLocationDescription(array $data): ?string
    {
        $loc = $data['results']['location'] ?? null;
        if (!\is_array($loc)) {
            return null;
        }
        $d = $loc['description'] ?? null;

        return \is_string($d) && $d !== '' ? $d : null;
    }

    /**
     * @return list<array{date: string, label: string, kind: string}>
     */
    private function parseSchoolEventsToMarkers(array $data): array
    {
        $events = $data['results']['location']['events'] ?? $data['results']['events'] ?? null;
        if (!\is_array($events)) {
            return [];
        }

        /** @var array<string, list<string>> $byDate */
        $byDate = [];

        foreach ($events as $ev) {
            if (!\is_array($ev)) {
                continue;
            }
            if (($ev['class'] ?? null) !== '0' && ($ev['class'] ?? null) !== 0) {
                continue;
            }
            $label = isset($ev['description']) && \is_string($ev['description']) ? trim($ev['description']) : 'Schulferien';
            $start = $ev['dateStart'] ?? null;
            $end = $ev['dateEnd'] ?? null;
            if (!\is_string($start) || !\is_string($end)) {
                continue;
            }
            try {
                $from = new \DateTimeImmutable($start);
                $to = new \DateTimeImmutable($end);
            } catch (\Exception) {
                continue;
            }
            if ($to < $from) {
                continue;
            }
            $cur = $from;
            while ($cur <= $to) {
                $key = $cur->format('Y-m-d');
                if (!isset($byDate[$key])) {
                    $byDate[$key] = [];
                }
                if (!\in_array($label, $byDate[$key], true)) {
                    $byDate[$key][] = $label;
                }
                $cur = $cur->modify('+1 day');
            }
        }

        ksort($byDate);
        $out = [];
        foreach ($byDate as $date => $labels) {
            $out[] = [
                'date' => $date,
                'label' => implode(' / ', $labels),
                'kind' => 'school',
            ];
        }

        return $out;
    }
}
