<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_commitment')]
#[ORM\Index(name: 'idx_ga_commitment_dept', columns: ['department_id'])]
#[ORM\Index(name: 'idx_ga_commitment_inquiry', columns: ['inquiry_id'])]
class DepartmentGrossanlassCommitment
{
    public const FAMILY_VEHICLE = 'vehicle';
    public const FAMILY_MATERIAL = 'material';

    public const ORIGIN_LOAN = 'loan';
    public const ORIGIN_BUY = 'buy';
    public const ORIGIN_BUY_RESALE = 'buy_resale';

    /** @var list<string> */
    public const FAMILIES = [self::FAMILY_VEHICLE, self::FAMILY_MATERIAL];

    /** @var list<string> */
    public const ORIGINS = [self::ORIGIN_LOAN, self::ORIGIN_BUY, self::ORIGIN_BUY_RESALE];

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(name: 'inquiry_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $inquiryId = null;

    #[ORM\ManyToOne(targetEntity: DepartmentGrossanlassInquiry::class)]
    #[ORM\JoinColumn(name: 'inquiry_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?DepartmentGrossanlassInquiry $inquiry = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'string', length: 16)]
    private string $family = self::FAMILY_MATERIAL;

    #[ORM\Column(type: 'string', length: 16)]
    private string $origin = self::ORIGIN_LOAN;

    #[ORM\Column(type: 'string', length: 255)]
    private string $source = '';

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $plate = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $barcode = null;

    #[ORM\Column(name: 'category_id', type: 'string', length: 64, nullable: true)]
    private ?string $categoryId = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $released = false;

    #[ORM\Column(name: 'present_from', type: 'datetime', nullable: true)]
    private ?\DateTime $presentFrom = null;

    #[ORM\Column(name: 'present_to', type: 'datetime', nullable: true)]
    private ?\DateTime $presentTo = null;

    #[ORM\Column(name: 'handover_from', type: 'datetime', nullable: true)]
    private ?\DateTime $handoverFrom = null;

    #[ORM\Column(name: 'handover_to', type: 'datetime', nullable: true)]
    private ?\DateTime $handoverTo = null;

    #[ORM\Column(name: 'return_from', type: 'datetime', nullable: true)]
    private ?\DateTime $returnFrom = null;

    #[ORM\Column(name: 'return_to', type: 'datetime', nullable: true)]
    private ?\DateTime $returnTo = null;

    #[ORM\Column(name: 'wish_label', type: 'string', length: 255, nullable: true)]
    private ?string $wishLabel = null;

    #[ORM\Column(name: 'wish_from', type: 'datetime', nullable: true)]
    private ?\DateTime $wishFrom = null;

    #[ORM\Column(name: 'wish_to', type: 'datetime', nullable: true)]
    private ?\DateTime $wishTo = null;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $services = [];

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'item_details', type: 'json')]
    private array $itemDetails = [];

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $quantity = 1;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $packed = false;

    #[ORM\Column(name: 'pack_phase', type: 'string', length: 16, options: ['default' => 'anlass'])]
    private string $packPhase = 'anlass';

    #[ORM\Column(name: 'returned_to_firm', type: 'boolean', options: ['default' => false])]
    private bool $returnedToFirm = false;

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

    public function getInquiryId(): ?string
    {
        return $this->inquiryId;
    }

    public function getInquiry(): ?DepartmentGrossanlassInquiry
    {
        return $this->inquiry;
    }

    public function setInquiry(?DepartmentGrossanlassInquiry $inquiry): self
    {
        $this->inquiry = $inquiry;
        $this->inquiryId = $inquiry?->getId();

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        $this->touch();

        return $this;
    }

    public function getFamily(): string
    {
        return $this->family;
    }

    public function setFamily(string $family): self
    {
        $this->family = $family;
        $this->touch();

        return $this;
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    public function setOrigin(string $origin): self
    {
        $this->origin = $origin;
        $this->touch();

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;
        $this->touch();

        return $this;
    }

    public function getPlate(): ?string
    {
        return $this->plate;
    }

    public function setPlate(?string $plate): self
    {
        $this->plate = $plate !== null && $plate !== '' ? $plate : null;
        $this->touch();

        return $this;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): self
    {
        $this->barcode = $barcode;
        $this->touch();

        return $this;
    }

    public function getCategoryId(): ?string
    {
        return $this->categoryId;
    }

    public function setCategoryId(?string $categoryId): self
    {
        $this->categoryId = $categoryId;
        $this->touch();

        return $this;
    }

    public function isReleased(): bool
    {
        return $this->released;
    }

    public function setReleased(bool $released): self
    {
        $this->released = $released;
        $this->touch();

        return $this;
    }

    public function getPresentFrom(): ?\DateTime
    {
        return $this->presentFrom;
    }

    public function setPresentFrom(?\DateTime $presentFrom): self
    {
        $this->presentFrom = $presentFrom;
        $this->touch();

        return $this;
    }

    public function getPresentTo(): ?\DateTime
    {
        return $this->presentTo;
    }

    public function setPresentTo(?\DateTime $presentTo): self
    {
        $this->presentTo = $presentTo;
        $this->touch();

        return $this;
    }

    public function getHandoverFrom(): ?\DateTime
    {
        return $this->handoverFrom;
    }

    public function setHandoverFrom(?\DateTime $handoverFrom): self
    {
        $this->handoverFrom = $handoverFrom;
        $this->touch();

        return $this;
    }

    public function getHandoverTo(): ?\DateTime
    {
        return $this->handoverTo;
    }

    public function setHandoverTo(?\DateTime $handoverTo): self
    {
        $this->handoverTo = $handoverTo;
        $this->touch();

        return $this;
    }

    public function getReturnFrom(): ?\DateTime
    {
        return $this->returnFrom;
    }

    public function setReturnFrom(?\DateTime $returnFrom): self
    {
        $this->returnFrom = $returnFrom;
        $this->touch();

        return $this;
    }

    public function getReturnTo(): ?\DateTime
    {
        return $this->returnTo;
    }

    public function setReturnTo(?\DateTime $returnTo): self
    {
        $this->returnTo = $returnTo;
        $this->touch();

        return $this;
    }

    public function getWishLabel(): ?string
    {
        return $this->wishLabel;
    }

    public function setWishLabel(?string $wishLabel): self
    {
        $this->wishLabel = $wishLabel;
        $this->touch();

        return $this;
    }

    public function getWishFrom(): ?\DateTime
    {
        return $this->wishFrom;
    }

    public function setWishFrom(?\DateTime $wishFrom): self
    {
        $this->wishFrom = $wishFrom;
        $this->touch();

        return $this;
    }

    public function getWishTo(): ?\DateTime
    {
        return $this->wishTo;
    }

    public function setWishTo(?\DateTime $wishTo): self
    {
        $this->wishTo = $wishTo;
        $this->touch();

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * @param list<array<string, mixed>> $services
     */
    public function setServices(array $services): self
    {
        $this->services = array_values($services);
        $this->touch();

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = max(1, $quantity);
        $this->touch();

        return $this;
    }

    public function isPacked(): bool
    {
        return $this->packed;
    }

    public function setPacked(bool $packed): self
    {
        $this->packed = $packed;
        $this->touch();

        return $this;
    }

    public function getPackPhase(): string
    {
        return $this->packPhase;
    }

    public function setPackPhase(string $packPhase): self
    {
        $this->packPhase = $packPhase;
        $this->touch();

        return $this;
    }

    public function isReturnedToFirm(): bool
    {
        return $this->returnedToFirm;
    }

    public function setReturnedToFirm(bool $returnedToFirm): self
    {
        $this->returnedToFirm = $returnedToFirm;
        $this->touch();

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getItemDetails(): array
    {
        return $this->itemDetails;
    }

    /**
     * @param array<string, mixed> $itemDetails
     */
    public function setItemDetails(array $itemDetails): self
    {
        $this->itemDetails = $itemDetails;
        $this->touch();

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

    private function touch(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
