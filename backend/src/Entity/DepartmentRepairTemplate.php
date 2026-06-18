<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Department-Override für Zeltblatt-Preise (Struktur kommt von repair_template).
 */
#[ORM\Entity(repositoryClass: \App\Repository\DepartmentRepairTemplateRepository::class)]
#[ORM\Table(name: 'department_repair_template')]
#[ORM\UniqueConstraint(name: 'uniq_dept_repair_template', columns: ['department_id', 'template_key'])]
#[ORM\Index(name: 'idx_dept_repair_template_dept', columns: ['department_id'])]
class DepartmentRepairTemplate
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'template_key', type: 'string', length: 50)]
    private string $templateKey;

    #[ORM\Column(name: 'prices_json', type: 'json')]
    private array $pricesJson = [];

    #[ORM\Column(name: 'flat_rate_chf', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $flatRateChf = null;

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

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

    public function getTemplateKey(): string
    {
        return $this->templateKey;
    }

    public function setTemplateKey(string $templateKey): self
    {
        $this->templateKey = $templateKey;
        return $this;
    }

    public function getPricesJson(): array
    {
        return $this->pricesJson;
    }

    public function setPricesJson(array $pricesJson): self
    {
        $this->pricesJson = $pricesJson;
        return $this;
    }

    public function getFlatRateChf(): ?string
    {
        return $this->flatRateChf;
    }

    public function setFlatRateChf(?string $flatRateChf): self
    {
        $this->flatRateChf = $flatRateChf;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
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

    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
