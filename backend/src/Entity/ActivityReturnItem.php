<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ActivityReturnItem - Rückgabeliste für eine Aktivität
 * 
 * Der Materialwart erfasst pro Material-Position:
 * - quantity_returned: Zurückgegebene Menge
 * - quantity_damaged: Beschädigte Menge
 * - quantity_missing: Fehlende Menge
 * - condition_in: Zustand bei Rückgabe
 */
#[ORM\Entity]
#[ORM\Table(name: 'activity_return_item')]
#[ORM\Index(name: 'idx_return_item_activity', columns: ['activity_id'])]
#[ORM\Index(name: 'idx_return_item_material', columns: ['material_item_id'])]
class ActivityReturnItem
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

    /** Zurückgegebene Menge */
    #[ORM\Column(name: 'quantity_returned', type: 'integer', options: ['default' => 0])]
    private int $quantityReturned = 0;

    /** Beschädigte Menge */
    #[ORM\Column(name: 'quantity_damaged', type: 'integer', options: ['default' => 0])]
    private int $quantityDamaged = 0;

    /** Fehlende Menge */
    #[ORM\Column(name: 'quantity_missing', type: 'integer', options: ['default' => 0])]
    private int $quantityMissing = 0;

    /** Zustand bei Rückgabe: ok, leicht_beschaedigt, beschaedigt, defekt */
    #[ORM\Column(name: 'condition_in', type: 'string', length: 50, options: ['default' => 'ok'])]
    private string $conditionIn = 'ok';

    /** Kommentar */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /** Retour erfasst von */
    #[ORM\Column(name: 'returned_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $returnedByUserId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'returned_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $returnedByUser = null;

    /** Wann zurückgegeben */
    #[ORM\Column(name: 'returned_at', type: 'datetime', nullable: true)]
    private ?\DateTime $returnedAt = null;

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

    public function getId(): ?string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }

    public function getActivityId(): string { return $this->activityId; }
    public function setActivityId(string $activityId): self { $this->activityId = $activityId; return $this; }

    public function getActivity(): Activity { return $this->activity; }
    public function setActivity(Activity $activity): self
    {
        $this->activity = $activity;
        $this->activityId = $activity->getId();
        return $this;
    }

    public function getMaterialItemId(): string { return $this->materialItemId; }
    public function setMaterialItemId(string $materialItemId): self { $this->materialItemId = $materialItemId; return $this; }

    public function getMaterialItem(): MaterialItem { return $this->materialItem; }
    public function setMaterialItem(MaterialItem $materialItem): self
    {
        $this->materialItem = $materialItem;
        $this->materialItemId = $materialItem->getId();
        return $this;
    }

    public function getQuantityReturned(): int { return $this->quantityReturned; }
    public function setQuantityReturned(int $quantityReturned): self { $this->quantityReturned = $quantityReturned; return $this; }

    public function getQuantityDamaged(): int { return $this->quantityDamaged; }
    public function setQuantityDamaged(int $quantityDamaged): self { $this->quantityDamaged = $quantityDamaged; return $this; }

    public function getQuantityMissing(): int { return $this->quantityMissing; }
    public function setQuantityMissing(int $quantityMissing): self { $this->quantityMissing = $quantityMissing; return $this; }

    public function getConditionIn(): string { return $this->conditionIn; }
    public function setConditionIn(string $conditionIn): self { $this->conditionIn = $conditionIn; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }

    public function getReturnedByUserId(): ?string { return $this->returnedByUserId; }
    public function setReturnedByUserId(?string $returnedByUserId): self { $this->returnedByUserId = $returnedByUserId; return $this; }

    public function getReturnedByUser(): ?User { return $this->returnedByUser; }
    public function setReturnedByUser(?User $returnedByUser): self
    {
        $this->returnedByUser = $returnedByUser;
        $this->returnedByUserId = $returnedByUser?->getId();
        return $this;
    }

    public function getReturnedAt(): ?\DateTime { return $this->returnedAt; }
    public function setReturnedAt(?\DateTime $returnedAt): self { $this->returnedAt = $returnedAt; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }
    public function setUpdatedAt(\DateTime $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    // === Helper ===

    /** Hat die Rückgabe Differenzen (Fehlmengen, Schäden)? */
    public function hasDifferences(): bool
    {
        return $this->quantityDamaged > 0 || $this->quantityMissing > 0;
    }

    /** Gibt die effektiv zurückgegebene "ok" Menge zurück */
    public function getQuantityOk(): int
    {
        return max(0, $this->quantityReturned - $this->quantityDamaged);
    }
}
