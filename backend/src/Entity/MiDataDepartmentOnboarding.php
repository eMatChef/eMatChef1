<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Server-side offer to set up one MiData department for one eMatChef user.
 *
 * The record only scopes the offer. Names are display snapshots; the final action
 * re-validates role and structure with a fresh MiData access token. No provider tokens are stored.
 */
#[ORM\Entity(repositoryClass: \App\Repository\MiDataDepartmentOnboardingRepository::class)]
#[ORM\Table(name: 'midata_department_onboarding')]
#[ORM\UniqueConstraint(
    name: 'uniq_midata_onboarding_user_group',
    columns: ['user_id', 'provider', 'external_department_group_id']
)]
#[ORM\HasLifecycleCallbacks]
class MiDataDepartmentOnboarding
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

    #[ORM\Column(name: 'external_person_id', type: 'string', length: 255)]
    private string $externalPersonId = '';

    #[ORM\Column(name: 'external_department_group_id', type: 'string', length: 255)]
    private string $externalDepartmentGroupId = '';

    #[ORM\Column(name: 'external_role_class', type: 'string', length: 128)]
    private string $externalRoleClass = '';

    #[ORM\Column(name: 'department_name', type: 'string', length: 255)]
    private string $departmentName = '';

    /** Display only; several Region levels are joined from top to bottom. */
    #[ORM\Column(name: 'region_name', type: 'string', length: 255, nullable: true)]
    private ?string $regionName = null;

    #[ORM\Column(name: 'kantonalverband_name', type: 'string', length: 255)]
    private string $kantonalverbandName = '';

    #[ORM\Column(name: 'expires_at', type: 'datetime')]
    private \DateTime $expiresAt;

    #[ORM\Column(name: 'completed_at', type: 'datetime', nullable: true)]
    private ?\DateTime $completedAt = null;

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

    public function getExternalPersonId(): string
    {
        return $this->externalPersonId;
    }

    public function setExternalPersonId(string $externalPersonId): self
    {
        $this->externalPersonId = $externalPersonId;

        return $this;
    }

    public function getExternalDepartmentGroupId(): string
    {
        return $this->externalDepartmentGroupId;
    }

    public function setExternalDepartmentGroupId(string $externalDepartmentGroupId): self
    {
        $this->externalDepartmentGroupId = $externalDepartmentGroupId;

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

    public function getDepartmentName(): string
    {
        return $this->departmentName;
    }

    public function setDepartmentName(string $departmentName): self
    {
        $this->departmentName = $departmentName;

        return $this;
    }

    public function getRegionName(): ?string
    {
        return $this->regionName;
    }

    public function setRegionName(?string $regionName): self
    {
        $this->regionName = $regionName;

        return $this;
    }

    public function getKantonalverbandName(): string
    {
        return $this->kantonalverbandName;
    }

    public function setKantonalverbandName(string $kantonalverbandName): self
    {
        $this->kantonalverbandName = $kantonalverbandName;

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

    public function getCompletedAt(): ?\DateTime
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTime $completedAt): self
    {
        $this->completedAt = $completedAt;

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

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
