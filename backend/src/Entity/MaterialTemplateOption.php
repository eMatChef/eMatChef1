<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MaterialTemplateOption – Spiegel von MaterialComboOption auf Vorlagen-Ebene.
 *
 * 12-stellige ID (Stammdaten-Konvention der Vorlagen-Tabellen).
 */
#[ORM\Entity]
#[ORM\Table(name: 'material_template_option')]
#[ORM\Index(name: 'idx_tpl_option_template', columns: ['template_id'])]
#[ORM\Index(name: 'idx_tpl_option_group', columns: ['option_group_id'])]
class MaterialTemplateOption
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'template_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $templateId;

    #[ORM\ManyToOne(targetEntity: MaterialTemplate::class)]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialTemplate $template;

    /** NULL = eigenständige Option (typ. Toggle). */
    #[ORM\Column(name: 'option_group_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $optionGroupId = null;

    #[ORM\ManyToOne(targetEntity: MaterialTemplateOptionGroup::class)]
    #[ORM\JoinColumn(name: 'option_group_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?MaterialTemplateOptionGroup $optionGroup = null;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    /** toggle | group */
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

    public function getTemplateId(): string
    {
        return $this->templateId;
    }

    public function getTemplate(): MaterialTemplate
    {
        return $this->template;
    }

    public function setTemplate(MaterialTemplate $template): self
    {
        $this->template = $template;
        $this->templateId = $template->getId();
        return $this;
    }

    public function getOptionGroupId(): ?string
    {
        return $this->optionGroupId;
    }

    public function getOptionGroup(): ?MaterialTemplateOptionGroup
    {
        return $this->optionGroup;
    }

    public function setOptionGroup(?MaterialTemplateOptionGroup $optionGroup): self
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
