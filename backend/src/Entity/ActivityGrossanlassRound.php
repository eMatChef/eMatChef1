<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_grossanlass_round')]
#[ORM\Index(name: 'idx_grossanlass_round_activity', columns: ['activity_id'])]
#[ORM\Index(name: 'idx_grossanlass_round_status', columns: ['status'])]
class ActivityGrossanlassRound
{
    public const TYPE_RESSORT_WUENSCHE = 'ressort_wuensche';

    public const PURPOSE_MATERIAL_WISH = 'material_wish';
    public const PURPOSE_COMPANY_TIP = 'company_tip';
    public const PURPOSE_FREE = 'free';

    /** @var list<string> */
    public const FORM_PURPOSES = [
        self::PURPOSE_MATERIAL_WISH,
        self::PURPOSE_COMPANY_TIP,
        self::PURPOSE_FREE,
    ];

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'activity_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $activityId;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'activity_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Activity $activity;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(name: 'round_type', type: 'string', length: 32)]
    private string $roundType = self::TYPE_RESSORT_WUENSCHE;

    #[ORM\Column(name: 'form_purpose', type: 'string', length: 32, options: ['default' => 'material_wish'])]
    private string $formPurpose = self::PURPOSE_MATERIAL_WISH;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_SCHEDULED;

    #[ORM\Column(name: 'opens_at', type: 'datetime', nullable: true)]
    private ?\DateTime $opensAt = null;

    #[ORM\Column(name: 'closes_at', type: 'datetime', nullable: true)]
    private ?\DateTime $closesAt = null;

    #[ORM\Column(name: 'use_auto_schedule', type: 'boolean', options: ['default' => false])]
    private bool $useAutoSchedule = false;

    #[ORM\Column(name: 'opened_at', type: 'datetime', nullable: true)]
    private ?\DateTime $openedAt = null;

    #[ORM\Column(name: 'closed_at', type: 'datetime', nullable: true)]
    private ?\DateTime $closedAt = null;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $createdByUserId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $createdByUser;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getRoundType(): string
    {
        return $this->roundType;
    }

    public function setRoundType(string $roundType): self
    {
        $this->roundType = $roundType;

        return $this;
    }

    public function getFormPurpose(): string
    {
        return $this->formPurpose;
    }

    public function setFormPurpose(string $formPurpose): self
    {
        $this->formPurpose = $formPurpose;

        return $this;
    }

    public function isMaterialWish(): bool
    {
        return $this->formPurpose === self::PURPOSE_MATERIAL_WISH;
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

    public function getOpensAt(): ?\DateTime
    {
        return $this->opensAt;
    }

    public function setOpensAt(?\DateTime $opensAt): self
    {
        $this->opensAt = $opensAt;

        return $this;
    }

    public function getClosesAt(): ?\DateTime
    {
        return $this->closesAt;
    }

    public function setClosesAt(?\DateTime $closesAt): self
    {
        $this->closesAt = $closesAt;

        return $this;
    }

    public function isUseAutoSchedule(): bool
    {
        return $this->useAutoSchedule;
    }

    public function setUseAutoSchedule(bool $useAutoSchedule): self
    {
        $this->useAutoSchedule = $useAutoSchedule;

        return $this;
    }

    public function getOpenedAt(): ?\DateTime
    {
        return $this->openedAt;
    }

    public function setOpenedAt(?\DateTime $openedAt): self
    {
        $this->openedAt = $openedAt;

        return $this;
    }

    public function getClosedAt(): ?\DateTime
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTime $closedAt): self
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function getCreatedByUserId(): string
    {
        return $this->createdByUserId;
    }

    public function getCreatedByUser(): User
    {
        return $this->createdByUser;
    }

    public function setCreatedByUser(User $user): self
    {
        $this->createdByUser = $user;
        $this->createdByUserId = $user->getId();

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function touchUpdatedAt(): self
    {
        $this->updatedAt = new \DateTime();

        return $this;
    }
}
