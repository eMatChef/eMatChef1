<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Serverseitiges, einmaliges Ergebnis eines Identitäts-Verknüpfens, gebunden an die eingeloggte Sitzung.
 * Die Rückkehr-URL enthält nur «profile_security=1»; Erfolg/Fehler holt die App über die API ab. So kann eine
 * präparierte URL weder das Profil öffnen noch einen Erfolg oder Fehler vortäuschen.
 */
class LinkResultStore
{
    private const TTL_SECONDS = 300;

    public function __construct(private readonly CacheItemPoolInterface $cache) {}

    public function put(string $sessionId, string $provider, string $status, ?string $reason): void
    {
        $item = $this->cache->getItem($this->key($sessionId));
        $item->set(['provider' => $provider, 'status' => $status, 'reason' => $reason]);
        $item->expiresAfter(self::TTL_SECONDS);
        $this->cache->save($item);
    }

    /** @return array{provider: string, status: string, reason: string|null}|null genau einmal abrufbar */
    public function take(string $sessionId): ?array
    {
        $key = $this->key($sessionId);
        $item = $this->cache->getItem($key);
        if (!$item->isHit()) {
            return null;
        }
        $value = $item->get();
        $this->cache->deleteItem($key);

        return \is_array($value) && isset($value['status'], $value['provider']) ? $value : null;
    }

    private function key(string $sessionId): string
    {
        return 'oauth_link_result_' . hash('sha256', $sessionId);
    }
}
