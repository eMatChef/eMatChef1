<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Arbeitspaket am Bauprojekt — nicht in Bedarf/Wünsche. */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_task')]
#[ORM\Index(name: 'idx_ga_task_dept', columns: ['department_id'])]
#[ORM\Index(name: 'idx_ga_task_group', columns: ['group_id'])]
#[ORM\Index(name: 'idx_ga_task_rework_event', columns: ['rework_event_id'])]
class DepartmentGrossanlassTask
{
    public const STATUS_OPEN = 'open';
    public const STATUS_PROGRESS = 'progress';
    /** Fertig gemeldet, wartet auf Abnahme (noch nicht abgeschlossen). */
    public const STATUS_REVIEW = 'review';
    public const STATUS_DONE = 'done';
    public const STATUSES = [self::STATUS_OPEN, self::STATUS_PROGRESS, self::STATUS_REVIEW, self::STATUS_DONE];

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

    #[ORM\Column(type: 'string', length: 255)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'starts_at', type: 'datetime', nullable: true)]
    private ?\DateTime $startsAt = null;

    #[ORM\Column(name: 'duration_minutes', type: 'integer', nullable: true)]
    private ?int $durationMinutes = null;

    #[ORM\Column(name: 'assignee_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $assigneeUserId = null;

    /** Automatisch angelegte Standardaufgabe «Allgemein» für Personalbedarf direkt am Bauprojekt; nicht in der Aufgabenübersicht. */
    #[ORM\Column(name: 'is_default', type: 'boolean', options: ['default' => false])]
    private bool $isDefault = false;

    /** open | progress | review | done (done nur nach Abnahme) */
    #[ORM\Column(type: 'string', length: 16, options: ['default' => 'open'])]
    private string $status = self::STATUS_OPEN;

    /** Blockierung (Problem-Meldung): gesetzt = blockiert, bis eine berechtigte Person sie aufhebt. Unabhängig von `status`. */
    #[ORM\Column(name: 'blocked_reason', type: 'text', nullable: true)]
    private ?string $blockedReason = null;

    #[ORM\Column(name: 'blocked_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $blockedByUserId = null;

    #[ORM\Column(name: 'blocked_at', type: 'datetime', nullable: true)]
    private ?\DateTime $blockedAt = null;

    /**
     * Nachbesserungsaufgabe: Verknüpfung mit dem Rückweisungsereignis der Gesamtabnahme (`department_grossanlass_order_event.id`,
     * Typ `returned`). NULL = normale Aufgabe. Wird nur beim Anlegen gesetzt.
     */
    #[ORM\Column(name: 'rework_event_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $reworkEventId = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;

        return $this;
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $trimmed = $description !== null ? trim($description) : '';
        $this->description = $trimmed === '' ? null : $trimmed;

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

    public function getAssigneeUserId(): ?string
    {
        return $this->assigneeUserId;
    }

    public function setAssigneeUserId(?string $assigneeUserId): self
    {
        $this->assigneeUserId = $assigneeUserId !== null && $assigneeUserId !== '' ? $assigneeUserId : null;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Ungültiger Aufgabenstatus');
        }
        $this->status = $status;

        return $this;
    }

    public function getReworkEventId(): ?string
    {
        return $this->reworkEventId;
    }

    public function setReworkEventId(?string $reworkEventId): self
    {
        $this->reworkEventId = $reworkEventId !== null && $reworkEventId !== '' ? $reworkEventId : null;

        return $this;
    }

    public function isBlocked(): bool
    {
        return $this->blockedReason !== null;
    }

    public function getBlockedReason(): ?string
    {
        return $this->blockedReason;
    }

    public function getBlockedByUserId(): ?string
    {
        return $this->blockedByUserId;
    }

    public function getBlockedAt(): ?\DateTime
    {
        return $this->blockedAt;
    }

    public function block(string $reason, string $userId): self
    {
        $this->blockedReason = $reason;
        $this->blockedByUserId = $userId;
        $this->blockedAt = new \DateTime();

        return $this;
    }

    public function unblock(): self
    {
        $this->blockedReason = null;
        $this->blockedByUserId = null;
        $this->blockedAt = null;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}
