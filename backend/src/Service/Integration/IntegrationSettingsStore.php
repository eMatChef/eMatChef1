<?php

declare(strict_types=1);

namespace App\Service\Integration;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Zentrale Integrations-Einstellungen (var/app/integration_settings.json, nicht in Git).
 * API-Keys nur serverseitig; Zugriff über IntegrationAdminController (ROLE_SUPERADMIN).
 */
final class IntegrationSettingsStore
{
    public const AUTH_SESSION_LIMIT_DEFAULT = 120;
    public const AUTH_REFRESH_LIMIT_DEFAULT = 30;
    public const AUTH_SESSION_LIMIT_MIN = 10;
    public const AUTH_SESSION_LIMIT_MAX = 1200;
    public const AUTH_REFRESH_LIMIT_MIN = 5;
    public const AUTH_REFRESH_LIMIT_MAX = 600;
    public const AUTOLOGOUT_TIMEOUT_MS_DEFAULT = 3600000;
    public const AUTOLOGOUT_WARNING_MS_DEFAULT = 300000;
    public const AUTOLOGOUT_ACTIVITY_THROTTLE_MS_DEFAULT = 5000;
    public const AUTOLOGOUT_REFRESH_INTERVAL_MS_DEFAULT = 1500000;
    public const AUTOLOGOUT_TIMEOUT_MS_MIN = 60000;
    public const AUTOLOGOUT_WARNING_MS_MIN = 15000;
    public const AUTOLOGOUT_ACTIVITY_THROTTLE_MS_MIN = 500;
    public const AUTOLOGOUT_REFRESH_INTERVAL_MS_MIN = 60000;
    public const AUTOLOGOUT_TIMEOUT_MS_MAX = 86400000;
    public const AUTOLOGOUT_WARNING_MS_MAX = 3600000;
    public const AUTOLOGOUT_ACTIVITY_THROTTLE_MS_MAX = 60000;
    public const AUTOLOGOUT_REFRESH_INTERVAL_MS_MAX = 3600000;
    /** @var list<string> */
    public const AUTOLOGOUT_EVENTS_ALLOWED = ['click', 'keydown', 'scroll', 'mousemove', 'wheel', 'touchmove'];
    /** @var list<string> */
    public const AUTOLOGOUT_EVENTS_DEFAULT = ['click', 'keydown', 'scroll'];

