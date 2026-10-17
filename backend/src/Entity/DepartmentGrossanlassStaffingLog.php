<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Append-only Protokoll der Helferzuteilung (Zuteilen, Ändern, Entfernen, Ausnahmen). */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_staffing_log')]
#[ORM\Index(name: 'idx_ga_staffing_log_dept', columns: ['department_id', 'created_at'])]
#[ORM\Index(name: 'idx_ga_staffing_log_assignment', columns: ['assignment_id'])]
class DepartmentGrossanlassStaffingLog
{
    public const ACTION_ASSIGNED = 'assigned';
    public const ACTION_CHANGED = 'changed';
    public const ACTION_CANCELLED = 'cancelled';
    public const ACTION_OVERRIDE = 'override';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    /** Bewusst ohne Fremdschlüssel: das Protokoll überlebt das Löschen von Zuteilung und Helfer. */
    #[ORM\Column(name: 'assignment_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $assignmentId;

    #[ORM\Column(name: 'helper_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $helperId;

    #[ORM\Column(type: 'string', length: 16)]
    private string $action;

    #[ORM\Column(name: 'user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $userId;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $details;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    /** @param array<string, mixed> $details */
    public function __construct(string $id, string $departmentId, ?string $assignmentId, string $helperId, string $action, ?string $userId, ?string $reason = null, array $details = [])
    {
        $this->id = $id;
        $this->departmentId = $departmentId;
        $this->assignmentId = $assignmentId;
        $this->helperId = $helperId;
        $this->action = $action;
        $this->userId = $userId;
        $this->reason = $reason;
        $this->details = $details;
        $this->createdAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function getDepartmentId(): string { return $this->departmentId; }
    public function getAssignmentId(): ?string { return $this->assignmentId; }
    public function getHelperId(): string { return $this->helperId; }
    public function getAction(): string { return $this->action; }
    public function getUserId(): ?string { return $this->userId; }
    public function getReason(): ?string { return $this->reason; }
    /** @return array<string, mixed> */
    public function getDetails(): array { return $this->details; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
}
