<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Standard-Zuordnung: Follow-up-Typ (source_kind) → Kostenstelle (+ optionale Defaults).
 */
#[ORM\Entity]
#[ORM\Table(name: 'accounting_cost_center_rule')]
#[ORM\UniqueConstraint(name: 'uk_accr_dept_source', columns: ['department_id', 'source_kind'])]
#[ORM\Index(name: 'idx_accr_dept', columns: ['department_id'])]
class AccountingCostCenterRule
{
    /** @var list<string> */
    public const SOURCE_KINDS = [
        AccountingAcquisitionFollowUp::SOURCE_BATCH,
        AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_CONSUMPTION,
        AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_REPLENISHMENT,
        AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_RENTAL,
        AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_WORKSHOP,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'source_kind', type: 'string', length: 32)]
    private string $sourceKind = '';

    #[ORM\ManyToOne(targetEntity: AccountingCostCenter::class)]
    #[ORM\JoinColumn(name: 'cost_center_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private AccountingCostCenter $costCenter;

    #[ORM\Column(name: 'default_entry_type', type: 'string', length: 32, nullable: true)]
    private ?string $defaultEntryType = null;

    #[ORM\Column(name: 'default_payment_method', type: 'string', length: 32, nullable: true)]
    private ?string $defaultPaymentMethod = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

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

    public function getDepartment(): Department
    {
        return $this->department;
    }

    public function setDepartment(Department $department): self
    {
        $this->department = $department;

        return $this;
    }

    public function getSourceKind(): string
    {
        return $this->sourceKind;
    }

    public function setSourceKind(string $sourceKind): self
    {
        $this->sourceKind = $sourceKind;

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

    public function getDefaultEntryType(): ?string
    {
        return $this->defaultEntryType;
    }

    public function setDefaultEntryType(?string $defaultEntryType): self
    {
        $this->defaultEntryType = $defaultEntryType;

        return $this;
    }

    public function getDefaultPaymentMethod(): ?string
    {
        return $this->defaultPaymentMethod;
    }

    public function setDefaultPaymentMethod(?string $defaultPaymentMethod): self
    {
        $this->defaultPaymentMethod = $defaultPaymentMethod;

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
