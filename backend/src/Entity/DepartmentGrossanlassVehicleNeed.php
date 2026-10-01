<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Fahrzeugwunsch am Bauprojekt: wann, wofür, welches Fahrzeug. */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_vehicle_need')]
#[ORM\Index(name: 'idx_ga_vehicle_need_group', columns: ['group_id'])]
class DepartmentGrossanlassVehicleNeed
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'group_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $groupId;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Group $group;

    #[ORM\Column(name: 'vehicle_label', type: 'string', length: 255)]
    private string $vehicleLabel = '';

    #[ORM\Column(name: 'task_label', type: 'string', length: 255)]
    private string $taskLabel = '';

    #[ORM\Column(name: 'category_label', type: 'string', length: 255, nullable: true)]
    private ?string $categoryLabel = null;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'starts_at', type: 'datetime', nullable: true)]
    private ?\DateTime $startsAt = null;

    #[ORM\Column(name: 'duration_minutes', type: 'integer', nullable: true)]
    private ?int $durationMinutes = null;

    #[ORM\Column(name: 'procurement_line_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $procurementLineId = null;

    #[ORM\ManyToOne(targetEntity: ActivityGrossanlassProcurementLine::class)]
    #[ORM\JoinColumn(name: 'procurement_line_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ActivityGrossanlassProcurementLine $procurementLine = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function setDepartment(Department $department): self
    {
        $this->department = $department;
        $this->departmentId = $department->getId();

        return $this;
    }

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function setGroup(Group $group): self
    {
        $this->group = $group;
        $this->groupId = $group->getId();

        return $this;
    }

    public function getVehicleLabel(): string
    {
        return $this->vehicleLabel;
    }

    public function setVehicleLabel(string $vehicleLabel): self
    {
        $this->vehicleLabel = $vehicleLabel;

        return $this;
    }

    public function getTaskLabel(): string
    {
        return $this->taskLabel;
    }

    public function setTaskLabel(string $taskLabel): self
    {
        $this->taskLabel = $taskLabel;

        return $this;
    }

    public function getCategoryLabel(): ?string
    {
        return $this->categoryLabel;
    }

    public function setCategoryLabel(?string $categoryLabel): self
    {
        $this->categoryLabel = $categoryLabel;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getStartsAt(): ?\DateTime
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTime $startsAt): self
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(?int $durationMinutes): self
    {
        $this->durationMinutes = $durationMinutes;

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

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}
