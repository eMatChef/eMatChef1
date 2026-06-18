<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ActivityPackItem - Packliste für eine Aktivität
 * 
 * Der Materialwart erfasst pro Material-Position:
 * - quantity_ordered: Bestellte Menge (aus ActivityItem)
 * - quantity_packed: Tatsächlich eingepackte Menge
 * - condition_out: Zustand bei Ausgabe (ok, leicht_beschaedigt, beschaedigt)
 * - batch_numbers: Seriennummern / Batch-Nummern (optional)
 * - notes: Kommentar
 */
#[ORM\Entity]
#[ORM\Table(name: 'activity_pack_item')]
#[ORM\Index(name: 'idx_pack_item_activity', columns: ['activity_id'])]
#[ORM\Index(name: 'idx_pack_item_material', columns: ['material_item_id'])]
class ActivityPackItem
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

    /** Bestellte Menge (aus der Material-Liste) */
    #[ORM\Column(name: 'quantity_ordered', type: 'integer', options: ['default' => 0])]
    private int $quantityOrdered = 0;

    /** Tatsächlich gepackte Menge */
    #[ORM\Column(name: 'quantity_packed', type: 'integer', options: ['default' => 0])]
    private int $quantityPacked = 0;

    /** Transport zum Event */
    #[ORM\Column(name: 'quantity_transport_to', type: 'integer', options: ['default' => 0])]
    private int $quantityTransportTo = 0;

    /** Tatsächlich am Event / ausgegeben */
    #[ORM\Column(name: 'quantity_issued', type: 'integer', options: ['default' => 0])]
    private int $quantityIssued = 0;

    /** Transport zurück ins Lager */
    #[ORM\Column(name: 'quantity_transport_back', type: 'integer', options: ['default' => 0])]
    private int $quantityTransportBack = 0;

    /** Retournierte Menge */
    #[ORM\Column(name: 'quantity_returned', type: 'integer', options: ['default' => 0])]
    private int $quantityReturned = 0;

    /** Eingelagerte Menge (MW: wieder ins Regal) */
    #[ORM\Column(name: 'quantity_stored', type: 'integer', options: ['default' => 0])]
    private int $quantityStored = 0;

    /** Zustand bei Ausgabe: ok, leicht_beschaedigt, beschaedigt */
    #[ORM\Column(name: 'condition_out', type: 'string', length: 50, options: ['default' => 'ok'])]
    private string $conditionOut = 'ok';

    /** Seriennummern / Batch-Nummern (optional) */
    #[ORM\Column(name: 'batch_numbers', type: 'text', nullable: true)]
    private ?string $batchNumbers = null;

    /** Kommentar */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /** Intent-Gruppe «Zusammen packen» (Phase 11) */
    #[ORM\Column(name: 'intent_id', type: 'string', length: 13, nullable: true, columnDefinition: 'CHARACTER(13) NULL')]
    private ?string $intentId = null;

    /** Gepackt von */
    #[ORM\Column(name: 'packed_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $packedByUserId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'packed_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $packedByUser = null;

    /** Wann gepackt */
    #[ORM\Column(name: 'packed_at', type: 'datetime', nullable: true)]
    private ?\DateTime $packedAt = null;

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

    public function getQuantityOrdered(): int { return $this->quantityOrdered; }
    public function setQuantityOrdered(int $quantityOrdered): self { $this->quantityOrdered = $quantityOrdered; return $this; }

    public function getQuantityPacked(): int { return $this->quantityPacked; }
    public function setQuantityPacked(int $quantityPacked): self { $this->quantityPacked = $quantityPacked; return $this; }

    public function getQuantityTransportTo(): int { return $this->quantityTransportTo; }
    public function setQuantityTransportTo(int $quantityTransportTo): self { $this->quantityTransportTo = $quantityTransportTo; return $this; }

    public function getQuantityIssued(): int { return $this->quantityIssued; }
    public function setQuantityIssued(int $quantityIssued): self { $this->quantityIssued = $quantityIssued; return $this; }

    public function getQuantityTransportBack(): int { return $this->quantityTransportBack; }
    public function setQuantityTransportBack(int $quantityTransportBack): self { $this->quantityTransportBack = $quantityTransportBack; return $this; }

    public function getQuantityReturned(): int { return $this->quantityReturned; }
    public function setQuantityReturned(int $quantityReturned): self { $this->quantityReturned = $quantityReturned; return $this; }

    public function getQuantityStored(): int { return $this->quantityStored; }
    public function setQuantityStored(int $quantityStored): self { $this->quantityStored = $quantityStored; return $this; }

    public function getConditionOut(): string { return $this->conditionOut; }
    public function setConditionOut(string $conditionOut): self { $this->conditionOut = $conditionOut; return $this; }

    public function getBatchNumbers(): ?string { return $this->batchNumbers; }
    public function setBatchNumbers(?string $batchNumbers): self { $this->batchNumbers = $batchNumbers; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }

    public function getPackedByUserId(): ?string { return $this->packedByUserId; }
    public function setPackedByUserId(?string $packedByUserId): self { $this->packedByUserId = $packedByUserId; return $this; }

    public function getPackedByUser(): ?User { return $this->packedByUser; }
    public function setPackedByUser(?User $packedByUser): self
    {
        $this->packedByUser = $packedByUser;
        $this->packedByUserId = $packedByUser?->getId();
        return $this;
    }

    public function getPackedAt(): ?\DateTime { return $this->packedAt; }
    public function setPackedAt(?\DateTime $packedAt): self { $this->packedAt = $packedAt; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }
    public function setUpdatedAt(\DateTime $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    // === Helper ===

    /** Ist die Menge vollständig gepackt? */
    public function isFullyPacked(): bool
    {
        return $this->quantityPacked >= $this->quantityOrdered;
    }

    /** Ist die Menge vollständig am Event? */
    public function isFullyIssued(): bool
    {
        return $this->quantityIssued >= $this->quantityPacked && $this->quantityPacked > 0;
    }

    /** Ist die Menge vollständig retourniert? */
    public function isFullyReturned(): bool
    {
        return $this->quantityReturned >= $this->quantityIssued && $this->quantityIssued > 0;
    }

    /** Gibt die Differenz zwischen bestellt und gepackt zurück */
    public function getPackDifference(): int
    {
        return $this->quantityOrdered - $this->quantityPacked;
    }

    /** Differenz zwischen gepackt und ausgegeben */
    public function getIssueDifference(): int
    {
        return $this->quantityPacked - $this->quantityIssued;
    }

    /** Differenz zwischen ausgegeben und retourniert */
    public function getReturnDifference(): int
    {
        return $this->quantityIssued - $this->quantityReturned;
    }

    /** Differenz zwischen retourniert und eingelagert */
    public function getStoreDifference(): int
    {
        return $this->quantityReturned - $this->quantityStored;
    }

    /** Ist die retournierte Menge vollständig eingelagert? */
    public function isFullyStored(): bool
    {
        return $this->quantityStored >= $this->quantityReturned && $this->quantityReturned > 0;
    }

    public function getIntentId(): ?string { return $this->intentId; }
    public function setIntentId(?string $intentId): self { $this->intentId = $intentId; return $this; }
}
