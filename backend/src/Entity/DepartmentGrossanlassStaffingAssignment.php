<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Zuteilung eines Helfers auf einen Personalbedarf, ganz oder teilweise (eigenes Zeitfenster innerhalb
 * des Bedarfsfensters). Interne Doppelbuchungen verhindert der Service transaktional unter Sperre
 * auf dem Helfer; Ausnahmen (`override_*`) nur durch GA-HV/GA-SUBHV mit Begründung, protokolliert in
 * {@see DepartmentGrossanlassStaffingLog}. Bewusst kein Exclusion Constraint.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_staffing_assignment')]
#[ORM\Index(name: 'idx_ga_staffing_assign_need', columns: ['need_id', 'starts_at', 'ends_at'])]
#[ORM\Index(name: 'idx_ga_staffing_assign_helper', columns: ['helper_id', 'starts_at', 'ends_at'])]
class DepartmentGrossanlassStaffingAssignment
{
    public const STATUS_PLANNED = 'planned';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    /** Durch eine Änderung der externen Buchung ungültig; zählt nicht zur Personaldeckung. */
    public const STATUS_CONFLICT = 'conflict';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\Column(name: 'need_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $needId;

    #[ORM\Column(name: 'helper_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $helperId;

    #[ORM\Column(name: 'starts_at', type: 'datetime')]
    private \DateTime $startsAt;

    #[ORM\Column(name: 'ends_at', type: 'datetime')]
    private \DateTime $endsAt;

    #[ORM\Column(type: 'string', length: 12, options: ['default' => self::STATUS_PLANNED])]
    private string $status = self::STATUS_PLANNED;

    #[ORM\Column(name: 'override_reason', type: 'text', nullable: true)]
    private ?string $overrideReason = null;

    #[ORM\Column(name: 'override_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $overrideByUserId = null;

    #[ORM\Column(name: 'override_at', type: 'datetime', nullable: true)]
    private ?\DateTime $overrideAt = null;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $createdByUserId = null;

    #[ORM\Column(name: 'status_before_conflict', type: 'string', length: 12, nullable: true)]
    private ?string $statusBeforeConflict = null;

    /** @var ?array<string, mixed> */
    #[ORM\Column(name: 'conflict_info', type: 'json', nullable: true)]
    private ?array $conflictInfo = null;

    #[ORM\Column(name: 'conflict_at', type: 'datetime', nullable: true)]
    private ?\DateTime $conflictAt = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct(string $id, string $departmentId, string $needId, string $helperId, \DateTime $startsAt, \DateTime $endsAt)
    {
        $this->id = $id;
        $this->departmentId = $departmentId;
        $this->needId = $needId;
        $this->helperId = $helperId;
        $this->setWindow($startsAt, $endsAt);
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function getDepartmentId(): string { return $this->departmentId; }
    public function getNeedId(): string { return $this->needId; }
    public function getHelperId(): string { return $this->helperId; }
    public function getStartsAt(): \DateTime { return $this->startsAt; }
    public function getEndsAt(): \DateTime { return $this->endsAt; }
    public function getStatus(): string { return $this->status; }
    public function isActive(): bool { return $this->status !== self::STATUS_CANCELLED; }
    public function isConflict(): bool { return $this->status === self::STATUS_CONFLICT; }
    /** @return ?array<string, mixed> */
    public function getConflictInfo(): ?array { return $this->conflictInfo; }
    public function getConflictAt(): ?\DateTime { return $this->conflictAt; }
    public function getOverrideReason(): ?string { return $this->overrideReason; }
    public function getOverrideByUserId(): ?string { return $this->overrideByUserId; }
    public function getOverrideAt(): ?\DateTime { return $this->overrideAt; }
    public function hasOverride(): bool { return $this->overrideReason !== null; }
    public function getCreatedByUserId(): ?string { return $this->createdByUserId; }
    public function setCreatedByUserId(?string $id): self { $this->createdByUserId = $id; return $this; }
    public function getVersion(): int { return $this->version; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }

    public function setWindow(\DateTime $startsAt, \DateTime $endsAt): self
    {
        if ($endsAt <= $startsAt) {
            throw new \InvalidArgumentException('Ende muss nach dem Start liegen');
        }
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /** @param array<string, mixed> $info */
    public function markConflict(array $info): self
    {
        if ($this->status !== self::STATUS_CONFLICT) {
            $this->statusBeforeConflict = $this->status;
            $this->conflictAt = new \DateTime();
        }
        $this->status = self::STATUS_CONFLICT;
        $this->conflictInfo = $info;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function resolveConflict(): self
    {
        $this->status = $this->statusBeforeConflict ?? self::STATUS_PLANNED;
        $this->statusBeforeConflict = null;
        $this->conflictInfo = null;
        $this->conflictAt = null;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function applyOverride(string $reason, string $userId): self
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Ausnahme braucht eine Begründung');
        }
        $this->overrideReason = $reason;
        $this->overrideByUserId = $userId;
        $this->overrideAt = new \DateTime();
        $this->updatedAt = new \DateTime();

        return $this;
    }
}
