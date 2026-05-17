<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Ausstehende Zuordnung einer Anschaffung zur Buchhaltung (Kostenstelle).
 * Status: pending → nach Erfassen der Buchung: recorded.
 */
#[ORM\Entity]
#[ORM\Table(name: 'accounting_acquisition_follow_up')]
#[ORM\Index(name: 'idx_aafu_department_status', columns: ['department_id', 'status'])]
class AccountingAcquisitionFollowUp
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RECORDED = 'recorded';

    public const SOURCE_BATCH = 'batch';
    public const SOURCE_ACTIVITY_CONSUMPTION = 'activity_consumption';
    public const SOURCE_ACTIVITY_REPLENISHMENT = 'activity_replenishment';
    public const SOURCE_ACTIVITY_LOSS = 'activity_loss';
    public const SOURCE_ACTIVITY_WORKSHOP = 'activity_workshop';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\ManyToOne(targetEntity: MaterialBatch::class)]
    #[ORM\JoinColumn(name: 'material_batch_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MaterialBatch $materialBatch = null;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'activity_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Activity $activity = null;

    #[ORM\Column(name: 'source_kind', type: 'string', length: 32, nullable: true)]
    private ?string $sourceKind = null;

    #[ORM\Column(name: 'source_ref_id', type: 'string', length: 13, nullable: true)]
    private ?string $sourceRefId = null;

    #[ORM\ManyToOne(targetEntity: MaterialItem::class)]
    #[ORM\JoinColumn(name: 'material_item_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MaterialItem $materialItem = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(name: 'suggested_date', type: 'date_immutable')]
    private \DateTimeImmutable $suggestedDate;

    #[ORM\Column(name: 'receipt_label', type: 'string', length: 255, nullable: true)]
    private ?string $receiptLabel = null;

    #[ORM\Column(type: 'string', length: 16)]
    private string $status = self::STATUS_PENDING;

    #[ORM\ManyToOne(targetEntity: AccountingBooking::class)]
    #[ORM\JoinColumn(name: 'accounting_booking_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AccountingBooking $accountingBooking = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->suggestedDate = new \DateTimeImmutable('today');
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

    public function getDepartment(): Department
    {
        return $this->department;
    }

    public function setDepartment(Department $department): self
    {
        $this->department = $department;

        return $this;
    }

    public function getMaterialBatch(): ?MaterialBatch
    {
        return $this->materialBatch;
    }

    public function setMaterialBatch(?MaterialBatch $materialBatch): self
    {
        $this->materialBatch = $materialBatch;

        return $this;
    }

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function setActivity(?Activity $activity): self
    {
        $this->activity = $activity;

        return $this;
    }

    public function getSourceKind(): ?string
    {
        return $this->sourceKind;
    }

    public function setSourceKind(?string $sourceKind): self
    {
        $this->sourceKind = $sourceKind;

        return $this;
    }

    public function getSourceRefId(): ?string
    {
        return $this->sourceRefId;
    }

    public function setSourceRefId(?string $sourceRefId): self
    {
        $this->sourceRefId = $sourceRefId;

        return $this;
    }

    public function getMaterialItem(): ?MaterialItem
    {
        return $this->materialItem;
    }

    public function setMaterialItem(?MaterialItem $materialItem): self
    {
        $this->materialItem = $materialItem;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getSuggestedDate(): \DateTimeImmutable
    {
        return $this->suggestedDate;
    }

    public function setSuggestedDate(\DateTimeImmutable $suggestedDate): self
    {
        $this->suggestedDate = $suggestedDate;

        return $this;
    }

    public function getReceiptLabel(): ?string
    {
        return $this->receiptLabel;
    }

    public function setReceiptLabel(?string $receiptLabel): self
    {
        $this->receiptLabel = $receiptLabel;

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

    public function getAccountingBooking(): ?AccountingBooking
    {
        return $this->accountingBooking;
    }

    public function setAccountingBooking(?AccountingBooking $accountingBooking): self
    {
        $this->accountingBooking = $accountingBooking;

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

    public function touchUpdatedAt(): self
    {
        $this->updatedAt = new \DateTime();

        return $this;
    }
}
