<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Geplantes Budget (Soll) in CHF pro Kostenstelle und Kalenderjahr.
 * ID: bg + Jahr + Hex (13 Zeichen).
 */
#[ORM\Entity]
#[ORM\Table(name: 'accounting_budget_line')]
#[ORM\UniqueConstraint(name: 'uk_abl_dept_cc_year', columns: ['department_id', 'cost_center_id', 'calendar_year'])]
#[ORM\Index(name: 'idx_abl_dept_year', columns: ['department_id', 'calendar_year'])]
class AccountingBudgetLine
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\ManyToOne(targetEntity: AccountingCostCenter::class)]
    #[ORM\JoinColumn(name: 'cost_center_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private AccountingCostCenter $costCenter;

    #[ORM\Column(name: 'calendar_year', type: 'integer')]
    private int $calendarYear;

    #[ORM\Column(name: 'amount_chf', type: 'decimal', precision: 12, scale: 2)]
    private string $amountChf = '0.00';

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

    public function getCalendarYear(): int
    {
        return $this->calendarYear;
    }

    public function setCalendarYear(int $calendarYear): self
    {
        $this->calendarYear = $calendarYear;

        return $this;
    }

    public function getAmountChf(): string
    {
        return $this->amountChf;
    }

    public function setAmountChf(string $amountChf): self
    {
        $this->amountChf = $amountChf;

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
