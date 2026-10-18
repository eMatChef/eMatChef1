<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Externe Datenquelle (JSON-API) eines Grossanlasses, z. B. ein fremder Helferpool. Der Zugangsschlüssel liegt nur
 * verschlüsselt in `secret_enc` und verlässt das Backend nie. `mapping` hält die frei konfigurierte Feldzuordnung.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_ext_source')]
class DepartmentGrossanlassExternalSource
{
    public const AUTH_NONE = 'none';
    public const AUTH_BEARER = 'bearer';
    public const AUTH_HEADER = 'header';
    public const AUTH_BASIC = 'basic';
    public const AUTH_TYPES = [self::AUTH_NONE, self::AUTH_BEARER, self::AUTH_HEADER, self::AUTH_BASIC];

    public const STATUS_NEVER = 'never';
    public const STATUS_OK = 'ok';
    public const STATUS_ERROR = 'error';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name = '';

    #[ORM\Column(name: 'base_url', type: 'string', length: 500, options: ['default' => ''])]
    private string $baseUrl = '';

    #[ORM\Column(name: 'endpoint_path', type: 'string', length: 300, options: ['default' => ''])]
    private string $endpointPath = '';

    #[ORM\Column(name: 'auth_type', type: 'string', length: 12, options: ['default' => self::AUTH_NONE])]
    private string $authType = self::AUTH_NONE;

    /** Headername bei `header`-Authentifizierung (z. B. X-API-Key). */
    #[ORM\Column(name: 'auth_header', type: 'string', length: 80, nullable: true)]
    private ?string $authHeader = null;

    /** Benutzername bei `basic`. */
    #[ORM\Column(name: 'auth_user', type: 'string', length: 180, nullable: true)]
    private ?string $authUser = null;

    #[ORM\Column(name: 'secret_enc', type: 'text', nullable: true)]
    private ?string $secretEnc = null;

    /** @var array<string, mixed> records_path, fields (Ziel => JSON-Pfad), cancelled_values, total_path, next_path */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $mapping = [];

    /** Der Benutzer bestätigt: ein Abruf liefert alle Datensätze (Voraussetzung, um fehlende als entfernt zu behandeln). */
    #[ORM\Column(name: 'snapshot_complete', type: 'boolean', options: ['default' => false])]
    private bool $snapshotComplete = false;

    #[ORM\Column(name: 'connection_status', type: 'string', length: 12, options: ['default' => self::STATUS_NEVER])]
    private string $connectionStatus = self::STATUS_NEVER;

    #[ORM\Column(name: 'last_tested_at', type: 'datetime', nullable: true)]
    private ?\DateTime $lastTestedAt = null;

    #[ORM\Column(name: 'last_error', type: 'string', length: 500, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(name: 'last_success_sync_at', type: 'datetime', nullable: true)]
    private ?\DateTime $lastSuccessSyncAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct(string $id, string $departmentId)
    {
        $this->id = $id;
        $this->departmentId = $departmentId;
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function getDepartmentId(): string { return $this->departmentId; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this->touch(); }
    public function getBaseUrl(): string { return $this->baseUrl; }
    public function setBaseUrl(string $v): self { $this->baseUrl = $v; return $this->touch(); }
    public function getEndpointPath(): string { return $this->endpointPath; }
    public function setEndpointPath(string $v): self { $this->endpointPath = $v; return $this->touch(); }
    public function getAuthType(): string { return $this->authType; }
    public function setAuthType(string $v): self { $this->authType = $v; return $this->touch(); }
    public function getAuthHeader(): ?string { return $this->authHeader; }
    public function setAuthHeader(?string $v): self { $this->authHeader = $v; return $this->touch(); }
    public function getAuthUser(): ?string { return $this->authUser; }
    public function setAuthUser(?string $v): self { $this->authUser = $v; return $this->touch(); }
    public function getSecretEnc(): ?string { return $this->secretEnc; }
    public function hasSecret(): bool { return $this->secretEnc !== null && $this->secretEnc !== ''; }
    public function setSecretEnc(?string $v): self { $this->secretEnc = $v; return $this->touch(); }
    /** @return array<string, mixed> */
    public function getMapping(): array { return $this->mapping; }
    /** @param array<string, mixed> $v */
    public function setMapping(array $v): self { $this->mapping = $v; return $this->touch(); }
    public function isSnapshotComplete(): bool { return $this->snapshotComplete; }
    public function setSnapshotComplete(bool $v): self { $this->snapshotComplete = $v; return $this->touch(); }
    public function getConnectionStatus(): string { return $this->connectionStatus; }
    public function getLastTestedAt(): ?\DateTime { return $this->lastTestedAt; }
    public function getLastError(): ?string { return $this->lastError; }
    public function getLastSuccessSyncAt(): ?\DateTime { return $this->lastSuccessSyncAt; }
    public function markSyncSuccess(): self { $this->lastSuccessSyncAt = new \DateTime(); return $this->touch(); }
    public function hasUrl(): bool { return $this->baseUrl !== ''; }

    public function recordConnection(bool $ok, ?string $error): self
    {
        $this->connectionStatus = $ok ? self::STATUS_OK : self::STATUS_ERROR;
        $this->lastTestedAt = new \DateTime();
        $this->lastError = $ok || $error === null ? null : mb_substr($error, 0, 500);

        return $this->touch();
    }

    private function touch(): self
    {
        $this->updatedAt = new \DateTime();

        return $this;
    }
}
