<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_config')]
class DepartmentGrossanlassConfig
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public const STRUKTUR_OFFEN = 'offen';
    public const STRUKTUR_VERSCHACHTELT = 'verschachtelt';
    public const STRUKTUR_PARALLEL = 'parallel';

    #[ORM\Id]
    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\OneToOne(targetEntity: Department::class, inversedBy: 'grossanlassConfig')]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'main_activity_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $mainActivityId = null;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'main_activity_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Activity $mainActivity = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => self::STATUS_DRAFT])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(name: 'published_at', type: 'datetime', nullable: true)]
    private ?\DateTime $publishedAt = null;

    #[ORM\Column(name: 'published_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $publishedByUserId = null;

    #[ORM\Column(name: 'struktur_modus', type: 'string', length: 20, options: ['default' => self::STRUKTUR_OFFEN])]
    private string $strukturModus = self::STRUKTUR_OFFEN;

    #[ORM\Column(name: 'planned_event_start', type: 'datetime')]
    private \DateTime $plannedEventStart;

    #[ORM\Column(name: 'planned_event_end', type: 'datetime', nullable: true)]
    private ?\DateTime $plannedEventEnd = null;

    public function getDepartmentId(): string
    {
        return $this->departmentId;
    }

    public function getDepartment(): Department
    {
        return $this->department;
    }

    public function setDepartment(Department $department): self
    {
        $this->department = $department;
        $this->departmentId = $department->getId();

        return $this;
    }

    public function getMainActivityId(): ?string
    {
        return $this->mainActivityId;
    }

    public function getMainActivity(): ?Activity
    {
        return $this->mainActivity;
    }

    public function setMainActivity(?Activity $activity): self
    {
        $this->mainActivity = $activity;
        $this->mainActivityId = $activity?->getId();

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

    public function getPublishedAt(): ?\DateTime
    {
        return $this->publishedAt;
    }

    public function getPublishedByUserId(): ?string
    {
        return $this->publishedByUserId;
    }

    public function getStrukturModus(): string
    {
        return $this->strukturModus;
    }

    public function setStrukturModus(string $strukturModus): self
    {
        $this->strukturModus = $strukturModus;

        return $this;
    }

    public function getPlannedEventStart(): \DateTime
    {
        return $this->plannedEventStart;
    }

    public function setPlannedEventStart(\DateTime $plannedEventStart): self
    {
        $this->plannedEventStart = $plannedEventStart;

        return $this;
    }

    public function getPlannedEventEnd(): ?\DateTime
    {
        return $this->plannedEventEnd;
    }

    public function setPlannedEventEnd(?\DateTime $plannedEventEnd): self
    {
        $this->plannedEventEnd = $plannedEventEnd;

        return $this;
    }
}
