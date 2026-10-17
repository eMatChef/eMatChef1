<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Abhängigkeit «Nachfolger wartet auf Vorgänger» innerhalb eines Grossanlasses:
 * Aufgabe → Aufgabe (`kind = task`) oder Bauauftrag → Bauauftrag (`kind = group`).
 *
 * Art (`mode`): `sequential` = Nachfolger wartet bis zur Abnahme des Vorgängers (Startsperre); `simultaneous` = Aufgaben werden
 * zeitlich koordiniert, ohne Startsperre (symmetrisch, nur Aufgabe ↔ Aufgabe; «Vorgänger»/«Nachfolger» sind dann nur die Anlegerichtung).
 *
 * Bewusst ohne Fremdschlüssel auf Aufgabe/Gruppe und ohne Cascade: Eine Abhängigkeit verschwindet nie stillschweigend.
 * Sie wird ausdrücklich entfernt (`removed_*`, bleibt als Protokoll) oder vorzeitig freigegeben (`released_*` mit Begründung).
 * Das Löschen von Aufgaben/Bauaufträgen mit aktiver Abhängigkeit wird im Service verweigert.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_dependency')]
#[ORM\Index(name: 'idx_ga_dependency_successor', columns: ['department_id', 'kind', 'successor_id'])]
#[ORM\Index(name: 'idx_ga_dependency_predecessor', columns: ['department_id', 'kind', 'predecessor_id'])]
class DepartmentGrossanlassDependency
{
    public const KIND_TASK = 'task';
    public const KIND_GROUP = 'group';
    public const MODE_SEQUENTIAL = 'sequential';
    public const MODE_SIMULTANEOUS = 'simultaneous';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(type: 'string', length: 8)]
    private string $kind;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => 'sequential'])]
    private string $mode = self::MODE_SEQUENTIAL;

    #[ORM\Column(name: 'mode_changed_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $modeChangedByUserId = null;

    #[ORM\Column(name: 'mode_changed_at', type: 'datetime', nullable: true)]
    private ?\DateTime $modeChangedAt = null;

    #[ORM\Column(name: 'successor_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $successorId;

    #[ORM\Column(name: 'predecessor_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $predecessorId;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $createdByUserId;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'released_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $releasedByUserId = null;

    #[ORM\Column(name: 'released_at', type: 'datetime', nullable: true)]
    private ?\DateTime $releasedAt = null;

    #[ORM\Column(name: 'release_reason', type: 'text', nullable: true)]
    private ?string $releaseReason = null;

    #[ORM\Column(name: 'removed_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $removedByUserId = null;

    #[ORM\Column(name: 'removed_at', type: 'datetime', nullable: true)]
    private ?\DateTime $removedAt = null;

    public function __construct(string $id, Department $department, string $kind, string $successorId, string $predecessorId, string $createdByUserId)
    {
        $this->id = $id;
        $this->department = $department;
        $this->departmentId = $department->getId();
        $this->kind = $kind;
        $this->successorId = $successorId;
        $this->predecessorId = $predecessorId;
        $this->createdByUserId = $createdByUserId;
        $this->createdAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function getDepartmentId(): string { return $this->departmentId; }
    public function getKind(): string { return $this->kind; }
    public function getMode(): string { return $this->mode; }
    public function isSimultaneous(): bool { return $this->mode === self::MODE_SIMULTANEOUS; }
    public function getModeChangedByUserId(): ?string { return $this->modeChangedByUserId; }
    public function getModeChangedAt(): ?\DateTime { return $this->modeChangedAt; }
    public function getSuccessorId(): string { return $this->successorId; }
    public function getPredecessorId(): string { return $this->predecessorId; }
    public function getCreatedByUserId(): string { return $this->createdByUserId; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function isReleased(): bool { return $this->releasedAt !== null; }
    public function getReleasedByUserId(): ?string { return $this->releasedByUserId; }
    public function getReleasedAt(): ?\DateTime { return $this->releasedAt; }
    public function getReleaseReason(): ?string { return $this->releaseReason; }
    public function isRemoved(): bool { return $this->removedAt !== null; }
    public function getRemovedByUserId(): ?string { return $this->removedByUserId; }
    public function getRemovedAt(): ?\DateTime { return $this->removedAt; }

    public function setInitialMode(string $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function changeMode(string $mode, string $userId): self
    {
        $this->mode = $mode;
        $this->modeChangedByUserId = $userId;
        $this->modeChangedAt = new \DateTime();

        return $this;
    }

    public function release(string $userId, string $reason): self
    {
        $this->releasedByUserId = $userId;
        $this->releasedAt = new \DateTime();
        $this->releaseReason = $reason;

        return $this;
    }

    public function remove(string $userId): self
    {
        $this->removedByUserId = $userId;
        $this->removedAt = new \DateTime();

        return $this;
    }
}
