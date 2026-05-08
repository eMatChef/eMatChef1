<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Group - Hierarchische Gruppen innerhalb eines Departments
 * 
 * Gruppen dienen zur Organisation der Mitglieder (z.B. Pfadi-Truppen, Abteilungen).
 * Jede Gruppe gehört zu einem Department und kann Untergruppen haben (parent/child).
 * Mitglieder werden über GroupMembership zugewiesen.
 */
#[ORM\Entity]
#[ORM\Table(name: '"group"')]
#[ORM\Index(name: 'idx_group_department', columns: ['department_id'])]
#[ORM\Index(name: 'idx_group_parent', columns: ['parent_id'])]
class Group
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    // Department-Zugehörigkeit
    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    // Name der Gruppe
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    // Hierarchie (parent/child)
    #[ORM\Column(name: 'parent_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $parentId = null;

    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Group $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: Group::class)]
    private Collection $children;

    // Sortierung
    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    // Mitgliedschaften
    #[ORM\OneToMany(mappedBy: 'group', targetEntity: GroupMembership::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $memberships;

    // Timestamps
    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->children = new ArrayCollection();
        $this->memberships = new ArrayCollection();
    }

    // === Getters & Setters ===

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getDepartmentId(): string
    {
        return $this->departmentId;
    }

    public function setDepartmentId(string $departmentId): self
    {
        $this->departmentId = $departmentId;
        return $this;
    }

    public function getDepartment(): Department
    {
        return $this->department;
    }

    public function setDepartment(Department $department): self
    {
        $this->department = $department;
        $this->departmentId = $department->getId();
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

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): self
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function getParent(): ?Group
    {
        return $this->parent;
    }

    public function setParent(?Group $parent): self
    {
        $this->parent = $parent;
        $this->parentId = $parent?->getId();
        return $this;
    }

    /**
     * @return Collection<int, Group>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(Group $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }
        return $this;
    }

    public function removeChild(Group $child): self
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    /**
     * @return Collection<int, GroupMembership>
     */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(GroupMembership $membership): self
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setGroup($this);
        }
        return $this;
    }

    public function removeMembership(GroupMembership $membership): self
    {
        if ($this->memberships->removeElement($membership)) {
            if ($membership->getGroup() === $this) {
                // orphanRemoval handles deletion
            }
        }
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
}
