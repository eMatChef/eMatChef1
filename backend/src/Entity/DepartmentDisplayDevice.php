<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Eigenständiges Anzeigegerät (z. B. ein Fernseher) eines Infoscreens. Geheimnisse nur als SHA-256-Hash.
 * Geräteidentität (Credential) und zeitlich begrenzte Freigabe (approvalExpiresAt) sind getrennt.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_display_device')]
class DepartmentDisplayDevice
{
    public const VIA_PAIRING = 'pairing';
    public const VIA_MANUAL = 'manual';
    public const VIA_MIGRATED = 'migrated';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'screen_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $screenId;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    #[ORM\Column(name: 'credential_hash', type: 'string', length: 64, columnDefinition: 'CHARACTER(64) NOT NULL')]
    private string $credentialHash;

    #[ORM\Column(name: 'previous_credential_hash', type: 'string', length: 64, nullable: true, columnDefinition: 'CHARACTER(64) NULL')]
    private ?string $previousCredentialHash = null;

    #[ORM\Column(name: 'credential_rotated_at', type: 'datetime')]
    private \DateTimeInterface $credentialRotatedAt;

    #[ORM\Column(name: 'created_via', type: 'string', length: 16)]
    private string $createdVia = self::VIA_PAIRING;

    #[ORM\Column(name: 'approval_expires_at', type: 'datetime')]
    private \DateTimeInterface $approvalExpiresAt;

    #[ORM\Column(name: 'approved_at', type: 'datetime')]
    private \DateTimeInterface $approvedAt;

    #[ORM\Column(name: 'last_contact_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastContactAt = null;

    #[ORM\Column(name: 'revoked_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $revokedAt = null;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $createdByUserId = null;

    /** Ablaufdatum, für das die 14-Tage-Erinnerung bereits verschickt wurde. */
    #[ORM\Column(name: 'reminder14_for', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $reminder14For = null;

    /** Ablaufdatum, für das die 3-Tage-Erinnerung bereits verschickt wurde. */
    #[ORM\Column(name: 'reminder3_for', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $reminder3For = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $now = new \DateTime();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->approvedAt = $now;
        $this->credentialRotatedAt = $now;
        $this->approvalExpiresAt = $now;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getScreenId(): string
    {
        return $this->screenId;
    }

    public function setScreenId(string $screenId): self
    {
        $this->screenId = $screenId;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getCredentialHash(): string
    {
        return $this->credentialHash;
    }

    public function setCredentialHash(string $credentialHash): self
    {
        $this->credentialHash = $credentialHash;

        return $this;
    }

    public function getPreviousCredentialHash(): ?string
    {
        return $this->previousCredentialHash;
    }

    public function setPreviousCredentialHash(?string $previousCredentialHash): self
    {
        $this->previousCredentialHash = $previousCredentialHash;

        return $this;
    }

    public function getCredentialRotatedAt(): \DateTimeInterface
    {
        return $this->credentialRotatedAt;
    }

    public function setCredentialRotatedAt(\DateTimeInterface $credentialRotatedAt): self
    {
        $this->credentialRotatedAt = $credentialRotatedAt;

        return $this;
    }

    public function getCreatedVia(): string
    {
        return $this->createdVia;
    }

    public function setCreatedVia(string $createdVia): self
    {
        $this->createdVia = $createdVia;

        return $this;
    }

    public function getApprovalExpiresAt(): \DateTimeInterface
    {
        return $this->approvalExpiresAt;
    }

    public function setApprovalExpiresAt(\DateTimeInterface $approvalExpiresAt): self
    {
        $this->approvalExpiresAt = $approvalExpiresAt;

        return $this;
    }

    public function getApprovedAt(): \DateTimeInterface
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(\DateTimeInterface $approvedAt): self
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    public function getLastContactAt(): ?\DateTimeInterface
    {
        return $this->lastContactAt;
    }

    public function setLastContactAt(?\DateTimeInterface $lastContactAt): self
    {
        $this->lastContactAt = $lastContactAt;

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeInterface
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTimeInterface $revokedAt): self
    {
        $this->revokedAt = $revokedAt;

        return $this;
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

    public function getReminder14For(): ?\DateTimeInterface
    {
        return $this->reminder14For;
    }

    public function setReminder14For(?\DateTimeInterface $reminder14For): self
    {
        $this->reminder14For = $reminder14For;

        return $this;
    }

    public function getReminder3For(): ?\DateTimeInterface
    {
        return $this->reminder3For;
    }

    public function setReminder3For(?\DateTimeInterface $reminder3For): self
    {
        $this->reminder3For = $reminder3For;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isApprovalExpired(?\DateTimeInterface $now = null): bool
    {
        return $this->approvalExpiresAt <= ($now ?? new \DateTime());
    }
}
