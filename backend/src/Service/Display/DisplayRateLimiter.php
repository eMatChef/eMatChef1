<?php

namespace App\Service\Display;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Einfacher Zähler pro Schlüssel und Zeitfenster für öffentliche Display-Endpunkte.
 */
final class DisplayRateLimiter
{
    public function __construct(private CacheItemPoolInterface $cache)
    {
    }

    public function isLimited(string $bucket, string $subject, int $max): bool
    {
        $item = $this->cache->getItem($this->key($bucket, $subject));

        return $item->isHit() && (int) $item->get() >= $max;
    }

    public function hit(string $bucket, string $subject, int $windowSeconds): void
    {
        $item = $this->cache->getItem($this->key($bucket, $subject));
        $count = $item->isHit() ? (int) $item->get() : 0;
        $item->set($count + 1);
        if (!$item->isHit()) {
            $item->expiresAfter($windowSeconds);
        }
        $this->cache->save($item);
    }

    private function key(string $bucket, string $subject): string
    {
        return 'display_rl|' . $bucket . '|' . hash('sha256', $subject);
    }
}
