<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'supplier_material_template_option')]
#[ORM\Index(name: 'idx_sup_tpl_option_template', columns: ['template_id'])]
#[ORM\Index(name: 'idx_sup_tpl_option_group', columns: ['option_group_id'])]
class SupplierMaterialTemplateOption
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'template_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $templateId;

    #[ORM\ManyToOne(targetEntity: SupplierMaterialTemplate::class, inversedBy: 'options')]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private SupplierMaterialTemplate $template;

    #[ORM\Column(name: 'option_group_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $optionGroupId = null;

    #[ORM\ManyToOne(targetEntity: SupplierMaterialTemplateOptionGroup::class)]
    #[ORM\JoinColumn(name: 'option_group_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?SupplierMaterialTemplateOptionGroup $optionGroup = null;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    #[ORM\Column(name: 'display_mode', type: 'string', length: 20, options: ['default' => 'toggle'])]
    private string $displayMode = 'toggle';

    #[ORM\Column(name: 'default_selected', type: 'boolean', options: ['default' => false])]
    private bool $defaultSelected = false;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    /** @var Collection<int, SupplierMaterialTemplateOptionDelta> */
    #[ORM\OneToMany(mappedBy: 'option', targetEntity: SupplierMaterialTemplateOptionDelta::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $deltas;

    public function __construct()
    {
        $this->deltas = new ArrayCollection();
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

    public function getTemplateId(): string
    {
        return $this->templateId;
    }

    public function getTemplate(): SupplierMaterialTemplate
    {
        return $this->template;
    }

    public function setTemplate(SupplierMaterialTemplate $template): self
    {
        $this->template = $template;
        $this->templateId = $template->getId();

        return $this;
    }

    public function getOptionGroupId(): ?string
    {
        return $this->optionGroupId;
    }

    public function getOptionGroup(): ?SupplierMaterialTemplateOptionGroup
    {
        return $this->optionGroup;
    }

    public function setOptionGroup(?SupplierMaterialTemplateOptionGroup $optionGroup): self
    {
        $this->optionGroup = $optionGroup;
        $this->optionGroupId = $optionGroup?->getId();

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

    public function getDisplayMode(): string
    {
        return $this->displayMode;
    }

    public function setDisplayMode(string $displayMode): self
    {
        $this->displayMode = $displayMode;

        return $this;
    }

    public function getDefaultSelected(): bool
    {
        return $this->defaultSelected;
    }

    public function setDefaultSelected(bool $defaultSelected): self
    {
        $this->defaultSelected = $defaultSelected;

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

    /** @return Collection<int, SupplierMaterialTemplateOptionDelta> */
    public function getDeltas(): Collection
    {
        return $this->deltas;
    }

    public function addDelta(SupplierMaterialTemplateOptionDelta $delta): self
    {
        if (!$this->deltas->contains($delta)) {
            $this->deltas->add($delta);
            $delta->setOption($this);
        }

        return $this;
    }

    public function clearDeltas(): self
    {
        $this->deltas->clear();

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(bool $withDeltas = false): array
    {
        $data = [
            'id' => $this->id,
            'option_group_id' => $this->optionGroupId,
            'name' => $this->name,
            'display_mode' => $this->displayMode,
            'default_selected' => $this->defaultSelected,
            'sort_order' => $this->sortOrder,
        ];

        if ($withDeltas) {
            $data['deltas'] = array_map(
                static fn (SupplierMaterialTemplateOptionDelta $d) => $d->toArray(),
                $this->deltas->toArray()
            );
        }

        return $data;
    }
}
