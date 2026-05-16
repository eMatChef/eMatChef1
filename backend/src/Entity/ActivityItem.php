<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ActivityItem - Verknüpfung einer Aktivität mit einem Material
 * 
 * Jede Zeile = 1 Material-Position in einer Aktivität.
 * Speichert gewünschte Menge, Priorität und Status.
 */
#[ORM\Entity]
#[ORM\Table(name: 'activity_item')]
#[ORM\Index(name: 'idx_activity_item_activity', columns: ['activity_id'])]
#[ORM\Index(name: 'idx_activity_item_material', columns: ['material_item_id'])]
class ActivityItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'activity_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $activityId;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'activity_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Activity $activity;

    #[ORM\Column(name: 'material_item_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $materialItemId;

    #[ORM\ManyToOne(targetEntity: MaterialItem::class)]
    #[ORM\JoinColumn(name: 'material_item_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialItem $materialItem;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $quantity = 1;

    /** priority: low, normal, high, critical */
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'normal'])]
    private string $priority = 'normal';

    /** status: requested, confirmed, picked_up, returned */
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'requested'])]
    private string $status = 'requested';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    // Verbrauchsmaterial & Preise
    #[ORM\Column(name: 'is_consumable', type: 'boolean', options: ['default' => false])]
    private bool $isConsumable = false;

    /** Nachbuchung / Bestandserhöhung während der Ausleihe (eigene Aktivitätszeile, nicht mit Ursprungsmenge zusammenführen) */
    #[ORM\Column(name: 'is_replenishment', type: 'boolean', options: ['default' => false])]
    private bool $isReplenishment = false;

    #[ORM\Column(name: 'unit_price', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $unitPrice = null;

    #[ORM\Column(name: 'line_total', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $lineTotal = null;

    /** price_type: rental_day, rental_week, rental_month, sale, free */
    #[ORM\Column(name: 'price_type', type: 'string', length: 20, nullable: true, options: ['default' => 'free'])]
    private ?string $priceType = 'free';

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // === Getters & Setters ===

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getActivityId(): string
    {
        return $this->activityId;
    }

    public function setActivityId(string $activityId): self
    {
        $this->activityId = $activityId;
        return $this;
    }

    public function getActivity(): Activity
    {
        return $this->activity;
    }

    public function setActivity(Activity $activity): self
    {
        $this->activity = $activity;
        $this->activityId = $activity->getId();
        return $this;
    }

    public function getMaterialItemId(): string
    {
        return $this->materialItemId;
    }

    public function setMaterialItemId(string $materialItemId): self
    {
        $this->materialItemId = $materialItemId;
        return $this;
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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
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

    // Verbrauchsmaterial & Preise Getters/Setters
    public function getIsConsumable(): bool { return $this->isConsumable; }
    public function setIsConsumable(bool $isConsumable): self { $this->isConsumable = $isConsumable; return $this; }

    public function getIsReplenishment(): bool { return $this->isReplenishment; }
    public function setIsReplenishment(bool $isReplenishment): self { $this->isReplenishment = $isReplenishment; return $this; }

    public function getUnitPrice(): ?string { return $this->unitPrice; }
    public function setUnitPrice(?string $unitPrice): self { $this->unitPrice = $unitPrice; return $this; }

    public function getLineTotal(): ?string { return $this->lineTotal; }
    public function setLineTotal(?string $lineTotal): self { $this->lineTotal = $lineTotal; return $this; }

    public function getPriceType(): ?string { return $this->priceType; }
    public function setPriceType(?string $priceType): self { $this->priceType = $priceType; return $this; }

    /**
     * Berechnet line_total = quantity × unit_price
     */
    public function calculateLineTotal(): self
    {
        if ($this->unitPrice !== null) {
            $this->lineTotal = (string) ($this->quantity * (float) $this->unitPrice);
        } else {
            $this->lineTotal = null;
        }
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
}
