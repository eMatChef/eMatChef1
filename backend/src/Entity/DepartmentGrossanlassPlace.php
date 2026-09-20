<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_place')]
#[ORM\UniqueConstraint(name: 'uniq_ga_place_code', columns: ['public_code'])]
#[ORM\Index(name: 'idx_ga_place_dept', columns: ['department_id'])]
#[ORM\Index(name: 'idx_ga_place_map', columns: ['map_id'])]
class DepartmentGrossanlassPlace
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    #[ORM\Column(name: 'group_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $groupId = null;

    #[ORM\Column(name: 'unterlager_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $unterlagerId = null;

    /** bauprojekt | unterlager | anfahrt | poi | area — Event-Standort. matplatz nur Altbestand; neu = Lagerstandort */
    #[ORM\Column(type: 'string', length: 16, options: ['default' => 'poi'])]
    private string $kind = 'poi';

    #[ORM\Column(name: 'map_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $mapId = null;

    #[ORM\ManyToOne(targetEntity: DepartmentGrossanlassMap::class)]
    #[ORM\JoinColumn(name: 'map_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?DepartmentGrossanlassMap $map = null;

    #[ORM\Column(name: 'map_x', type: 'float', nullable: true)]
    private ?float $mapX = null;

    #[ORM\Column(name: 'map_y', type: 'float', nullable: true)]
    private ?float $mapY = null;

    #[ORM\Column(name: 'latitude', type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(name: 'longitude', type: 'float', nullable: true)]
    private ?float $longitude = null;

    /** WGS84-Eckpunkte eines Bereich-Polygons (GA-area). */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $polygon = null;

    /** Wichtige Punkte (Abladezone, Anfahrt) auf Stammdaten */
    #[ORM\Column(name: 'starred', type: 'boolean', options: ['default' => false])]
    private bool $starred = false;

    #[ORM\Column(name: 'public_code', type: 'string', length: 32)]
    private string $publicCode = '';

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }

    public function getDepartmentId(): string { return $this->departmentId; }
    public function getDepartment(): Department { return $this->department; }
    public function setDepartment(Department $department): self
    {
        $this->department = $department;
        $this->departmentId = $department->getId();
        return $this;
    }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getGroupId(): ?string { return $this->groupId; }
    public function setGroupId(?string $groupId): self { $this->groupId = $groupId ?: null; return $this; }

    public function getUnterlagerId(): ?string { return $this->unterlagerId; }
    public function setUnterlagerId(?string $unterlagerId): self { $this->unterlagerId = $unterlagerId ?: null; return $this; }

    public function getKind(): string { return $this->kind; }
    public function setKind(string $kind): self { $this->kind = $kind; return $this; }

    public function getMapId(): ?string { return $this->mapId; }
    public function getMap(): ?DepartmentGrossanlassMap { return $this->map; }
    public function setMap(?DepartmentGrossanlassMap $map): self
    {
        $this->map = $map;
        $this->mapId = $map?->getId();
        return $this;
    }

    public function getMapX(): ?float { return $this->mapX; }
    public function setMapX(?float $mapX): self { $this->mapX = $mapX; return $this; }

    public function getMapY(): ?float { return $this->mapY; }
    public function setMapY(?float $mapY): self { $this->mapY = $mapY; return $this; }

    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(?float $latitude): self { $this->latitude = $latitude; return $this; }

    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(?float $longitude): self { $this->longitude = $longitude; return $this; }

    /** @return list<array{lat: float, lng: float}>|null */
    public function getPolygon(): ?array { return $this->polygon; }

    /** @param list<array{lat: float, lng: float}>|null $polygon */
    public function setPolygon(?array $polygon): self { $this->polygon = $polygon; return $this; }

    public function isStarred(): bool { return $this->starred; }
    public function setStarred(bool $starred): self { $this->starred = $starred; return $this; }

    public function getPublicCode(): string { return $this->publicCode; }
    public function setPublicCode(string $publicCode): self { $this->publicCode = $publicCode; return $this; }
}
