<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MaterialRelatedAccessory – „verwandtes Zubehör" (Empfehlungs-Verknüpfung).
 *
 * Verknüpft ein Material (typischerweise eine Kombo) mit anderen Materialien,
 * die als Zubehör empfohlen werden. Bewusst GETRENNT von der Stückliste
 * (`MaterialComboComponent`): Zubehör ist KEIN Bauteil des Sets, sondern eine
 * separate Empfehlung, die im Aktivitäts-Flow als eigene Position vorgeschlagen
 * wird. Nutzbar für alle Typen (physische und virtuelle Kombo).
 *
 * 13-stellige ID mit Prefix "ra" (Related Accessory).
 */
#[ORM\Entity]
#[ORM\Table(name: 'material_related_accessory')]
#[ORM\Index(name: 'idx_related_accessory_material', columns: ['material_id'])]
#[ORM\Index(name: 'idx_related_accessory_accessory', columns: ['accessory_material_id'])]
#[ORM\UniqueConstraint(name: 'uniq_related_accessory_pair', columns: ['material_id', 'accessory_material_id'])]
class MaterialRelatedAccessory
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    /** Das Material, dem das Zubehör zugeordnet ist (z.B. die Kombo). */
    #[ORM\Column(name: 'material_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $materialId;

    #[ORM\ManyToOne(targetEntity: MaterialItem::class)]
    #[ORM\JoinColumn(name: 'material_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialItem $material;

    /** Das empfohlene Zubehör-Material. */
    #[ORM\Column(name: 'accessory_material_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $accessoryMaterialId;

    #[ORM\ManyToOne(targetEntity: MaterialItem::class)]
    #[ORM\JoinColumn(name: 'accessory_material_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialItem $accessoryMaterial;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getMaterialId(): string
    {
        return $this->materialId;
    }

    public function getMaterial(): MaterialItem
    {
        return $this->material;
    }

    public function setMaterial(MaterialItem $material): self
    {
        $this->material = $material;
        $this->materialId = $material->getId();
        return $this;
    }

    public function getAccessoryMaterialId(): string
    {
        return $this->accessoryMaterialId;
    }

    public function getAccessoryMaterial(): MaterialItem
    {
        return $this->accessoryMaterial;
    }

    public function setAccessoryMaterial(MaterialItem $accessoryMaterial): self
    {
        $this->accessoryMaterial = $accessoryMaterial;
        $this->accessoryMaterialId = $accessoryMaterial->getId();
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
}
