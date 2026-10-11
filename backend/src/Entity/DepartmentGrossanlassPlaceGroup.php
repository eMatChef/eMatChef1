<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Zuordnung Standort ↔ Gruppe (Ressort, Bereich, Bauprojekt).
 *
 * Ein Standort kann mehreren Gruppen zugeordnet sein und eine Gruppe mehreren Standorten
 * (ein Polygon plus beliebig viele Punkte). Löschen eines Standorts oder einer Gruppe entfernt nur die Zuordnung.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_place_group')]
#[ORM\Index(name: 'idx_ga_place_group_group', columns: ['group_id'])]
#[ORM\Index(name: 'idx_ga_place_group_place', columns: ['place_id'])]
class DepartmentGrossanlassPlaceGroup
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: DepartmentGrossanlassPlace::class)]
    #[ORM\JoinColumn(name: 'place_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private DepartmentGrossanlassPlace $place;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Group $group;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct(DepartmentGrossanlassPlace $place, Group $group)
    {
        $this->place = $place;
        $this->group = $group;
        $this->createdAt = new \DateTime();
    }

    public function getPlace(): DepartmentGrossanlassPlace { return $this->place; }
    public function getGroup(): Group { return $this->group; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
}
