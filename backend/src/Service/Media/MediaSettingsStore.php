<?php

declare(strict_types=1);

namespace App\Service\Media;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Medien-Einstellungen (var/app/media_settings.json, nicht in Git).
 *
 * Schema:
 * {
 *   "retention_years": 10,
 *   "compression_enabled": true,
 *   "updated_at": "2026-05-30T12:00:00+02:00"
 * }
 */
final class MediaSettingsStore
{
    public const RETENTION_YEARS_DEFAULT = 10;
    public const RETENTION_YEARS_MIN = 1;
    public const RETENTION_YEARS_MAX = 20;

    private string $filePath;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
        $this->filePath = $this->projectDir . '/var/app/media_settings.json';
    }

    public function isCompressionEnabled(): bool
    {
        $data = $this->readFile();
        if (!isset($data['compression_enabled'])) {
            return true;
        }

        return (bool) $data['compression_enabled'];
    }

    public function getRetentionYears(): int
    {
        $data = $this->readFile();
        $raw = $data['retention_years'] ?? null;
        $value = is_numeric($raw) ? (int) $raw : self::RETENTION_YEARS_DEFAULT;

        return max(self::RETENTION_YEARS_MIN, min(self::RETENTION_YEARS_MAX, $value));
    }

    /** @return array<string, mixed>|null */
    private function readFile(): ?array
    {
        if (!is_file($this->filePath)) {
            return null;
        }

        $json = file_get_contents($this->filePath);
        if ($json === false || trim($json) === '') {
            return null;
        }

        $data = json_decode($json, true);

        return \is_array($data) ? $data : null;
    }
}
