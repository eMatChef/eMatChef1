<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'supplier_material_template_option_group')]
#[ORM\Index(name: 'idx_sup_tpl_optgroup_template', columns: ['template_id'])]
class SupplierMaterialTemplateOptionGroup
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'template_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $templateId;

    #[ORM\ManyToOne(targetEntity: SupplierMaterialTemplate::class, inversedBy: 'optionGroups')]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private SupplierMaterialTemplate $template;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    #[ORM\Column(name: 'selection_type', type: 'string', length: 20, options: ['default' => 'exclusive'])]
    private string $selectionType = 'exclusive';

    #[ORM\Column(name: 'min_select', type: 'integer', options: ['default' => 0])]
    private int $minSelect = 0;

    #[ORM\Column(name: 'max_select', type: 'integer', nullable: true)]
    private ?int $maxSelect = null;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSelectionType(): string
    {
        return $this->selectionType;
    }

    public function setSelectionType(string $selectionType): self
    {
        $this->selectionType = $selectionType;

        return $this;
    }

    public function getMinSelect(): int
    {
        return $this->minSelect;
    }

    public function setMinSelect(int $minSelect): self
    {
        $this->minSelect = $minSelect;

        return $this;
    }

    public function getMaxSelect(): ?int
    {
        return $this->maxSelect;
    }

    public function setMaxSelect(?int $maxSelect): self
    {
        $this->maxSelect = $maxSelect;

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

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'selection_type' => $this->selectionType,
            'min_select' => $this->minSelect,
            'max_select' => $this->maxSelect,
            'sort_order' => $this->sortOrder,
        ];
    }
}
