<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SupplierCatalogItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SupplierCatalogItemRepository::class)]
#[ORM\Table(name: 'supplier_catalog_item')]
#[ORM\UniqueConstraint(name: 'uniq_supplier_catalog_sku', columns: ['supplier_company_id', 'sku'])]
class SupplierCatalogItem
{
    public const TRACKING_BULK = 'bulk';
    public const TRACKING_SERIALIZED = 'serialized';

    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_DEPARTMENTS = 'departments';
    public const VISIBILITY_GLOBAL = 'global';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_PENDING_REVIEW = 'pending_review';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'supplier_company_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $supplierCompanyId;

    #[ORM\ManyToOne(targetEntity: SupplierCompany::class)]
    #[ORM\JoinColumn(name: 'supplier_company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private SupplierCompany $supplierCompany;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    private ?string $sku = null;

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    private ?string $manufacturer = null;

    #[ORM\Column(name: 'tracking_type', type: 'string', length: 20)]
    #[Assert\Choice(choices: [self::TRACKING_BULK, self::TRACKING_SERIALIZED])]
    private string $trackingType = self::TRACKING_BULK;

    #[ORM\Column(name: 'unit_price', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $unitPrice = null;

    #[ORM\Column(type: 'string', length: 3, options: ['default' => 'CHF'])]
    private string $currency = 'CHF';

    #[ORM\Column(name: 'min_qty', type: 'integer', nullable: true)]
    private ?int $minQty = null;

    #[ORM\Column(name: 'pack_size', type: 'integer', nullable: true)]
    private ?int $packSize = null;

    #[ORM\Column(name: 'category_hint', type: 'string', length: 255, nullable: true)]
    private ?string $categoryHint = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'external_ref', type: 'string', length: 120, nullable: true)]
    private ?string $externalRef = null;

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => self::VISIBILITY_PRIVATE])]
    #[Assert\Choice(choices: [self::VISIBILITY_PRIVATE, self::VISIBILITY_DEPARTMENTS, self::VISIBILITY_GLOBAL])]
    private string $visibility = self::VISIBILITY_PRIVATE;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => self::STATUS_DRAFT])]
    #[Assert\Choice(choices: [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_PENDING_REVIEW])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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

    public function getSupplierCompanyId(): string
    {
        return $this->supplierCompanyId;
    }

    public function setSupplierCompanyId(string $supplierCompanyId): self
    {
        $this->supplierCompanyId = $supplierCompanyId;

        return $this;
    }

    public function getSupplierCompany(): SupplierCompany
    {
        return $this->supplierCompany;
    }

    public function setSupplierCompany(SupplierCompany $supplierCompany): self
    {
        $this->supplierCompany = $supplierCompany;
        $this->supplierCompanyId = $supplierCompany->getId() ?? $this->supplierCompanyId;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): self
    {
        $this->sku = $sku !== null && trim($sku) !== '' ? trim($sku) : null;

        return $this;
    }

    public function getManufacturer(): ?string
    {
        return $this->manufacturer;
    }

    public function setManufacturer(?string $manufacturer): self
    {
        $this->manufacturer = $manufacturer !== null && trim($manufacturer) !== '' ? trim($manufacturer) : null;

        return $this;
    }

    public function getTrackingType(): string
    {
        return $this->trackingType;
    }

    public function setTrackingType(string $trackingType): self
    {
        $this->trackingType = $trackingType;

        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?string $unitPrice): self
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = strtoupper(trim($currency));

        return $this;
    }

    public function getMinQty(): ?int
    {
        return $this->minQty;
    }

    public function setMinQty(?int $minQty): self
    {
        $this->minQty = $minQty;

        return $this;
    }

    public function getPackSize(): ?int
    {
        return $this->packSize;
    }

    public function setPackSize(?int $packSize): self
    {
        $this->packSize = $packSize;

        return $this;
    }

    public function getCategoryHint(): ?string
    {
        return $this->categoryHint;
    }

    public function setCategoryHint(?string $categoryHint): self
    {
        $this->categoryHint = $categoryHint !== null && trim($categoryHint) !== '' ? trim($categoryHint) : null;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description !== null && trim($description) !== '' ? trim($description) : null;

        return $this;
    }

    public function getExternalRef(): ?string
    {
        return $this->externalRef;
    }

    public function setExternalRef(?string $externalRef): self
    {
        $this->externalRef = $externalRef !== null && trim($externalRef) !== '' ? trim($externalRef) : null;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function setVisibility(string $visibility): self
    {
        $this->visibility = $visibility;

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

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'supplier_company_id' => $this->supplierCompanyId,
            'name' => $this->name,
            'sku' => $this->sku,
            'manufacturer' => $this->manufacturer,
            'tracking_type' => $this->trackingType,
            'unit_price' => $this->unitPrice !== null ? (float) $this->unitPrice : null,
            'currency' => $this->currency,
            'min_qty' => $this->minQty,
            'pack_size' => $this->packSize,
            'category_hint' => $this->categoryHint,
            'description' => $this->description,
            'external_ref' => $this->externalRef,
            'is_active' => $this->isActive,
            'visibility' => $this->visibility,
            'status' => $this->status,
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
