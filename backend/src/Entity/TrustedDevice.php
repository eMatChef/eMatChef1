<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Browser, für den der User beim Login die eMatChef-Login-MFA überspringen darf.
 * Eigener Lebenszyklus, unabhängig von UserSession. Das Credential (zufällig, kein Fingerprint) liegt nur
 * als Hash vor; der Klartext steht ausschliesslich im HttpOnly-Cookie des Browsers.
 */
#[ORM\Entity(repositoryClass: \App\Repository\TrustedDeviceRepository::class)]
#[ORM\Table(name: 'trusted_device')]
#[ORM\UniqueConstraint(name: 'uniq_trusted_device_credential', columns: ['credential_hash'])]
#[ORM\Index(name: 'idx_trusted_device_user', columns: ['user_id', 'revoked_at'])]
class TrustedDevice
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'credential_hash', type: 'string', length: 64)]
    private string $credentialHash;

    /** Anzeigename aus dem User-Agent, z. B. «Chrome auf Windows». */
    #[ORM\Column(type: 'string', length: 120)]
    private string $label;

    /** Zeitpunkt der letzten Vertrauens-Erteilung (MFA bestanden und «vertrauen» gewählt). */
    #[ORM\Column(name: 'trusted_at', type: 'datetime')]
    private \DateTime $trustedAt;

    /** Ablauf zum Zeitpunkt der Erteilung; die Prüfung nimmt zusätzlich die aktuelle Rollenregel (TrustedDevicePolicy). */
    #[ORM\Column(name: 'expires_at', type: 'datetime')]
    private \DateTime $expiresAt;

    #[ORM\Column(name: 'last_used_at', type: 'datetime', nullable: true)]
    private ?\DateTime $lastUsedAt = null;

    #[ORM\Column(name: 'revoked_at', type: 'datetime', nullable: true)]
    private ?\DateTime $revokedAt = null;

    #[ORM\Column(name: 'revoked_reason', type: 'string', length: 32, nullable: true)]
    private ?string $revokedReason = null;

    public function __construct(string $id, User $user, string $credentialHash, string $label, \DateTime $expiresAt)
    {
        $this->id = $id;
        $this->user = $user;
        $this->credentialHash = $credentialHash;
        $this->label = mb_substr($label, 0, 120);
        $this->trustedAt = new \DateTime();
        $this->expiresAt = $expiresAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getTrustedAt(): \DateTime
    {
        return $this->trustedAt;
    }

    public function getExpiresAt(): \DateTime
    {
        return $this->expiresAt;
    }

    public function getLastUsedAt(): ?\DateTime
    {
        return $this->lastUsedAt;
    }

    public function getRevokedAt(): ?\DateTime
    {
        return $this->revokedAt;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function markUsed(): self
    {
        $this->lastUsedAt = new \DateTime();

        return $this;
    }

    /** Vertrauen erneuern: neues Credential, neue Laufzeit (nur nach erfolgreicher MFA). */
    public function renew(string $credentialHash, string $label, \DateTime $expiresAt): self
    {
        $this->credentialHash = $credentialHash;
        $this->label = mb_substr($label, 0, 120);
        $this->trustedAt = new \DateTime();
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function revoke(string $reason): self
    {
        if ($this->revokedAt === null) {
            $this->revokedAt = new \DateTime();
            $this->revokedReason = $reason;
        }

        return $this;
    }

    /**
     * Gültig bis zum früheren von gespeichertem Ablauf und «Erteilung + aktuelle Rollen-Dauer»:
     * wird ein User später Admin, gilt sofort die kürzere Admin-Frist ab der Erteilung.
     */
    public function effectiveExpiry(int $policyDays): \DateTime
    {
        $byPolicy = (clone $this->trustedAt)->modify('+' . $policyDays . ' days');

        return min($this->expiresAt, $byPolicy);
    }

    public function isUsable(int $policyDays, ?\DateTime $now = null): bool
    {
        return !$this->isRevoked() && $this->effectiveExpiry($policyDays) > ($now ?? new \DateTime());
    }
}
