<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Druckkorb-Eintrag mit öffentlicher QR-URL.
 *
 * entity_type:
 * - batch — Material-Charge (public_url: /i/m/{materialCode}/b/{batchCode})
 * - activity — Anlass (/i/a/{activityCode})
 * - workshop — Werkstatt-Ticket (/i/w/{workshopCode})
 */
#[ORM\Entity]
#[ORM\Table(name: 'print_task_item')]
#[ORM\Index(name: 'idx_print_task_dept_status_created', columns: ['department_id', 'status', 'created_at'])]
class PrintTaskItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $createdByUserId = null;

    #[ORM\Column(name: 'entity_type', type: 'string', length: 32)]
    private string $entityType;

    #[ORM\Column(name: 'entity_id', type: 'string', length: 20)]
    private string $entityId;

    #[ORM\Column(name: 'label', type: 'string', length: 255)]
    private string $label;

    #[ORM\Column(name: 'public_code', type: 'string', length: 64, nullable: true)]
    private ?string $publicCode = null;

    #[ORM\Column(name: 'public_url', type: 'string', length: 512)]
    private string $publicUrl;

    #[ORM\Column(name: 'status', type: 'string', length: 16, options: ['default' => 'pending'])]
    private string $status = 'pending';

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'printed_at', type: 'datetime', nullable: true)]
    private ?\DateTime $printedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }

    public function getDepartmentId(): string { return $this->departmentId; }
    public function setDepartmentId(string $departmentId): self { $this->departmentId = $departmentId; return $this; }

    public function getCreatedByUserId(): ?string { return $this->createdByUserId; }
    public function setCreatedByUserId(?string $createdByUserId): self { $this->createdByUserId = $createdByUserId; return $this; }

    public function getEntityType(): string { return $this->entityType; }
    public function setEntityType(string $entityType): self { $this->entityType = $entityType; return $this; }

    public function getEntityId(): string { return $this->entityId; }
    public function setEntityId(string $entityId): self { $this->entityId = $entityId; return $this; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }

    public function getPublicCode(): ?string { return $this->publicCode; }
    public function setPublicCode(?string $publicCode): self { $this->publicCode = $publicCode; return $this; }

    public function getPublicUrl(): string { return $this->publicUrl; }
    public function setPublicUrl(string $publicUrl): self { $this->publicUrl = $publicUrl; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }

    public function getPrintedAt(): ?\DateTime { return $this->printedAt; }
    public function setPrintedAt(?\DateTime $printedAt): self { $this->printedAt = $printedAt; return $this; }
}

