<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'supplier_material_template_component')]
#[ORM\Index(name: 'idx_sup_tpl_comp_template', columns: ['template_id'])]
class SupplierMaterialTemplateComponent
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'template_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $templateId;

    #[ORM\ManyToOne(targetEntity: SupplierMaterialTemplate::class, inversedBy: 'components')]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private SupplierMaterialTemplate $template;

    #[ORM\Column(name: 'component_type', type: 'string', length: 60)]
    private string $componentType;

    #[ORM\Column(type: 'string', length: 160)]
    private string $name;

    #[ORM\Column(name: 'required_qty', type: 'integer', options: ['default' => 1])]
    private int $requiredQty = 1;

    #[ORM\Column(name: 'is_optional', type: 'boolean', options: ['default' => false])]
    private bool $isOptional = false;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'bulk'])]
    private string $tracking = 'bulk';

    #[ORM\Column(name: 'component_source', type: 'string', length: 20, options: ['default' => 'stock'])]
    private string $componentSource = 'stock';

    #[ORM\Column(name: 'is_generic', type: 'boolean', options: ['default' => false])]
    private bool $isGeneric = false;

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

    public function getComponentType(): string
    {
        return $this->componentType;
    }

    public function setComponentType(string $componentType): self
    {
        $this->componentType = $componentType;

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

    public function getRequiredQty(): int
    {
        return $this->requiredQty;
    }

    public function setRequiredQty(int $requiredQty): self
    {
        $this->requiredQty = $requiredQty;

        return $this;
    }

    public function getIsOptional(): bool
    {
        return $this->isOptional;
    }

    public function setIsOptional(bool $isOptional): self
    {
        $this->isOptional = $isOptional;

        return $this;
    }

    public function getTracking(): string
    {
        return $this->tracking;
    }

    public function setTracking(string $tracking): self
    {
        $this->tracking = $tracking;

        return $this;
    }

    public function getComponentSource(): string
    {
        return $this->componentSource;
    }

    public function setComponentSource(string $componentSource): self
    {
        $this->componentSource = $componentSource;

        return $this;
    }

    public function getIsGeneric(): bool
    {
        return $this->isGeneric;
    }

    public function setIsGeneric(bool $isGeneric): self
    {
        $this->isGeneric = $isGeneric;

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
            'component_type' => $this->componentType,
            'name' => $this->name,
            'required_qty' => $this->requiredQty,
            'is_optional' => $this->isOptional,
            'tracking' => $this->tracking,
            'component_source' => $this->componentSource,
            'is_generic' => $this->isGeneric,
            'sort_order' => $this->sortOrder,
        ];
    }
}
