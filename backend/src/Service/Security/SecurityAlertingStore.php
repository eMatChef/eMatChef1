<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\SecurityAlertEvent;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;

final class SecurityAlertingStore
{
    private const LOGIN_WINDOW_MINUTES = 15;
    private const LOGIN_THRESHOLD = 5;
    private const LOGIN_ALERT_TYPE = 'login_bruteforce_threshold_exceeded';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function recordLoginFailure(Request $request, int $statusCode): void
    {
        $path = $request->getPathInfo();
        if ($path !== '/api/auth/login_check') {
            return;
        }
        if (!\in_array($statusCode, [401, 429], true)) {
            return;
        }

        $identifier = $this->readIdentifier($request);
        $ip = (string) ($request->getClientIp() ?? 'unknown');
        $fingerprintRaw = strtolower(trim($identifier !== '' ? $identifier : 'unknown')) . '|' . $ip;
        $fingerprint = hash('sha256', $fingerprintRaw);
        $minuteKey = (new \DateTimeImmutable())->format('YmdHi');
        $counterKey = sprintf('security_login_fail_%s_%s', $minuteKey, $fingerprint);

        $item = $this->cache->getItem($counterKey);
        $count = (int) ($item->isHit() ? $item->get() : 0);
        $count++;
        $item->set($count);
        $item->expiresAfter((self::LOGIN_WINDOW_MINUTES + 5) * 60);
        $this->cache->save($item);

        $rollingCount = 0;
        $now = new \DateTimeImmutable();
        for ($i = 0; $i < self::LOGIN_WINDOW_MINUTES; $i++) {
            $k = sprintf(
                'security_login_fail_%s_%s',
                $now->sub(new \DateInterval('PT' . $i . 'M'))->format('YmdHi'),
                $fingerprint
            );
            $it = $this->cache->getItem($k);
            if ($it->isHit()) {
                $rollingCount += (int) $it->get();
            }
        }

        if ($rollingCount <= self::LOGIN_THRESHOLD) {
            return;
        }

        // Nur einmal pro 15-Minuten-Zeitfenster persistieren
        $windowSlot = $this->windowSlot($now);
        $dedupeKey = sprintf('security_login_alerted_%s_%s', $windowSlot, $fingerprint);
        $dedupe = $this->cache->getItem($dedupeKey);
        if ($dedupe->isHit()) {
            return;
        }
        $dedupe->set(1);
        $dedupe->expiresAfter(self::LOGIN_WINDOW_MINUTES * 60);
        $this->cache->save($dedupe);

        $event = new SecurityAlertEvent();
        $event->setId(IdGenerator::generate13Unique($this->entityManager, SecurityAlertEvent::class, 'se'));
        $event->setAlertType(self::LOGIN_ALERT_TYPE);
        $event->setSeverity('high');
        $event->setSourceKey($fingerprint);
        $event->setWindowMinutes(self::LOGIN_WINDOW_MINUTES);
        $event->setEventCount($rollingCount);
        $event->setIpAddress($ip);
        $event->setIdentifier($identifier !== '' ? strtolower(trim($identifier)) : null);
        $event->setPath($path);
        $event->setStatusCode($statusCode);
        $event->setContext([
            'message' => 'Mehr als 5 Login-Fehlversuche in 15 Minuten',
            'threshold' => self::LOGIN_THRESHOLD,
            'window_minutes' => self::LOGIN_WINDOW_MINUTES,
            'status' => $statusCode,
            'user_agent' => (string) $request->headers->get('User-Agent', ''),
        ]);

        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function recentEvents(int $limit = 100): array
    {
        $rows = $this->entityManager
            ->createQueryBuilder()
            ->select('e.id, e.alertType, e.severity, e.windowMinutes, e.eventCount, e.ipAddress, e.identifier, e.path, e.statusCode, e.context, e.createdAt')
            ->from(SecurityAlertEvent::class, 'e')
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults(max(1, min(500, $limit)))
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $row): array {
            return [
                'id' => (string) ($row['id'] ?? ''),
                'alert_type' => (string) ($row['alertType'] ?? ''),
                'severity' => (string) ($row['severity'] ?? 'warning'),
                'window_minutes' => (int) ($row['windowMinutes'] ?? 0),
                'event_count' => (int) ($row['eventCount'] ?? 0),
                'ip_address' => $row['ipAddress'] ?? null,
                'identifier' => $row['identifier'] ?? null,
                'path' => (string) ($row['path'] ?? ''),
                'status_code' => isset($row['statusCode']) ? (int) $row['statusCode'] : null,
                'context' => \is_array($row['context'] ?? null) ? $row['context'] : [],
                'created_at' => ($row['createdAt'] instanceof \DateTimeInterface)
                    ? $row['createdAt']->format(\DateTimeInterface::ATOM)
                    : null,
            ];
        }, $rows);
    }

    /**
     * @return array{threshold:int,window_minutes:int}
     */
    public function loginThresholdConfig(): array
    {
        return [
            'threshold' => self::LOGIN_THRESHOLD,
            'window_minutes' => self::LOGIN_WINDOW_MINUTES,
        ];
    }

    private function readIdentifier(Request $request): string
    {
        $content = trim((string) $request->getContent());
        if ($content === '') {
            return '';
        }
        $data = json_decode($content, true);
        if (!\is_array($data)) {
            return '';
        }
        return trim((string) ($data['email'] ?? $data['username'] ?? ''));
    }

    private function windowSlot(\DateTimeImmutable $now): string
    {
        $slot = intdiv((int) $now->format('i'), self::LOGIN_WINDOW_MINUTES);
        return $now->format('YmdH') . sprintf('%02d', $slot);
    }
}

