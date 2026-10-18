<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Geordneter Stopp einer Tour (Aufladen/Abholen/Abladen) mit geplanter Zeit; gehört optional zu einem Bedarf der Tour. */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_tour_stop')]
#[ORM\Index(name: 'idx_ga_tour_stop_tour', columns: ['tour_id'])]
class DepartmentGrossanlassTourStop
{
    public const KIND_LOAD = 'load';
    public const KIND_PICKUP = 'pickup';
    public const KIND_UNLOAD = 'unload';
    public const KINDS = [self::KIND_LOAD, self::KIND_PICKUP, self::KIND_UNLOAD];

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'tour_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $tourId;

    #[ORM\ManyToOne(targetEntity: DepartmentGrossanlassTour::class)]
    #[ORM\JoinColumn(name: 'tour_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private DepartmentGrossanlassTour $tour;

    #[ORM\Column(name: 'item_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $itemId = null;

    #[ORM\ManyToOne(targetEntity: DepartmentGrossanlassTourItem::class)]
    #[ORM\JoinColumn(name: 'item_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?DepartmentGrossanlassTourItem $item = null;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'string', length: 10)]
    private string $kind = self::KIND_LOAD;

    #[ORM\Column(name: 'place_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $placeId = null;

    #[ORM\Column(type: 'string', length: 255, options: ['default' => ''])]
    private string $label = '';

    #[ORM\Column(name: 'planned_at', type: 'datetime', nullable: true)]
    private ?\DateTime $plannedAt = null;

    public function getId(): string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }
    public function getTourId(): string { return $this->tourId; }
    public function setTour(DepartmentGrossanlassTour $tour): self
    {
        $this->tour = $tour;
        $this->tourId = $tour->getId();
        return $this;
    }
    public function getItemId(): ?string { return $this->itemId; }
    public function setItem(?DepartmentGrossanlassTourItem $item): self
    {
        $this->item = $item;
        $this->itemId = $item?->getId();
        return $this;
    }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): self { $this->sortOrder = $sortOrder; return $this; }
    public function getKind(): string { return $this->kind; }
    public function setKind(string $kind): self { $this->kind = $kind; return $this; }
    public function getPlaceId(): ?string { return $this->placeId; }
    public function setPlaceId(?string $placeId): self { $this->placeId = $placeId ?: null; return $this; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }
    public function getPlannedAt(): ?\DateTime { return $this->plannedAt; }
    public function setPlannedAt(?\DateTime $plannedAt): self { $this->plannedAt = $plannedAt; return $this; }
}
