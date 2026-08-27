<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'print_media')]
#[ORM\UniqueConstraint(name: 'uniq_print_media_key', columns: ['catalog_key'])]
class PrintMedia
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';

    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_ORGANISATION = 'organisation';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'catalog_key', type: 'string', length: 64)]
    private string $catalogKey;

    #[ORM\Column(type: 'string', length: 32)]
    private string $family;

    #[ORM\Column(type: 'string', length: 80)]
    private string $brand;

    #[ORM\Column(type: 'string', length: 64)]
    private string $sku;

    #[ORM\Column(type: 'string', length: 160)]
    private string $name;

    #[ORM\Column(name: 'width_mm', type: 'decimal', precision: 8, scale: 2)]
    private string $widthMm;

    #[ORM\Column(name: 'height_mm', type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $heightMm = null;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $cols = 1;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $rows = 1;

    #[ORM\Column(name: 'is_continuous', type: 'boolean', options: ['default' => false])]
    private bool $isContinuous = false;

    #[ORM\Column(name: 'default_cut_length_mm', type: 'integer', nullable: true)]
    private ?int $defaultCutLengthMm = null;

    #[ORM\Column(name: 'shape', type: 'string', length: 16, options: ['default' => 'rect'])]
    private string $shape = 'rect';

    #[ORM\Column(name: 'sheet_width_mm', type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $sheetWidthMm = null;

    #[ORM\Column(name: 'sheet_height_mm', type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $sheetHeightMm = null;

    #[ORM\Column(name: 'margin_top_mm', type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $marginTopMm = null;

    #[ORM\Column(name: 'margin_left_mm', type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $marginLeftMm = null;

    #[ORM\Column(name: 'gap_x_mm', type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $gapXMm = null;

    #[ORM\Column(name: 'gap_y_mm', type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $gapYMm = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PUBLISHED;

    #[ORM\Column(type: 'string', length: 20)]
    private string $scope = self::SCOPE_GLOBAL;

    #[ORM\Column(name: 'organisation_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $organisationId = null;

    #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $createdByUserId = null;

    #[ORM\Column(name: 'reviewed_by_user_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $reviewedByUserId = null;

    #[ORM\Column(name: 'global_requested', type: 'boolean', options: ['default' => false])]
    private bool $globalRequested = false;

    #[ORM\Column(name: 'reviewed_at', type: 'datetime', nullable: true)]
    private ?\DateTime $reviewedAt = null;

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

    public function getCatalogKey(): string { return $this->catalogKey; }
    public function setCatalogKey(string $catalogKey): self { $this->catalogKey = $catalogKey; return $this; }

    public function getFamily(): string { return $this->family; }
    public function setFamily(string $family): self { $this->family = $family; return $this; }

    public function getBrand(): string { return $this->brand; }
    public function setBrand(string $brand): self { $this->brand = $brand; return $this; }

    public function getSku(): string { return $this->sku; }
    public function setSku(string $sku): self { $this->sku = $sku; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getWidthMm(): string { return $this->widthMm; }
    public function setWidthMm(string $widthMm): self { $this->widthMm = $widthMm; return $this; }

    public function getHeightMm(): ?string { return $this->heightMm; }
    public function setHeightMm(?string $heightMm): self { $this->heightMm = $heightMm; return $this; }

    public function getCols(): int { return $this->cols; }
    public function setCols(int $cols): self { $this->cols = $cols; return $this; }

    public function getRows(): int { return $this->rows; }
    public function setRows(int $rows): self { $this->rows = $rows; return $this; }

    public function isContinuous(): bool { return $this->isContinuous; }
    public function setIsContinuous(bool $isContinuous): self { $this->isContinuous = $isContinuous; return $this; }

    public function getDefaultCutLengthMm(): ?int { return $this->defaultCutLengthMm; }
    public function setDefaultCutLengthMm(?int $defaultCutLengthMm): self { $this->defaultCutLengthMm = $defaultCutLengthMm; return $this; }

    public function getShape(): string { return $this->shape; }
    public function setShape(string $shape): self { $this->shape = $shape; return $this; }

    public function getSheetWidthMm(): ?string { return $this->sheetWidthMm; }
    public function setSheetWidthMm(?string $sheetWidthMm): self { $this->sheetWidthMm = $sheetWidthMm; return $this; }

    public function getSheetHeightMm(): ?string { return $this->sheetHeightMm; }
    public function setSheetHeightMm(?string $sheetHeightMm): self { $this->sheetHeightMm = $sheetHeightMm; return $this; }

    public function getMarginTopMm(): ?string { return $this->marginTopMm; }
    public function setMarginTopMm(?string $marginTopMm): self { $this->marginTopMm = $marginTopMm; return $this; }

    public function getMarginLeftMm(): ?string { return $this->marginLeftMm; }
    public function setMarginLeftMm(?string $marginLeftMm): self { $this->marginLeftMm = $marginLeftMm; return $this; }

    public function getGapXMm(): ?string { return $this->gapXMm; }
    public function setGapXMm(?string $gapXMm): self { $this->gapXMm = $gapXMm; return $this; }

    public function getGapYMm(): ?string { return $this->gapYMm; }
    public function setGapYMm(?string $gapYMm): self { $this->gapYMm = $gapYMm; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getScope(): string { return $this->scope; }
    public function setScope(string $scope): self { $this->scope = $scope; return $this; }

    public function getOrganisationId(): ?string { return $this->organisationId; }
    public function setOrganisationId(?string $organisationId): self { $this->organisationId = $organisationId; return $this; }

    public function getCreatedByUserId(): ?string { return $this->createdByUserId; }
    public function setCreatedByUserId(?string $createdByUserId): self { $this->createdByUserId = $createdByUserId; return $this; }

    public function getReviewedByUserId(): ?string { return $this->reviewedByUserId; }
    public function setReviewedByUserId(?string $reviewedByUserId): self { $this->reviewedByUserId = $reviewedByUserId; return $this; }

    public function isGlobalRequested(): bool { return $this->globalRequested; }
    public function setGlobalRequested(bool $globalRequested): self { $this->globalRequested = $globalRequested; return $this; }

    public function getReviewedAt(): ?\DateTime { return $this->reviewedAt; }
    public function setReviewedAt(?\DateTime $reviewedAt): self { $this->reviewedAt = $reviewedAt; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTime(); return $this; }
}
