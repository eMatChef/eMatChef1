<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * TOTP-Faktor eines Users (RFC 6238). Das Secret liegt nur verschlüsselt (SecretBox) vor und verlässt
 * den Server ausschließlich in der Antwort auf "Einrichtung starten".
 *
 * Aktiv ist TOTP erst, wenn ein erster Code bestätigt wurde (activated_at). Eine Neueinrichtung läuft über
 * pending_secret_encrypted; das bisherige Secret bleibt bis zur erfolgreichen Bestätigung gültig.
 */
#[ORM\Entity(repositoryClass: \App\Repository\UserTotpRepository::class)]
#[ORM\Table(name: 'user_totp')]
#[ORM\UniqueConstraint(name: 'uniq_user_totp_user', columns: ['user_id'])]
class UserTotp
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'secret_encrypted', type: 'text', nullable: true)]
    private ?string $secretEncrypted = null;

    #[ORM\Column(name: 'pending_secret_encrypted', type: 'text', nullable: true)]
    private ?string $pendingSecretEncrypted = null;

    #[ORM\Column(name: 'pending_expires_at', type: 'datetime', nullable: true)]
    private ?\DateTime $pendingExpiresAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'activated_at', type: 'datetime', nullable: true)]
    private ?\DateTime $activatedAt = null;

    /** Zeitschritt des zuletzt akzeptierten Codes (Replay-Schutz). */
    #[ORM\Column(name: 'last_used_step', type: 'bigint', nullable: true)]
    private ?int $lastUsedStep = null;

    #[ORM\Column(name: 'failed_attempts', type: 'integer', options: ['default' => 0])]
    private int $failedAttempts = 0;

    #[ORM\Column(name: 'locked_until', type: 'datetime', nullable: true)]
    private ?\DateTime $lockedUntil = null;

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

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getSecretEncrypted(): ?string
    {
        return $this->secretEncrypted;
    }

    public function getPendingSecretEncrypted(): ?string
    {
        return $this->pendingSecretEncrypted;
    }

    public function getPendingExpiresAt(): ?\DateTime
    {
        return $this->pendingExpiresAt;
    }

    public function getActivatedAt(): ?\DateTime
    {
        return $this->activatedAt;
    }

    public function isActive(): bool
    {
        return $this->activatedAt !== null && $this->secretEncrypted !== null;
    }

    public function hasPendingEnrollment(?\DateTime $now = null): bool
    {
        return $this->pendingSecretEncrypted !== null
            && $this->pendingExpiresAt !== null
            && $this->pendingExpiresAt > ($now ?? new \DateTime());
    }

    public function startEnrollment(string $encryptedSecret, \DateTime $expiresAt): self
    {
        $this->pendingSecretEncrypted = $encryptedSecret;
        $this->pendingExpiresAt = $expiresAt;

        return $this;
    }

    /**
     * Pending-Secret wird das aktive Secret; ein früheres Secret ist damit ungültig.
     * $step = Zeitschritt, der als verbraucht gilt. Die Bestätigung bei der Einrichtung übergibt keinen:
     * sie beweist nur den Besitz und soll den ersten Login im selben Zeitfenster nicht blockieren.
     */
    public function activatePending(?int $step = null): self
    {
        $this->secretEncrypted = $this->pendingSecretEncrypted;
        $this->pendingSecretEncrypted = null;
        $this->pendingExpiresAt = null;
        $this->activatedAt = new \DateTime();
        $this->lastUsedStep = $step;
        $this->resetFailures();

        return $this;
    }

    public function clearPending(): self
    {
        $this->pendingSecretEncrypted = null;
        $this->pendingExpiresAt = null;

        return $this;
    }

    public function getLastUsedStep(): ?int
    {
        return $this->lastUsedStep;
    }

    public function setLastUsedStep(int $step): self
    {
        $this->lastUsedStep = $step;

        return $this;
    }

    public function isLocked(?\DateTime $now = null): bool
    {
        return $this->lockedUntil !== null && $this->lockedUntil > ($now ?? new \DateTime());
    }

    public function registerFailure(int $maxAttempts, \DateTime $lockUntil): self
    {
        ++$this->failedAttempts;
        if ($this->failedAttempts >= $maxAttempts) {
            $this->lockedUntil = $lockUntil;
            $this->failedAttempts = 0;
        }

        return $this;
    }

    public function resetFailures(): self
    {
        $this->failedAttempts = 0;
        $this->lockedUntil = null;

        return $this;
    }
}