    private string $filePath;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
        $this->filePath = $this->projectDir . '/var/app/integration_settings.json';
    }

    public function getFcalApiKey(): string
    {
        $data = $this->readFile();

        return isset($data['fcal_api_key']) && \is_string($data['fcal_api_key'])
            ? trim($data['fcal_api_key'])
            : '';
    }

    public function isFcalApiKeyConfigured(): bool
    {
        return $this->getFcalApiKey() !== '';
    }

    public function setFcalApiKey(string $apiKey): void
    {
        $existing = $this->readFile() ?? [];
        $trimmed = trim($apiKey);
        if ($trimmed === '') {
            unset($existing['fcal_api_key']);
        } else {
            $existing['fcal_api_key'] = $trimmed;
        }
        $existing['updated_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $this->writeFile($existing);
    }

    public function getAuthSessionLimitPerMinute(): int
    {
        $data = $this->readFile();
        $raw = $data['auth_session_limit_per_minute'] ?? null;
        $value = is_numeric($raw) ? (int) $raw : self::AUTH_SESSION_LIMIT_DEFAULT;
        return max(self::AUTH_SESSION_LIMIT_MIN, min(self::AUTH_SESSION_LIMIT_MAX, $value));
    }

    public function getAuthRefreshLimitPerMinute(): int
    {
        $data = $this->readFile();
        $raw = $data['auth_refresh_limit_per_minute'] ?? null;
        $value = is_numeric($raw) ? (int) $raw : self::AUTH_REFRESH_LIMIT_DEFAULT;
        return max(self::AUTH_REFRESH_LIMIT_MIN, min(self::AUTH_REFRESH_LIMIT_MAX, $value));
    }

    public function setAuthRateLimitsPerMinute(int $sessionLimit, int $refreshLimit): void
    {
        $session = max(self::AUTH_SESSION_LIMIT_MIN, min(self::AUTH_SESSION_LIMIT_MAX, $sessionLimit));
        $refresh = max(self::AUTH_REFRESH_LIMIT_MIN, min(self::AUTH_REFRESH_LIMIT_MAX, $refreshLimit));
        $existing = $this->readFile() ?? [];
        $existing['auth_session_limit_per_minute'] = $session;
        $existing['auth_refresh_limit_per_minute'] = $refresh;
        $existing['updated_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $this->writeFile($existing);
    }

    /**
     * @return array{timeout_ms:int,warning_ms:int,activity_throttle_ms:int,refresh_interval_ms:int,activity_events:list<string>}
     */
    public function getAutoLogoutConfig(): array
    {
        $data = $this->readFile() ?? [];
        $eventsRaw = isset($data['autologout_activity_events']) ? (string) $data['autologout_activity_events'] : '';

        return [
            'timeout_ms' => $this->clampInt(
                $data['autologout_timeout_ms'] ?? null,
                self::AUTOLOGOUT_TIMEOUT_MS_DEFAULT,
                self::AUTOLOGOUT_TIMEOUT_MS_MIN,
                self::AUTOLOGOUT_TIMEOUT_MS_MAX
            ),
            'warning_ms' => $this->clampInt(
                $data['autologout_warning_ms'] ?? null,
                self::AUTOLOGOUT_WARNING_MS_DEFAULT,
                self::AUTOLOGOUT_WARNING_MS_MIN,
                self::AUTOLOGOUT_WARNING_MS_MAX
            ),
            'activity_throttle_ms' => $this->clampInt(
                $data['autologout_activity_throttle_ms'] ?? null,
                self::AUTOLOGOUT_ACTIVITY_THROTTLE_MS_DEFAULT,
                self::AUTOLOGOUT_ACTIVITY_THROTTLE_MS_MIN,
                self::AUTOLOGOUT_ACTIVITY_THROTTLE_MS_MAX
            ),
            'refresh_interval_ms' => $this->clampInt(
                $data['autologout_refresh_interval_ms'] ?? null,
                self::AUTOLOGOUT_REFRESH_INTERVAL_MS_DEFAULT,
                self::AUTOLOGOUT_REFRESH_INTERVAL_MS_MIN,
                self::AUTOLOGOUT_REFRESH_INTERVAL_MS_MAX
            ),
            'activity_events' => $this->sanitizeAutoLogoutEvents($eventsRaw),
        ];
    }

    /**
     * @param array{timeout_ms?:int,warning_ms?:int,activity_throttle_ms?:int,refresh_interval_ms?:int,activity_events?:string} $data
     */
    public function setAutoLogoutConfig(array $data): void
    {
        $current = $this->getAutoLogoutConfig();
        $existing = $this->readFile() ?? [];
        $existing['autologout_timeout_ms'] = $this->clampInt(
            $data['timeout_ms'] ?? $current['timeout_ms'],
            self::AUTOLOGOUT_TIMEOUT_MS_DEFAULT,
            self::AUTOLOGOUT_TIMEOUT_MS_MIN,
            self::AUTOLOGOUT_TIMEOUT_MS_MAX
        );
        $existing['autologout_warning_ms'] = $this->clampInt(
            $data['warning_ms'] ?? $current['warning_ms'],
            self::AUTOLOGOUT_WARNING_MS_DEFAULT,
            self::AUTOLOGOUT_WARNING_MS_MIN,
            self::AUTOLOGOUT_WARNING_MS_MAX
        );
        $existing['autologout_activity_throttle_ms'] = $this->clampInt(
            $data['activity_throttle_ms'] ?? $current['activity_throttle_ms'],
            self::AUTOLOGOUT_ACTIVITY_THROTTLE_MS_DEFAULT,
            self::AUTOLOGOUT_ACTIVITY_THROTTLE_MS_MIN,
            self::AUTOLOGOUT_ACTIVITY_THROTTLE_MS_MAX
        );
        $existing['autologout_refresh_interval_ms'] = $this->clampInt(
            $data['refresh_interval_ms'] ?? $current['refresh_interval_ms'],
            self::AUTOLOGOUT_REFRESH_INTERVAL_MS_DEFAULT,
            self::AUTOLOGOUT_REFRESH_INTERVAL_MS_MIN,
            self::AUTOLOGOUT_REFRESH_INTERVAL_MS_MAX
        );
        $existing['autologout_activity_events'] = implode(',', $this->sanitizeAutoLogoutEvents((string) ($data['activity_events'] ?? '')));
        $existing['updated_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $this->writeFile($existing);
    }

    /**
     * @return list<string>
     */
    private function sanitizeAutoLogoutEvents(string $csv): array
    {
        $parts = array_values(array_filter(array_map(
            static fn (string $v): string => strtolower(trim($v)),
            explode(',', $csv)
        )));
        $allowed = array_values(array_intersect($parts, self::AUTOLOGOUT_EVENTS_ALLOWED));
        if (\count($allowed) === 0) {
            return self::AUTOLOGOUT_EVENTS_DEFAULT;
        }
        return array_values(array_unique($allowed));
    }

    private function clampInt(mixed $raw, int $default, int $min, int $max): int
    {
        $value = is_numeric($raw) ? (int) $raw : $default;
        return max($min, min($max, $value));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readFile(): ?array
    {
        if (!is_readable($this->filePath)) {
            return null;
        }
        $raw = file_get_contents($this->filePath);
        if ($raw === false || trim($raw) === '') {
            return null;
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeFile(array $data): void
    {
        $dir = \dirname($this->filePath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Verzeichnis var/app konnte nicht angelegt werden.');
        }
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($this->filePath, $json) === false) {
            throw new \RuntimeException('integration_settings.json konnte nicht geschrieben werden.');
        }
        @chmod($this->filePath, 0660);
    }
}
