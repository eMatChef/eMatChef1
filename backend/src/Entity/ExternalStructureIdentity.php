<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\ExternalStructureIdentityRepository::class)]
#[ORM\Table(name: 'external_structure_identity')]
#[ORM\UniqueConstraint(
    name: 'uniq_external_structure_provider_group',
    columns: ['provider', 'external_group_id']
)]
#[ORM\Index(name: 'idx_external_structure_department', columns: ['department_id'])]
#[ORM\Index(name: 'idx_external_structure_group', columns: ['group_id'])]
#[ORM\Index(name: 'idx_external_structure_organisation', columns: ['organisation_id'])]
#[ORM\HasLifecycleCallbacks]
class ExternalStructureIdentity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, options: ['fixed' => true])]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(type: 'string', length: 32)]
    private string $provider = '';

    #[ORM\Column(name: 'external_group_id', type: 'string', length: 255)]
    private string $externalGroupId = '';

    #[ORM\Column(name: 'external_type', type: 'string', length: 128, nullable: true)]
    private ?string $externalType = null;

    #[ORM\Column(name: 'external_name', type: 'string', length: 255, nullable: true)]
    private ?string $externalName = null;

    #[ORM\Column(name: 'external_parent_id', type: 'string', length: 255, nullable: true)]
    private ?string $externalParentId = null;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Department $department = null;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Group $group = null;

    #[ORM\ManyToOne(targetEntity: Organisation::class)]
    #[ORM\JoinColumn(name: 'organisation_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Organisation $organisation = null;

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

    public function getExternalType(): ?string
    {
        return $this->externalType;
    }

    public function setExternalType(?string $externalType): self
    {
        $this->externalType = $externalType;

        return $this;
    }

    public function getExternalName(): ?string
    {
        return $this->externalName;
    }

    public function setExternalName(?string $externalName): self
    {
        $this->externalName = $externalName;

        return $this;
    }

    public function getExternalParentId(): ?string
    {
        return $this->externalParentId;
    }

    public function setExternalParentId(?string $externalParentId): self
    {
        $this->externalParentId = $externalParentId;

        return $this;
    }

    public function getDepartment(): ?Department
    {
        return $this->department;
    }

    public function setDepartment(?Department $department): self
    {
        $this->department = $department;

        return $this;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function getOrganisation(): ?Organisation
    {
        return $this->organisation;
    }

    public function setOrganisation(?Organisation $organisation): self
    {
        $this->organisation = $organisation;

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

    public function hasExactlyOneInternalTarget(): bool
    {
        $targetCount = (int) ($this->department !== null)
            + (int) ($this->group !== null)
            + (int) ($this->organisation !== null);

        return $targetCount === 1;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function assertExactlyOneInternalTarget(): void
    {
        if (!$this->hasExactlyOneInternalTarget()) {
            throw new \LogicException('External structure identity must target exactly one Organisation, Department, or Group');
        }
    }
}
