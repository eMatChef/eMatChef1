<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_map')]
#[ORM\Index(name: 'idx_ga_map_dept', columns: ['department_id'])]
class DepartmentGrossanlassMap
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

    #[ORM\Column(name: 'image_filename', type: 'string', length: 255, nullable: true)]
    private ?string $imageFilename = null;

    #[ORM\Column(name: 'image_width', type: 'integer', options: ['default' => 0])]
    private int $imageWidth = 0;

    #[ORM\Column(name: 'image_height', type: 'integer', options: ['default' => 0])]
    private int $imageHeight = 0;

    #[ORM\Column(name: 'bounds_north', type: 'float', nullable: true)]
    private ?float $boundsNorth = null;

    #[ORM\Column(name: 'bounds_south', type: 'float', nullable: true)]
    private ?float $boundsSouth = null;

    #[ORM\Column(name: 'bounds_east', type: 'float', nullable: true)]
    private ?float $boundsEast = null;

    #[ORM\Column(name: 'bounds_west', type: 'float', nullable: true)]
    private ?float $boundsWest = null;

    /** 0–1: wie deckend der Geländeplan über der Basiskarte liegt (1 = undurchsichtig). */
    #[ORM\Column(name: 'overlay_opacity', type: 'float', options: ['default' => 0.92])]
    private float $overlayOpacity = 0.92;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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
    public function setName(string $name): self
    {
        $this->name = $name;
        $this->touch();
        return $this;
    }

    public function getImageFilename(): ?string { return $this->imageFilename; }
    public function setImageFilename(?string $imageFilename): self
    {
        $this->imageFilename = $imageFilename ?: null;
        $this->touch();
        return $this;
    }

    public function getImageWidth(): int { return $this->imageWidth; }
    public function setImageWidth(int $imageWidth): self
    {
        $this->imageWidth = max(0, $imageWidth);
        $this->touch();
        return $this;
    }

    public function getImageHeight(): int { return $this->imageHeight; }
    public function setImageHeight(int $imageHeight): self
    {
        $this->imageHeight = max(0, $imageHeight);
        $this->touch();
        return $this;
    }

    public function getBoundsNorth(): ?float { return $this->boundsNorth; }
    public function getBoundsSouth(): ?float { return $this->boundsSouth; }
    public function getBoundsEast(): ?float { return $this->boundsEast; }
    public function getBoundsWest(): ?float { return $this->boundsWest; }

    public function setBounds(?float $north, ?float $south, ?float $east, ?float $west): self
    {
        $this->boundsNorth = $north;
        $this->boundsSouth = $south;
        $this->boundsEast = $east;
        $this->boundsWest = $west;
        $this->touch();
        return $this;
    }

    public function hasBounds(): bool
    {
        return $this->boundsNorth !== null
            && $this->boundsSouth !== null
            && $this->boundsEast !== null
            && $this->boundsWest !== null
            && $this->boundsNorth > $this->boundsSouth
            && $this->boundsEast > $this->boundsWest;
    }

    public function getOverlayOpacity(): float { return $this->overlayOpacity; }
    public function setOverlayOpacity(float $overlayOpacity): self
    {
        $this->overlayOpacity = max(0.3, min(1.0, $overlayOpacity));
        $this->touch();
        return $this;
    }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }

    private function touch(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
