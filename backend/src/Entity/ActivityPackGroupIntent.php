<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_pack_group_intent')]
#[ORM\Index(name: 'idx_pack_group_intent_activity', columns: ['activity_id'])]
class ActivityPackGroupIntent
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

    #[ORM\Column(type: 'string', length: 80, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $createdByUserId;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'resolved_at', type: 'datetime', nullable: true)]
    private ?\DateTime $resolvedAt = null;

    #[ORM\Column(name: 'resolved_container_id', type: 'string', length: 13, nullable: true, columnDefinition: 'CHARACTER(13) NULL')]
    private ?string $resolvedContainerId = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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
    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $label): self { $this->label = $label; return $this; }
    public function getCreatedByUserId(): string { return $this->createdByUserId; }
    public function setCreatedByUserId(string $id): self { $this->createdByUserId = $id; return $this; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function getResolvedAt(): ?\DateTime { return $this->resolvedAt; }
    public function setResolvedAt(?\DateTime $at): self { $this->resolvedAt = $at; return $this; }
    public function getResolvedContainerId(): ?string { return $this->resolvedContainerId; }
    public function setResolvedContainerId(?string $id): self { $this->resolvedContainerId = $id; return $this; }
    public function isResolved(): bool { return $this->resolvedAt !== null; }
}
