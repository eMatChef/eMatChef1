<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Macht den OAuth-State einmalig: ein bereits eingelöster State (Replay des Callback-Links) wird abgelehnt.
 * Zusätzlich zur signierten, kurzlebigen State-Cookie-Bindung; Einträge verfallen mit der State-Laufzeit.
 */
class OAuthStateReplayGuard
{
    private const TTL_SECONDS = 900;

    public function __construct(private readonly CacheInterface $cache) {}

    /** @return bool true beim ersten Einlösen, false bei jedem weiteren */
    public function consume(string $provider, string $state): bool
    {
        $first = false;
        $this->cache->get('oauth_state_' . hash('sha256', $provider . '|' . $state), function (ItemInterface $item) use (&$first): bool {
            $item->expiresAfter(self::TTL_SECONDS);
            $first = true;

            return true;
        });

        return $first;
    }
}
