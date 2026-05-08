<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * MaterialTemplate - Zelt-/Kombinations-Vorlagen
 * 
 * Definiert die Struktur eines Zelts oder einer Kombination.
 * Aus einer Vorlage können konkrete MaterialItems (Combos) erstellt werden.
 * Ersetzt die statischen JSON-Dateien aus v4.
 * 
 * Sichtbarkeit:
 * - department_id = NULL → Zentrale Vorlage (Hersteller), sichtbar für alle
 * - department_id = X    → Department-eigene Vorlage, nur für dieses Department
 * 
 * Bearbeitung:
 * - Zentral (NULL): Nur superadmin/org dürfen bearbeiten
 * - Department (X): Department-matwart und höher dürfen bearbeiten
 * - Später: Vendor-Portal → Hersteller pflegen ihre eigenen Vorlagen
 */
#[ORM\Entity]
#[ORM\Table(name: 'material_template')]
#[ORM\Index(name: 'idx_template_department', columns: ['department_id'])]
#[ORM\Index(name: 'idx_template_manufacturer', columns: ['manufacturer'])]
#[ORM\Index(name: 'idx_template_scope', columns: ['scope'])]
class MaterialTemplate
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    /** NULL = zentrale Vorlage (für alle Departments sichtbar) */
    #[ORM\Column(name: 'department_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $departmentId = null;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Department $department = null;

    /**
     * Scope der Vorlage:
     * - global:     Zentrale Hersteller-Vorlage (department_id=NULL), sichtbar für alle
     * - department:  Eigene Vorlage eines Departments (department_id gesetzt)
     */
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'global'])]
    private string $scope = 'global';

    #[ORM\Column(type: 'string', length: 160)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $manufacturer = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(name: 'category_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $categoryId = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    /** physical_combo oder virtual_combo */
    #[ORM\Column(name: 'material_type', type: 'string', length: 20, options: ['default' => 'physical_combo'])]
    private string $materialType = 'physical_combo';

    /** gruppenzelt, sonstiges */
    #[ORM\Column(name: 'tent_type', type: 'string', length: 40, nullable: true)]
    private ?string $tentType = null;

    /** Personenkapazität */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $capacity = null;

    /** complete_only, individual, flexible */
    #[ORM\Column(name: 'reservation_mode', type: 'string', length: 20, nullable: true)]
    private ?string $reservationMode = null;

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    /** Herkunft: 'hajk', 'spatz', 'tortuga', 'wico', 'zelthangar', 'custom' */
    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    #[ORM\OneToMany(mappedBy: 'template', targetEntity: MaterialTemplateComponent::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $components;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->components = new ArrayCollection();
    }

    // ========== Getters & Setters ==========

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getDepartmentId(): ?string
    {
        return $this->departmentId;
    }

    public function setDepartmentId(?string $departmentId): self
    {
        $this->departmentId = $departmentId;
        return $this;
    }

    public function getDepartment(): ?Department
    {
        return $this->department;
    }

    public function setDepartment(?Department $department): self
    {
        $this->department = $department;
        $this->departmentId = $department?->getId();
        return $this;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): self
    {
        $this->scope = $scope;
        return $this;
    }

    /**
     * Ist dies eine zentrale Vorlage (sichtbar für alle)?
     */
    public function isGlobal(): bool
    {
        return $this->departmentId === null || $this->scope === 'global';
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getManufacturer(): ?string
    {
        return $this->manufacturer;
    }

    public function setManufacturer(?string $manufacturer): self
    {
        $this->manufacturer = $manufacturer;
        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function getCategoryId(): ?string
    {
        return $this->categoryId;
    }

    public function setCategoryId(?string $categoryId): self
    {
        $this->categoryId = $categoryId;
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;
        $this->categoryId = $category?->getId();
        return $this;
    }

    public function getMaterialType(): string
    {
        return $this->materialType;
    }

    public function setMaterialType(string $materialType): self
    {
        $this->materialType = $materialType;
        return $this;
    }

    public function getTentType(): ?string
    {
        return $this->tentType;
    }

    public function setTentType(?string $tentType): self
    {
        $this->tentType = $tentType;
        return $this;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(?int $capacity): self
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function getReservationMode(): ?string
    {
        return $this->reservationMode;
    }

    public function setReservationMode(?string $reservationMode): self
    {
        $this->reservationMode = $reservationMode;
        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;
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
     * @return Collection<int, MaterialTemplateComponent>
     */
    public function getComponents(): Collection
    {
        return $this->components;
    }

    public function addComponent(MaterialTemplateComponent $component): self
    {
        if (!$this->components->contains($component)) {
            $this->components->add($component);
            $component->setTemplate($this);
        }
        return $this;
    }

    public function removeComponent(MaterialTemplateComponent $component): self
    {
        $this->components->removeElement($component);
        return $this;
    }

    /**
     * Anzahl der Pflichtkomponenten
     */
    public function getRequiredComponentCount(): int
    {
        return $this->components->filter(fn($c) => !$c->getIsOptional())->count();
    }

    /**
     * Gesamtanzahl aller Komponenten
     */
    public function getTotalComponentCount(): int
    {
        return $this->components->count();
    }
}
