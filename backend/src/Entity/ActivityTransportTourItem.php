<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_transport_tour_item')]
#[ORM\Index(name: 'idx_transport_tour_item_tour', columns: ['tour_id'])]
class ActivityTransportTourItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'tour_id', type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    private string $tourId;

    #[ORM\ManyToOne(targetEntity: ActivityTransportTour::class)]
    #[ORM\JoinColumn(name: 'tour_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ActivityTransportTour $tour;

    #[ORM\Column(name: 'pack_container_id', type: 'string', length: 13, nullable: true, columnDefinition: 'CHARACTER(13) NULL')]
    private ?string $packContainerId = null;

    #[ORM\Column(name: 'pack_item_id', type: 'string', length: 13, nullable: true, columnDefinition: 'CHARACTER(13) NULL')]
    private ?string $packItemId = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quantity = null;

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
    public function getTourId(): string { return $this->tourId; }
    public function setTour(ActivityTransportTour $tour): self
    {
        $this->tour = $tour;
        $this->tourId = $tour->getId();
        return $this;
    }
    public function getTour(): ActivityTransportTour { return $this->tour; }
    public function getPackContainerId(): ?string { return $this->packContainerId; }
    public function setPackContainerId(?string $id): self { $this->packContainerId = $id; return $this; }
    public function getPackItemId(): ?string { return $this->packItemId; }
    public function setPackItemId(?string $id): self { $this->packItemId = $id; return $this; }
    public function getQuantity(): ?int { return $this->quantity; }
    public function setQuantity(?int $quantity): self { $this->quantity = $quantity; return $this; }
    public function touch(): self { $this->updatedAt = new \DateTime(); return $this; }
}
