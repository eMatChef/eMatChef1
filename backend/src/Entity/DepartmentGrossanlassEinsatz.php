<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_einsatz')]
#[ORM\Index(name: 'idx_ga_einsatz_dept', columns: ['department_id'])]
#[ORM\Index(name: 'idx_ga_einsatz_commitment', columns: ['commitment_id'])]
class DepartmentGrossanlassEinsatz
{
    public const KIND_EINSATZ = 'einsatz';
    public const KIND_ORDER = 'order';

    public const STATUS_PLANNED = 'planned';
    public const STATUS_PENDING = 'pending_approval';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_RETURNED = 'returned';

    public const PLACE_LAGER = 'lager';
    public const PLACE_ASSIGNED = 'assigned';
    public const PLACE_OUT = 'out';

    public const DELIVERY_TRIP = 'trip';
    public const DELIVERY_PICKUP = 'pickup';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'commitment_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $commitmentId = null;

    #[ORM\ManyToOne(targetEntity: DepartmentGrossanlassCommitment::class)]
    #[ORM\JoinColumn(name: 'commitment_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?DepartmentGrossanlassCommitment $commitment = null;

    #[ORM\Column(name: 'wish_line_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $wishLineId = null;

    #[ORM\Column(name: 'group_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $groupId = null;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Group $group = null;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => self::KIND_EINSATZ])]
    private string $kind = self::KIND_EINSATZ;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $qty = 1;

    #[ORM\Column(name: 'starts_at', type: 'datetime')]
    private \DateTime $startsAt;

    #[ORM\Column(name: 'ends_at', type: 'datetime')]
    private \DateTime $endsAt;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => self::STATUS_PLANNED])]
    private string $status = self::STATUS_PLANNED;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => self::PLACE_ASSIGNED])]
    private string $place = self::PLACE_ASSIGNED;

    #[ORM\Column(type: 'string', length: 120, options: ['default' => ''])]
    private string $who = '';

    #[ORM\Column(type: 'string', length: 16, options: ['default' => self::DELIVERY_PICKUP])]
    private string $delivery = self::DELIVERY_PICKUP;

    #[ORM\Column(name: 'trip_released_at', type: 'datetime', nullable: true)]
    private ?\DateTime $tripReleasedAt = null;

    #[ORM\Column(name: 'chauffeur_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $chauffeurUserId = null;

    #[ORM\Column(name: 'destination_place_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $destinationPlaceId = null;

    #[ORM\Column(name: 'issued_to_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $issuedToUserId = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $packed = false;

    #[ORM\Column(name: 'pack_phase', type: 'string', length: 16, options: ['default' => 'anlass'])]
    private string $packPhase = 'anlass';

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->startsAt = new \DateTime();
        $this->endsAt = new \DateTime();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTime();
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

    public function getCommitmentId(): ?string { return $this->commitmentId; }
    public function getCommitment(): ?DepartmentGrossanlassCommitment { return $this->commitment; }
    public function setCommitment(?DepartmentGrossanlassCommitment $commitment): self
    {
        $this->commitment = $commitment;
        $this->commitmentId = $commitment?->getId();
        $this->touch();
        return $this;
    }

    public function getWishLineId(): ?string { return $this->wishLineId; }
    public function setWishLineId(?string $wishLineId): self
    {
        $this->wishLineId = $wishLineId ?: null;
        $this->touch();
        return $this;
    }

    public function getGroupId(): ?string { return $this->groupId; }
    public function getGroup(): ?Group { return $this->group; }
    public function setGroup(?Group $group): self
    {
        $this->group = $group;
        $this->groupId = $group?->getId();
        $this->touch();
        return $this;
    }

    public function getKind(): string { return $this->kind; }
    public function setKind(string $kind): self { $this->kind = $kind; $this->touch(); return $this; }

    public function getQty(): int { return $this->qty; }
    public function setQty(int $qty): self { $this->qty = max(1, $qty); $this->touch(); return $this; }

    public function getStartsAt(): \DateTime { return $this->startsAt; }
    public function setStartsAt(\DateTime $startsAt): self { $this->startsAt = $startsAt; $this->touch(); return $this; }

    public function getEndsAt(): \DateTime { return $this->endsAt; }
    public function setEndsAt(\DateTime $endsAt): self { $this->endsAt = $endsAt; $this->touch(); return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; $this->touch(); return $this; }

    public function getPlace(): string { return $this->place; }
    public function setPlace(string $place): self { $this->place = $place; $this->touch(); return $this; }

    public function getWho(): string { return $this->who; }
    public function setWho(string $who): self { $this->who = $who; $this->touch(); return $this; }

    public function getDelivery(): string { return $this->delivery; }
    public function setDelivery(string $delivery): self
    {
        $this->delivery = $delivery === self::DELIVERY_TRIP ? self::DELIVERY_TRIP : self::DELIVERY_PICKUP;
        if ($this->delivery === self::DELIVERY_PICKUP) {
            $this->tripReleasedAt = null;
        }
        $this->touch();
        return $this;
    }

    public function isTrip(): bool
    {
        return $this->delivery === self::DELIVERY_TRIP;
    }

    public function getTripReleasedAt(): ?\DateTime { return $this->tripReleasedAt; }
    public function setTripReleasedAt(?\DateTime $tripReleasedAt): self
    {
        $this->tripReleasedAt = $tripReleasedAt;
        $this->touch();
        return $this;
    }

    public function isTripReleased(): bool
    {
        return $this->tripReleasedAt !== null;
    }

    public function getChauffeurUserId(): ?string { return $this->chauffeurUserId; }
    public function setChauffeurUserId(?string $chauffeurUserId): self
    {
        $this->chauffeurUserId = $chauffeurUserId ?: null;
        $this->touch();
        return $this;
    }

    public function getDestinationPlaceId(): ?string { return $this->destinationPlaceId; }
    public function setDestinationPlaceId(?string $destinationPlaceId): self
    {
        $this->destinationPlaceId = $destinationPlaceId ?: null;
        $this->touch();
        return $this;
    }

    public function getIssuedToUserId(): ?string { return $this->issuedToUserId; }
    public function setIssuedToUserId(?string $issuedToUserId): self
    {
        $this->issuedToUserId = $issuedToUserId ?: null;
        $this->touch();
        return $this;
    }

    public function isPacked(): bool { return $this->packed; }
    public function setPacked(bool $packed): self { $this->packed = $packed; $this->touch(); return $this; }

    public function getPackPhase(): string { return $this->packPhase; }
    public function setPackPhase(string $packPhase): self { $this->packPhase = $packPhase; $this->touch(); return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }
}
