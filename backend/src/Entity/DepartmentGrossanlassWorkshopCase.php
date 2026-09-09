<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_workshop_case')]
#[ORM\Index(name: 'idx_ga_workshop_case_dept', columns: ['department_id'])]
#[ORM\Index(name: 'idx_ga_workshop_case_status', columns: ['status'])]
class DepartmentGrossanlassWorkshopCase
{
    public const ORIGIN_OWN = 'own';
    public const ORIGIN_LOAN = 'loan';
    public const ORIGIN_BUY = 'buy';

    /** @var list<string> */
    public const ORIGINS = [self::ORIGIN_OWN, self::ORIGIN_LOAN, self::ORIGIN_BUY];

    public const PATH_REPAIR = 'repair';
    public const PATH_OWNER = 'owner';

    /** @var list<string> */
    public const PATHS = [self::PATH_REPAIR, self::PATH_OWNER];

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_WAITING_OWNER = 'waiting_owner';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_WAITING_OWNER,
        self::STATUS_DONE,
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

    #[ORM\Column(type: 'string', length: 255)]
    private string $title = '';

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\Column(type: 'string', length: 16)]
    private string $origin = self::ORIGIN_LOAN;

    #[ORM\Column(name: 'owner_firm_name', type: 'string', length: 255)]
    private string $ownerFirmName = '';

    #[ORM\Column(name: 'material_label', type: 'string', length: 255)]
    private string $materialLabel = '';

    #[ORM\Column(name: 'path', type: 'string', length: 24)]
    private string $path = self::PATH_REPAIR;

    #[ORM\Column(type: 'string', length: 24)]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(name: 'created_by_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $createdById;

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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        $this->touch();

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        $this->touch();

        return $this;
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    public function setOrigin(string $origin): self
    {
        $this->origin = $origin;
        $this->touch();

        return $this;
    }

    public function getOwnerFirmName(): string
    {
        return $this->ownerFirmName;
    }

    public function setOwnerFirmName(string $ownerFirmName): self
    {
        $this->ownerFirmName = $ownerFirmName;
        $this->touch();

        return $this;
    }

    public function getMaterialLabel(): string
    {
        return $this->materialLabel;
    }

    public function setMaterialLabel(string $materialLabel): self
    {
        $this->materialLabel = $materialLabel;
        $this->touch();

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        $this->touch();

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        $this->touch();

        return $this;
    }

    public function getCreatedById(): string
    {
        return $this->createdById;
    }

    public function setCreatedById(string $createdById): self
    {
        $this->createdById = $createdById;

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

    private function touch(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
