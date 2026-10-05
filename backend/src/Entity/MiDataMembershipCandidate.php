<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Unverified MiData membership candidate taken from the login userinfo, used only for the search mode.
 *
 * A candidate authorizes nothing and is never an onboarding offer. Selecting it starts a fresh MiData
 * login that verifies role and structure before a {@see MiDataDepartmentOnboarding} offer can exist.
 */
#[ORM\Entity(repositoryClass: \App\Repository\MiDataMembershipCandidateRepository::class)]
#[ORM\Table(name: 'midata_membership_candidate')]
#[ORM\UniqueConstraint(
    name: 'uniq_midata_candidate_user_group',
    columns: ['user_id', 'provider', 'external_group_id']
)]
#[ORM\HasLifecycleCallbacks]
class MiDataMembershipCandidate
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

    #[ORM\Column(type: 'string', length: 32)]
    private string $provider = 'midata';

    #[ORM\Column(name: 'external_group_id', type: 'string', length: 255)]
    private string $externalGroupId = '';

    #[ORM\Column(name: 'external_role_class', type: 'string', length: 128)]
    private string $externalRoleClass = '';

    #[ORM\Column(name: 'display_name', type: 'string', length: 255)]
    private string $displayName = '';

    #[ORM\Column(name: 'expires_at', type: 'datetime')]
    private \DateTime $expiresAt;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->expiresAt = new \DateTime();
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

    public function getExternalGroupId(): string
    {
        return $this->externalGroupId;
    }

    public function setExternalGroupId(string $externalGroupId): self
    {
        $this->externalGroupId = $externalGroupId;

        return $this;
    }

    public function getExternalRoleClass(): string
    {
        return $this->externalRoleClass;
    }

    public function setExternalRoleClass(string $externalRoleClass): self
    {
        $this->externalRoleClass = $externalRoleClass;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getExpiresAt(): \DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTime $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function isExpired(\DateTimeInterface $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
