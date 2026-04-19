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
