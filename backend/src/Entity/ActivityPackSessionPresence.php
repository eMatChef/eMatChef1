<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_pack_session_presence')]
#[ORM\Index(name: 'idx_pack_presence_activity', columns: ['activity_id', 'last_seen_at'])]
class ActivityPackSessionPresence
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

    #[ORM\Column(name: 'user_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $userId;

    #[ORM\Column(name: 'display_name', type: 'string', length: 120)]
    private string $displayName;

    #[ORM\Column(type: 'string', length: 80, nullable: true)]
    private ?string $shelf = null;

    #[ORM\Column(name: 'container_id', type: 'string', length: 13, nullable: true, columnDefinition: 'CHARACTER(13) NULL')]
    private ?string $containerId = null;

    #[ORM\Column(name: 'journey_step', type: 'string', length: 32, nullable: true)]
    private ?string $journeyStep = null;

    #[ORM\Column(name: 'last_seen_at', type: 'datetime')]
    private \DateTime $lastSeenAt;

    public function __construct()
    {
        $this->lastSeenAt = new \DateTime();
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
    public function getUserId(): string { return $this->userId; }
    public function setUserId(string $id): self { $this->userId = $id; return $this; }
    public function getDisplayName(): string { return $this->displayName; }
    public function setDisplayName(string $name): self { $this->displayName = $name; return $this; }
    public function getShelf(): ?string { return $this->shelf; }
    public function setShelf(?string $shelf): self { $this->shelf = $shelf; return $this; }
    public function getContainerId(): ?string { return $this->containerId; }
    public function setContainerId(?string $id): self { $this->containerId = $id; return $this; }
    public function getJourneyStep(): ?string { return $this->journeyStep; }
    public function setJourneyStep(?string $step): self { $this->journeyStep = $step; return $this; }
    public function getLastSeenAt(): \DateTime { return $this->lastSeenAt; }
    public function touch(): self { $this->lastSeenAt = new \DateTime(); return $this; }
}
