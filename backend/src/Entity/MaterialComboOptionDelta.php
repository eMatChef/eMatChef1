<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MaterialComboOptionDelta – ±Stückliste einer Option (Weg B).
 *
 * Eine Zeile = (Teil, ±Menge, component_source). Auflösung der Endmenge je Teil:
 *   Σ basis.qty + Σ (gewählte option.delta.qty_delta)   → pro Teil auf ≥ 0 geklemmt.
 *
 * `self_provided`-Zeilen zählen NIE in Flaschenhals/Reservierung (nur Checkliste).
 *
 * 13-stellige ID mit Prefix "dt" (DelTa).
 */
#[ORM\Entity]
#[ORM\Table(name: 'material_combo_option_delta')]
#[ORM\Index(name: 'idx_combo_optdelta_option', columns: ['option_id'])]
#[ORM\Index(name: 'idx_combo_optdelta_component', columns: ['component_material_id'])]
class MaterialComboOptionDelta
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'option_id', type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    private string $optionId;

    #[ORM\ManyToOne(targetEntity: MaterialComboOption::class)]
    #[ORM\JoinColumn(name: 'option_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialComboOption $option;

    #[ORM\Column(name: 'component_material_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $componentMaterialId;

    #[ORM\ManyToOne(targetEntity: MaterialItem::class)]
    #[ORM\JoinColumn(name: 'component_material_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialItem $componentMaterial;

    /** ±Menge (z. B. +1, −12) */
    #[ORM\Column(name: 'qty_delta', type: 'integer')]
    private int $qtyDelta = 0;

    /** on_issue / bulk */
    #[ORM\Column(name: 'assignment_mode', type: 'string', length: 20, options: ['default' => 'bulk'])]
    private string $assignmentMode = 'bulk';

    /** serialized / bulk */
    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $tracking = null;

    /** stock | self_provided */
    #[ORM\Column(name: 'component_source', type: 'string', length: 20, options: ['default' => 'stock'])]
    private string $componentSource = 'stock';

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

    public function getOption(): MaterialComboOption
    {
        return $this->option;
    }

    public function setOption(MaterialComboOption $option): self
    {
        $this->option = $option;
        $this->optionId = $option->getId();
        return $this;
    }

    public function getComponentMaterialId(): string
    {
        return $this->componentMaterialId;
    }

    public function getComponentMaterial(): MaterialItem
    {
        return $this->componentMaterial;
    }

    public function setComponentMaterial(MaterialItem $componentMaterial): self
    {
        $this->componentMaterial = $componentMaterial;
        $this->componentMaterialId = $componentMaterial->getId();
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

    public function getAssignmentMode(): string
    {
        return $this->assignmentMode;
    }

    public function setAssignmentMode(string $assignmentMode): self
    {
        $this->assignmentMode = $assignmentMode;
        return $this;
    }

    public function getTracking(): ?string
    {
        return $this->tracking;
    }

    public function setTracking(?string $tracking): self
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
