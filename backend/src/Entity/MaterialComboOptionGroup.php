<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MaterialComboOptionGroup – Auswahl-Gruppe einer virtuellen Kombo (Weg B).
 *
 * Bündelt Optionen mit einer Auswahlregel (z. B. „Innenzelt" 1–2, „Aufbau" genau 1).
 * Schema deckt von Anfang an Gruppen/Auswahl-Modus mit ab; die UI (Paket 6) nutzt
 * Gruppen erst später – Paket 5 verwendet nur eigenständige Toggle-Optionen
 * (option_group_id = NULL).
 *
 * 13-stellige ID mit Prefix "og" (Option-Group).
 */
#[ORM\Entity]
#[ORM\Table(name: 'material_combo_option_group')]
#[ORM\Index(name: 'idx_combo_optgroup_material', columns: ['material_item_id'])]
class MaterialComboOptionGroup
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    /** Die Kombo, zu der die Gruppe gehört. */
    #[ORM\Column(name: 'material_item_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $materialItemId;

    #[ORM\ManyToOne(targetEntity: MaterialItem::class)]
    #[ORM\JoinColumn(name: 'material_item_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialItem $materialItem;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    /** exclusive (genau 1) | multi (mehrere) | quantity (n–m Stück) */
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

    public function getMaterialItemId(): string
    {
        return $this->materialItemId;
    }

    public function getMaterialItem(): MaterialItem
    {
        return $this->materialItem;
    }

    public function setMaterialItem(MaterialItem $materialItem): self
    {
        $this->materialItem = $materialItem;
        $this->materialItemId = $materialItem->getId();
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
}
