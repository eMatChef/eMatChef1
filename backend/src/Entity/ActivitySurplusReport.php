<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Überschüssige Esswaren/Verbrauchsmaterial bei Aktivitäts-Retour
 * (nicht auf Packliste — siehe docs/activities/surplus-return-food.md).
 */
#[ORM\Entity]
#[ORM\Table(name: 'activity_surplus_report')]
#[ORM\Index(name: 'idx_surplus_report_activity', columns: ['activity_id'])]
#[ORM\Index(name: 'idx_surplus_report_department', columns: ['department_id'])]
#[ORM\Index(name: 'idx_surplus_report_status', columns: ['status'])]
#[ORM\Index(name: 'idx_surplus_report_material', columns: ['material_item_id'])]
class ActivitySurplusReport
{
    public const STATUS_OPEN = 'open';
    public const STATUS_MATCHED = 'matched';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_DISMISSED = 'dismissed';

    public const KIND_FOOD = 'food';
    public const KIND_CONSUMABLE = 'consumable';
    public const KIND_OTHER = 'other';

    public const ALL_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_MATCHED,
        self::STATUS_RESOLVED,
        self::STATUS_DISMISSED,
    ];

    public const ALL_KINDS = [
        self::KIND_FOOD,
        self::KIND_CONSUMABLE,
        self::KIND_OTHER,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'activity_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $activityId;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'activity_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Activity $activity;

    #[ORM\Column(name: 'reported_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $reportedByUserId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reported_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $reportedByUser = null;

    #[ORM\Column(name: 'name_free_text', type: 'string', length: 255)]
    private string $nameFreeText = '';

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $qty = 1;

    /** food | consumable | other */
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'food'])]
    private string $kind = self::KIND_FOOD;

    #[ORM\Column(name: 'expiry_date', type: 'date', nullable: true)]
    private ?\DateTimeInterface $expiryDate = null;

    #[ORM\Column(name: 'material_item_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $materialItemId = null;

    #[ORM\ManyToOne(targetEntity: MaterialItem::class)]
    #[ORM\JoinColumn(name: 'material_item_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MaterialItem $materialItem = null;

    #[ORM\Column(name: 'resolved_batch_id', type: 'string', length: 13, nullable: true, columnDefinition: 'CHARACTER(13) NULL')]
    private ?string $resolvedBatchId = null;

    #[ORM\ManyToOne(targetEntity: MaterialBatch::class)]
    #[ORM\JoinColumn(name: 'resolved_batch_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MaterialBatch $resolvedBatch = null;

    #[ORM\Column(name: 'inventory_task_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $inventoryTaskId = null;

    #[ORM\ManyToOne(targetEntity: InventoryTask::class)]
    #[ORM\JoinColumn(name: 'inventory_task_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?InventoryTask $inventoryTask = null;

    /** open | matched | resolved | dismissed */
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'open'])]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getDepartmentId(): string
    {
        return $this->departmentId;
    }

    public function setDepartmentId(string $departmentId): self
    {
        $this->departmentId = $departmentId;

        return $this;
    }

    public function getDepartment(): Department
    {
        return $this->department;
    }

    public function setDepartment(Department $department): self
    {
        $this->department = $department;
        $this->departmentId = $department->getId();

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

    public function getReportedByUserId(): ?string
    {
        return $this->reportedByUserId;
    }

    public function setReportedByUserId(?string $reportedByUserId): self
    {
        $this->reportedByUserId = $reportedByUserId;

        return $this;
    }

    public function getReportedByUser(): ?User
    {
        return $this->reportedByUser;
    }

    public function setReportedByUser(?User $reportedByUser): self
    {
        $this->reportedByUser = $reportedByUser;
        $this->reportedByUserId = $reportedByUser?->getId();

        return $this;
    }

    public function getNameFreeText(): string
    {
        return $this->nameFreeText;
    }

    public function setNameFreeText(string $nameFreeText): self
    {
        $this->nameFreeText = $nameFreeText;

        return $this;
    }

    public function getQty(): int
    {
        return $this->qty;
    }

    public function setQty(int $qty): self
    {
        $this->qty = $qty;

        return $this;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function setKind(string $kind): self
    {
        $this->kind = $kind;

        return $this;
    }

    public function getExpiryDate(): ?\DateTimeInterface
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(?\DateTimeInterface $expiryDate): self
    {
        $this->expiryDate = $expiryDate;

        return $this;
    }

    public function getMaterialItemId(): ?string
    {
        return $this->materialItemId;
    }

    public function setMaterialItemId(?string $materialItemId): self
    {
        $this->materialItemId = $materialItemId;

        return $this;
    }

    public function getMaterialItem(): ?MaterialItem
    {
        return $this->materialItem;
    }

    public function setMaterialItem(?MaterialItem $materialItem): self
    {
        $this->materialItem = $materialItem;
        $this->materialItemId = $materialItem?->getId();

        return $this;
    }

    public function getResolvedBatchId(): ?string
    {
        return $this->resolvedBatchId;
    }

    public function setResolvedBatchId(?string $resolvedBatchId): self
    {
        $this->resolvedBatchId = $resolvedBatchId;

        return $this;
    }

    public function getResolvedBatch(): ?MaterialBatch
    {
        return $this->resolvedBatch;
    }

    public function setResolvedBatch(?MaterialBatch $resolvedBatch): self
    {
        $this->resolvedBatch = $resolvedBatch;
        $this->resolvedBatchId = $resolvedBatch?->getId();

        return $this;
    }

    public function getInventoryTaskId(): ?string
    {
        return $this->inventoryTaskId;
    }

    public function setInventoryTaskId(?string $inventoryTaskId): self
    {
        $this->inventoryTaskId = $inventoryTaskId;

        return $this;
    }

    public function getInventoryTask(): ?InventoryTask
    {
        return $this->inventoryTask;
    }

    public function setInventoryTask(?InventoryTask $inventoryTask): self
    {
        $this->inventoryTask = $inventoryTask;
        $this->inventoryTaskId = $inventoryTask?->getId();

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

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTime();

        return $this;
    }
}
