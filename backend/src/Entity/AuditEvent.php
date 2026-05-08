<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'audit_event')]
#[ORM\Index(name: 'idx_audit_entity', columns: ['entity_type', 'entity_id', 'created_at'])]
#[ORM\Index(name: 'idx_audit_actor', columns: ['actor_user_id', 'created_at'])]
#[ORM\Index(name: 'idx_audit_target', columns: ['target_user_id', 'created_at'])]
#[ORM\Index(name: 'idx_audit_department', columns: ['department_id', 'created_at'])]
#[ORM\Index(name: 'idx_audit_created', columns: ['created_at'])]
class AuditEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'entity_type', type: 'string', length: 40)]
    private string $entityType;

    #[ORM\Column(name: 'entity_id', type: 'string', length: 64)]
    private string $entityId;

    #[ORM\Column(type: 'string', length: 64)]
    private string $action;

    #[ORM\Column(name: 'actor_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $actorUserId = null;

    #[ORM\Column(name: 'target_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $targetUserId = null;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $departmentId = null;

    #[ORM\Column(type: 'json')]
    private array $changes = [];

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
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

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): self
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getActorUserId(): ?string
    {
        return $this->actorUserId;
    }

    public function setActorUserId(?string $actorUserId): self
    {
        $this->actorUserId = $actorUserId;
        return $this;
    }

    public function getTargetUserId(): ?string
    {
        return $this->targetUserId;
    }

    public function setTargetUserId(?string $targetUserId): self
    {
        $this->targetUserId = $targetUserId;
        return $this;
    }

    public function getDepartmentId(): ?string
    {
        return $this->departmentId;
    }

    public function setDepartmentId(?string $departmentId): self
    {
        $this->departmentId = $departmentId;
        return $this;
    }

    public function getChanges(): array
    {
        return $this->changes;
    }

    public function setChanges(array $changes): self
    {
        $this->changes = $changes;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}
