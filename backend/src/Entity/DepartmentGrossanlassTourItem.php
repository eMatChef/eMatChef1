<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Verknüpfung einer Tour mit genau einem bestehenden Transportbedarf: einem Fahrauftrag (Einsatz `delivery = trip`) oder
 * einem Fahrzeugwunsch. Die Eindeutigkeit je Einsatz bzw. Fahrzeugwunsch verhindert, dass ein Bedarf in zwei Touren liegt.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_tour_item')]
#[ORM\Index(name: 'idx_ga_tour_item_tour', columns: ['tour_id'])]
#[ORM\UniqueConstraint(name: 'uq_ga_tour_item_einsatz', columns: ['einsatz_id'], options: ['where' => '(outcome IS NULL)'])]
#[ORM\UniqueConstraint(name: 'uq_ga_tour_item_vehicle_need', columns: ['vehicle_need_id'], options: ['where' => '(outcome IS NULL)'])]
class DepartmentGrossanlassTourItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'tour_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $tourId;

    #[ORM\ManyToOne(targetEntity: DepartmentGrossanlassTour::class)]
    #[ORM\JoinColumn(name: 'tour_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private DepartmentGrossanlassTour $tour;

    #[ORM\Column(name: 'einsatz_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $einsatzId = null;

    #[ORM\Column(name: 'vehicle_need_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $vehicleNeedId = null;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    /** NULL = aktiv in der Tour; `delivered` / `not_executed` nach dem Tourabschluss (Historie bleibt, Bedarf ist wieder frei). */
    #[ORM\Column(type: 'string', length: 16, nullable: true)]
    private ?string $outcome = null;

    public function getId(): string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }
    public function getTourId(): string { return $this->tourId; }
    public function setTour(DepartmentGrossanlassTour $tour): self
    {
        $this->tour = $tour;
        $this->tourId = $tour->getId();
        return $this;
    }
    public function getEinsatzId(): ?string { return $this->einsatzId; }
    public function setEinsatzId(?string $id): self { $this->einsatzId = $id; return $this; }
    public function getVehicleNeedId(): ?string { return $this->vehicleNeedId; }
    public function setVehicleNeedId(?string $id): self { $this->vehicleNeedId = $id; return $this; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): self { $this->sortOrder = $sortOrder; return $this; }
    public function getOutcome(): ?string { return $this->outcome; }
    public function setOutcome(?string $outcome): self { $this->outcome = $outcome; return $this; }
    /** Bedarfs-ID, wie sie die Disposition führt (`vn:<id>` für Fahrzeugwünsche). */
    public function needKey(): string { return $this->einsatzId ?? 'vn:' . $this->vehicleNeedId; }
}
