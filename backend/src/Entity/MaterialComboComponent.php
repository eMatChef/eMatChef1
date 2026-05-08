<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MaterialComboComponent - Verknüpft Combo-Artikel mit ihren Komponenten
 * 
 * Bildet die reale Beziehung zwischen einem Combo-MaterialItem (Zelt)
 * und seinen Einzelteilen (Aussenzelt, Innenzelt, Heringe, etc.).
 * 
 * Unterstützt 4 Zuweisungsmodi:
 * - fixed:    Physical Combo – dauerhaft verbaut (Batch fest zugewiesen)
 * - assigned: Virtual Combo – bei Erstellung zugewiesen (Batch zugewiesen, tauschbar)
 * - on_issue: Virtual Combo – erst beim Packen/Ausgeben zugewiesen (Batch noch NULL)
 * - bulk:     Bulk-Teile – nur Menge, keine Seriennummer (Batch immer NULL)
 * 
 * 13-stellige ID mit Prefix "cc" (Combo-Component, transaktionsartig)
 */
#[ORM\Entity]
#[ORM\Table(name: 'material_combo_component')]
#[ORM\Index(name: 'idx_combo_parent', columns: ['parent_material_id'])]
#[ORM\Index(name: 'idx_combo_component', columns: ['component_material_id'])]
#[ORM\Index(name: 'idx_combo_batch', columns: ['component_batch_id'])]
class MaterialComboComponent
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    /** Das Combo-Material (z.B. "Spatz braun", "Phoenix Zelt #1") */
    #[ORM\Column(name: 'parent_material_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $parentMaterialId;

    #[ORM\ManyToOne(targetEntity: MaterialItem::class)]
    #[ORM\JoinColumn(name: 'parent_material_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialItem $parentMaterial;

    /** Der Komponenten-Artikeltyp (z.B. "Spatz Aussenzelt", "Heringe") */
    #[ORM\Column(name: 'component_material_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $componentMaterialId;

    #[ORM\ManyToOne(targetEntity: MaterialItem::class)]
    #[ORM\JoinColumn(name: 'component_material_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialItem $componentMaterial;

    /** Die konkrete Seriennummer-Instanz (nur bei serialized, NULL bei bulk/on_issue) */
    #[ORM\Column(name: 'component_batch_id', type: 'string', length: 13, nullable: true, columnDefinition: 'CHARACTER(13) NULL')]
    private ?string $componentBatchId = null;

    #[ORM\ManyToOne(targetEntity: MaterialBatch::class)]
    #[ORM\JoinColumn(name: 'component_batch_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MaterialBatch $componentBatch = null;

    /** Menge (für Bulk-Teile: 10 Heringe, 17 Stahlheringe. Für serialized: immer 1) */
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $qty = 1;

    /** Rolle/Typ der Komponente: 'aussenzelt', 'innenzelt', 'heringe', etc. */
    #[ORM\Column(name: 'component_role', type: 'string', length: 60, nullable: true)]
    private ?string $componentRole = null;

    /** fixed, assigned, on_issue, bulk */
    #[ORM\Column(name: 'assignment_mode', type: 'string', length: 20, options: ['default' => 'bulk'])]
    private string $assignmentMode = 'bulk';

    #[ORM\Column(name: 'is_optional', type: 'boolean', options: ['default' => false])]
    private bool $isOptional = false;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

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

    // --- Parent Material ---

    public function getParentMaterialId(): string
    {
        return $this->parentMaterialId;
    }

    public function setParentMaterialId(string $parentMaterialId): self
    {
        $this->parentMaterialId = $parentMaterialId;
        return $this;
    }

    public function getParentMaterial(): MaterialItem
    {
        return $this->parentMaterial;
    }

    public function setParentMaterial(MaterialItem $parentMaterial): self
    {
        $this->parentMaterial = $parentMaterial;
        $this->parentMaterialId = $parentMaterial->getId();
        return $this;
    }

    // --- Component Material ---

    public function getComponentMaterialId(): string
    {
        return $this->componentMaterialId;
    }

    public function setComponentMaterialId(string $componentMaterialId): self
    {
        $this->componentMaterialId = $componentMaterialId;
        return $this;
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

    // --- Component Batch (nullable) ---

    public function getComponentBatchId(): ?string
    {
        return $this->componentBatchId;
    }

    public function setComponentBatchId(?string $componentBatchId): self
    {
        $this->componentBatchId = $componentBatchId;
        return $this;
    }

    public function getComponentBatch(): ?MaterialBatch
    {
        return $this->componentBatch;
    }

    public function setComponentBatch(?MaterialBatch $componentBatch): self
    {
        $this->componentBatch = $componentBatch;
        $this->componentBatchId = $componentBatch?->getId();
        return $this;
    }

    // --- Qty, Role, Mode ---

    public function getQty(): int
    {
        return $this->qty;
    }

    public function setQty(int $qty): self
    {
        $this->qty = $qty;
        return $this;
    }

    public function getComponentRole(): ?string
    {
        return $this->componentRole;
    }

    public function setComponentRole(?string $componentRole): self
    {
        $this->componentRole = $componentRole;
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

    public function getIsOptional(): bool
    {
        return $this->isOptional;
    }

    public function setIsOptional(bool $isOptional): self
    {
        $this->isOptional = $isOptional;
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

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    // ========== Helper Methods ==========

    /**
     * Ist die Komponente einem konkreten Batch (Seriennummer) zugewiesen?
     */
    public function isAssignedToBatch(): bool
    {
        return $this->componentBatchId !== null;
    }

    /**
     * Ist diese Komponente ein Bulk-Teil (nur Menge, keine SN)?
     */
    public function isBulk(): bool
    {
        return $this->assignmentMode === 'bulk';
    }

    /**
     * Ist diese Komponente fest verbaut (physical combo)?
     */
    public function isFixed(): bool
    {
        return $this->assignmentMode === 'fixed';
    }

    /**
     * Wartet diese Komponente noch auf Zuweisung bei Ausgabe?
     */
    public function isAwaitingAssignment(): bool
    {
        return $this->assignmentMode === 'on_issue' && $this->componentBatchId === null;
    }
}
