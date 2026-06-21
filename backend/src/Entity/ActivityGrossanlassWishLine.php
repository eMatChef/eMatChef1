<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_grossanlass_wish_line')]
#[ORM\Index(name: 'idx_grossanlass_wish_round', columns: ['round_id'])]
#[ORM\Index(name: 'idx_grossanlass_wish_group', columns: ['group_id'])]
class ActivityGrossanlassWishLine
{
    public const KIND_MATERIAL = 'material';
    public const KIND_FAHRZEUG = 'fahrzeug';
    public const KIND_BEIDES = 'beides';

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_ACCEPTED = 'accepted';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'round_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $roundId;

    #[ORM\ManyToOne(targetEntity: ActivityGrossanlassRound::class)]
    #[ORM\JoinColumn(name: 'round_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ActivityGrossanlassRound $round;

    #[ORM\Column(name: 'group_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $groupId;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Group $group;

    #[ORM\Column(name: 'wish_kind', type: 'string', length: 20)]
    private string $wishKind;

    #[ORM\Column(type: 'string', length: 255)]
    private string $label;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    #[ORM\Column(type: 'string', length: 255)]
    private string $location;

    #[ORM\Column(name: 'valid_from', type: 'datetime')]
    private \DateTime $validFrom;

    #[ORM\Column(name: 'valid_to', type: 'datetime')]
    private \DateTime $validTo;

    #[ORM\Column(name: 'timeframe_notes', type: 'text', nullable: true)]
    private ?string $timeframeNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_REQUESTED;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $createdByUserId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $createdByUser;

    #[ORM\Column(name: 'response_id', type: 'string', length: 12, nullable: true, unique: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $responseId = null;

    #[ORM\OneToOne(targetEntity: ActivityGrossanlassWishResponse::class)]
    #[ORM\JoinColumn(name: 'response_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ActivityGrossanlassWishResponse $response = null;

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

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function setGroup(Group $group): self
    {
        $this->group = $group;
        $this->groupId = $group->getId();

        return $this;
    }

    public function getWishKind(): string
    {
        return $this->wishKind;
    }

    public function setWishKind(string $wishKind): self
    {
        $this->wishKind = $wishKind;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(string $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getValidFrom(): \DateTime
    {
        return $this->validFrom;
    }

    public function setValidFrom(\DateTime $validFrom): self
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidTo(): \DateTime
    {
        return $this->validTo;
    }

    public function setValidTo(\DateTime $validTo): self
    {
        $this->validTo = $validTo;

        return $this;
    }

    public function getTimeframeNotes(): ?string
    {
        return $this->timeframeNotes;
    }

    public function setTimeframeNotes(?string $timeframeNotes): self
    {
        $this->timeframeNotes = $timeframeNotes;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

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

    public function getResponseId(): ?string
    {
        return $this->responseId;
    }

    public function getResponse(): ?ActivityGrossanlassWishResponse
    {
        return $this->response;
    }

    public function setResponse(?ActivityGrossanlassWishResponse $response): self
    {
        $this->response = $response;
        $this->responseId = $response?->getId();

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
