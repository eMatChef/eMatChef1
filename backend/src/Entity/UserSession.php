<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AuthMethod;
use Doctrine\ORM\Mapping as ORM;

/**
 * Serverseitige logische Login-Sitzung. Die ID ist die `sid` im JWT; Refresh-Tokens hängen daran.
 * Ein widerrufener Datensatz macht JWT und Refresh-Tokens der Sitzung sofort ungültig.
 */
#[ORM\Entity(repositoryClass: \App\Repository\UserSessionRepository::class)]
#[ORM\Table(name: 'user_session')]
#[ORM\Index(name: 'idx_user_session_user', columns: ['user_id', 'revoked_at'])]
class UserSession
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'auth_method', type: 'string', length: 32, enumType: AuthMethod::class)]
    private AuthMethod $authMethod;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'last_seen_at', type: 'datetime')]
    private \DateTime $lastSeenAt;

    /** Roh-User-Agent (gekürzt), nur für spätere Geräteanzeige; kein Fingerprinting. */
    #[ORM\Column(name: 'user_agent', type: 'string', length: 255, nullable: true)]
    private ?string $userAgent;

    #[ORM\Column(name: 'mfa_verified_at', type: 'datetime', nullable: true)]
    private ?\DateTime $mfaVerifiedAt = null;

    #[ORM\Column(name: 'step_up_at', type: 'datetime', nullable: true)]
    private ?\DateTime $stepUpAt = null;

    #[ORM\Column(name: 'revoked_at', type: 'datetime', nullable: true)]
    private ?\DateTime $revokedAt = null;

    #[ORM\Column(name: 'revoked_reason', type: 'string', length: 32, nullable: true)]
    private ?string $revokedReason = null;

    public function __construct(User $user, AuthMethod $authMethod, ?string $userAgent = null)
    {
        $this->id = self::generateId();
        $this->user = $user;
        $this->authMethod = $authMethod;
        $this->createdAt = new \DateTime();
        $this->lastSeenAt = new \DateTime();
        $userAgent = $userAgent !== null ? trim($userAgent) : '';
        $this->userAgent = $userAgent !== '' ? mb_substr($userAgent, 0, 255) : null;
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

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getLastSeenAt(): \DateTime
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(\DateTime $lastSeenAt): self
    {
        $this->lastSeenAt = $lastSeenAt;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    /** Beim Login mit bestandener MFA-Challenge gesetzt; gilt nur für diese Sitzung. */
    public function markMfaVerified(): self
    {
        $this->mfaVerifiedAt = new \DateTime();

        return $this;
    }

    public function getMfaVerifiedAt(): ?\DateTime
    {
        return $this->mfaVerifiedAt;
    }

    public function getStepUpAt(): ?\DateTime
    {
        return $this->stepUpAt;
    }

    public function getRevokedAt(): ?\DateTime
    {
        return $this->revokedAt;
    }

    public function getRevokedReason(): ?string
    {
        return $this->revokedReason;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function revoke(string $reason): self
    {
        if ($this->revokedAt === null) {
            $this->revokedAt = new \DateTime();
            $this->revokedReason = $reason;
        }

        return $this;
    }

    /** UUID v4 (RFC 4122) ohne zusätzliche Abhängigkeit. */
    private static function generateId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
