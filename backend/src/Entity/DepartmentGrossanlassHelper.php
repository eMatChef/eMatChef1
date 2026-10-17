<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Helfer im Helferpool eines Grossanlasses (`department_id` = GA-Host). Kein eMatChef-Login nötig;
 * `user_id` verknüpft optional ein Konto. Archivieren statt löschen. Fremdschlüssel, Check- und
 * Teilindizes stehen in der Migration `Version20261018090000`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_helper')]
#[ORM\Index(name: 'idx_ga_helper_dept_status_name', columns: ['department_id', 'status', 'last_name', 'first_name'])]
class DepartmentGrossanlassHelper
{
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_IMPORT = 'import';
    public const SOURCE_API = 'api';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    /** Herkunfts-Department des Helfers (optional, z. B. Pfadi-Abteilung). */
    #[ORM\Column(name: 'origin_department_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $originDepartmentId = null;

    #[ORM\Column(name: 'user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $userId = null;

    #[ORM\Column(name: 'first_name', type: 'string', length: 100, options: ['default' => ''])]
    private string $firstName = '';

    #[ORM\Column(name: 'last_name', type: 'string', length: 100, options: ['default' => ''])]
    private string $lastName = '';

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'string', length: 10, options: ['default' => self::SOURCE_MANUAL])]
    private string $source = self::SOURCE_MANUAL;

    #[ORM\Column(type: 'string', length: 10, options: ['default' => self::STATUS_ACTIVE])]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'archived_at', type: 'datetime', nullable: true)]
    private ?\DateTime $archivedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct(string $id, string $departmentId)
    {
        $this->id = $id;
        $this->departmentId = $departmentId;
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function getDepartmentId(): string { return $this->departmentId; }
    public function getOriginDepartmentId(): ?string { return $this->originDepartmentId; }
    public function setOriginDepartmentId(?string $id): self { $this->originDepartmentId = $id; return $this->touch(); }
    public function getUserId(): ?string { return $this->userId; }
    public function setUserId(?string $userId): self { $this->userId = $userId; return $this->touch(); }
    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(string $v): self { $this->firstName = $v; return $this->touch(); }
    public function getLastName(): string { return $this->lastName; }
    public function setLastName(string $v): self { $this->lastName = $v; return $this->touch(); }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $v): self { $this->email = $v; return $this->touch(); }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): self { $this->phone = $v; return $this->touch(); }
    public function getSource(): string { return $this->source; }
    public function setSource(string $v): self { $this->source = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function isArchived(): bool { return $this->status === self::STATUS_ARCHIVED; }
    public function getArchivedAt(): ?\DateTime { return $this->archivedAt; }
    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $v): self { $this->note = $v; return $this->touch(); }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }

    public function archive(): self
    {
        $this->status = self::STATUS_ARCHIVED;
        $this->archivedAt = new \DateTime();

        return $this->touch();
    }

    public function restore(): self
    {
        $this->status = self::STATUS_ACTIVE;
        $this->archivedAt = null;

        return $this->touch();
    }

    private function touch(): self
    {
        $this->updatedAt = new \DateTime();

        return $this;
    }
}
