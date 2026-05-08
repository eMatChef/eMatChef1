<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'public_found_item_message')]
#[ORM\Index(name: 'idx_pfim_dept_unread', columns: ['department_id', 'read_at'])]
#[ORM\Index(name: 'idx_pfim_dept_status', columns: ['department_id', 'status'])]
class PublicFoundItemMessage
{
    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    /** material | batch */
    #[ORM\Column(name: 'entity_type', type: 'string', length: 8)]
    private string $entityType;

    #[ORM\Column(name: 'material_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $materialId = null;

    #[ORM\Column(name: 'batch_id', type: 'string', length: 20, nullable: true)]
    private ?string $batchId = null;

    #[ORM\Column(name: 'public_code', type: 'string', length: 64)]
    private string $publicCode;

    #[ORM\Column(name: 'material_name', type: 'string', length: 512)]
    private string $materialName;

    #[ORM\Column(name: 'department_name', type: 'string', length: 512)]
    private string $departmentName;

    #[ORM\Column(name: 'serial_line', type: 'string', length: 512, nullable: true)]
    private ?string $serialLine = null;

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\Column(name: 'sender_name', type: 'string', length: 120, nullable: true)]
    private ?string $senderName = null;

    #[ORM\Column(name: 'sender_email', type: 'string', length: 255, nullable: true)]
    private ?string $senderEmail = null;

    #[ORM\Column(name: 'public_url', type: 'string', length: 512)]
    private string $publicUrl;

    #[ORM\Column(name: 'read_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $readAt = null;

    #[ORM\Column(name: 'read_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $readByUserId = null;

    /** open | in_progress | done */
    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getDepartmentId(): string
    {
        return $this->department->getId();
    }

    public function getDepartment(): Department
    {
        return $this->department;
    }

    public function setDepartment(Department $department): self
    {
        $this->department = $department;

        return $this;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getMaterialId(): ?string
    {
        return $this->materialId;
    }

    public function setMaterialId(?string $materialId): self
    {
        $this->materialId = $materialId;

        return $this;
    }

    public function getBatchId(): ?string
    {
        return $this->batchId;
    }

    public function setBatchId(?string $batchId): self
    {
        $this->batchId = $batchId;

        return $this;
    }

    public function getPublicCode(): string
    {
        return $this->publicCode;
    }

    public function setPublicCode(string $publicCode): self
    {
        $this->publicCode = $publicCode;

        return $this;
    }

    public function getMaterialName(): string
    {
        return $this->materialName;
    }

    public function setMaterialName(string $materialName): self
    {
        $this->materialName = $materialName;

        return $this;
    }

    public function getDepartmentName(): string
    {
        return $this->departmentName;
    }

    public function setDepartmentName(string $departmentName): self
    {
        $this->departmentName = $departmentName;

        return $this;
    }

    public function getSerialLine(): ?string
    {
        return $this->serialLine;
    }

    public function setSerialLine(?string $serialLine): self
    {
        $this->serialLine = $serialLine;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getSenderName(): ?string
    {
        return $this->senderName;
    }

    public function setSenderName(?string $senderName): self
    {
        $this->senderName = $senderName;

        return $this;
    }

    public function getSenderEmail(): ?string
    {
        return $this->senderEmail;
    }

    public function setSenderEmail(?string $senderEmail): self
    {
        $this->senderEmail = $senderEmail;

        return $this;
    }

    public function getPublicUrl(): string
    {
        return $this->publicUrl;
    }

    public function setPublicUrl(string $publicUrl): self
    {
        $this->publicUrl = $publicUrl;

        return $this;
    }

    public function getReadAt(): ?\DateTimeInterface
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeInterface $readAt): self
    {
        $this->readAt = $readAt;

        return $this;
    }

    public function getReadByUserId(): ?string
    {
        return $this->readByUserId;
    }

    public function setReadByUserId(?string $readByUserId): self
    {
        $this->readByUserId = $readByUserId;

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

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
