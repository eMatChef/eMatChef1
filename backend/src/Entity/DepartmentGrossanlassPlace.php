<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_place')]
#[ORM\UniqueConstraint(name: 'uniq_ga_place_code', columns: ['public_code'])]
#[ORM\Index(name: 'idx_ga_place_dept', columns: ['department_id'])]
class DepartmentGrossanlassPlace
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    #[ORM\Column(name: 'group_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $groupId = null;

    #[ORM\Column(name: 'unterlager_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $unterlagerId = null;

    #[ORM\Column(name: 'public_code', type: 'string', length: 32)]
    private string $publicCode = '';

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }

    public function getDepartmentId(): string { return $this->departmentId; }
    public function getDepartment(): Department { return $this->department; }
    public function setDepartment(Department $department): self
    {
        $this->department = $department;
        $this->departmentId = $department->getId();
        return $this;
    }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getGroupId(): ?string { return $this->groupId; }
    public function setGroupId(?string $groupId): self { $this->groupId = $groupId ?: null; return $this; }

    public function getUnterlagerId(): ?string { return $this->unterlagerId; }
    public function setUnterlagerId(?string $unterlagerId): self { $this->unterlagerId = $unterlagerId ?: null; return $this; }

    public function getPublicCode(): string { return $this->publicCode; }
    public function setPublicCode(string $publicCode): self { $this->publicCode = $publicCode; return $this; }
}
