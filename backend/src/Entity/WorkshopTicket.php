<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * WorkshopTicket - Werkstatt-Aufträge
 * 
 * Typen:
 * - repair: Reparatur
 * - inspection: Inspektion / Prüfung
 * - writeoff: Abschreibung
 * - cleaning: Reinigung
 * 
 * Prioritäten: low, normal, high, urgent
 * Status: open, in_progress, waiting_parts, completed, cancelled
 */
#[ORM\Entity]
#[ORM\Table(name: 'workshop_ticket')]
#[ORM\Index(name: 'idx_workshop_department', columns: ['department_id'])]
#[ORM\Index(name: 'idx_workshop_status', columns: ['status'])]
#[ORM\Index(name: 'idx_workshop_material', columns: ['material_item_id'])]
#[ORM\Index(name: 'idx_workshop_type', columns: ['type'])]
#[ORM\Index(name: 'idx_workshop_priority', columns: ['priority'])]
class WorkshopTicket
{
    // === Type Constants ===
    public const TYPE_REPAIR = 'repair';
    public const TYPE_INSPECTION = 'inspection';
    public const TYPE_WRITEOFF = 'writeoff';
    public const TYPE_CLEANING = 'cleaning';

    public const ALL_TYPES = [
        self::TYPE_REPAIR,
        self::TYPE_INSPECTION,
        self::TYPE_WRITEOFF,
        self::TYPE_CLEANING,
    ];

