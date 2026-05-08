<?php

declare(strict_types=1);

namespace App\Service\Security;

use Psr\Cache\CacheItemPoolInterface;

final class SecurityMetricsStore
{
    private const CACHE_PREFIX = 'security_metrics_minute_';

    public function __construct(private readonly CacheItemPoolInterface $cache)
    {
    }

    public function record(string $path, int $statusCode): void
    {
        $minuteKey = (new \DateTimeImmutable())->format('YmdHi');
        $cacheKey = self::CACHE_PREFIX . $minuteKey;
        $item = $this->cache->getItem($cacheKey);
        $data = $item->isHit() && \is_array($item->get()) ? $item->get() : [
            'totals' => [],
            'paths' => [],
        ];

        $status = (string) $statusCode;
        $normalizedPath = $this->normalizePath($path);
        $pathKey = $status . '|' . $normalizedPath;

        $data['totals'][$status] = (int) ($data['totals'][$status] ?? 0) + 1;
        $data['paths'][$pathKey] = (int) ($data['paths'][$pathKey] ?? 0) + 1;

        $item->set($data);
        $item->expiresAfter(2 * 60 * 60); // 2h reicht für kurzfristiges Monitoring
        $this->cache->save($item);
    }

    /**
     * @return array{
     *   minutes:int,
     *   totals:array<string,int>,
     *   top_paths:list<array{status:int,path:string,count:int}>
     * }
     */
    public function snapshot(int $minutes = 60): array
    {
        $window = max(1, min(1440, $minutes));
        $totals = [
            '401' => 0,
            '429' => 0,
            '5xx' => 0,
        ];
        $pathCounts = [];

        $now = new \DateTimeImmutable();
        for ($i = 0; $i < $window; $i++) {
            $minute = $now->sub(new \DateInterval('PT' . $i . 'M'))->format('YmdHi');
            $item = $this->cache->getItem(self::CACHE_PREFIX . $minute);
            if (!$item->isHit()) {
                continue;
            }
            $data = $item->get();
            if (!\is_array($data)) {
                continue;
            }
            $minuteTotals = \is_array($data['totals'] ?? null) ? $data['totals'] : [];
            $totals['401'] += (int) ($minuteTotals['401'] ?? 0);
            $totals['429'] += (int) ($minuteTotals['429'] ?? 0);
            foreach ($minuteTotals as $status => $count) {
                if ((int) $status >= 500) {
                    $totals['5xx'] += (int) $count;
                }
            }

            $minutePaths = \is_array($data['paths'] ?? null) ? $data['paths'] : [];
            foreach ($minutePaths as $k => $count) {
                $pathCounts[$k] = (int) ($pathCounts[$k] ?? 0) + (int) $count;
            }
        }

        arsort($pathCounts);
        $top = [];
        foreach (array_slice($pathCounts, 0, 20, true) as $k => $count) {
            [$status, $path] = explode('|', (string) $k, 2);
            $top[] = [
                'status' => (int) $status,
                'path' => $path,
                'count' => (int) $count,
            ];
        }

        return [
            'minutes' => $window,
            'totals' => $totals,
            'top_paths' => $top,
        ];
    }

    private function normalizePath(string $path): string
    {
        $p = preg_replace('#/[0-9]{4,}(?=/|$)#', '/:id', $path) ?? $path;
        $p = preg_replace('#/[a-f0-9]{12,}(?=/|$)#i', '/:id', $p) ?? $p;
        return $p;
    }
}

