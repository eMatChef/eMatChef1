<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_js_order')]
#[ORM\Index(name: 'idx_js_order_status', columns: ['status'])]
class ActivityJsOrder
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_CANCELLED = 'cancelled';

    public const DELIVERY_FRANKO = 'franko';
    public const DELIVERY_PICKUP_THUN = 'pickup_thun';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'activity_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $activityId;

    #[ORM\OneToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'activity_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Activity $activity;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => self::STATUS_DRAFT])]
    private string $status = self::STATUS_DRAFT;

    /** @var array<string, mixed>|null Blöcke 1–3 inkl. user_overridden */
    #[ORM\Column(name: 'form_data', type: 'json', nullable: true)]
    private ?array $formData = null;

    #[ORM\Column(name: 'participant_count', type: 'integer', nullable: true)]
    private ?int $participantCount = null;

    #[ORM\Column(name: 'delivery_type', type: 'string', length: 20, options: ['default' => self::DELIVERY_FRANKO])]
    private string $deliveryType = self::DELIVERY_FRANKO;

    #[ORM\Column(name: 'ordered_at', type: 'datetime', nullable: true)]
    private ?\DateTime $orderedAt = null;

    #[ORM\Column(name: 'ordered_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $orderedByUserId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'ordered_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $orderedByUser = null;

    #[ORM\Column(name: 'generated_pdf_media_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $generatedPdfMediaId = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    /** @var Collection<int, ActivityJsOrderItem> */
    #[ORM\OneToMany(mappedBy: 'jsOrder', targetEntity: ActivityJsOrderItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->items = new ArrayCollection();
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

    public function getActivityId(): string
    {
        return $this->activityId;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getFormData(): ?array
    {
        return $this->formData;
    }

    /** @param array<string, mixed>|null $formData */
    public function setFormData(?array $formData): self
    {
        $this->formData = $formData;

        return $this;
    }

    public function getParticipantCount(): ?int
    {
        return $this->participantCount;
    }

    public function setParticipantCount(?int $participantCount): self
    {
        $this->participantCount = $participantCount;

        return $this;
    }

    public function getDeliveryType(): string
    {
        return $this->deliveryType;
    }

    public function setDeliveryType(string $deliveryType): self
    {
        $this->deliveryType = $deliveryType;

        return $this;
    }

    public function getOrderedAt(): ?\DateTime
    {
        return $this->orderedAt;
    }

    public function setOrderedAt(?\DateTime $orderedAt): self
    {
        $this->orderedAt = $orderedAt;

        return $this;
    }

    public function getOrderedByUserId(): ?string
    {
        return $this->orderedByUserId;
    }

    public function getOrderedByUser(): ?User
    {
        return $this->orderedByUser;
    }

    public function setOrderedByUser(?User $orderedByUser): self
    {
        $this->orderedByUser = $orderedByUser;
        $this->orderedByUserId = $orderedByUser?->getId();

        return $this;
    }

    public function getGeneratedPdfMediaId(): ?string
    {
        return $this->generatedPdfMediaId;
    }

    public function setGeneratedPdfMediaId(?string $generatedPdfMediaId): self
    {
        $this->generatedPdfMediaId = $generatedPdfMediaId;

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

    /** @return Collection<int, ActivityJsOrderItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ActivityJsOrderItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setJsOrder($this);
        }

        return $this;
    }

    public function removeItem(ActivityJsOrderItem $item): self
    {
        if ($this->items->removeElement($item) && $item->getJsOrder() === $this) {
            $item->setJsOrder(null);
        }

        return $this;
    }
}
