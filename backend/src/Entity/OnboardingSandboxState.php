<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Registry: Übungs-Aktivität/Camp pro Department+User (Onboarding Hybrid-Sandbox).
 * Spec: docs/onboarding/sandboxtoolactivities/
 */
#[ORM\Entity]
#[ORM\Table(name: 'onboarding_sandbox_state')]
#[ORM\UniqueConstraint(name: 'uniq_onboarding_sandbox_dept_user', columns: ['department_id', 'user_id'])]
#[ORM\Index(name: 'idx_onboarding_sandbox_state_dept', columns: ['department_id'])]
class OnboardingSandboxState
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

    #[ORM\Column(name: 'user_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $userId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'activity_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $activityId = null;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'activity_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Activity $activity = null;

    #[ORM\Column(name: 'camp_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $campId = null;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'camp_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Activity $camp = null;

    #[ORM\Column(name: 'venue_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $venueId = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(name: 'venue_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Address $venue = null;

    #[ORM\Column(name: 'last_for_tour', type: 'string', length: 64, nullable: true)]
    private ?string $lastForTour = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getDepartmentId(): string
    {
        return $this->departmentId;
    }

    public function setDepartment(Department $department): self
    {
        $this->department = $department;
        $this->departmentId = (string) $department->getId();
        return $this;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        $this->userId = (string) $user->getId();
        return $this;
    }

    public function getActivityId(): ?string
    {
        return $this->activityId;
    }

    public function setActivity(?Activity $activity): self
    {
        $this->activity = $activity;
        $this->activityId = $activity?->getId();
        return $this;
    }

    public function getCampId(): ?string
    {
        return $this->campId;
    }

    public function setCamp(?Activity $camp): self
    {
        $this->camp = $camp;
        $this->campId = $camp?->getId();
        return $this;
    }

    public function getVenueId(): ?string
    {
        return $this->venueId;
    }

    public function setVenue(?Address $venue): self
    {
        $this->venue = $venue;
        $this->venueId = $venue?->getId();
        return $this;
    }

    public function getLastForTour(): ?string
    {
        return $this->lastForTour;
    }

    public function setLastForTour(?string $lastForTour): self
    {
        $this->lastForTour = $lastForTour;
        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTime();
        return $this;
    }
}
