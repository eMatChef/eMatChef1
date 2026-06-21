<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_grossanlass_round_form')]
class ActivityGrossanlassRoundForm
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'round_id', type: 'string', length: 12, unique: true, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $roundId;

    #[ORM\OneToOne(targetEntity: ActivityGrossanlassRound::class)]
    #[ORM\JoinColumn(name: 'round_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ActivityGrossanlassRound $round;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $introText = null;

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

    public function getIntroText(): ?string
    {
        return $this->introText;
    }

    public function setIntroText(?string $introText): self
    {
        $this->introText = $introText;

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
