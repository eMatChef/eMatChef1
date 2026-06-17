<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_vehicle')]
#[ORM\Index(name: 'idx_department_vehicle_dept', columns: ['department_id'])]
class DepartmentVehicle
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $plate = null;

    #[ORM\Column(name: 'length_m', type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $lengthM = null;

    #[ORM\Column(name: 'width_m', type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $widthM = null;

    #[ORM\Column(name: 'height_m', type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $heightM = null;

    #[ORM\Column(name: 'max_payload_kg', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $maxPayloadKg = null;

    #[ORM\Column(name: 'max_volume_m3', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $maxVolumeM3 = null;

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }
    public function getDepartmentId(): string { return $this->departmentId; }
    public function setDepartment(Department $department): self
    {
        $this->department = $department;
        $this->departmentId = $department->getId();
        return $this;
    }
    public function getDepartment(): Department { return $this->department; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getPlate(): ?string { return $this->plate; }
    public function setPlate(?string $plate): self { $this->plate = $plate; return $this; }
    public function getLengthM(): ?string { return $this->lengthM; }
    public function setLengthM(?string $v): self { $this->lengthM = $v; return $this; }
    public function getWidthM(): ?string { return $this->widthM; }
    public function setWidthM(?string $v): self { $this->widthM = $v; return $this; }
    public function getHeightM(): ?string { return $this->heightM; }
    public function setHeightM(?string $v): self { $this->heightM = $v; return $this; }
    public function getMaxPayloadKg(): ?string { return $this->maxPayloadKg; }
    public function setMaxPayloadKg(?string $v): self { $this->maxPayloadKg = $v; return $this; }
    public function getMaxVolumeM3(): ?string { return $this->maxVolumeM3; }
    public function setMaxVolumeM3(?string $v): self { $this->maxVolumeM3 = $v; return $this; }
    public function getIsActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }
    public function touch(): self { $this->updatedAt = new \DateTime(); return $this; }
}
