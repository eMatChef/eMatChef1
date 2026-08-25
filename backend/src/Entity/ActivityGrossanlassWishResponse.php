<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_grossanlass_wish_response')]
#[ORM\Index(name: 'idx_grossanlass_wish_response_round', columns: ['round_id'])]
#[ORM\Index(name: 'idx_grossanlass_wish_response_group', columns: ['group_id'])]
class ActivityGrossanlassWishResponse
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DISCARDED = 'discarded';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'round_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $roundId;

    #[ORM\ManyToOne(targetEntity: ActivityGrossanlassRound::class)]
    #[ORM\JoinColumn(name: 'round_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ActivityGrossanlassRound $round;

    #[ORM\Column(name: 'form_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $formId;

    #[ORM\ManyToOne(targetEntity: ActivityGrossanlassRoundForm::class)]
    #[ORM\JoinColumn(name: 'form_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ActivityGrossanlassRoundForm $form;

    #[ORM\Column(name: 'group_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $groupId = null;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Group $group = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_REQUESTED;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $createdByUserId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $createdByUser;

    #[ORM\Column(name: 'updated_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $updatedByUserId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedByUser = null;

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

    public function getRoundId(): string
    {
        return $this->roundId;
    }

    public function getRound(): ActivityGrossanlassRound
    {
        return $this->round;
    }

    public function setRound(ActivityGrossanlassRound $round): self
    {
        $this->round = $round;
        $this->roundId = $round->getId();

        return $this;
    }

    public function getFormId(): string
    {
        return $this->formId;
    }

    public function getForm(): ActivityGrossanlassRoundForm
    {
        return $this->form;
    }

    public function setForm(ActivityGrossanlassRoundForm $form): self
    {
        $this->form = $form;
        $this->formId = $form->getId();

        return $this;
    }

    public function getGroupId(): ?string
    {
        return $this->groupId;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): self
    {
        $this->group = $group;
        $this->groupId = $group?->getId();

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

    public function getUpdatedByUserId(): ?string
    {
        return $this->updatedByUserId;
    }

    public function setUpdatedByUser(?User $user): self
    {
        $this->updatedByUser = $user;
        $this->updatedByUserId = $user?->getId();

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

    public function touchUpdatedAt(?User $user = null): self
    {
        $this->updatedAt = new \DateTime();
        if ($user) {
            $this->setUpdatedByUser($user);
        }

        return $this;
    }
}
