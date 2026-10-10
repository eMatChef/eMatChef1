<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Kurzlebige Kopplungsanfrage eines Fernsehers (display.-Host) an einen bestehenden Infoscreen.
 * Geheimnisse (QR-Token, Poll-Secret) liegen nur als SHA-256-Hash in der Datenbank.
 */
#[ORM\Entity]
#[ORM\Table(name: 'display_pairing_request')]
class DisplayPairingRequest
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CONSUMED = 'consumed';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'token_hash', type: 'string', length: 64, columnDefinition: 'CHARACTER(64) NOT NULL')]
    private string $tokenHash;

    #[ORM\Column(name: 'poll_secret_hash', type: 'string', length: 64, columnDefinition: 'CHARACTER(64) NOT NULL')]
    private string $pollSecretHash;

    #[ORM\Column(name: 'user_code', type: 'string', length: 8)]
    private string $userCode;

    #[ORM\Column(type: 'string', length: 16)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'screen_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $screenId = null;

    #[ORM\Column(name: 'approved_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $approvedByUserId = null;

    #[ORM\Column(name: 'device_name', type: 'string', length: 120, nullable: true)]
    private ?string $deviceName = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'expires_at', type: 'datetime')]
    private \DateTimeInterface $expiresAt;

    #[ORM\Column(name: 'approved_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $approvedAt = null;

    #[ORM\Column(name: 'consumed_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $consumedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): self
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function getPollSecretHash(): string
    {
        return $this->pollSecretHash;
    }

    public function setPollSecretHash(string $pollSecretHash): self
    {
        $this->pollSecretHash = $pollSecretHash;

        return $this;
    }

    public function getUserCode(): string
    {
        return $this->userCode;
    }

    public function setUserCode(string $userCode): self
    {
        $this->userCode = $userCode;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getScreenId(): ?string
    {
        return $this->screenId;
    }

    public function setScreenId(?string $screenId): self
    {
        $this->screenId = $screenId;

        return $this;
    }

    public function getApprovedByUserId(): ?string
    {
        return $this->approvedByUserId;
    }

    public function setApprovedByUserId(?string $approvedByUserId): self
    {
        $this->approvedByUserId = $approvedByUserId;

        return $this;
    }

    public function getDeviceName(): ?string
    {
        return $this->deviceName;
    }

    public function setDeviceName(?string $deviceName): self
    {
        $this->deviceName = $deviceName;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function isExpired(?\DateTimeInterface $now = null): bool
    {
        return $this->expiresAt <= ($now ?? new \DateTime());
    }

    public function getApprovedAt(): ?\DateTimeInterface
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeInterface $approvedAt): self
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    public function getConsumedAt(): ?\DateTimeInterface
    {
        return $this->consumedAt;
    }

    public function setConsumedAt(?\DateTimeInterface $consumedAt): self
    {
        $this->consumedAt = $consumedAt;

        return $this;
    }
}
