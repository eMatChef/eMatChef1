<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_budget')]
#[ORM\Index(name: 'idx_ga_budget_dept', columns: ['department_id'])]
class DepartmentGrossanlassBudget
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'payer_group_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $payerGroupId = null;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(name: 'payer_group_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Group $payerGroup = null;

    #[ORM\Column(name: 'rahmen_chf', type: 'decimal', precision: 12, scale: 2)]
    private string $rahmenChf = '0.00';

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
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

    public function getRahmenChf(): string
    {
        return $this->rahmenChf;
    }

    public function setRahmenChf(string $rahmenChf): self
    {
        $this->rahmenChf = $rahmenChf;

        return $this;
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
}
