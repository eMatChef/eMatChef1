<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Änderungswunsch eines Ressorts an einem Fahrauftrag. Die bestehende Planung bleibt gültig, bis die Logistik entscheidet
 * (übernehmen, angepasst übernehmen oder begründet ablehnen).
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_change_request')]
#[ORM\Index(name: 'idx_ga_change_request_einsatz', columns: ['einsatz_id'])]
class DepartmentGrossanlassChangeRequest
{
    public const STATUS_OPEN = 'open';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_ADJUSTED = 'adjusted';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'einsatz_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $einsatzId;

    #[ORM\Column(name: 'requested_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $requestedByUserId = null;

    #[ORM\Column(type: 'text', options: ['default' => ''])]
    private string $note = '';

    #[ORM\Column(name: 'proposed_starts_at', type: 'datetime', nullable: true)]
    private ?\DateTime $proposedStartsAt = null;

    #[ORM\Column(name: 'proposed_ends_at', type: 'datetime', nullable: true)]
    private ?\DateTime $proposedEndsAt = null;

    #[ORM\Column(name: 'proposed_destination_place_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $proposedDestinationPlaceId = null;

    #[ORM\Column(type: 'string', length: 12, options: ['default' => self::STATUS_OPEN])]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(name: 'decision_note', type: 'text', options: ['default' => ''])]
    private string $decisionNote = '';

    #[ORM\Column(name: 'decided_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $decidedByUserId = null;

    #[ORM\Column(name: 'decided_at', type: 'datetime', nullable: true)]
    private ?\DateTime $decidedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }
    public function getDepartmentId(): string { return $this->departmentId; }
    public function setDepartment(Department $department): self
    {
        $this->department = $department;
        $this->departmentId = $department->getId();
        return $this;
    }
    public function getEinsatzId(): string { return $this->einsatzId; }
    public function setEinsatzId(string $einsatzId): self { $this->einsatzId = $einsatzId; return $this; }
    public function getRequestedByUserId(): ?string { return $this->requestedByUserId; }
    public function setRequestedByUserId(?string $userId): self { $this->requestedByUserId = $userId; return $this; }
    public function getNote(): string { return $this->note; }
    public function setNote(string $note): self { $this->note = $note; return $this; }
    public function getProposedStartsAt(): ?\DateTime { return $this->proposedStartsAt; }
    public function getProposedEndsAt(): ?\DateTime { return $this->proposedEndsAt; }
    public function setProposedWindow(?\DateTime $from, ?\DateTime $to): self { $this->proposedStartsAt = $from; $this->proposedEndsAt = $to; return $this; }
    public function getProposedDestinationPlaceId(): ?string { return $this->proposedDestinationPlaceId; }
    public function setProposedDestinationPlaceId(?string $id): self { $this->proposedDestinationPlaceId = $id ?: null; return $this; }
    public function getStatus(): string { return $this->status; }
    public function isOpen(): bool { return $this->status === self::STATUS_OPEN; }
    public function getDecisionNote(): string { return $this->decisionNote; }
    public function getDecidedAt(): ?\DateTime { return $this->decidedAt; }
    public function decide(string $status, string $note, ?string $userId, \DateTime $at): self
    {
        $this->status = $status;
        $this->decisionNote = $note;
        $this->decidedByUserId = $userId;
        $this->decidedAt = $at;
        return $this;
    }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
}
