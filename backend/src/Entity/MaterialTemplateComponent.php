<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MaterialTemplateComponent - Bauteile einer Zelt-/Kombi-Vorlage
 * 
 * Definiert welche Komponenten eine Vorlage benötigt.
 * z.B. "hajk 6er": Außenzelt oft serialisiert (eine SN), Heringe/Zubehör als bulk (Menge).
 */
#[ORM\Entity]
#[ORM\Table(name: 'material_template_component')]
#[ORM\Index(name: 'idx_tpl_comp_template', columns: ['template_id'])]
class MaterialTemplateComponent
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'template_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $templateId;

    #[ORM\ManyToOne(targetEntity: MaterialTemplate::class, inversedBy: 'components')]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialTemplate $template;

    /** Typ-Bezeichner: 'aussenzelt', 'innenzelt', 'gestaenge', 'heringe', 'tasche', etc. */
    #[ORM\Column(name: 'component_type', type: 'string', length: 60)]
    private string $componentType;

    /** Anzeigename: "Außenzelt", "Heringe", "Transporttasche" */
    #[ORM\Column(type: 'string', length: 160)]
    private string $name;

    /** Benötigte Anzahl: 1, 2, 10, 17... */
    #[ORM\Column(name: 'required_qty', type: 'integer', options: ['default' => 1])]
    private int $requiredQty = 1;

    /** Optional? z.B. Tortuga Bodendecke */
    #[ORM\Column(name: 'is_optional', type: 'boolean', options: ['default' => false])]
    private bool $isOptional = false;

    /** serialized oder bulk – bestimmt ob Seriennummer eingegeben wird */
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'bulk'])]
    private string $tracking = 'bulk';

    /** Mögliche Reparaturtypen: ["loch", "riss", "abspannung"] */
    #[ORM\Column(name: 'repair_types', type: 'json', nullable: true)]
    private ?array $repairTypes = null;

    /** Übergreifendes Material: Name bleibt generisch (z.B. "Heringe" statt "Heringe Phoenix Zelthangar") */
    #[ORM\Column(name: 'is_generic', type: 'boolean', options: ['default' => false])]
    private bool $isGeneric = false;

    /** Sortierreihenfolge */
    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    // ========== Getters & Setters ==========

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

    public function getRepairTypes(): ?array
    {
        return $this->repairTypes;
    }

    public function setRepairTypes(?array $repairTypes): self
    {
        $this->repairTypes = $repairTypes;
        return $this;
    }

    public function getIsGeneric(): bool { return $this->isGeneric; }
    public function setIsGeneric(bool $isGeneric): self { $this->isGeneric = $isGeneric; return $this; }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    /**
     * Ist diese Komponente serialisiert?
     */
    public function isSerialized(): bool
    {
        return $this->tracking === 'serialized';
    }

    /**
     * Ist diese Komponente ein Bulk-Artikel?
     */
    public function isBulk(): bool
    {
        return $this->tracking === 'bulk';
    }
}
