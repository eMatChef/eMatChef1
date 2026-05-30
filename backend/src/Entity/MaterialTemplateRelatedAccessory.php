<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MaterialTemplateRelatedAccessory – „verwandtes Zubehör" auf Vorlagen-Ebene.
 *
 * Spiegelt `MaterialRelatedAccessory` für Vorlagen: Da Vorlagen abstrakt sind
 * (Bauteil-Bezug über Name/Typ statt konkretem MaterialItem), werden Zubehör-
 * Empfehlungen als Name/Typ geführt. Beim „Vorlage → Material" werden sie zu
 * konkreten Material-Verknüpfungen (`MaterialRelatedAccessory`) aufgelöst.
 */
#[ORM\Entity]
#[ORM\Table(name: 'material_template_related_accessory')]
#[ORM\Index(name: 'idx_tpl_related_accessory_template', columns: ['template_id'])]
class MaterialTemplateRelatedAccessory
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'template_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $templateId;

    #[ORM\ManyToOne(targetEntity: MaterialTemplate::class, inversedBy: 'relatedAccessories')]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialTemplate $template;

    /** Anzeigename des Zubehörs, z.B. „Kochlöffel-Set". */
    #[ORM\Column(type: 'string', length: 160)]
    private string $name;

    /** Optionaler Typ-Bezeichner (analog Komponenten). */
    #[ORM\Column(name: 'component_type', type: 'string', length: 60, nullable: true)]
    private ?string $componentType = null;

    /** Übergreifendes Material: Name bleibt generisch (kein Modell/Hersteller anhängen). */
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

    public function setTemplateId(string $templateId): self
    {
        $this->templateId = $templateId;
        return $this;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getComponentType(): ?string
    {
        return $this->componentType;
    }

    public function setComponentType(?string $componentType): self
    {
        $this->componentType = $componentType;
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
