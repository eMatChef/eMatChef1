<?php

namespace App\Service\Display;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Einfacher Zähler pro Schlüssel mit festem Zeitfenster für öffentliche Display-Endpunkte.
 * Das Fenster beginnt beim ersten Treffer und wird durch weitere Treffer nicht verlängert.
 */
final class DisplayRateLimiter
{
    public function __construct(private CacheItemPoolInterface $cache)
    {
    }

    public function isLimited(string $bucket, string $subject, int $max): bool
    {
        $state = $this->state($bucket, $subject);

        return $state !== null && $state['count'] >= $max;
    }

    public function hit(string $bucket, string $subject, int $windowSeconds): void
    {
        $item = $this->cache->getItem($this->key($bucket, $subject));
        $state = $this->state($bucket, $subject);
        $now = time();
        if ($state === null) {
            $state = ['count' => 0, 'until' => $now + $windowSeconds];
        }
        $state['count']++;
        $item->set($state);
        // Verbleibende Zeit des Fensters: ein erneutes save() ohne Ablauf würde sonst nie verfallen.
        $item->expiresAfter(max(1, $state['until'] - $now));
        $this->cache->save($item);
    }

    /** @return array{count: int, until: int}|null */
    private function state(string $bucket, string $subject): ?array
    {
        $item = $this->cache->getItem($this->key($bucket, $subject));
        if (!$item->isHit()) {
            return null;
        }
        $value = $item->get();
        if (!is_array($value) || !isset($value['count'], $value['until']) || (int) $value['until'] <= time()) {
            return null;
        }

        return ['count' => (int) $value['count'], 'until' => (int) $value['until']];
    }

    private function key(string $bucket, string $subject): string
    {
        return 'display_rl|' . $bucket . '|' . hash('sha256', $subject);
    }
}
