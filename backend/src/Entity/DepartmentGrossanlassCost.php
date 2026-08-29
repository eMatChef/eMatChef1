<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_cost')]
#[ORM\Index(name: 'idx_ga_cost_dept', columns: ['department_id'])]
#[ORM\Index(name: 'idx_ga_cost_dept_payer', columns: ['department_id', 'payer_group_id'])]
#[ORM\Index(name: 'idx_ga_cost_dept_requesting', columns: ['department_id', 'requesting_group_id'])]
#[ORM\Index(name: 'idx_ga_cost_dept_kind', columns: ['department_id', 'cost_kind'])]
#[ORM\Index(name: 'idx_ga_cost_line', columns: ['procurement_line_id'])]
#[ORM\Index(name: 'idx_ga_cost_commitment', columns: ['commitment_id'])]
class DepartmentGrossanlassCost
{
    public const KIND_PURCHASE = 'purchase';
    public const KIND_RENTAL = 'rental';
    public const KIND_LOAN = 'loan';
    public const KIND_BUY_RESALE = 'buy_resale';
    public const KIND_ANCILLARY = 'ancillary';

    /** @var list<string> */
    public const KINDS = [
        self::KIND_PURCHASE,
        self::KIND_RENTAL,
        self::KIND_LOAN,
        self::KIND_BUY_RESALE,
        self::KIND_ANCILLARY,
    ];

    public const ASSET_EXPENSE = 'expense';
    public const ASSET_INVENTORY = 'inventory';

    /** @var list<string> */
    public const ASSET_TREATMENTS = [self::ASSET_EXPENSE, self::ASSET_INVENTORY];

    public const STATUS_PLANNED = 'planned';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_PAID = 'paid';
    public const STATUS_FOR_SALE = 'for_sale';
    public const STATUS_SOLD = 'sold';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_COMMITTED,
        self::STATUS_PAID,
        self::STATUS_FOR_SALE,
        self::STATUS_SOLD,
        self::STATUS_RETURNED,
        self::STATUS_CANCELLED,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'procurement_line_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $procurementLineId = null;

    #[ORM\ManyToOne(targetEntity: ActivityGrossanlassProcurementLine::class)]
    #[ORM\JoinColumn(name: 'procurement_line_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ActivityGrossanlassProcurementLine $procurementLine = null;

    #[ORM\Column(name: 'commitment_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $commitmentId = null;

    #[ORM\ManyToOne(targetEntity: DepartmentGrossanlassCommitment::class)]
    #[ORM\JoinColumn(name: 'commitment_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?DepartmentGrossanlassCommitment $commitment = null;

    #[ORM\Column(name: 'cost_kind', type: 'string', length: 16)]
    private string $costKind = self::KIND_LOAN;

    #[ORM\Column(name: 'asset_treatment', type: 'string', length: 16, nullable: true)]
    private ?string $assetTreatment = null;

    #[ORM\Column(name: 'requesting_group_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $requestingGroupId = null;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(name: 'requesting_group_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Group $requestingGroup = null;

    #[ORM\Column(name: 'payer_group_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $payerGroupId = null;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(name: 'payer_group_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?Group $payerGroup = null;

    #[ORM\Column(name: 'category_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $categoryId = null;

    #[ORM\ManyToOne(targetEntity: ActivityGrossanlassProcurementCategory::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ActivityGrossanlassProcurementCategory $category = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $label = '';

    #[ORM\Column(name: 'partner_address_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $partnerAddressId = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(name: 'partner_address_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Address $partnerAddress = null;

    #[ORM\Column(name: 'soll_chf', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $sollChf = null;

    #[ORM\Column(name: 'cash_out_chf', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $cashOutChf = null;

    #[ORM\Column(name: 'deposit_chf', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $depositChf = null;

    #[ORM\Column(name: 'deposit_returned_chf', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $depositReturnedChf = null;

    #[ORM\Column(name: 'proceeds_expected_chf', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $proceedsExpectedChf = null;

    #[ORM\Column(name: 'proceeds_actual_chf', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $proceedsActualChf = null;

