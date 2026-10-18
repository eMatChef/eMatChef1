<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Änderungsprotokoll einer Tour (wer hat wann was geändert, bestätigt oder zurückgenommen). */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_tour_event')]
#[ORM\Index(name: 'idx_ga_tour_event_tour', columns: ['tour_id'])]
class DepartmentGrossanlassTourEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'tour_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $tourId;

    #[ORM\ManyToOne(targetEntity: DepartmentGrossanlassTour::class)]
    #[ORM\JoinColumn(name: 'tour_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private DepartmentGrossanlassTour $tour;

    #[ORM\Column(name: 'user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $userId = null;

    #[ORM\Column(type: 'string', length: 24)]
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
    public function setTour(DepartmentGrossanlassTour $tour): self
    {
        $this->tour = $tour;
        $this->tourId = $tour->getId();
        return $this;
    }
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
