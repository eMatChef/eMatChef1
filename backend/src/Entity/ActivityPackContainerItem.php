<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_pack_container_item')]
#[ORM\Index(name: 'idx_pack_container_item_container', columns: ['pack_container_id'])]
#[ORM\Index(name: 'idx_pack_container_item_material', columns: ['material_item_id'])]
#[ORM\Index(name: 'idx_pack_container_item_batch', columns: ['material_batch_id'])]
class ActivityPackContainerItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'pack_container_id', type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    private string $packContainerId;

    #[ORM\ManyToOne(targetEntity: ActivityPackContainer::class)]
    #[ORM\JoinColumn(name: 'pack_container_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ActivityPackContainer $packContainer;

    #[ORM\Column(name: 'material_item_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $materialItemId;

    #[ORM\ManyToOne(targetEntity: MaterialItem::class)]
    #[ORM\JoinColumn(name: 'material_item_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialItem $materialItem;

    #[ORM\Column(name: 'material_batch_id', type: 'string', length: 13, nullable: true, columnDefinition: 'CHARACTER(13) NULL')]
    private ?string $materialBatchId = null;

    #[ORM\ManyToOne(targetEntity: MaterialBatch::class)]
    #[ORM\JoinColumn(name: 'material_batch_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MaterialBatch $materialBatch = null;

    #[ORM\Column(name: 'quantity_packed', type: 'integer', options: ['default' => 0])]
    private int $quantityPacked = 0;

    #[ORM\Column(name: 'quantity_issued', type: 'integer', options: ['default' => 0])]
    private int $quantityIssued = 0;

    #[ORM\Column(name: 'quantity_returned', type: 'integer', options: ['default' => 0])]
    private int $quantityReturned = 0;

    #[ORM\Column(name: 'condition_out', type: 'string', length: 50, options: ['default' => 'ok'])]
    private string $conditionOut = 'ok';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }
    public function getPackContainerId(): string { return $this->packContainerId; }
    public function setPackContainerId(string $packContainerId): self { $this->packContainerId = $packContainerId; return $this; }
    public function getPackContainer(): ActivityPackContainer { return $this->packContainer; }
    public function setPackContainer(ActivityPackContainer $packContainer): self { $this->packContainer = $packContainer; $this->packContainerId = $packContainer->getId(); return $this; }
    public function getMaterialItemId(): string { return $this->materialItemId; }
    public function setMaterialItemId(string $materialItemId): self { $this->materialItemId = $materialItemId; return $this; }
    public function getMaterialItem(): MaterialItem { return $this->materialItem; }
    public function setMaterialItem(MaterialItem $materialItem): self { $this->materialItem = $materialItem; $this->materialItemId = $materialItem->getId(); return $this; }
    public function getMaterialBatchId(): ?string { return $this->materialBatchId; }
    public function setMaterialBatchId(?string $materialBatchId): self { $this->materialBatchId = $materialBatchId; return $this; }
    public function getMaterialBatch(): ?MaterialBatch { return $this->materialBatch; }
    public function setMaterialBatch(?MaterialBatch $materialBatch): self { $this->materialBatch = $materialBatch; $this->materialBatchId = $materialBatch?->getId(); return $this; }
    public function getQuantityPacked(): int { return $this->quantityPacked; }
    public function setQuantityPacked(int $quantityPacked): self { $this->quantityPacked = $quantityPacked; return $this; }
    public function getQuantityIssued(): int { return $this->quantityIssued; }
    public function setQuantityIssued(int $quantityIssued): self { $this->quantityIssued = $quantityIssued; return $this; }
    public function getQuantityReturned(): int { return $this->quantityReturned; }
    public function setQuantityReturned(int $quantityReturned): self { $this->quantityReturned = $quantityReturned; return $this; }
    public function getConditionOut(): string { return $this->conditionOut; }
    public function setConditionOut(string $conditionOut): self { $this->conditionOut = $conditionOut; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTime(); return $this; }
}

