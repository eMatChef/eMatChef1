<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Protokoll der Disposition zu einem Fahrauftrag, einer Tour oder einem Änderungswunsch: Freigabe, Rücknahme,
 * manuelle Ablieferung (mit Begründung), nicht ausgeführte Fahrt, Entscheid über Änderungswünsche.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_dispatch_event')]
#[ORM\Index(name: 'idx_ga_dispatch_event_subject', columns: ['department_id', 'subject_type', 'subject_id'])]
class DepartmentGrossanlassDispatchEvent
{
    public const SUBJECT_TRIP = 'trip';
    public const SUBJECT_TOUR = 'tour';
    public const SUBJECT_REQUEST = 'request';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'subject_type', type: 'string', length: 16)]
    private string $subjectType = self::SUBJECT_TRIP;

    #[ORM\Column(name: 'subject_id', type: 'string', length: 40)]
    private string $subjectId = '';

    #[ORM\Column(name: 'user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $userId = null;

    #[ORM\Column(type: 'string', length: 32)]
    private string $action = '';

    #[ORM\Column(type: 'text', options: ['default' => ''])]
    private string $note = '';

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }
    public function setDepartment(Department $department): self
    {
        $this->department = $department;
        $this->departmentId = $department->getId();
        return $this;
    }
    public function getSubjectType(): string { return $this->subjectType; }
    public function getSubjectId(): string { return $this->subjectId; }
    public function setSubject(string $type, string $id): self { $this->subjectType = $type; $this->subjectId = $id; return $this; }
    public function getUserId(): ?string { return $this->userId; }
    public function setUserId(?string $userId): self { $this->userId = $userId; return $this; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $action): self { $this->action = $action; return $this; }
    public function getNote(): string { return $this->note; }
    public function setNote(string $note): self { $this->note = $note; return $this; }
    /** Fachzeit des Ereignisses (BusinessClock des Departments); ohne Aufruf gilt die Erfassungszeit. */
    public function setCreatedAt(\DateTime $createdAt): self { $this->createdAt = $createdAt; return $this; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
}