    // === Priority Constants ===
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const ALL_PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    // === Status Constants ===
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_WAITING_PARTS = 'waiting_parts';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const ALL_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_WAITING_PARTS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    // === Erlaubte Status-Übergänge ===
    public const STATUS_TRANSITIONS = [
        self::STATUS_OPEN           => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS    => [self::STATUS_WAITING_PARTS, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_WAITING_PARTS  => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED      => [],
        self::STATUS_CANCELLED      => [self::STATUS_OPEN], // Wiedereröffnen erlaubt
    ];

    // === Fields ===

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'material_item_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $materialItemId;

    #[ORM\ManyToOne(targetEntity: MaterialItem::class)]
    #[ORM\JoinColumn(name: 'material_item_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialItem $materialItem;

    /** Bei serialisierten Artikeln: konkrete Instanz (Charge/Seriennummer) */
    #[ORM\Column(name: 'material_batch_id', type: 'string', length: 13, nullable: true, columnDefinition: 'CHARACTER(13) NULL')]
    private ?string $materialBatchId = null;

    #[ORM\ManyToOne(targetEntity: MaterialBatch::class)]
    #[ORM\JoinColumn(name: 'material_batch_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MaterialBatch $materialBatch = null;

    #[ORM\Column(name: 'activity_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $activityId = null;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'activity_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Activity $activity = null;

    #[ORM\Column(name: 'issue_report_id', type: 'string', length: 13, nullable: true, columnDefinition: 'CHARACTER(13) NULL')]
    private ?string $issueReportId = null;

    #[ORM\ManyToOne(targetEntity: ActivityIssueReport::class)]
    #[ORM\JoinColumn(name: 'issue_report_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ActivityIssueReport $issueReport = null;

    /** Typ: repair, inspection, writeoff, cleaning */
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'repair'])]
    private string $type = 'repair';

    /** Priorität: low, normal, high, urgent */
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'normal'])]
    private string $priority = 'normal';

    /** Status: open, in_progress, waiting_parts, completed, cancelled */
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'open'])]
    private string $status = 'open';

    /** Betroffene Menge (Bulk); bei Serialisiertem implizit 1 */
    #[ORM\Column(name: 'affected_quantity', type: 'integer', nullable: true)]
    private ?int $affectedQuantity = null;

    /** Titel */
    #[ORM\Column(type: 'string', length: 200)]
    private string $title;

    /** Beschreibung */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** Zugewiesen an */
    #[ORM\Column(name: 'assigned_to_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $assignedToUserId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'assigned_to_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $assignedToUser = null;

    #[ORM\Column(name: 'assigned_to_supplier_company_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $assignedToSupplierCompanyId = null;

    #[ORM\ManyToOne(targetEntity: SupplierCompany::class)]
    #[ORM\JoinColumn(name: 'assigned_to_supplier_company_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?SupplierCompany $assignedToSupplierCompany = null;

    /** Geschätzte Kosten */
    #[ORM\Column(name: 'estimated_cost', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $estimatedCost = null;

    /** Tatsächliche Kosten */
    #[ORM\Column(name: 'actual_cost', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $actualCost = null;

    /** Verwendete Ersatzteile (JSON) */
    #[ORM\Column(name: 'parts_used', type: 'json', nullable: true)]
    private ?array $partsUsed = null;

    /** Fotos (JSON array of URLs) */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $photos = null;

    /** Wann gestartet */
    #[ORM\Column(name: 'started_at', type: 'datetime', nullable: true)]
    private ?\DateTime $startedAt = null;

    /** Wann abgeschlossen */
    #[ORM\Column(name: 'completed_at', type: 'datetime', nullable: true)]
    private ?\DateTime $completedAt = null;

    /** Erstellt von */
    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $createdByUserId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdByUser = null;

    /** Ergebnis/Abschluss-Notiz */
    #[ORM\Column(name: 'resolution_notes', type: 'text', nullable: true)]
    private ?string $resolutionNotes = null;

    /** Ergebnis-Aktion bei Abschluss: repaired, writeoff, ok */
    #[ORM\Column(name: 'resolution_action', type: 'string', length: 20, nullable: true)]
    private ?string $resolutionAction = null;

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

    public function getDepartmentId(): string { return $this->departmentId; }
    public function setDepartmentId(string $departmentId): self { $this->departmentId = $departmentId; return $this; }

    public function getDepartment(): Department { return $this->department; }
    public function setDepartment(Department $department): self
    {
        $this->department = $department;
        $this->departmentId = $department->getId();
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

    public function getMaterialBatchId(): ?string { return $this->materialBatchId; }
    public function setMaterialBatchId(?string $materialBatchId): self { $this->materialBatchId = $materialBatchId; return $this; }

    public function getMaterialBatch(): ?MaterialBatch { return $this->materialBatch; }
    public function setMaterialBatch(?MaterialBatch $materialBatch): self
    {
        $this->materialBatch = $materialBatch;
        $this->materialBatchId = $materialBatch?->getId();
        return $this;
    }

    public function getActivityId(): ?string { return $this->activityId; }
    public function setActivityId(?string $activityId): self { $this->activityId = $activityId; return $this; }

    public function getActivity(): ?Activity { return $this->activity; }
    public function setActivity(?Activity $activity): self
    {
        $this->activity = $activity;
        $this->activityId = $activity?->getId();
        return $this;
    }

    public function getIssueReportId(): ?string { return $this->issueReportId; }
    public function setIssueReportId(?string $issueReportId): self { $this->issueReportId = $issueReportId; return $this; }

    public function getIssueReport(): ?ActivityIssueReport { return $this->issueReport; }
    public function setIssueReport(?ActivityIssueReport $issueReport): self
    {
        $this->issueReport = $issueReport;
        $this->issueReportId = $issueReport?->getId();
        return $this;
    }

    public function getAffectedQuantity(): ?int { return $this->affectedQuantity; }
    public function setAffectedQuantity(?int $affectedQuantity): self { $this->affectedQuantity = $affectedQuantity; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getPriority(): string { return $this->priority; }
    public function setPriority(string $priority): self { $this->priority = $priority; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getAssignedToUserId(): ?string { return $this->assignedToUserId; }
    public function setAssignedToUserId(?string $assignedToUserId): self { $this->assignedToUserId = $assignedToUserId; return $this; }

    public function getAssignedToUser(): ?User { return $this->assignedToUser; }
    public function setAssignedToUser(?User $user): self
    {
        $this->assignedToUser = $user;
        $this->assignedToUserId = $user?->getId();
        return $this;
    }

    public function getAssignedToSupplierCompanyId(): ?string
    {
        return $this->assignedToSupplierCompanyId;
    }

    public function setAssignedToSupplierCompanyId(?string $assignedToSupplierCompanyId): self
    {
        $this->assignedToSupplierCompanyId = $assignedToSupplierCompanyId;
        return $this;
    }

    public function getAssignedToSupplierCompany(): ?SupplierCompany
    {
        return $this->assignedToSupplierCompany;
    }

    public function setAssignedToSupplierCompany(?SupplierCompany $company): self
    {
        $this->assignedToSupplierCompany = $company;
        $this->assignedToSupplierCompanyId = $company?->getId();
        return $this;
    }

    public function getEstimatedCost(): ?string { return $this->estimatedCost; }
    public function setEstimatedCost(?string $estimatedCost): self { $this->estimatedCost = $estimatedCost; return $this; }

    public function getActualCost(): ?string { return $this->actualCost; }
    public function setActualCost(?string $actualCost): self { $this->actualCost = $actualCost; return $this; }

    public function getPartsUsed(): ?array { return $this->partsUsed; }
    public function setPartsUsed(?array $partsUsed): self { $this->partsUsed = $partsUsed; return $this; }

    public function getPhotos(): ?array { return $this->photos; }
    public function setPhotos(?array $photos): self { $this->photos = $photos; return $this; }

    public function getStartedAt(): ?\DateTime { return $this->startedAt; }
    public function setStartedAt(?\DateTime $startedAt): self { $this->startedAt = $startedAt; return $this; }

    public function getCompletedAt(): ?\DateTime { return $this->completedAt; }
    public function setCompletedAt(?\DateTime $completedAt): self { $this->completedAt = $completedAt; return $this; }

    public function getCreatedByUserId(): ?string { return $this->createdByUserId; }
    public function setCreatedByUserId(?string $createdByUserId): self { $this->createdByUserId = $createdByUserId; return $this; }

    public function getCreatedByUser(): ?User { return $this->createdByUser; }
    public function setCreatedByUser(?User $user): self
    {
        $this->createdByUser = $user;
        $this->createdByUserId = $user?->getId();
        return $this;
    }

    public function getResolutionNotes(): ?string { return $this->resolutionNotes; }
    public function setResolutionNotes(?string $resolutionNotes): self { $this->resolutionNotes = $resolutionNotes; return $this; }

    public function getResolutionAction(): ?string { return $this->resolutionAction; }
    public function setResolutionAction(?string $resolutionAction): self { $this->resolutionAction = $resolutionAction; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }
    public function setUpdatedAt(\DateTime $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // === Helper Methods ===

    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = self::STATUS_TRANSITIONS[$this->status] ?? [];
        return in_array($newStatus, $allowed);
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            self::TYPE_REPAIR => 'Reparatur',
            self::TYPE_INSPECTION => 'Inspektion',
            self::TYPE_WRITEOFF => 'Abschreibung',
            self::TYPE_CLEANING => 'Reinigung',
            default => $this->type,
        };
    }

    public function getPriorityLabel(): string
    {
        return match($this->priority) {
            self::PRIORITY_LOW => 'Niedrig',
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_HIGH => 'Hoch',
            self::PRIORITY_URGENT => 'Dringend',
            default => $this->priority,
        };
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_OPEN => 'Offen',
            self::STATUS_IN_PROGRESS => 'In Arbeit',
            self::STATUS_WAITING_PARTS => 'Wartet auf Teile',
            self::STATUS_COMPLETED => 'Erledigt',
            self::STATUS_CANCELLED => 'Abgebrochen',
            default => $this->status,
        };
    }

    public function isOpen(): bool { return $this->status === self::STATUS_OPEN; }
    public function isInProgress(): bool { return $this->status === self::STATUS_IN_PROGRESS; }
    public function isCompleted(): bool { return $this->status === self::STATUS_COMPLETED; }
    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }
}
