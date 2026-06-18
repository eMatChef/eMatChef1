<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_replenishment_wish')]
#[ORM\Index(name: 'idx_replenishment_wish_activity', columns: ['activity_id'])]
class ActivityReplenishmentWish
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

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

    #[ORM\Column(name: 'quantity_requested', type: 'integer')]
    private int $quantityRequested;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'string', length: 16)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'requested_by_user_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $requestedByUserId;

    #[ORM\Column(name: 'requested_at', type: 'datetime')]
    private \DateTime $requestedAt;

    #[ORM\Column(name: 'decided_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $decidedByUserId = null;

    #[ORM\Column(name: 'decided_at', type: 'datetime', nullable: true)]
    private ?\DateTime $decidedAt = null;

    #[ORM\Column(name: 'rejection_reason', type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(name: 'fulfilled_activity_item_id', type: 'string', length: 13, nullable: true, columnDefinition: 'CHARACTER(13) NULL')]
    private ?string $fulfilledActivityItemId = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'availability_snapshot', type: 'json', nullable: true)]
    private ?array $availabilitySnapshot = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->requestedAt = new \DateTime();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }
    public function getActivityId(): string { return $this->activityId; }
    public function setActivity(Activity $activity): self
    {
        $this->activity = $activity;
        $this->activityId = $activity->getId();
        return $this;
    }
    public function getActivity(): Activity { return $this->activity; }
    public function getMaterialItemId(): string { return $this->materialItemId; }
    public function setMaterialItem(MaterialItem $material): self
    {
        $this->materialItem = $material;
        $this->materialItemId = $material->getId();
        return $this;
    }
    public function getMaterialItem(): MaterialItem { return $this->materialItem; }
    public function getQuantityRequested(): int { return $this->quantityRequested; }
    public function setQuantityRequested(int $qty): self { $this->quantityRequested = $qty; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getRequestedByUserId(): string { return $this->requestedByUserId; }
    public function setRequestedByUserId(string $id): self { $this->requestedByUserId = $id; return $this; }
    public function getRequestedAt(): \DateTime { return $this->requestedAt; }
    public function getDecidedByUserId(): ?string { return $this->decidedByUserId; }
    public function setDecidedByUserId(?string $id): self { $this->decidedByUserId = $id; return $this; }
    public function getDecidedAt(): ?\DateTime { return $this->decidedAt; }
    public function setDecidedAt(?\DateTime $at): self { $this->decidedAt = $at; return $this; }
    public function getRejectionReason(): ?string { return $this->rejectionReason; }
    public function setRejectionReason(?string $reason): self { $this->rejectionReason = $reason; return $this; }
    public function getFulfilledActivityItemId(): ?string { return $this->fulfilledActivityItemId; }
    public function setFulfilledActivityItemId(?string $id): self { $this->fulfilledActivityItemId = $id; return $this; }
    /** @return array<string, mixed>|null */
    public function getAvailabilitySnapshot(): ?array { return $this->availabilitySnapshot; }
    /** @param array<string, mixed>|null $snapshot */
    public function setAvailabilitySnapshot(?array $snapshot): self { $this->availabilitySnapshot = $snapshot; return $this; }
    public function touch(): self { $this->updatedAt = new \DateTime(); return $this; }
}
