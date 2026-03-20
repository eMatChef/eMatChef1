<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'storage_rack')]
#[ORM\Index(name: 'idx_storage_rack_department', columns: ['department_id'])]
#[ORM\Index(name: 'idx_storage_rack_address', columns: ['storage_address_id'])]
class StorageRack
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

    #[ORM\Column(name: 'storage_address_id', type: 'string', length: 12, nullable: false, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private ?string $storageAddressId = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(name: 'storage_address_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?Address $storageAddress = null;

    #[ORM\Column(type: 'string', length: 80)]
    private string $name;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

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
    public function setDepartmentId(string $departmentId): self { $this->departmentId = $departmentId; return $this; }
    public function getDepartment(): Department { return $this->department; }
    public function setDepartment(Department $department): self { $this->department = $department; $this->departmentId = $department->getId(); return $this; }
    public function getStorageAddressId(): ?string { return $this->storageAddressId; }
    public function setStorageAddressId(string $storageAddressId): self { $this->storageAddressId = $storageAddressId; return $this; }
    public function getStorageAddress(): ?Address { return $this->storageAddress; }
    public function setStorageAddress(Address $storageAddress): self { $this->storageAddress = $storageAddress; $this->storageAddressId = $storageAddress->getId(); return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): self { $this->sortOrder = $sortOrder; return $this; }
    public function getIsActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTime(); return $this; }
}

