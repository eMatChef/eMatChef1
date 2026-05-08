<?php

namespace App\EventSubscriber;

use App\Service\Integration\IntegrationSettingsStore;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiAuthRateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly IntegrationSettingsStore $integrationSettings,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 250],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $method = strtoupper($request->getMethod());

        $limit = null;
        if ($path === '/api/auth/session' && $method === 'GET') {
            $limit = $this->integrationSettings->getAuthSessionLimitPerMinute();
        } elseif ($path === '/api/token/refresh' && $method === 'POST') {
            $limit = $this->integrationSettings->getAuthRefreshLimitPerMinute();
        }

        if ($limit === null) {
            return;
        }

        $ip = (string) ($request->getClientIp() ?? 'unknown');
        $bucket = (new \DateTimeImmutable())->format('YmdHi');
        $cacheKey = 'auth_rate|' . hash('sha256', $path . '|' . $method . '|' . $ip . '|' . $bucket);
        $item = $this->cache->getItem($cacheKey);
        $count = 0;
        if ($item->isHit()) {
            $value = $item->get();
            $count = is_numeric($value) ? (int) $value : 0;
        }
        $count++;
        $item->set($count);
        $item->expiresAfter(70);
        $this->cache->save($item);

        if ($count <= $limit) {
            return;
        }

        $retryAfterSeconds = max(1, 60 - (int) date('s'));

        $this->logger->warning('API auth rate limit exceeded', [
            'path' => $path,
            'method' => $method,
            'ip' => $ip,
            'count' => $count,
            'limit_per_minute' => $limit,
            'retry_after_seconds' => $retryAfterSeconds,
        ]);

        $response = new JsonResponse([
            'error' => 'Zu viele Anfragen. Bitte kurz warten und erneut versuchen.',
        ], 429);
        $response->headers->set('Retry-After', (string) $retryAfterSeconds);
        $event->setResponse($response);
    }
}

