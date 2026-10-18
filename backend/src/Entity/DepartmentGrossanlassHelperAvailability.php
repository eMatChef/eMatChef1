<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Explizites Verfügbarkeitsfenster eines Helfers; mehrere Intervalle pro Helfer möglich.
 * Überlappungen werden transaktional im Service (Sperre pro Helfer) geprüft, nicht per Constraint.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_helper_availability')]
#[ORM\Index(name: 'idx_ga_helper_avail_helper', columns: ['helper_id', 'starts_at', 'ends_at'])]
#[ORM\Index(name: 'idx_ga_helper_avail_dept', columns: ['department_id', 'starts_at', 'ends_at'])]
class DepartmentGrossanlassHelperAvailability
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\Column(name: 'helper_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $helperId;

    #[ORM\Column(name: 'starts_at', type: 'datetime')]
    private \DateTime $startsAt;

    #[ORM\Column(name: 'ends_at', type: 'datetime')]
    private \DateTime $endsAt;

    /** Gesetzt, wenn das Fenster aus einer externen Buchung (API-Daten) stammt; dann pflegt es nur die Synchronisation. */
    #[ORM\Column(name: 'ext_record_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $extRecordId = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct(string $id, string $departmentId, string $helperId, \DateTime $startsAt, \DateTime $endsAt)
    {
        $this->id = $id;
        $this->departmentId = $departmentId;
        $this->helperId = $helperId;
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;
        $this->createdAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function getDepartmentId(): string { return $this->departmentId; }
    public function getHelperId(): string { return $this->helperId; }
    public function getStartsAt(): \DateTime { return $this->startsAt; }
    public function getEndsAt(): \DateTime { return $this->endsAt; }

    public function getExtRecordId(): ?string { return $this->extRecordId; }
    public function setExtRecordId(?string $id): self { $this->extRecordId = $id; return $this; }

    public function setWindow(\DateTime $startsAt, \DateTime $endsAt): self
    {
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;

        return $this;
    }
}
