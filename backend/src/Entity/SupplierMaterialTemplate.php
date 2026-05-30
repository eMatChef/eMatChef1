<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SupplierMaterialTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SupplierMaterialTemplateRepository::class)]
#[ORM\Table(name: 'supplier_material_template')]
class SupplierMaterialTemplate
{
    public const MATERIAL_TYPE_PHYSICAL_COMBO = 'physical_combo';
    public const MATERIAL_TYPE_VIRTUAL_COMBO = 'virtual_combo';

    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_DEPARTMENTS = 'departments';
    public const VISIBILITY_GLOBAL = 'global';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_PENDING_REVIEW = 'pending_review';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'supplier_company_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $supplierCompanyId;

    #[ORM\ManyToOne(targetEntity: SupplierCompany::class)]
    #[ORM\JoinColumn(name: 'supplier_company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private SupplierCompany $supplierCompany;

    #[ORM\Column(type: 'string', length: 160)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $manufacturer = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(name: 'material_type', type: 'string', length: 20, options: ['default' => self::MATERIAL_TYPE_PHYSICAL_COMBO])]
    #[Assert\Choice(choices: [self::MATERIAL_TYPE_PHYSICAL_COMBO, self::MATERIAL_TYPE_VIRTUAL_COMBO])]
    private string $materialType = self::MATERIAL_TYPE_PHYSICAL_COMBO;

    #[ORM\Column(name: 'tent_type', type: 'string', length: 40, nullable: true)]
    private ?string $tentType = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $capacity = null;

    #[ORM\Column(name: 'category_hint', type: 'string', length: 255, nullable: true)]
    private ?string $categoryHint = null;

    #[ORM\Column(name: 'unit_price', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $unitPrice = null;

    #[ORM\Column(type: 'string', length: 3, options: ['default' => 'CHF'])]
    private string $currency = 'CHF';

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => self::VISIBILITY_PRIVATE])]
    #[Assert\Choice(choices: [self::VISIBILITY_PRIVATE, self::VISIBILITY_DEPARTMENTS, self::VISIBILITY_GLOBAL])]
    private string $visibility = self::VISIBILITY_PRIVATE;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => self::STATUS_DRAFT])]
    #[Assert\Choice(choices: [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_PENDING_REVIEW])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(name: 'legacy_material_template_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $legacyMaterialTemplateId = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    /** @var Collection<int, SupplierMaterialTemplateComponent> */
    #[ORM\OneToMany(mappedBy: 'template', targetEntity: SupplierMaterialTemplateComponent::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $components;

    /** @var Collection<int, SupplierMaterialTemplateOptionGroup> */
    #[ORM\OneToMany(mappedBy: 'template', targetEntity: SupplierMaterialTemplateOptionGroup::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $optionGroups;

    /** @var Collection<int, SupplierMaterialTemplateOption> */
    #[ORM\OneToMany(mappedBy: 'template', targetEntity: SupplierMaterialTemplateOption::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $options;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->components = new ArrayCollection();
        $this->optionGroups = new ArrayCollection();
        $this->options = new ArrayCollection();
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

    public function getSupplierCompanyId(): string
    {
        return $this->supplierCompanyId;
    }

    public function getSupplierCompany(): SupplierCompany
    {
        return $this->supplierCompany;
    }

    public function setSupplierCompany(SupplierCompany $supplierCompany): self
    {
        $this->supplierCompany = $supplierCompany;
        $this->supplierCompanyId = $supplierCompany->getId();

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

    public function getCategoryHint(): ?string
    {
        return $this->categoryHint;
    }

    public function setCategoryHint(?string $categoryHint): self
    {
        $this->categoryHint = $categoryHint;

        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?string $unitPrice): self
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = strtoupper(trim($currency));

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function setVisibility(string $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

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

    public function getLegacyMaterialTemplateId(): ?string
    {
        return $this->legacyMaterialTemplateId;
    }

    public function setLegacyMaterialTemplateId(?string $legacyMaterialTemplateId): self
    {
        $this->legacyMaterialTemplateId = $legacyMaterialTemplateId;

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

    public function touch(): self
    {
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /** @return Collection<int, SupplierMaterialTemplateComponent> */
    public function getComponents(): Collection
    {
        return $this->components;
    }

    public function addComponent(SupplierMaterialTemplateComponent $component): self
    {
        if (!$this->components->contains($component)) {
            $this->components->add($component);
            $component->setTemplate($this);
        }

        return $this;
    }

    public function clearComponents(): self
    {
        $this->components->clear();

        return $this;
    }

    /** @return Collection<int, SupplierMaterialTemplateOptionGroup> */
    public function getOptionGroups(): Collection
    {
        return $this->optionGroups;
    }

    public function addOptionGroup(SupplierMaterialTemplateOptionGroup $group): self
    {
        if (!$this->optionGroups->contains($group)) {
            $this->optionGroups->add($group);
            $group->setTemplate($this);
        }

        return $this;
    }

    public function clearOptionGroups(): self
    {
        $this->optionGroups->clear();

        return $this;
    }

    /** @return Collection<int, SupplierMaterialTemplateOption> */
    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function addOption(SupplierMaterialTemplateOption $option): self
    {
        if (!$this->options->contains($option)) {
            $this->options->add($option);
            $option->setTemplate($this);
        }

        return $this;
    }

    public function clearOptions(): self
    {
        $this->options->clear();

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(bool $detailed = false): array
    {
        $data = [
            'id' => $this->id,
            'supplier_company_id' => $this->supplierCompanyId,
            'name' => $this->name,
            'description' => $this->description,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'material_type' => $this->materialType,
            'tent_type' => $this->tentType,
            'capacity' => $this->capacity,
            'category_hint' => $this->categoryHint,
            'unit_price' => $this->unitPrice !== null ? (float) $this->unitPrice : null,
            'currency' => $this->currency,
            'is_active' => $this->isActive,
            'visibility' => $this->visibility,
            'status' => $this->status,
            'source' => $this->source,
            'legacy_material_template_id' => $this->legacyMaterialTemplateId,
            'component_count' => $this->components->count(),
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];

        if (!$detailed) {
            return $data;
        }

        $data['components'] = array_map(
            static fn (SupplierMaterialTemplateComponent $c) => $c->toArray(),
            $this->components->toArray()
        );

        $data['option_groups'] = [];
        foreach ($this->optionGroups as $group) {
            $groupData = $group->toArray();
            $groupData['options'] = [];
            foreach ($this->options as $option) {
                if ($option->getOptionGroupId() === $group->getId()) {
                    $groupData['options'][] = $option->toArray(true);
                }
            }
            $data['option_groups'][] = $groupData;
        }

        $data['standalone_options'] = [];
        foreach ($this->options as $option) {
            if ($option->getOptionGroupId() === null) {
                $data['standalone_options'][] = $option->toArray(true);
            }
        }

        return $data;
    }
}