    #[ORM\Column(type: 'string', length: 16)]
    private string $status = self::STATUS_PLANNED;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): string
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

    public function getProcurementLineId(): ?string
    {
        return $this->procurementLineId;
    }

    public function getProcurementLine(): ?ActivityGrossanlassProcurementLine
    {
        return $this->procurementLine;
    }

    public function setProcurementLine(?ActivityGrossanlassProcurementLine $line): self
    {
        $this->procurementLine = $line;
        $this->procurementLineId = $line?->getId();

        return $this;
    }

    public function getCommitmentId(): ?string
    {
        return $this->commitmentId;
    }

    public function getCommitment(): ?DepartmentGrossanlassCommitment
    {
        return $this->commitment;
    }

    public function setCommitment(?DepartmentGrossanlassCommitment $commitment): self
    {
        $this->commitment = $commitment;
        $this->commitmentId = $commitment?->getId();

        return $this;
    }

    public function getCostKind(): string
    {
        return $this->costKind;
    }

    public function setCostKind(string $costKind): self
    {
        $this->costKind = $costKind;

        return $this;
    }

    public function getAssetTreatment(): ?string
    {
        return $this->assetTreatment;
    }

    public function setAssetTreatment(?string $assetTreatment): self
    {
        $this->assetTreatment = $assetTreatment;

        return $this;
    }

    public function getRequestingGroupId(): ?string
    {
        return $this->requestingGroupId;
    }

    public function getRequestingGroup(): ?Group
    {
        return $this->requestingGroup;
    }

    public function setRequestingGroup(?Group $group): self
    {
        $this->requestingGroup = $group;
        $this->requestingGroupId = $group?->getId();

        return $this;
    }

    public function getPayerGroupId(): ?string
    {
        return $this->payerGroupId;
    }

    public function getPayerGroup(): ?Group
    {
        return $this->payerGroup;
    }

    public function setPayerGroup(?Group $group): self
    {
        $this->payerGroup = $group;
        $this->payerGroupId = $group?->getId();

        return $this;
    }

    public function getCategoryId(): ?string
    {
        return $this->categoryId;
    }

    public function getCategory(): ?ActivityGrossanlassProcurementCategory
    {
        return $this->category;
    }

    public function setCategory(?ActivityGrossanlassProcurementCategory $category): self
    {
        $this->category = $category;
        $this->categoryId = $category?->getId();

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getPartnerAddressId(): ?string
    {
        return $this->partnerAddressId;
    }

    public function getPartnerAddress(): ?Address
    {
        return $this->partnerAddress;
    }

    public function setPartnerAddress(?Address $address): self
    {
        $this->partnerAddress = $address;
        $this->partnerAddressId = $address?->getId();

        return $this;
    }

    public function getSollChf(): ?string
    {
        return $this->sollChf;
    }

    public function setSollChf(?string $sollChf): self
    {
        $this->sollChf = $sollChf;

        return $this;
    }

    public function getCashOutChf(): ?string
    {
        return $this->cashOutChf;
    }

    public function setCashOutChf(?string $cashOutChf): self
    {
        $this->cashOutChf = $cashOutChf;

        return $this;
    }

    public function getDepositChf(): ?string
    {
        return $this->depositChf;
    }

    public function setDepositChf(?string $depositChf): self
    {
        $this->depositChf = $depositChf;

        return $this;
    }

    public function getDepositReturnedChf(): ?string
    {
        return $this->depositReturnedChf;
    }

    public function setDepositReturnedChf(?string $depositReturnedChf): self
    {
        $this->depositReturnedChf = $depositReturnedChf;

        return $this;
    }

    public function getProceedsExpectedChf(): ?string
    {
        return $this->proceedsExpectedChf;
    }

    public function setProceedsExpectedChf(?string $proceedsExpectedChf): self
    {
        $this->proceedsExpectedChf = $proceedsExpectedChf;

        return $this;
    }

    public function getProceedsActualChf(): ?string
    {
        return $this->proceedsActualChf;
    }

    public function setProceedsActualChf(?string $proceedsActualChf): self
    {
        $this->proceedsActualChf = $proceedsActualChf;

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

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function touchUpdatedAt(): self
    {
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function isMainKind(): bool
    {
        return $this->costKind !== self::KIND_ANCILLARY;
    }
}
