<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_grossanlass_config')]
class ActivityGrossanlassConfig
{
    public const ROLE_ANLASS = 'anlass';
    public const ROLE_AUFBAU = 'aufbau';
    public const ROLE_ABBAU = 'abbau';
    public const ROLE_VOREVENT = 'vorevent';
    public const ROLE_NACH_EVENT = 'nach_event';

    #[ORM\Id]
    #[ORM\Column(name: 'activity_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $activityId;

    #[ORM\OneToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'activity_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Activity $activity;

    #[ORM\Column(name: 'grossanlass_role', type: 'string', length: 20)]
    private string $grossanlassRole;

    public function getActivityId(): string
    {
        return $this->activityId;
    }

    public function getActivity(): Activity
    {
        return $this->activity;
    }

    public function setActivity(Activity $activity): self
    {
        $this->activity = $activity;
        $this->activityId = $activity->getId();

        return $this;
    }

    public function getGrossanlassRole(): string
    {
        return $this->grossanlassRole;
    }

    public function setGrossanlassRole(string $grossanlassRole): self
    {
        $this->grossanlassRole = $grossanlassRole;

        return $this;
    }
}
