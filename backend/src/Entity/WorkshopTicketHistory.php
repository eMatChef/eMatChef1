<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * WorkshopTicketHistory - Protokolliert alle Änderungen an Workshop-Tickets
 * 
 * Jeder Eintrag enthält:
 * - Den vollständigen Zustand (snapshot) zum Zeitpunkt der Speicherung
 * - Die konkreten Änderungen (changes) als Diff zum vorherigen Zustand
 * - Wer die Änderung durchgeführt hat
 * 
 * Actions:
 * - created: Ticket erstellt (manuell oder automatisch)
 * - updated: Felder geändert (Titel, Beschreibung, Kosten, etc.)
 * - status_changed: Status-Übergang (open → in_progress, etc.)
 * - assigned: Zuweisung geändert
 * - completed: Ticket abgeschlossen (mit resolution_action)
 * - cancelled: Ticket abgebrochen
 * - auto_created_issue: Automatisch aus IssueReport erstellt
 * - auto_created_return: Automatisch aus Rückgabe erstellt
 */
#[ORM\Entity]
#[ORM\Table(name: 'workshop_ticket_history')]
#[ORM\Index(name: 'idx_wt_history_ticket', columns: ['workshop_ticket_id'])]
#[ORM\Index(name: 'idx_wt_history_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_wt_history_action', columns: ['action'])]
class WorkshopTicketHistory
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'workshop_ticket_id', type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    private string $workshopTicketId;

    #[ORM\ManyToOne(targetEntity: WorkshopTicket::class)]
    #[ORM\JoinColumn(name: 'workshop_ticket_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private WorkshopTicket $workshopTicket;

    #[ORM\Column(name: 'user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $userId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    /** @var string Typ der Aktion */
    #[ORM\Column(type: 'string', length: 30)]
    private string $action = 'updated';

    /** @var array Vollständiger Zustand zum Zeitpunkt der Speicherung */
    #[ORM\Column(type: 'json')]
    private array $snapshot = [];

    /** @var array Geänderte Felder mit old/new Werten */
    #[ORM\Column(type: 'json')]
    private array $changes = [];

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // === Getters & Setters ===

    public function getId(): ?string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }

    public function getWorkshopTicketId(): string { return $this->workshopTicketId; }
    public function setWorkshopTicketId(string $workshopTicketId): self { $this->workshopTicketId = $workshopTicketId; return $this; }

    public function getWorkshopTicket(): WorkshopTicket { return $this->workshopTicket; }
    public function setWorkshopTicket(WorkshopTicket $workshopTicket): self
    {
        $this->workshopTicket = $workshopTicket;
        $this->workshopTicketId = $workshopTicket->getId();
        return $this;
    }

    public function getUserId(): ?string { return $this->userId; }
    public function setUserId(?string $userId): self { $this->userId = $userId; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self
    {
        $this->user = $user;
        $this->userId = $user?->getId();
        return $this;
    }

    public function getAction(): string { return $this->action; }
    public function setAction(string $action): self { $this->action = $action; return $this; }

    public function getSnapshot(): array { return $this->snapshot; }
    public function setSnapshot(array $snapshot): self { $this->snapshot = $snapshot; return $this; }

    public function getChanges(): array { return $this->changes; }
    public function setChanges(array $changes): self { $this->changes = $changes; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt): self { $this->createdAt = $createdAt; return $this; }

    // === Action Constants ===
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_STATUS_CHANGED = 'status_changed';
    public const ACTION_ASSIGNED = 'assigned';
    public const ACTION_COMPLETED = 'completed';
    public const ACTION_CANCELLED = 'cancelled';
    public const ACTION_AUTO_CREATED_ISSUE = 'auto_created_issue';
    public const ACTION_AUTO_CREATED_RETURN = 'auto_created_return';
}
