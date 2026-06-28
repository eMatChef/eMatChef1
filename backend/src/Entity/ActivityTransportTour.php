<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_transport_tour')]
#[ORM\Index(name: 'idx_transport_tour_activity', columns: ['activity_id'])]
class ActivityTransportTour
{
    public const DIRECTION_OUTBOUND = 'outbound';
    public const DIRECTION_INBOUND = 'inbound';

    public const STATUS_PLANNED = 'planned';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_ARRIVED = 'arrived';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'activity_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $activityId;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'activity_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Activity $activity;

    #[ORM\Column(type: 'string', length: 80)]
    private string $label;

    #[ORM\Column(name: 'vehicle_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $vehicleId;

    #[ORM\ManyToOne(targetEntity: DepartmentVehicle::class)]
    #[ORM\JoinColumn(name: 'vehicle_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private DepartmentVehicle $vehicle;

    #[ORM\Column(name: 'lending_department_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $lendingDepartmentId = null;

    #[ORM\Column(type: 'string', length: 16)]
    private string $direction;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => 'planned'])]
    private string $status = self::STATUS_PLANNED;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $createdByUserId = null;

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
    public function getActivityId(): string { return $this->activityId; }
    public function setActivity(Activity $activity): self
    {
        $this->activity = $activity;
        $this->activityId = $activity->getId();
        return $this;
    }
    public function getActivity(): Activity { return $this->activity; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }
    public function getVehicleId(): string { return $this->vehicleId; }
    public function setVehicle(DepartmentVehicle $vehicle): self
    {
        $this->vehicle = $vehicle;
        $this->vehicleId = $vehicle->getId();
        return $this;
    }
    public function getVehicle(): DepartmentVehicle { return $this->vehicle; }
    public function getLendingDepartmentId(): ?string { return $this->lendingDepartmentId; }
    public function setLendingDepartmentId(?string $id): self { $this->lendingDepartmentId = $id; return $this; }
    public function getDirection(): string { return $this->direction; }
    public function setDirection(string $direction): self { $this->direction = $direction; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): self { $this->sortOrder = $sortOrder; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }
    public function getCreatedByUserId(): ?string { return $this->createdByUserId; }
    public function setCreatedByUserId(?string $id): self { $this->createdByUserId = $id; return $this; }
    public function touch(): self { $this->updatedAt = new \DateTime(); return $this; }
}
