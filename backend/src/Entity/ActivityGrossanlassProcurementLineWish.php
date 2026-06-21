<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_grossanlass_procurement_line_wish')]
class ActivityGrossanlassProcurementLineWish
{
    #[ORM\Id]
    #[ORM\Column(name: 'procurement_line_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $procurementLineId;

    #[ORM\Id]
    #[ORM\Column(name: 'wish_line_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $wishLineId;

    #[ORM\ManyToOne(targetEntity: ActivityGrossanlassProcurementLine::class)]
    #[ORM\JoinColumn(name: 'procurement_line_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ActivityGrossanlassProcurementLine $procurementLine;

    #[ORM\ManyToOne(targetEntity: ActivityGrossanlassWishLine::class)]
    #[ORM\JoinColumn(name: 'wish_line_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ActivityGrossanlassWishLine $wishLine;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getProcurementLineId(): string
    {
        return $this->procurementLineId;
    }

    public function getWishLineId(): string
    {
        return $this->wishLineId;
    }

    public function getProcurementLine(): ActivityGrossanlassProcurementLine
    {
        return $this->procurementLine;
    }

    public function setProcurementLine(ActivityGrossanlassProcurementLine $line): self
    {
        $this->procurementLine = $line;
        $this->procurementLineId = $line->getId();

        return $this;
    }

    public function getWishLine(): ActivityGrossanlassWishLine
    {
        return $this->wishLine;
    }

    public function setWishLine(ActivityGrossanlassWishLine $wishLine): self
    {
        $this->wishLine = $wishLine;
        $this->wishLineId = $wishLine->getId();

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}
