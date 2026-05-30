<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MaterialTemplateOptionDelta – Spiegel von MaterialComboOptionDelta auf Vorlagen-Ebene.
 *
 * Bauteil-Bezug abstrakt über component_type/Name (statt konkretem material_item).
 * Beim „Vorlage → Material" zu konkreten Kombo-Deltas aufgelöst.
 *
 * 12-stellige ID (Stammdaten-Konvention der Vorlagen-Tabellen).
 */
#[ORM\Entity]
#[ORM\Table(name: 'material_template_option_delta')]
#[ORM\Index(name: 'idx_tpl_optdelta_option', columns: ['option_id'])]
class MaterialTemplateOptionDelta
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'option_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $optionId;

    #[ORM\ManyToOne(targetEntity: MaterialTemplateOption::class)]
    #[ORM\JoinColumn(name: 'option_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialTemplateOption $option;

    /** Typ-Bezeichner des Bauteils (z. B. 'innenzelt', 'heringe'). */
    #[ORM\Column(name: 'component_type', type: 'string', length: 60)]
    private string $componentType;

    /** Anzeigename des Bauteils. */
    #[ORM\Column(type: 'string', length: 160)]
    private string $name;

    /** ±Menge (z. B. +1, −12) */
    #[ORM\Column(name: 'qty_delta', type: 'integer')]
    private int $qtyDelta = 0;

    /** serialized / bulk */
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'bulk'])]
    private string $tracking = 'bulk';

    /** stock | self_provided */
    #[ORM\Column(name: 'component_source', type: 'string', length: 20, options: ['default' => 'stock'])]
    private string $componentSource = 'stock';

    /** Übergreifendes Material: Name bleibt generisch. */
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

    public function getOptionId(): string
    {
        return $this->optionId;
    }

    public function getOption(): MaterialTemplateOption
    {
        return $this->option;
    }

    public function setOption(MaterialTemplateOption $option): self
    {
        $this->option = $option;
        $this->optionId = $option->getId();
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

    public function getQtyDelta(): int
    {
        return $this->qtyDelta;
    }

    public function setQtyDelta(int $qtyDelta): self
    {
        $this->qtyDelta = $qtyDelta;
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
}
