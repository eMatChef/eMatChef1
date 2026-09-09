<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_pack')]
#[ORM\UniqueConstraint(name: 'uniq_ga_pack_code', columns: ['public_code'])]
#[ORM\Index(name: 'idx_ga_pack_einsatz', columns: ['einsatz_id'])]
class DepartmentGrossanlassPack
{
    public const STATUS_STAGING = 'staging';
    public const STATUS_TRIP_RELEASED = 'trip_released';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_AT_PLACE = 'at_place';

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

    #[ORM\ManyToOne(targetEntity: DepartmentGrossanlassEinsatz::class)]
    #[ORM\JoinColumn(name: 'einsatz_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private DepartmentGrossanlassEinsatz $einsatz;

    #[ORM\Column(name: 'public_code', type: 'string', length: 32)]
    private string $publicCode = '';

    #[ORM\Column(type: 'string', length: 16, options: ['default' => self::STATUS_STAGING])]
    private string $status = self::STATUS_STAGING;

    #[ORM\Column(name: 'current_place_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $currentPlaceId = null;

    #[ORM\Column(name: 'trip_released_at', type: 'datetime', nullable: true)]
    private ?\DateTime $tripReleasedAt = null;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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
    public function getEinsatz(): DepartmentGrossanlassEinsatz { return $this->einsatz; }
    public function setEinsatz(DepartmentGrossanlassEinsatz $einsatz): self
    {
        $this->einsatz = $einsatz;
        $this->einsatzId = $einsatz->getId();
        return $this;
    }

    public function getPublicCode(): string { return $this->publicCode; }
    public function setPublicCode(string $publicCode): self { $this->publicCode = $publicCode; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self
    {
        $this->status = $status;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCurrentPlaceId(): ?string { return $this->currentPlaceId; }
    public function setCurrentPlaceId(?string $currentPlaceId): self
    {
        $this->currentPlaceId = $currentPlaceId ?: null;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getTripReleasedAt(): ?\DateTime { return $this->tripReleasedAt; }
    public function setTripReleasedAt(?\DateTime $tripReleasedAt): self
    {
        $this->tripReleasedAt = $tripReleasedAt;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isTripReleased(): bool { return $this->tripReleasedAt !== null; }

    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): self { $this->sortOrder = $sortOrder; return $this; }
}
