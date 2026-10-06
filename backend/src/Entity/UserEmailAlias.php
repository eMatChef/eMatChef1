<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Zusätzliche E-Mail-Adresse eines Users (Primary bleibt Profile.email).
 * Erst mit verified_at gilt sie als bewiesen; nur verifizierte, login_enabled Adressen dienen dem Login
 * und dem Start eines Passwort-Resets.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_email_alias')]
#[ORM\UniqueConstraint(name: 'uniq_user_email_alias_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_user_email_alias_verification_token', columns: ['verification_token_hash'])]
class UserEmailAlias
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, options: ['fixed' => true])]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'user_id', type: 'string', length: 12, options: ['fixed' => true])]
    private string $userId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 180)]
    private string $email = '';

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'verified_at', type: 'datetime', nullable: true)]
    private ?\DateTime $verifiedAt = null;

    #[ORM\Column(name: 'login_enabled', type: 'boolean', options: ['default' => true])]
    private bool $loginEnabled = true;

    /** sha256 des Bestätigungs-Tokens; Klartext nur im Link der Bestätigungsmail. */
    #[ORM\Column(name: 'verification_token_hash', type: 'string', length: 64, nullable: true)]
    private ?string $verificationTokenHash = null;

    #[ORM\Column(name: 'verification_expires_at', type: 'datetime', nullable: true)]
    private ?\DateTime $verificationExpiresAt = null;

    #[ORM\Column(name: 'verification_sent_at', type: 'datetime', nullable: true)]
    private ?\DateTime $verificationSentAt = null;

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

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        $this->userId = $user->getId();

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getVerifiedAt(): ?\DateTime
    {
        return $this->verifiedAt;
    }

    public function isVerified(): bool
    {
        return $this->verifiedAt !== null;
    }

    public function markVerified(): self
    {
        $this->verifiedAt ??= new \DateTime();
        $this->verificationTokenHash = null;
        $this->verificationExpiresAt = null;

        return $this;
    }

    /** Adresse gilt wieder als unbestätigt (z. B. bisher unbestätigte Primary nach Primary-Wechsel). */
    public function resetVerification(): self
    {
        $this->verifiedAt = null;

        return $this;
    }

    public function isLoginEnabled(): bool
    {
        return $this->loginEnabled;
    }

    public function setLoginEnabled(bool $loginEnabled): self
    {
        $this->loginEnabled = $loginEnabled;

        return $this;
    }

    /** Verifiziert und für Login/Passwort-Reset freigegeben. */
    public function canLogin(): bool
    {
        return $this->isVerified() && $this->loginEnabled;
    }

    public function getVerificationExpiresAt(): ?\DateTime
    {
        return $this->verificationExpiresAt;
    }

    public function getVerificationSentAt(): ?\DateTime
    {
        return $this->verificationSentAt;
    }

    public function setVerificationToken(string $tokenHash, \DateTime $expiresAt): self
    {
        $this->verificationTokenHash = $tokenHash;
        $this->verificationExpiresAt = $expiresAt;
        $this->verificationSentAt = new \DateTime();

        return $this;
    }

    /** Unbestätigte Adresse mit abgelaufenem Link blockiert die Adresse nicht mehr. */
    public function isExpiredUnverified(?\DateTime $now = null): bool
    {
        return !$this->isVerified()
            && ($this->verificationExpiresAt === null || $this->verificationExpiresAt < ($now ?? new \DateTime()));
    }
}
