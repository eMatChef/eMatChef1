<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_grossanlass_procurement_line')]
#[ORM\Index(name: 'idx_grossanlass_procurement_dept', columns: ['department_id'])]
#[ORM\Index(name: 'idx_grossanlass_procurement_group', columns: ['group_id'])]
#[ORM\Index(name: 'idx_grossanlass_procurement_status', columns: ['status'])]
#[ORM\Index(name: 'idx_gpl_category', columns: ['category_id'])]
class ActivityGrossanlassProcurementLine
{
    public const STATUS_BEDARF = 'bedarf';
    public const STATUS_OFFERTE = 'offerte_eingeholt';
    public const STATUS_BUDGETIERT = 'budgetiert';
    public const STATUS_BESTELLT = 'bestellt';
    public const STATUS_TEILWEISE = 'teilweise_erhalten';
    public const STATUS_ERHALTEN = 'erhalten';

    public const SOURCE_FROM_WISH = 'from_wish';
    public const SOURCE_DIRECT = 'direct';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'group_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $groupId;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Group $group;

    #[ORM\Column(name: 'wish_kind', type: 'string', length: 20)]
    private string $wishKind;

    #[ORM\Column(type: 'string', length: 255)]
    private string $label;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    #[ORM\Column(name: 'quantity_asked', type: 'integer', nullable: true)]
    private ?int $quantityAsked = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $location;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'category_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $categoryId = null;

    #[ORM\ManyToOne(targetEntity: ActivityGrossanlassProcurementCategory::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ActivityGrossanlassProcurementCategory $category = null;

    #[ORM\Column(type: 'string', length: 32)]
    private string $status = self::STATUS_BEDARF;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => self::SOURCE_FROM_WISH])]
    private string $source = self::SOURCE_FROM_WISH;

    #[ORM\Column(name: 'self_organized', type: 'boolean', options: ['default' => false])]
    private bool $selfOrganized = false;

    #[ORM\Column(name: 'pickup_need', type: 'string', length: 8, nullable: true)]
    private ?string $pickupNeed = null;

    #[ORM\Column(name: 'pickup_place', type: 'string', length: 255, nullable: true)]
    private ?string $pickupPlace = null;

    #[ORM\Column(name: 'return_needed', type: 'boolean', options: ['default' => false])]
    private bool $returnNeeded = false;

    #[ORM\Column(name: 'quantity_unit', type: 'string', length: 8, options: ['default' => 'Stk'])]
    private string $quantityUnit = 'Stk';

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $createdByUserId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $createdByUser;

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

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function setGroup(Group $group): self
    {
        $this->group = $group;
        $this->groupId = $group->getId();

        return $this;
    }

    public function getWishKind(): string
    {
        return $this->wishKind;
    }

    public function setWishKind(string $wishKind): self
    {
        $this->wishKind = $wishKind;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getQuantityAsked(): ?int
    {
        return $this->quantityAsked;
    }

    public function setQuantityAsked(?int $quantityAsked): self
    {
        $this->quantityAsked = $quantityAsked;

        return $this;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(string $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCategoryId(): ?string
    {
        return $this->categoryId;
    }

    public function getCategory(): ?ActivityGrossanlassProcurementCategory
    {
        return $this->category;
    }

    public function setCategory(?ActivityGrossanlassProcurementCategory $category): self
    {
        $this->category = $category;
        $this->categoryId = $category?->getId();

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

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function isSelfOrganized(): bool
    {
        return $this->selfOrganized;
    }

    public function setSelfOrganized(bool $selfOrganized): self
    {
        $this->selfOrganized = $selfOrganized;

        return $this;
    }

    public function getPickupNeed(): ?string
    {
        return $this->pickupNeed;
    }

    public function setPickupNeed(?string $pickupNeed): self
    {
        $this->pickupNeed = $pickupNeed;

        return $this;
    }

    public function getPickupPlace(): ?string
    {
        return $this->pickupPlace;
    }

    public function setPickupPlace(?string $pickupPlace): self
    {
        $this->pickupPlace = $pickupPlace;

        return $this;
    }

    public function isReturnNeeded(): bool
    {
        return $this->returnNeeded;
    }

    public function setReturnNeeded(bool $returnNeeded): self
    {
        $this->returnNeeded = $returnNeeded;

        return $this;
    }

    public function getQuantityUnit(): string
    {
        return $this->quantityUnit !== '' ? $this->quantityUnit : 'Stk';
    }

    public function setQuantityUnit(?string $quantityUnit): self
    {
        $this->quantityUnit = strtolower(trim((string) $quantityUnit)) === 'm' ? 'm' : 'Stk';

        return $this;
    }

    public function getCreatedByUserId(): string
    {
        return $this->createdByUserId;
    }

    public function getCreatedByUser(): User
    {
        return $this->createdByUser;
    }

    public function setCreatedByUser(User $user): self
    {
        $this->createdByUser = $user;
        $this->createdByUserId = $user->getId();

        return $this;
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
