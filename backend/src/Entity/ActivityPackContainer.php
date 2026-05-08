<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_pack_container')]
#[ORM\Index(name: 'idx_pack_container_activity', columns: ['activity_id'])]
#[ORM\Index(name: 'idx_pack_container_batch', columns: ['container_batch_id'])]
class ActivityPackContainer
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'activity_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $activityId;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'activity_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Activity $activity;

    #[ORM\Column(name: 'container_batch_id', type: 'string', length: 13, nullable: true, columnDefinition: 'CHARACTER(13) NULL')]
    private ?string $containerBatchId = null;

    #[ORM\ManyToOne(targetEntity: MaterialBatch::class)]
    #[ORM\JoinColumn(name: 'container_batch_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MaterialBatch $containerBatch = null;

    #[ORM\Column(type: 'string', length: 120)]
    private string $label;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'draft'])]
    private string $status = 'draft';

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
    public function setActivityId(string $activityId): self { $this->activityId = $activityId; return $this; }
    public function getActivity(): Activity { return $this->activity; }
    public function setActivity(Activity $activity): self { $this->activity = $activity; $this->activityId = $activity->getId(); return $this; }
    public function getContainerBatchId(): ?string { return $this->containerBatchId; }
    public function setContainerBatchId(?string $containerBatchId): self { $this->containerBatchId = $containerBatchId; return $this; }
    public function getContainerBatch(): ?MaterialBatch { return $this->containerBatch; }
    public function setContainerBatch(?MaterialBatch $containerBatch): self { $this->containerBatch = $containerBatch; $this->containerBatchId = $containerBatch?->getId(); return $this; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTime(); return $this; }
}

