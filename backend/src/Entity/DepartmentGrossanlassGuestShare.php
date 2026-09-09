<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_guest_share')]
#[ORM\Index(name: 'idx_ga_guest_share_host', columns: ['host_department_id'])]
#[ORM\Index(name: 'idx_ga_guest_share_guest', columns: ['guest_department_id'])]
class DepartmentGrossanlassGuestShare
{
    public const KIND_OFFER = 'offer';
    public const KIND_SALE = 'sale';

    public const STATUS_OFFERED = 'offered';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_COMPLETED = 'completed';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'host_department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $hostDepartmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'host_department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $hostDepartment;

    #[ORM\Column(name: 'guest_department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $guestDepartmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'guest_department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $guestDepartment;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => self::KIND_OFFER])]
    private string $kind = self::KIND_OFFER;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => self::STATUS_OFFERED])]
    private string $status = self::STATUS_OFFERED;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $qty = 1;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => DepartmentGrossanlassCommitment::FAMILY_MATERIAL])]
    private string $family = DepartmentGrossanlassCommitment::FAMILY_MATERIAL;

    #[ORM\Column(name: 'material_item_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $materialItemId = null;

    #[ORM\Column(name: 'commitment_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $commitmentId = null;

    #[ORM\ManyToOne(targetEntity: DepartmentGrossanlassCommitment::class)]
    #[ORM\JoinColumn(name: 'commitment_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?DepartmentGrossanlassCommitment $commitment = null;

    #[ORM\Column(name: 'starts_at', type: 'datetime', nullable: true)]
    private ?\DateTime $startsAt = null;

    #[ORM\Column(name: 'ends_at', type: 'datetime', nullable: true)]
    private ?\DateTime $endsAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }

    public function getHostDepartmentId(): string { return $this->hostDepartmentId; }
    public function getHostDepartment(): Department { return $this->hostDepartment; }
    public function setHostDepartment(Department $department): self
    {
        $this->hostDepartment = $department;
        $this->hostDepartmentId = $department->getId();
        return $this;
    }

    public function getGuestDepartmentId(): string { return $this->guestDepartmentId; }
    public function getGuestDepartment(): Department { return $this->guestDepartment; }
    public function setGuestDepartment(Department $department): self
    {
        $this->guestDepartment = $department;
        $this->guestDepartmentId = $department->getId();
        $this->touch();
        return $this;
    }

    public function getKind(): string { return $this->kind; }
    public function setKind(string $kind): self { $this->kind = $kind; $this->touch(); return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; $this->touch(); return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; $this->touch(); return $this; }

    public function getQty(): int { return $this->qty; }
    public function setQty(int $qty): self { $this->qty = max(1, $qty); $this->touch(); return $this; }

    public function getFamily(): string { return $this->family; }
    public function setFamily(string $family): self { $this->family = $family; $this->touch(); return $this; }

    public function getMaterialItemId(): ?string { return $this->materialItemId; }
    public function setMaterialItemId(?string $id): self { $this->materialItemId = $id; $this->touch(); return $this; }

    public function getCommitmentId(): ?string { return $this->commitmentId; }
    public function getCommitment(): ?DepartmentGrossanlassCommitment { return $this->commitment; }
    public function setCommitment(?DepartmentGrossanlassCommitment $commitment): self
    {
        $this->commitment = $commitment;
        $this->commitmentId = $commitment?->getId();
        $this->touch();
        return $this;
    }

    public function getStartsAt(): ?\DateTime { return $this->startsAt; }
    public function setStartsAt(?\DateTime $value): self { $this->startsAt = $value; $this->touch(); return $this; }

    public function getEndsAt(): ?\DateTime { return $this->endsAt; }
    public function setEndsAt(?\DateTime $value): self { $this->endsAt = $value; $this->touch(); return $this; }
}
