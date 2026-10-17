<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Nachvollziehbarer Verlauf einer GA-Aufgabe: fertig gemeldet, abgenommen, zurückgegeben. */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_task_event')]
#[ORM\Index(name: 'idx_ga_task_event_task', columns: ['task_id', 'created_at'])]
class DepartmentGrossanlassTaskEvent
{
    public const REPORTED = 'reported';
    public const ACCEPTED = 'accepted';
    public const RETURNED = 'returned';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'task_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $taskId;

    #[ORM\Column(type: 'string', length: 16)]
    private string $type;

    #[ORM\Column(name: 'user_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $userId;

    /** Optionaler Kommentar bei der Fertigmeldung bzw. Begründung bei der Rückgabe. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct(string $id, string $taskId, string $type, string $userId, ?string $note)
    {
        $this->id = $id;
        $this->taskId = $taskId;
        $this->type = $type;
        $this->userId = $userId;
        $this->note = $note;
        $this->createdAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function getTaskId(): string { return $this->taskId; }
    public function getType(): string { return $this->type; }
    public function getUserId(): string { return $this->userId; }
    public function getNote(): ?string { return $this->note; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
}
