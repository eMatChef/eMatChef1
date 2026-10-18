<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Importierter externer Datensatz (z. B. eine Buchung) mit letztem bekannten Zustand. Eindeutig je Quelle und
 * externem Schlüssel; Änderungen stehen in {@see DepartmentGrossanlassExternalChange}.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_ext_record')]
class DepartmentGrossanlassExternalRecord
{
    public const STATE_ACTIVE = 'active';
    public const STATE_CANCELLED = 'cancelled';
    /** In einer nachweislich vollständigen Antwort nicht mehr enthalten. */
    public const STATE_REMOVED = 'removed';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'source_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $sourceId;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\Column(name: 'external_key', type: 'string', length: 190)]
    private string $externalKey;

    #[ORM\Column(type: 'string', length: 12, options: ['default' => self::STATE_ACTIVE])]
    private string $state = self::STATE_ACTIVE;

    /** @var array<string, mixed> Normalisierte, gemappte Werte (Ziel-Felder), nicht das Rohobjekt. */
    #[ORM\Column(type: 'json')]
    private array $data = [];

    #[ORM\Column(name: 'content_hash', type: 'string', length: 64, columnDefinition: 'CHAR(64) NOT NULL')]
    private string $contentHash = '';

    #[ORM\Column(name: 'payload_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $payloadId = null;

    #[ORM\Column(name: 'helper_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $helperId = null;

    #[ORM\Column(name: 'first_imported_at', type: 'datetime')]
    private \DateTime $firstImportedAt;

    #[ORM\Column(name: 'last_synced_at', type: 'datetime')]
    private \DateTime $lastSyncedAt;

    public function __construct(string $id, string $sourceId, string $departmentId, string $externalKey)
    {
        $this->id = $id;
        $this->sourceId = $sourceId;
        $this->departmentId = $departmentId;
        $this->externalKey = $externalKey;
        $this->firstImportedAt = new \DateTime();
        $this->lastSyncedAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function getSourceId(): string { return $this->sourceId; }
    public function getDepartmentId(): string { return $this->departmentId; }
    public function getExternalKey(): string { return $this->externalKey; }
    public function getState(): string { return $this->state; }
    public function setState(string $v): self { $this->state = $v; return $this; }
    /** @return array<string, mixed> */
    public function getData(): array { return $this->data; }
    /** @param array<string, mixed> $v */
    public function setData(array $v, string $hash): self { $this->data = $v; $this->contentHash = $hash; return $this; }
    public function getContentHash(): string { return $this->contentHash; }
    public function getPayloadId(): ?string { return $this->payloadId; }
    public function setPayloadId(?string $v): self { $this->payloadId = $v; return $this; }
    public function getHelperId(): ?string { return $this->helperId; }
    public function setHelperId(?string $v): self { $this->helperId = $v; return $this; }
    public function getFirstImportedAt(): \DateTime { return $this->firstImportedAt; }
    public function getLastSyncedAt(): \DateTime { return $this->lastSyncedAt; }
    public function touchSynced(): self { $this->lastSyncedAt = new \DateTime(); return $this; }
}
