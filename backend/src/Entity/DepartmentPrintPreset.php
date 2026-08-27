<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_print_preset')]
#[ORM\Index(name: 'idx_dept_print_preset_dept', columns: ['department_id'])]
class DepartmentPrintPreset
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    #[ORM\Column(name: 'device_model_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $deviceModelId;

    #[ORM\ManyToOne(targetEntity: PrintDeviceModel::class)]
    #[ORM\JoinColumn(name: 'device_model_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private PrintDeviceModel $deviceModel;

    #[ORM\Column(name: 'media_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $mediaId;

    #[ORM\ManyToOne(targetEntity: PrintMedia::class)]
    #[ORM\JoinColumn(name: 'media_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private PrintMedia $media;

    #[ORM\Column(name: 'cut_length_mm', type: 'integer', nullable: true)]
    private ?int $cutLengthMm = null;

    #[ORM\Column(name: 'is_default', type: 'boolean', options: ['default' => false])]
    private bool $isDefault = false;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $createdByUserId = null;

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
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getDeviceModelId(): string { return $this->deviceModelId; }

    public function getDeviceModel(): PrintDeviceModel { return $this->deviceModel; }

    public function setDeviceModel(PrintDeviceModel $deviceModel): self
    {
        $this->deviceModel = $deviceModel;
        $this->deviceModelId = $deviceModel->getId();
        return $this;
    }

    public function getMediaId(): string { return $this->mediaId; }

    public function getMedia(): PrintMedia { return $this->media; }

    public function setMedia(PrintMedia $media): self
    {
        $this->media = $media;
        $this->mediaId = $media->getId();
        return $this;
    }

    public function getCutLengthMm(): ?int { return $this->cutLengthMm; }
    public function setCutLengthMm(?int $cutLengthMm): self { $this->cutLengthMm = $cutLengthMm; return $this; }

    public function isDefault(): bool { return $this->isDefault; }
    public function setIsDefault(bool $isDefault): self { $this->isDefault = $isDefault; return $this; }

    public function getCreatedByUserId(): ?string { return $this->createdByUserId; }
    public function setCreatedByUserId(?string $createdByUserId): self { $this->createdByUserId = $createdByUserId; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTime(); return $this; }
}
