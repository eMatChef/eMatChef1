<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MaterialComboOption – eine wählbare Option einer virtuellen Kombo (Weg B).
 *
 * Alles Wählbare ist eine Option mit Delta-Liste (siehe README Abschnitt 6).
 * `display_mode` ist ENTKOPPELT von den Deltas:
 *  - toggle: Ja/Nein-Schalter (degenerierte Option, option_group_id = NULL)
 *  - group:  Auswahl innerhalb einer Options-Gruppe (Paket 6)
 *
 * `is_optional` der Komponente wird durch eine Toggle-Option mit +1-Delta ersetzt/abgeleitet.
 *
 * 13-stellige ID mit Prefix "op" (OPtion).
 */
#[ORM\Entity]
#[ORM\Table(name: 'material_combo_option')]
#[ORM\Index(name: 'idx_combo_option_material', columns: ['material_item_id'])]
#[ORM\Index(name: 'idx_combo_option_group', columns: ['option_group_id'])]
class MaterialComboOption
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    /** Die Kombo (Redundanz für einfache Queries). */
    #[ORM\Column(name: 'material_item_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $materialItemId;

    #[ORM\ManyToOne(targetEntity: MaterialItem::class)]
    #[ORM\JoinColumn(name: 'material_item_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialItem $materialItem;

    /** NULL = eigenständige Option (kein Gruppenzwang, typ. Toggle). */
    #[ORM\Column(name: 'option_group_id', type: 'string', length: 13, nullable: true, columnDefinition: 'CHARACTER(13) NULL')]
    private ?string $optionGroupId = null;

    #[ORM\ManyToOne(targetEntity: MaterialComboOptionGroup::class)]
    #[ORM\JoinColumn(name: 'option_group_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?MaterialComboOptionGroup $optionGroup = null;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    /** toggle (Ja/Nein) | group (Auswahl) — entkoppelt von den Deltas */
    #[ORM\Column(name: 'display_mode', type: 'string', length: 20, options: ['default' => 'toggle'])]
    private string $displayMode = 'toggle';

    #[ORM\Column(name: 'default_selected', type: 'boolean', options: ['default' => false])]
    private bool $defaultSelected = false;

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

    public function getOptionGroupId(): ?string
    {
        return $this->optionGroupId;
    }

    public function getOptionGroup(): ?MaterialComboOptionGroup
    {
        return $this->optionGroup;
    }

    public function setOptionGroup(?MaterialComboOptionGroup $optionGroup): self
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
}
