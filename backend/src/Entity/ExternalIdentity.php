<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\ExternalIdentityRepository::class)]
#[ORM\Table(name: 'external_identity')]
#[ORM\UniqueConstraint(name: 'uniq_external_identity_provider_external_user_id', columns: ['provider', 'external_user_id'])]
class ExternalIdentity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, options: ['fixed' => true])]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'user_id', type: 'string', length: 12, options: ['fixed' => true])]
    private string $userId;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'externalIdentities')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 32)]
    private string $provider = '';

    #[ORM\Column(name: 'external_user_id', type: 'string', length: 255)]
    private string $externalUserId = '';

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $email = null;

    /** Anzeigename laut Anbieter (nur Anzeige in Profil → Sicherheit, nie für Zuordnung/Login). */
    #[ORM\Column(name: 'display_name', type: 'string', length: 180, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getExternalUserId(): string
    {
        return $this->externalUserId;
    }

    public function setExternalUserId(string $externalUserId): self
    {
        $this->externalUserId = $externalUserId;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): self
    {
        $displayName = $displayName !== null ? trim($displayName) : null;
        $this->displayName = $displayName !== null && $displayName !== '' ? mb_substr($displayName, 0, 180) : null;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
