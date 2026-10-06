<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Einmal verwendbarer Recovery Code zum TOTP. Nur der Hash wird gespeichert. */
#[ORM\Entity(repositoryClass: \App\Repository\UserRecoveryCodeRepository::class)]
#[ORM\Table(name: 'user_recovery_code')]
#[ORM\Index(name: 'idx_user_recovery_code_user', columns: ['user_id'])]
class UserRecoveryCode
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'code_hash', type: 'string', length: 64)]
    private string $codeHash;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'used_at', type: 'datetime', nullable: true)]
    private ?\DateTime $usedAt = null;

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

    public function getCodeHash(): string
    {
        return $this->codeHash;
    }

    public function setCodeHash(string $codeHash): self
    {
        $this->codeHash = $codeHash;

        return $this;
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }

    public function markUsed(): self
    {
        $this->usedAt = new \DateTime();

        return $this;
    }
}
