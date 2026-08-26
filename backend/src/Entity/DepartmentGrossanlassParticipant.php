<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_participant')]
#[ORM\UniqueConstraint(name: 'uniq_ga_participant_host_guest', columns: ['host_department_id', 'guest_department_id'])]
#[ORM\Index(name: 'idx_ga_participant_guest', columns: ['guest_department_id'])]
class DepartmentGrossanlassParticipant
{
    public const STATUS_PLANNED = 'planned';
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

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

    #[ORM\Column(type: 'string', length: 20, options: ['default' => self::STATUS_PLANNED])]
    private string $status = self::STATUS_PLANNED;

    #[ORM\Column(name: 'guest_group_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $guestGroupId = null;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(name: 'guest_group_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Group $guestGroup = null;

    #[ORM\Column(name: 'guest_activity_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $guestActivityId = null;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'guest_activity_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Activity $guestActivity = null;

    #[ORM\Column(name: 'invited_at', type: 'datetime', nullable: true)]
    private ?\DateTime $invitedAt = null;

    #[ORM\Column(name: 'decided_at', type: 'datetime', nullable: true)]
    private ?\DateTime $decidedAt = null;

    #[ORM\Column(name: 'decided_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $decidedByUserId = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getHostDepartment(): Department
    {
        return $this->hostDepartment;
    }

    public function setHostDepartment(Department $department): self
    {
        $this->hostDepartment = $department;
        $this->hostDepartmentId = $department->getId();

        return $this;
    }

    public function getGuestDepartment(): Department
    {
        return $this->guestDepartment;
    }

    public function getGuestDepartmentId(): string
    {
        return $this->guestDepartmentId;
    }

    public function setGuestDepartment(Department $department): self
    {
        $this->guestDepartment = $department;
        $this->guestDepartmentId = $department->getId();

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getGuestGroup(): ?Group
    {
        return $this->guestGroup;
    }

    public function setGuestGroup(?Group $group): self
    {
        $this->guestGroup = $group;
        $this->guestGroupId = $group?->getId();

        return $this;
    }

    public function getGuestActivity(): ?Activity
    {
        return $this->guestActivity;
    }

    public function getGuestActivityId(): ?string
    {
        return $this->guestActivityId;
    }

    public function setGuestActivity(?Activity $activity): self
    {
        $this->guestActivity = $activity;
        $this->guestActivityId = $activity?->getId();

        return $this;
    }

    public function getInvitedAt(): ?\DateTime
    {
        return $this->invitedAt;
    }

    public function setInvitedAt(?\DateTime $invitedAt): self
    {
        $this->invitedAt = $invitedAt;

        return $this;
    }

    public function setDecidedAt(?\DateTime $decidedAt): self
    {
        $this->decidedAt = $decidedAt;

        return $this;
    }

    public function setDecidedByUserId(?string $userId): self
    {
        $this->decidedByUserId = $userId;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}
