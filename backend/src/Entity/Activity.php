<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Activity - Zentrale Entität für Aktivitäten/Events/Vermietungen/Ausleihen
 * 
 * Ersetzt das alte Konzept von separaten "Orders" und "Loans" aus v4.
 * Eine Aktivität ist der Container für alles: Vermietungen, Events, Lager, interne Nutzung.
 */
#[ORM\Entity]
#[ORM\Table(name: 'activity')]
#[ORM\Index(name: 'idx_activity_department', columns: ['department_id'])]
#[ORM\Index(name: 'idx_activity_status', columns: ['status'])]
#[ORM\Index(name: 'idx_activity_type', columns: ['type'])]
#[ORM\Index(name: 'idx_activity_group', columns: ['group_id'])]
class Activity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    // Department (Owner)
    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    // Gruppe (Group-Entity, für die die Aktivität erstellt wird)
    #[ORM\Column(name: 'group_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $groupId = null;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Group $group = null;

    // Laufende Nummer pro Department (z.B. #001, #002)
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $no = null;

    // Grunddaten
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 7, nullable: true)]
    private ?string $color = null; // Hex-Farbe z.B. #4f46e5

    // Typ: activity, camp, event, external
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'activity'])]
    private string $type = 'activity';

    // Status: draft, submitted, approved, packing, packed, transport_out, at_event, transport_back, returned, storing, completed, cancelled
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'draft'])]
    private string $status = 'draft';

    /** false = Stepper-Zwischenstand, Detailansicht erst nach finalem Wizard-Schritt */
    #[ORM\Column(name: 'create_wizard_completed', type: 'boolean', options: ['default' => true])]
    private bool $createWizardCompleted = true;

    // Submitted-Timestamp (wann der Leader freigegeben hat)
    #[ORM\Column(name: 'submitted_at', type: 'datetime', nullable: true)]
    private ?\DateTime $submittedAt = null;

    // Approved-Timestamp (wann der Materialwart bestätigt hat)
    #[ORM\Column(name: 'approved_at', type: 'datetime', nullable: true)]
    private ?\DateTime $approvedAt = null;

    // Issued-Timestamp (wann Material ausgegeben wurde)
    #[ORM\Column(name: 'issued_at', type: 'datetime', nullable: true)]
    private ?\DateTime $issuedAt = null;

    // Returned-Timestamp (wann Material zurückkam)
    #[ORM\Column(name: 'returned_at', type: 'datetime', nullable: true)]
    private ?\DateTime $returnedAt = null;

    // Completed-Timestamp
    #[ORM\Column(name: 'completed_at', type: 'datetime', nullable: true)]
    private ?\DateTime $completedAt = null;

    // Kommentar bei Zurückweisung (approved → submitted)
    #[ORM\Column(name: 'rejection_comment', type: 'text', nullable: true)]
    private ?string $rejectionComment = null;

    // Zeiträume
    #[ORM\Column(name: 'planning_start', type: 'datetime', nullable: true)]
    private ?\DateTime $planningStart = null;

    #[ORM\Column(name: 'planning_end', type: 'datetime', nullable: true)]
    private ?\DateTime $planningEnd = null;

    #[ORM\Column(name: 'usage_start', type: 'datetime', nullable: true)]
    private ?\DateTime $usageStart = null;

    #[ORM\Column(name: 'usage_end', type: 'datetime', nullable: true)]
    private ?\DateTime $usageEnd = null;

    // Kunden-/Mieteradresse (z. B. bei Typ extern)
    #[ORM\Column(name: 'address_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $addressId = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(name: 'address_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Address $address = null;

    // Eventstandort (Lager, Event, extern)
    #[ORM\Column(name: 'venue_address_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $venueAddressId = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(name: 'venue_address_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Address $venueAddress = null;

    /** J+S-Leihmaterial: Lieferadresse (kann vom Eventstandort abweichen) */
    #[ORM\Column(name: 'js_delivery_address_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $jsDeliveryAddressId = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(name: 'js_delivery_address_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Address $jsDeliveryAddress = null;

    // Verantwortlicher User
    #[ORM\Column(name: 'responsible_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $responsibleUserId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'responsible_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $responsibleUser = null;

    // Ersteller
    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $createdByUserId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdByUser = null;

    #[ORM\Column(name: 'submitted_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $submittedByUserId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'submitted_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $submittedByUser = null;

    // Preise (für Vermietungen)
    /** pricing_mode: 'set_price' (Pauschal) oder 'item_price' (Einzelpreise pro Artikel) */
    #[ORM\Column(name: 'pricing_mode', type: 'string', length: 20, nullable: true, options: ['default' => 'item_price'])]
    private ?string $pricingMode = 'item_price';

    #[ORM\Column(name: 'total_price', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $totalPrice = null;

    #[ORM\Column(name: 'deposit_amount', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $depositAmount = null;

    #[ORM\Column(name: 'deposit_paid', type: 'boolean', options: ['default' => false])]
    private bool $depositPaid = false;

    #[ORM\Column(name: 'is_paid', type: 'boolean', options: ['default' => false])]
    private bool $isPaid = false;

    // Material-Anzahl (Cache, wird bei Änderung aktualisiert)
    #[ORM\Column(name: 'item_count', type: 'integer', options: ['default' => 0])]
    private int $itemCount = 0;

    // Notizen
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    // Eingeladene Departments (Camp/Event)
    #[ORM\Column(name: 'invited_departments', type: 'json', nullable: true)]
    private ?array $invitedDepartments = null;

    /** Camp/Event: Leiter plant J+S-Leihmaterial (separates Bestellformular, nicht Material-Suche) */
    #[ORM\Column(name: 'wants_js_material', type: 'boolean', options: ['default' => false])]
    private bool $wantsJsMaterial = false;

    /** Camp/Event: Teilnehmerzahl für J+S-Dotation (optional bis Formular) */
    #[ORM\Column(name: 'participant_count', type: 'integer', nullable: true)]
    private ?int $participantCount = null;

    // Timestamps
    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    #[ORM\Column(name: 'deleted_at', type: 'datetime', nullable: true)]
    private ?\DateTime $deletedAt = null;

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

    public function getGroupId(): ?string
    {
        return $this->groupId;
    }

    public function setGroupId(?string $groupId): self
    {
        $this->groupId = $groupId;
        return $this;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): self
    {
        $this->group = $group;
        $this->groupId = $group?->getId();
        return $this;
    }

    public function getNo(): ?int
    {
        return $this->no;
    }

    public function setNo(?int $no): self
    {
        $this->no = $no;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
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

    public function isCreateWizardCompleted(): bool
    {
        return $this->createWizardCompleted;
    }

    public function setCreateWizardCompleted(bool $createWizardCompleted): self
    {
        $this->createWizardCompleted = $createWizardCompleted;
        return $this;
    }

    public function getPlanningStart(): ?\DateTime
    {
        return $this->planningStart;
    }

    public function setPlanningStart(?\DateTime $planningStart): self
    {
        $this->planningStart = $planningStart;
        return $this;
    }

    public function getPlanningEnd(): ?\DateTime
    {
        return $this->planningEnd;
    }

    public function setPlanningEnd(?\DateTime $planningEnd): self
    {
        $this->planningEnd = $planningEnd;
        return $this;
    }

    public function getUsageStart(): ?\DateTime
    {
        return $this->usageStart;
    }

    public function setUsageStart(?\DateTime $usageStart): self
    {
        $this->usageStart = $usageStart;
        return $this;
    }

    public function getUsageEnd(): ?\DateTime
    {
        return $this->usageEnd;
    }

    public function setUsageEnd(?\DateTime $usageEnd): self
    {
        $this->usageEnd = $usageEnd;
        return $this;
    }

    public function getAddressId(): ?string
    {
        return $this->addressId;
    }

    public function setAddressId(?string $addressId): self
    {
        $this->addressId = $addressId;
        return $this;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): self
    {
        $this->address = $address;
        $this->addressId = $address?->getId();
        return $this;
    }

    public function getVenueAddressId(): ?string
    {
        return $this->venueAddressId;
    }

    public function setVenueAddressId(?string $venueAddressId): self
    {
        $this->venueAddressId = $venueAddressId;
        return $this;
    }

    public function getVenueAddress(): ?Address
    {
        return $this->venueAddress;
    }

    public function setVenueAddress(?Address $venueAddress): self
    {
        $this->venueAddress = $venueAddress;
        $this->venueAddressId = $venueAddress?->getId();
        return $this;
    }

    public function getJsDeliveryAddressId(): ?string
    {
        return $this->jsDeliveryAddressId;
    }

    public function setJsDeliveryAddressId(?string $jsDeliveryAddressId): self
    {
        $this->jsDeliveryAddressId = $jsDeliveryAddressId;
        return $this;
    }

    public function getJsDeliveryAddress(): ?Address
    {
        return $this->jsDeliveryAddress;
    }

    public function setJsDeliveryAddress(?Address $jsDeliveryAddress): self
    {
        $this->jsDeliveryAddress = $jsDeliveryAddress;
        $this->jsDeliveryAddressId = $jsDeliveryAddress?->getId();
        return $this;
    }

    public function getResponsibleUserId(): ?string
    {
        return $this->responsibleUserId;
    }

    public function setResponsibleUserId(?string $responsibleUserId): self
    {
        $this->responsibleUserId = $responsibleUserId;
        return $this;
    }

    public function getResponsibleUser(): ?User
    {
        return $this->responsibleUser;
    }

    public function setResponsibleUser(?User $responsibleUser): self
    {
        $this->responsibleUser = $responsibleUser;
        $this->responsibleUserId = $responsibleUser?->getId();
        return $this;
    }

    public function getCreatedByUserId(): ?string
    {
        return $this->createdByUserId;
    }

    public function setCreatedByUserId(?string $createdByUserId): self
    {
        $this->createdByUserId = $createdByUserId;
        return $this;
    }

    public function getCreatedByUser(): ?User
    {
        return $this->createdByUser;
    }

    public function setCreatedByUser(?User $createdByUser): self
    {
        $this->createdByUser = $createdByUser;
        $this->createdByUserId = $createdByUser?->getId();
        return $this;
    }

    public function getSubmittedByUserId(): ?string
    {
        return $this->submittedByUserId;
    }

    public function setSubmittedByUserId(?string $submittedByUserId): self
    {
        $this->submittedByUserId = $submittedByUserId;
        return $this;
    }

    public function getSubmittedByUser(): ?User
    {
        return $this->submittedByUser;
    }

    public function setSubmittedByUser(?User $submittedByUser): self
    {
        $this->submittedByUser = $submittedByUser;
        $this->submittedByUserId = $submittedByUser?->getId();
        return $this;
    }

    public function getPricingMode(): ?string
    {
        return $this->pricingMode;
    }

    public function setPricingMode(?string $pricingMode): self
    {
        $this->pricingMode = $pricingMode;
        return $this;
    }

    public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(?string $totalPrice): self
    {
        $this->totalPrice = $totalPrice;
        return $this;
    }

    public function getDepositAmount(): ?string
    {
        return $this->depositAmount;
    }

    public function setDepositAmount(?string $depositAmount): self
    {
        $this->depositAmount = $depositAmount;
        return $this;
    }

    public function isDepositPaid(): bool
    {
        return $this->depositPaid;
    }

    public function setDepositPaid(bool $depositPaid): self
    {
        $this->depositPaid = $depositPaid;
        return $this;
    }

    public function isPaid(): bool
    {
        return $this->isPaid;
    }

    public function setIsPaid(bool $isPaid): self
    {
        $this->isPaid = $isPaid;
        return $this;
    }

    public function getItemCount(): int
    {
        return $this->itemCount;
    }

    public function setItemCount(int $itemCount): self
    {
        $this->itemCount = $itemCount;
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

    public function getInvitedDepartments(): ?array
    {
        return $this->invitedDepartments;
    }

    public function setInvitedDepartments(?array $invitedDepartments): self
    {
        $this->invitedDepartments = $invitedDepartments;
        return $this;
    }

    public function getWantsJsMaterial(): bool
    {
        return $this->wantsJsMaterial;
    }

    public function setWantsJsMaterial(bool $wantsJsMaterial): self
    {
        $this->wantsJsMaterial = $wantsJsMaterial;
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

    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTime $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    // === Workflow Timestamps Getters/Setters ===

    public function getSubmittedAt(): ?\DateTime
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTime $submittedAt): self
    {
        $this->submittedAt = $submittedAt;
        return $this;
    }

    public function getApprovedAt(): ?\DateTime
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTime $approvedAt): self
    {
        $this->approvedAt = $approvedAt;
        return $this;
    }

    public function getIssuedAt(): ?\DateTime
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(?\DateTime $issuedAt): self
    {
        $this->issuedAt = $issuedAt;
        return $this;
    }

    public function getReturnedAt(): ?\DateTime
    {
        return $this->returnedAt;
    }

    public function setReturnedAt(?\DateTime $returnedAt): self
    {
        $this->returnedAt = $returnedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTime
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTime $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getRejectionComment(): ?string
    {
        return $this->rejectionComment;
    }

    public function setRejectionComment(?string $rejectionComment): self
    {
        $this->rejectionComment = $rejectionComment;
        return $this;
    }

    // === Status Constants ===

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PACKING = 'packing';
    public const STATUS_PACKED = 'packed';
    public const STATUS_TRANSPORT_OUT = 'transport_out';
    /** Material am Event / bei der Gruppe (Stepper-UI: «Am Anlass» / issue) */
    public const STATUS_AT_EVENT = 'at_event';
    public const STATUS_TRANSPORT_BACK = 'transport_back';
    public const STATUS_RETURNED = 'returned';
    /** MW lagert Material ein (Stepper-UI: store) */
    public const STATUS_STORING = 'storing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const ALL_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_APPROVED,
        self::STATUS_PACKING,
        self::STATUS_PACKED,
        self::STATUS_TRANSPORT_OUT,
        self::STATUS_AT_EVENT,
        self::STATUS_TRANSPORT_BACK,
        self::STATUS_RETURNED,
        self::STATUS_STORING,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /** camp/event = Logistics-Workflow mit Transport-Stufen */
    public static function usesLogisticsWorkflow(?string $activityType): bool
    {
        return \in_array($activityType ?? '', ['camp', 'event'], true);
    }

    /**
     * Erlaubte Status-Übergänge (Superset; Profil-Filter in ActivityController::getTransitions).
     */
    public const STATUS_TRANSITIONS = [
        self::STATUS_DRAFT     => [self::STATUS_SUBMITTED, self::STATUS_CANCELLED],
        self::STATUS_SUBMITTED => [self::STATUS_APPROVED, self::STATUS_PACKING, self::STATUS_CANCELLED],
        self::STATUS_APPROVED  => [self::STATUS_PACKING, self::STATUS_SUBMITTED, self::STATUS_CANCELLED],
        self::STATUS_PACKING   => [self::STATUS_PACKED, self::STATUS_CANCELLED],
        self::STATUS_PACKED    => [self::STATUS_TRANSPORT_OUT, self::STATUS_AT_EVENT, self::STATUS_PACKING, self::STATUS_CANCELLED],
        self::STATUS_TRANSPORT_OUT => [self::STATUS_AT_EVENT, self::STATUS_PACKED],
        self::STATUS_AT_EVENT  => [self::STATUS_TRANSPORT_BACK, self::STATUS_RETURNED, self::STATUS_PACKED],
        self::STATUS_TRANSPORT_BACK => [self::STATUS_RETURNED, self::STATUS_AT_EVENT],
        self::STATUS_RETURNED  => [self::STATUS_STORING, self::STATUS_AT_EVENT],
        self::STATUS_STORING   => [self::STATUS_COMPLETED, self::STATUS_RETURNED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    /**
     * Profilabhängige Ziel-Status aus STATUS_TRANSITIONS filtern.
     *
     * @param list<string> $targets
     * @return list<string>
     */
    public static function filterTransitionTargets(string $currentStatus, ?string $activityType, array $targets): array
    {
        $logistics = self::usesLogisticsWorkflow($activityType);

        return array_values(array_filter($targets, static function (string $target) use ($currentStatus, $logistics): bool {
            if ($currentStatus === self::STATUS_PACKED) {
                if ($logistics && $target === self::STATUS_AT_EVENT) {
                    return false;
                }
                if (!$logistics && $target === self::STATUS_TRANSPORT_OUT) {
                    return false;
                }
            }
            if ($currentStatus === self::STATUS_AT_EVENT) {
                if ($logistics && $target === self::STATUS_RETURNED) {
                    return false;
                }
                if (!$logistics && $target === self::STATUS_TRANSPORT_BACK) {
                    return false;
                }
            }
            if ($currentStatus === self::STATUS_RETURNED && $target === self::STATUS_COMPLETED) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Wer darf welchen Übergang durchführen?
     * Format: "from->to" => ['required_role1', 'required_role2']
     * 
     * Rollen-Kontext:
     * - 'member': Gruppenmitglied (GroupMembership role=member)
     * - 'leader': Gruppenleiter (GroupMembership role=leader)
     * - 'mw': Materialwart (Membership role=mw)
     * - 'dc': Abteilungskoordination (Membership role=dc)
     * - 'creator': Ersteller der Aktivität (User-Id-Vergleich, nur bei passenden Übergängen)
     * - 'sa': Super-Admin
     * - 'org': Organisations-Admin
     */
    public const TRANSITION_PERMISSIONS = [
        // Einreichen: Ersteller, Gruppenleiter, DC, MW (Gruppenmitglied darf eigene Aktivität einreichen)
        'draft->submitted'    => ['creator', 'leader', 'dc', 'mw'],
        'draft->cancelled'    => ['leader', 'member', 'u', 'l1', 'l2', 'l3', 'dc', 'mw', 'sub', 'org', 'sa'],
        // Bestätigen / direkt Packen: MW, DC, Gruppenleiter, Org-Admins (nicht reines Gruppenmitglied)
        'submitted->approved' => ['mw', 'dc', 'leader', 'org', 'sa'],
        'submitted->packing'  => ['mw', 'dc', 'leader', 'org', 'sa'], // Annehmen & direkt Packen
        'submitted->cancelled'=> ['leader', 'mw', 'sa', 'org'],
        'approved->packing'   => ['mw', 'sa', 'org'],
        'approved->submitted' => ['mw', 'sa', 'org'], // Zurückweisung
        'approved->cancelled' => ['mw', 'dc', 'sa', 'org'],
        'packing->packed'     => ['mw', 'sa', 'org'],
        'packing->cancelled'  => ['mw', 'dc', 'sa', 'org'],
        'packed->at_event'    => ['mw', 'sa', 'org', 'creator', 'member'],
        'packed->transport_out' => ['mw', 'sa', 'org', 'creator', 'member'],
        'packed->packing'     => ['mw', 'dc', 'sa', 'org'],
        'packed->cancelled'   => ['mw', 'dc', 'sa', 'org'],
        'transport_out->at_event' => ['mw', 'sa', 'org', 'creator', 'member'],
        'transport_out->packed' => ['mw', 'dc', 'sa', 'org'],
        'at_event->packed'    => ['mw', 'dc', 'sa', 'org'],
        'at_event->transport_back' => ['mw', 'sa', 'org', 'creator', 'member'],
        'at_event->returned'  => ['mw', 'sa', 'org', 'creator', 'member'],
        'transport_back->returned' => ['mw', 'sa', 'org', 'creator', 'member'],
        'transport_back->at_event' => ['mw', 'dc', 'sa', 'org'],
        'returned->at_event'  => ['mw', 'dc', 'sa', 'org'],
        'returned->storing'   => ['mw', 'sa', 'org'],
        'storing->completed'  => ['mw', 'sa', 'org'],
        'storing->returned'   => ['mw', 'dc', 'sa', 'org'],
    ];

    /**
     * Prüft ob ein Status-Übergang erlaubt ist
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = self::STATUS_TRANSITIONS[$this->status] ?? [];
        return in_array($newStatus, $allowed, true);
    }

    /**
     * Gibt die erlaubten Rollen für einen Übergang zurück
     */
    public static function getTransitionPermissions(string $fromStatus, string $toStatus): array
    {
        $key = $fromStatus . '->' . $toStatus;
        return self::TRANSITION_PERMISSIONS[$key] ?? [];
    }

    // === Helper Methods ===

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPacking(): bool
    {
        return $this->status === self::STATUS_PACKING;
    }

    public function isPacked(): bool
    {
        return $this->status === self::STATUS_PACKED;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_AT_EVENT;
    }

    public function isAtEvent(): bool
    {
        return $this->isIssued();
    }

    public function isReturned(): bool
    {
        return $this->status === self::STATUS_RETURNED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Prüft ob die Aktivität storniert werden kann
     * (Nur vor der Ausgabe möglich)
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_APPROVED,
            self::STATUS_PACKING,
            self::STATUS_PACKED,
        ]);
    }

    /**
     * Prüft ob Material bearbeitet werden kann (nur im Entwurf)
     */
    public function isMaterialEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT]);
    }

    /**
     * Prüft ob die Packliste bearbeitet werden kann
     */
    public function isPackListEditable(): bool
    {
        return in_array($this->status, [
            self::STATUS_PACKING,
            self::STATUS_PACKED,
            self::STATUS_TRANSPORT_OUT,
            self::STATUS_AT_EVENT,
            self::STATUS_TRANSPORT_BACK,
            self::STATUS_RETURNED,
            self::STATUS_STORING,
        ], true);
    }

    /**
     * Prüft ob Meldungen (Reparatur/Verlust/Verbrauch) erstellt werden können.
     * Ab «Am Anlass»; Quick (activity/external) zusätzlich ab «gepackt» in der Ausgabe-Phase.
     */
    public function canReportIssues(): bool
    {
        if (in_array($this->status, [
            self::STATUS_AT_EVENT,
            self::STATUS_RETURNED,
        ], true)) {
            return true;
        }

        if ($this->status === self::STATUS_PACKED && ($this->isActivity() || $this->isExternal())) {
            return true;
        }

        return false;
    }

    /**
     * Prüft ob die Rückgabe erfasst werden kann
     */
    public function isReturnEditable(): bool
    {
        return $this->status === self::STATUS_RETURNED;
    }

    /**
     * Setzt den Workflow-Timestamp basierend auf dem neuen Status
     */
    public function applyStatusTimestamp(string $newStatus): void
    {
        $now = new \DateTime();
        match ($newStatus) {
            self::STATUS_SUBMITTED => $this->submittedAt = $now,
            self::STATUS_APPROVED  => $this->approvedAt = $now,
            self::STATUS_AT_EVENT    => $this->issuedAt = $now,
            self::STATUS_RETURNED  => $this->returnedAt = $now,
            self::STATUS_COMPLETED => $this->completedAt = $now,
            default => null,
        };
    }

    public function isActivity(): bool
    {
        return $this->type === 'activity';
    }

    public function isEvent(): bool
    {
        return $this->type === 'event';
    }

    public function isCamp(): bool
    {
        return $this->type === 'camp';
    }

    public function isExternal(): bool
    {
        return $this->type === 'external';
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }
}
