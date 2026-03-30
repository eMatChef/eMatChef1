<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Buchung / Kostenfall (CHF, kein Doppelbuch).
 * ID: kb + Jahr + Hex (13 Zeichen), siehe IdGenerator::generate13.
 */
#[ORM\Entity]
#[ORM\Table(name: 'accounting_booking')]
#[ORM\Index(name: 'idx_ab_department_booked', columns: ['department_id', 'booked_at'])]
#[ORM\Index(name: 'idx_ab_cost_center', columns: ['cost_center_id'])]
#[ORM\Index(name: 'idx_ab_material_item', columns: ['department_id', 'material_item_id'])]
class AccountingBooking
{
    public const ENTRY_PURCHASE = 'purchase';
    public const ENTRY_REPAIR_EXTERNAL = 'repair_external';
    public const ENTRY_REPAIR_INTERNAL = 'repair_internal';
    public const ENTRY_AMORTIZATION = 'amortization';
    public const ENTRY_OTHER = 'other';

    public const PAYMENT_ADVANCE_MW = 'advance_mw';
    public const PAYMENT_CASH_GROUP = 'cash_group';
    public const PAYMENT_SUPPLIER = 'supplier_invoice';
    public const PAYMENT_ASSOCIATION = 'association';
    public const PAYMENT_OTHER = 'other';

    /** @var list<string> */
    public const ENTRY_TYPES = [
        self::ENTRY_PURCHASE,
        self::ENTRY_REPAIR_EXTERNAL,
        self::ENTRY_REPAIR_INTERNAL,
        self::ENTRY_AMORTIZATION,
        self::ENTRY_OTHER,
    ];

    /** @var list<string> */
    public const PAYMENT_METHODS = [
        self::PAYMENT_ADVANCE_MW,
        self::PAYMENT_CASH_GROUP,
        self::PAYMENT_SUPPLIER,
        self::PAYMENT_ASSOCIATION,
        self::PAYMENT_OTHER,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\ManyToOne(targetEntity: AccountingCostCenter::class)]
    #[ORM\JoinColumn(name: 'cost_center_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private AccountingCostCenter $costCenter;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Group $group = null;

    #[ORM\ManyToOne(targetEntity: MaterialItem::class)]
    #[ORM\JoinColumn(name: 'material_item_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MaterialItem $materialItem = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(name: 'booked_at', type: 'date_immutable')]
    private \DateTimeImmutable $bookedAt;

    #[ORM\Column(name: 'entry_type', type: 'string', length: 32)]
    private string $entryType = self::ENTRY_OTHER;

    #[ORM\Column(name: 'payment_method', type: 'string', length: 32, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(name: 'receipt_label', type: 'string', length: 255, nullable: true)]
    private ?string $receiptLabel = null;

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
        $this->bookedAt = new \DateTimeImmutable('today');
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

    public function getCostCenter(): AccountingCostCenter
    {
        return $this->costCenter;
    }

    public function setCostCenter(AccountingCostCenter $costCenter): self
    {
        $this->costCenter = $costCenter;
        return $this;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): self
    {
        $this->group = $group;
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

    public function getBookedAt(): \DateTimeImmutable
    {
        return $this->bookedAt;
    }

    public function setBookedAt(\DateTimeImmutable $bookedAt): self
    {
        $this->bookedAt = $bookedAt;
        return $this;
    }

    public function getEntryType(): string
    {
        return $this->entryType;
    }

    public function setEntryType(string $entryType): self
    {
        $this->entryType = $entryType;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;
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
