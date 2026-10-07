<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AuthMethod;
use Doctrine\ORM\Mapping as ORM;

/**
 * Kurzlebige, einmalige MFA-Challenge nach erfolgreicher primärer Authentifizierung.
 * Sie gewährt keinerlei API-Zugriff; erst die erfolgreiche Verifikation erzeugt Sitzung und Tokens.
 * Gespeichert wird nur der Hash des Challenge-Tokens.
 */
#[ORM\Entity(repositoryClass: \App\Repository\MfaChallengeRepository::class)]
#[ORM\Table(name: 'mfa_challenge')]
#[ORM\UniqueConstraint(name: 'uniq_mfa_challenge_token', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_mfa_challenge_user', columns: ['user_id'])]
class MfaChallenge
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'token_hash', type: 'string', length: 64)]
    private string $tokenHash;

    /** Primärer Loginweg, an den die Challenge gebunden ist (wird zur Methode der neuen Sitzung). */
    #[ORM\Column(name: 'auth_method', type: 'string', length: 32, enumType: AuthMethod::class)]
    private AuthMethod $authMethod;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'expires_at', type: 'datetime')]
    private \DateTime $expiresAt;

    #[ORM\Column(name: 'used_at', type: 'datetime', nullable: true)]
    private ?\DateTime $usedAt = null;

    #[ORM\Column(name: 'failed_attempts', type: 'integer', options: ['default' => 0])]
    private int $failedAttempts = 0;

    public function __construct(string $id, User $user, string $tokenHash, AuthMethod $authMethod, \DateTime $expiresAt)
    {
        $this->id = $id;
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->authMethod = $authMethod;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTime();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getAuthMethod(): AuthMethod
    {
        return $this->authMethod;
    }

    public function getExpiresAt(): \DateTime
    {
        return $this->expiresAt;
    }

    public function isUsable(?\DateTime $now = null): bool
    {
        return $this->usedAt === null && $this->expiresAt > ($now ?? new \DateTime());
    }

    public function markUsed(): self
    {
        $this->usedAt = new \DateTime();

        return $this;
    }

    public function registerFailure(): int
    {
        return ++$this->failedAttempts;
    }
}
