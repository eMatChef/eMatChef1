<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_grossanlass_procurement_category')]
#[ORM\UniqueConstraint(name: 'uniq_gpc_dept_system_key', columns: ['department_id', 'system_key'])]
#[ORM\Index(name: 'idx_gpc_dept', columns: ['department_id'])]
#[ORM\Index(name: 'idx_gpc_parent', columns: ['parent_id'])]
class ActivityGrossanlassProcurementCategory
{
    public const SYSTEM_KEY_JS = 'js';

    public const JS_NAME = 'J+S';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'parent_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $parentId = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?self $parent = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'rahmen_chf', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $rahmenChf = null;

    #[ORM\Column(name: 'system_key', type: 'string', length: 32, nullable: true)]
    private ?string $systemKey = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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
        return $this->departmentId;
    }

    public function getDepartment(): Department
    {
        return $this->department;
    }

    public function setDepartment(Department $department): self
    {
        $this->department = $department;
        $this->departmentId = $department->getId();

        return $this;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;
        $this->parentId = $parent?->getId();

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getRahmenChf(): ?string
    {
        return $this->rahmenChf;
    }

    public function setRahmenChf(?string $rahmenChf): self
    {
        $this->rahmenChf = $rahmenChf;

        return $this;
    }

    public function getSystemKey(): ?string
    {
        return $this->systemKey;
    }

    public function setSystemKey(?string $systemKey): self
    {
        $this->systemKey = $systemKey;

        return $this;
    }

    public function isSystemLocked(): bool
    {
        return $this->systemKey !== null && $this->systemKey !== '';
    }

    /**
     * Existing top-level names that we treat as the fixed J+S package.
     */
    public static function isJsNameAlias(string $name): bool
    {
        $n = mb_strtolower(trim($name), 'UTF-8');
        $n = str_replace([' ', "\u{00a0}", '-', '_'], '', $n);

        return in_array($n, ['j+s', 'j&s', 'junds'], true);
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function touchUpdatedAt(): self
    {
        $this->updatedAt = new \DateTime();

        return $this;
    }
}
