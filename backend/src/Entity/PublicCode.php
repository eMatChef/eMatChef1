<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'public_code')]
#[ORM\Index(name: 'idx_public_code_entity', columns: ['entity_type', 'entity_id'])]
#[ORM\Index(name: 'idx_public_code_department_type', columns: ['department_id', 'entity_type'])]
class PublicCode
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'entity_type', type: 'string', length: 32)]
    private string $entityType;

    #[ORM\Column(name: 'entity_id', type: 'string', length: 20)]
    private string $entityId;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\Column(name: 'public_code', type: 'string', length: 64, unique: true)]
    private string $publicCode;

    #[ORM\Column(name: 'is_public', type: 'boolean', options: ['default' => true])]
    private bool $isPublic = true;

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $createdByUserId = null;

    #[ORM\Column(name: 'revoked_at', type: 'datetime', nullable: true)]
    private ?\DateTime $revokedAt = null;

    #[ORM\Column(name: 'expires_at', type: 'datetime', nullable: true)]
    private ?\DateTime $expiresAt = null;

    #[ORM\Column(name: 'last_scanned_at', type: 'datetime', nullable: true)]
    private ?\DateTime $lastScannedAt = null;

    #[ORM\Column(name: 'scan_count', type: 'integer', options: ['default' => 0])]
    private int $scanCount = 0;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): self
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getDepartmentId(): string
    {
        return $this->departmentId;
    }

    public function setDepartmentId(string $departmentId): self
    {
        $this->departmentId = $departmentId;
        return $this;
    }

    public function getPublicCode(): string
    {
        return $this->publicCode;
    }

    public function setPublicCode(string $publicCode): self
    {
        $this->publicCode = $publicCode;
        return $this;
    }

    public function getIsPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): self
    {
        $this->isPublic = $isPublic;
        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getCreatedByUserId(): ?string
    {
        return $this->createdByUserId;
    }

    public function setCreatedByUserId(?string $createdByUserId): self
    {
        $this->createdByUserId = $createdByUserId;
        return $this;
    }

    public function getRevokedAt(): ?\DateTime
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTime $revokedAt): self
    {
        $this->revokedAt = $revokedAt;
        return $this;
    }

    public function getExpiresAt(): ?\DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTime $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getLastScannedAt(): ?\DateTime
    {
        return $this->lastScannedAt;
    }

    public function setLastScannedAt(?\DateTime $lastScannedAt): self
    {
        $this->lastScannedAt = $lastScannedAt;
        return $this;
    }

    public function getScanCount(): int
    {
        return $this->scanCount;
    }

    public function setScanCount(int $scanCount): self
    {
        $this->scanCount = $scanCount;
        return $this;
    }
}

