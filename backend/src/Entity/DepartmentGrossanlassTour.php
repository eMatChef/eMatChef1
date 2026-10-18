<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Transporttour der Logistik-Disposition: organisatorische Zusammenfassung bestehender Fahraufträge (Einsatz mit
 * `delivery = trip`) und Fahrzeugwünsche mit Fahrzeug, Chauffeur und geordneten Stopps. Enthält keine Material- oder
 * Fahrauftragsdaten. «Bestätigt» ist nicht «zur Ausführung freigegeben» (`tripReleasedAt` am Einsatz).
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_tour')]
#[ORM\Index(name: 'idx_ga_tour_dept', columns: ['department_id'])]
class DepartmentGrossanlassTour
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CLOSED = 'closed';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name = '';

    #[ORM\Column(type: 'string', length: 16, options: ['default' => self::STATUS_DRAFT])]
    private string $status = self::STATUS_DRAFT;

    /** Fahrzeug aus den Beständen des Anlasses (Commitment der Familie «vehicle»). */
    #[ORM\Column(name: 'vehicle_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $vehicleId = null;

    #[ORM\Column(name: 'chauffeur_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $chauffeurUserId = null;

    #[ORM\Column(type: 'string', length: 80, nullable: true)]
    private ?string $trailer = null;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $createdByUserId = null;

    #[ORM\Column(name: 'confirmed_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $confirmedByUserId = null;

    #[ORM\Column(name: 'confirmed_at', type: 'datetime', nullable: true)]
    private ?\DateTime $confirmedAt = null;

    #[ORM\Column(name: 'closed_at', type: 'datetime', nullable: true)]
    private ?\DateTime $closedAt = null;

    #[ORM\Column(name: 'closed_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $closedByUserId = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function touch(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }
    public function getDepartmentId(): string { return $this->departmentId; }
    public function setDepartment(Department $department): self
    {
        $this->department = $department;
        $this->departmentId = $department->getId();
        return $this;
    }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; $this->touch(); return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; $this->touch(); return $this; }
    public function isConfirmed(): bool { return $this->status === self::STATUS_CONFIRMED; }
    public function getVehicleId(): ?string { return $this->vehicleId; }
    public function setVehicleId(?string $vehicleId): self { $this->vehicleId = $vehicleId ?: null; $this->touch(); return $this; }
    public function getChauffeurUserId(): ?string { return $this->chauffeurUserId; }
    public function setChauffeurUserId(?string $userId): self { $this->chauffeurUserId = $userId ?: null; $this->touch(); return $this; }
    public function getTrailer(): ?string { return $this->trailer; }
    public function setTrailer(?string $trailer): self { $this->trailer = $trailer !== null && trim($trailer) !== '' ? trim($trailer) : null; $this->touch(); return $this; }
    public function getCreatedByUserId(): ?string { return $this->createdByUserId; }
    public function setCreatedByUserId(?string $userId): self { $this->createdByUserId = $userId; return $this; }
    public function getConfirmedByUserId(): ?string { return $this->confirmedByUserId; }
    public function getConfirmedAt(): ?\DateTime { return $this->confirmedAt; }
    public function setConfirmation(?string $userId, ?\DateTime $at): self
    {
        $this->confirmedByUserId = $userId;
        $this->confirmedAt = $at;
        $this->touch();
        return $this;
    }
    public function isClosed(): bool { return $this->status === self::STATUS_CLOSED; }
    public function getClosedAt(): ?\DateTime { return $this->closedAt; }
    public function close(?string $userId, \DateTime $at): self
    {
        $this->status = self::STATUS_CLOSED;
        $this->closedByUserId = $userId;
        $this->closedAt = $at;
        $this->touch();
        return $this;
    }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
}
