<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Reguläre Inventur-Aufgabe (z. B. Jahresinventur), optional verknüpft mit Inspektions-Ticket.
 */
#[ORM\Entity]
#[ORM\Table(name: 'inventory_task')]
#[ORM\Index(name: 'idx_inventory_task_department', columns: ['department_id'])]
#[ORM\Index(name: 'idx_inventory_task_status', columns: ['status'])]
#[ORM\Index(name: 'idx_inventory_task_workshop_ticket', columns: ['workshop_ticket_id'])]
class InventoryTask
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const ALL_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
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

    #[ORM\Column(type: 'string', length: 200)]
    private string $title;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'open'])]
    private string $status = self::STATUS_OPEN;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'lines_json', type: 'json')]
    private array $linesJson = ['lines' => []];

    #[ORM\Column(name: 'workshop_ticket_id', type: 'string', length: 13, nullable: true, columnDefinition: 'CHARACTER(13) NULL')]
    private ?string $workshopTicketId = null;

    #[ORM\ManyToOne(targetEntity: WorkshopTicket::class)]
    #[ORM\JoinColumn(name: 'workshop_ticket_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?WorkshopTicket $workshopTicket = null;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $createdByUserId = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTimeInterface $updatedAt;

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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

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

    /** @return array<string, mixed> */
    public function getLinesJson(): array
    {
        return $this->linesJson;
    }

    /** @param array<string, mixed> $linesJson */
    public function setLinesJson(array $linesJson): self
    {
        $this->linesJson = $linesJson;

        return $this;
    }

    public function getWorkshopTicketId(): ?string
    {
        return $this->workshopTicketId;
    }

    public function setWorkshopTicketId(?string $workshopTicketId): self
    {
        $this->workshopTicketId = $workshopTicketId;

        return $this;
    }

    public function getWorkshopTicket(): ?WorkshopTicket
    {
        return $this->workshopTicket;
    }

    public function setWorkshopTicket(?WorkshopTicket $workshopTicket): self
    {
        $this->workshopTicket = $workshopTicket;
        $this->workshopTicketId = $workshopTicket?->getId();

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

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function updateTimestamps(): self
    {
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => 'Offen',
            self::STATUS_IN_PROGRESS => 'In Bearbeitung',
            self::STATUS_COMPLETED => 'Abgeschlossen',
            self::STATUS_CANCELLED => 'Abgebrochen',
            default => $this->status,
        };
    }
}
