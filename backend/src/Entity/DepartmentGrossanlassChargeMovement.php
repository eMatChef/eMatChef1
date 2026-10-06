<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Mengenbasierte physische Bewegung einer Charge (Zusage).
 * Physische Wahrheit für Wareneingang, später Rücknahme und Disposition.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_charge_movement')]
#[ORM\Index(name: 'idx_ga_charge_movement_commitment', columns: ['commitment_id'])]
#[ORM\Index(name: 'idx_ga_charge_movement_dept', columns: ['department_id'])]
#[ORM\Index(name: 'idx_ga_charge_movement_place', columns: ['place_id'])]
#[ORM\Index(name: 'idx_ga_charge_movement_einsatz', columns: ['einsatz_id'])]
#[ORM\Index(name: 'idx_ga_charge_movement_user', columns: ['created_by_user_id'])]
class DepartmentGrossanlassChargeMovement
{
    public const KIND_RECEIVED = 'received';
    public const KIND_RETURNED_TO_STORE = 'returned_to_store';
    public const KIND_TO_WORKSHOP = 'to_workshop';
    public const KIND_RETURNED_TO_OWNER = 'returned_to_owner';
    public const KIND_SOLD_HANDOVER = 'sold_handover';
    public const KIND_DISPOSED = 'disposed';
    public const KIND_CONSUMED = 'consumed';
    public const KIND_LOST = 'lost';

    /** @var list<string> */
    public const KINDS = [
        self::KIND_RECEIVED,
        self::KIND_RETURNED_TO_STORE,
        self::KIND_TO_WORKSHOP,
        self::KIND_RETURNED_TO_OWNER,
        self::KIND_SOLD_HANDOVER,
        self::KIND_DISPOSED,
        self::KIND_CONSUMED,
        self::KIND_LOST,
    ];

    /** Arten mit fertigem Ablauf. Die übrigen sind vorbereitet, aber noch nicht buchbar. */
    public const BOOKABLE_KINDS = [self::KIND_RECEIVED];

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'commitment_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $commitmentId;

    #[ORM\ManyToOne(targetEntity: DepartmentGrossanlassCommitment::class)]
    #[ORM\JoinColumn(name: 'commitment_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private DepartmentGrossanlassCommitment $commitment;

    #[ORM\Column(type: 'string', length: 24)]
    private string $kind = self::KIND_RECEIVED;

    #[ORM\Column(type: 'integer')]
    private int $quantity = 0;

    #[ORM\Column(name: 'occurred_at', type: 'datetime')]
    private \DateTime $occurredAt;

    #[ORM\Column(name: 'place_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $placeId = null;

    #[ORM\ManyToOne(targetEntity: DepartmentGrossanlassPlace::class)]
    #[ORM\JoinColumn(name: 'place_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?DepartmentGrossanlassPlace $place = null;

    #[ORM\Column(name: 'einsatz_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $einsatzId = null;

    #[ORM\ManyToOne(targetEntity: DepartmentGrossanlassEinsatz::class)]
    #[ORM\JoinColumn(name: 'einsatz_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?DepartmentGrossanlassEinsatz $einsatz = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $createdByUserId = null;

    /** Null bei Übernahme aus Altdaten (Migration) oder gelöschtem User. */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdByUser = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->occurredAt = new \DateTime();
        $this->createdAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }

    public function getDepartmentId(): string { return $this->departmentId; }
    public function getDepartment(): Department { return $this->department; }

    public function getCommitmentId(): string { return $this->commitmentId; }
    public function getCommitment(): DepartmentGrossanlassCommitment { return $this->commitment; }

    public function setCommitment(DepartmentGrossanlassCommitment $commitment): self
    {
        $this->commitment = $commitment;
        $this->commitmentId = $commitment->getId();
        $this->department = $commitment->getDepartment();
        $this->departmentId = $commitment->getDepartmentId();

        return $this;
    }

    public function getKind(): string { return $this->kind; }
    public function setKind(string $kind): self { $this->kind = $kind; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): self { $this->quantity = $quantity; return $this; }

    public function getOccurredAt(): \DateTime { return $this->occurredAt; }
    public function setOccurredAt(\DateTime $occurredAt): self { $this->occurredAt = $occurredAt; return $this; }

    public function getPlaceId(): ?string { return $this->placeId; }
    public function getPlace(): ?DepartmentGrossanlassPlace { return $this->place; }

    public function setPlace(?DepartmentGrossanlassPlace $place): self
    {
        $this->place = $place;
        $this->placeId = $place?->getId();

        return $this;
    }

    public function getEinsatzId(): ?string { return $this->einsatzId; }
    public function getEinsatz(): ?DepartmentGrossanlassEinsatz { return $this->einsatz; }

    public function setEinsatz(?DepartmentGrossanlassEinsatz $einsatz): self
    {
        $this->einsatz = $einsatz;
        $this->einsatzId = $einsatz?->getId();

        return $this;
    }

    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): self { $this->note = $note; return $this; }

    public function getCreatedByUserId(): ?string { return $this->createdByUserId; }
    public function getCreatedByUser(): ?User { return $this->createdByUser; }

    public function setCreatedByUser(?User $user): self
    {
        $this->createdByUser = $user;
        $this->createdByUserId = $user?->getId();

        return $this;
    }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
}
