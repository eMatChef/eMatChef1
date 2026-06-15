<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_js_order_item')]
#[ORM\Index(name: 'idx_js_order_item_order', columns: ['js_order_id'])]
#[ORM\Index(name: 'idx_js_order_item_material', columns: ['material_item_id'])]
class ActivityJsOrderItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'js_order_id', type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    private string $jsOrderId;

    #[ORM\ManyToOne(targetEntity: ActivityJsOrder::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'js_order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?ActivityJsOrder $jsOrder = null;

    #[ORM\Column(name: 'material_item_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $materialItemId;

    #[ORM\ManyToOne(targetEntity: MaterialItem::class)]
    #[ORM\JoinColumn(name: 'material_item_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialItem $materialItem;

    #[ORM\Column(name: 'quantity_ordered', type: 'integer', options: ['default' => 0])]
    private int $quantityOrdered = 0;

    #[ORM\Column(name: 'dotation_suggested', type: 'integer', nullable: true)]
    private ?int $dotationSuggested = null;

    #[ORM\Column(name: 'order_confirmed', type: 'boolean', options: ['default' => false])]
    private bool $orderConfirmed = false;

    #[ORM\Column(name: 'quantity_received', type: 'integer', options: ['default' => 0])]
    private int $quantityReceived = 0;

    #[ORM\Column(name: 'quantity_returned', type: 'integer', options: ['default' => 0])]
    private int $quantityReturned = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function touchUpdatedAt(): void
    {
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

    public function getJsOrderId(): string
    {
        return $this->jsOrderId;
    }

    public function getJsOrder(): ?ActivityJsOrder
    {
        return $this->jsOrder;
    }

    public function setJsOrder(?ActivityJsOrder $jsOrder): self
    {
        $this->jsOrder = $jsOrder;
        $this->jsOrderId = $jsOrder?->getId() ?? '';

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

    public function getQuantityOrdered(): int
    {
        return $this->quantityOrdered;
    }

    public function setQuantityOrdered(int $quantityOrdered): self
    {
        $this->quantityOrdered = $quantityOrdered;

        return $this;
    }

    public function getDotationSuggested(): ?int
    {
        return $this->dotationSuggested;
    }

    public function setDotationSuggested(?int $dotationSuggested): self
    {
        $this->dotationSuggested = $dotationSuggested;

        return $this;
    }

    public function isOrderConfirmed(): bool
    {
        return $this->orderConfirmed;
    }

    public function setOrderConfirmed(bool $orderConfirmed): self
    {
        $this->orderConfirmed = $orderConfirmed;

        return $this;
    }

    public function getQuantityReceived(): int
    {
        return $this->quantityReceived;
    }

    public function setQuantityReceived(int $quantityReceived): self
    {
        $this->quantityReceived = $quantityReceived;

        return $this;
    }

    public function getQuantityReturned(): int
    {
        return $this->quantityReturned;
    }

    public function setQuantityReturned(int $quantityReturned): self
    {
        $this->quantityReturned = $quantityReturned;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

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

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }
}
