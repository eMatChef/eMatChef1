<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Plattform-Stamm für Zeltblatt-Struktur (ohne Department-Preise).
 */
#[ORM\Entity(repositoryClass: \App\Repository\RepairTemplateRepository::class)]
#[ORM\Table(name: 'repair_template')]
#[ORM\UniqueConstraint(name: 'uniq_repair_template_key', columns: ['template_key'])]
#[ORM\Index(name: 'idx_repair_template_active', columns: ['is_active'])]
class RepairTemplate
{
    public const MATERIAL_CLASS_TENT = 'tent';

    public const ALL_MATERIAL_CLASSES = [
        self::MATERIAL_CLASS_TENT,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'template_key', type: 'string', length: 50)]
    private string $templateKey;

    #[ORM\Column(type: 'string', length: 160)]
    private string $name;

    #[ORM\Column(name: 'material_class', type: 'string', length: 30, options: ['default' => 'tent'])]
    private string $materialClass = self::MATERIAL_CLASS_TENT;

    #[ORM\Column(name: 'structure_json', type: 'json')]
    private array $structureJson = [];

    #[ORM\Column(name: 'diagram_json', type: 'json', nullable: true)]
    private ?array $diagramJson = null;

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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

    public function getTemplateKey(): string
    {
        return $this->templateKey;
    }

    public function setTemplateKey(string $templateKey): self
    {
        $this->templateKey = $templateKey;
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

    public function getMaterialClass(): string
    {
        return $this->materialClass;
    }

    public function setMaterialClass(string $materialClass): self
    {
        $this->materialClass = $materialClass;
        return $this;
    }

    public function getStructureJson(): array
    {
        return $this->structureJson;
    }

    public function setStructureJson(array $structureJson): self
    {
        $this->structureJson = $structureJson;
        return $this;
    }

    public function getDiagramJson(): ?array
    {
        return $this->diagramJson;
    }

    public function setDiagramJson(?array $diagramJson): self
    {
        $this->diagramJson = $diagramJson;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
