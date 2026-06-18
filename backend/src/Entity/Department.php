<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department')]
class Department
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null; // Nullable für automatische ID-Generierung

    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $organisationId;

    #[ORM\ManyToOne(targetEntity: Organisation::class, inversedBy: 'departments')]
    #[ORM\JoinColumn(name: 'organisation_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Organisation $organisation;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(name: 'parent_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $parentId = null;

    #[ORM\ManyToOne(targetEntity: Department::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Department $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: Department::class)]
    private Collection $children;

    // Rechnungsadresse
    #[ORM\Column(name: 'billing_address_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $billingAddressId = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(name: 'billing_address_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Address $billingAddress = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    #[ORM\OneToMany(mappedBy: 'department', targetEntity: Membership::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $memberships;

    #[ORM\Column(name: 'is_grossanlass', type: 'boolean', options: ['default' => false])]
    private bool $isGrossanlass = false;

    #[ORM\OneToOne(mappedBy: 'department', targetEntity: DepartmentGrossanlassConfig::class)]
    private ?DepartmentGrossanlassConfig $grossanlassConfig = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->memberships = new ArrayCollection();
        $this->children = new ArrayCollection();
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

    public function getOrganisationId(): string
    {
        return $this->organisationId;
    }

    public function setOrganisationId(string $organisationId): self
    {
        $this->organisationId = $organisationId;
        return $this;
    }

    public function getOrganisation(): Organisation
    {
        return $this->organisation;
    }

    public function setOrganisation(Organisation $organisation): self
    {
        $this->organisation = $organisation;
        $this->organisationId = $organisation->getId();
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
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

    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * @return Collection<int, Membership>
     */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(Membership $membership): self
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setDepartment($this);
        }
        return $this;
    }

    public function removeMembership(Membership $membership): self
    {
        if ($this->memberships->removeElement($membership)) {
            if ($membership->getDepartment() === $this) {
                $membership->setDepartment(null);
            }
        }
        return $this;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): self
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function getParent(): ?Department
    {
        return $this->parent;
    }

    public function setParent(?Department $parent): self
    {
        $this->parent = $parent;
        $this->parentId = $parent?->getId();
        return $this;
    }

    /**
     * @return Collection<int, Department>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(Department $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }
        return $this;
    }

    public function removeChild(Department $child): self
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }
        return $this;
    }

    public function getBillingAddressId(): ?string
    {
        return $this->billingAddressId;
    }

    public function getBillingAddress(): ?Address
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(?Address $address): self
    {
        $this->billingAddress = $address;
        $this->billingAddressId = $address?->getId();
        return $this;
    }

    public function isGrossanlass(): bool
    {
        return $this->isGrossanlass;
    }

    public function setIsGrossanlass(bool $isGrossanlass): self
    {
        $this->isGrossanlass = $isGrossanlass;

        return $this;
    }

    public function getGrossanlassConfig(): ?DepartmentGrossanlassConfig
    {
        return $this->grossanlassConfig;
    }

    public function setGrossanlassConfig(?DepartmentGrossanlassConfig $grossanlassConfig): self
    {
        $this->grossanlassConfig = $grossanlassConfig;

        return $this;
    }
}

