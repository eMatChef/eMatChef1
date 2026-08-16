<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Material - Stammdaten (ohne Mengen!)
 * 
 * Alle Mengen kommen aus MaterialBatch
 */
#[ORM\Entity]
#[ORM\Table(name: 'material_item')]
class MaterialItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $no = null;

    #[ORM\Column(type: 'string', length: 160)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'category_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $categoryId = null;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'materials')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\Column(name: 'storage_address_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $storageAddressId = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(name: 'storage_address_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Address $storageAddress = null;

    #[ORM\Column(type: 'string', length: 160, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'ok'])]
    private string $condition = 'ok'; // ok, defect, repair, lost

    // Details
    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    private ?string $material = null;

    #[ORM\Column(name: 'size_length', type: 'string', length: 120, nullable: true)]
    private ?string $sizeLength = null;

    #[ORM\Column(name: 'size_width', type: 'string', length: 120, nullable: true)]
    private ?string $sizeWidth = null;

    #[ORM\Column(name: 'size_height', type: 'string', length: 120, nullable: true)]
    private ?string $sizeHeight = null;

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    private ?string $weight = null;

    /** Behälter (Kiste/Tasche/Fass …): kann anderen Lagerinhalt aufnehmen; steuert u.a. Container-Batch-Listen. */
    #[ORM\Column(name: 'is_container', type: 'boolean', options: ['default' => false])]
    private bool $isContainer = false;

    // Zelt-/Kombi-Felder (u.a. relevant wenn is_container oder Combo)

    /** gruppenzelt, sonstiges */
    #[ORM\Column(name: 'tent_type', type: 'string', length: 40, nullable: true)]
    private ?string $tentType = null;

    /** Personenkapazität */
    #[ORM\Column(name: 'tent_capacity', type: 'integer', nullable: true)]
    private ?int $tentCapacity = null;

    /** Plattform-Zeltblatt-Vorlage (z. B. spatz, phoenix) */
    #[ORM\Column(name: 'repair_template_key', type: 'string', length: 50, nullable: true)]
    private ?string $repairTemplateKey = null;

    /**
     * Referenz-Kiste (MaterialBatch): bei physischer Kombination aus Kisten-Inhalt erzeugt –
     * für späteren Abgleich Plan (Komponenten) vs. Ist (Inhalt der Kiste).
     */
    #[ORM\Column(name: 'linked_container_batch_id', type: 'string', length: 13, nullable: true, columnDefinition: 'CHARACTER(13) NULL')]
    private ?string $linkedContainerBatchId = null;

    #[ORM\ManyToOne(targetEntity: MaterialBatch::class)]
    #[ORM\JoinColumn(name: 'linked_container_batch_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MaterialBatch $linkedContainerBatch = null;

    // Material- und Tracking-Typ
    #[ORM\Column(name: 'material_type', type: 'string', length: 20, options: ['default' => 'physical'])]
    private string $materialType = 'physical'; // physical, physical_combo, virtual_combo

    #[ORM\Column(name: 'tracking_type', type: 'string', length: 20, nullable: true)]
    private ?string $trackingType = null; // serialized, bulk

    /**
     * Entwurfs-Status für Kombos: 'draft' (in Bearbeitung, nicht buchbar) | 'ready' (fertig, buchbar).
     * Einzelartikel sind immer 'ready'.
     */
    #[ORM\Column(name: 'combo_status', type: 'string', length: 20, options: ['default' => 'ready'])]
    private string $comboStatus = 'ready';

    // Identifikation
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $manufacturer = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(name: 'warranty_until', type: 'date', nullable: true)]
    private ?\DateTime $warrantyUntil = null;

    // Verleih
    #[ORM\Column(name: 'rental_external_allowed', type: 'boolean', options: ['default' => false])]
    private bool $rentalExternalAllowed = false;

    #[ORM\Column(name: 'rental_scope', type: 'string', length: 32, nullable: true)]
    private ?string $rentalScope = null; // department, organisation, public

    #[ORM\Column(name: 'rental_requires_approval', type: 'boolean', options: ['default' => false])]
    private bool $rentalRequiresApproval = false;

    #[ORM\Column(name: 'rental_price_day', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $rentalPriceDay = null;

    #[ORM\Column(name: 'rental_price_week', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $rentalPriceWeek = null;

    #[ORM\Column(name: 'rental_price_month', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $rentalPriceMonth = null;

    #[ORM\Column(name: 'rental_deposit', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $rentalDeposit = null;

    #[ORM\Column(name: 'rental_lead_days', type: 'integer', nullable: true)]
    private ?int $rentalLeadDays = null;

    #[ORM\Column(name: 'rental_max_days', type: 'integer', nullable: true)]
    private ?int $rentalMaxDays = null;

    #[ORM\Column(name: 'rental_notes', type: 'text', nullable: true)]
    private ?string $rentalNotes = null;

    /** Eingaben Vermiet-Amortisationsrechner (JSON), optional */
    #[ORM\Column(name: 'rental_calc_params', type: 'json', nullable: true)]
    private ?array $rentalCalcParams = null;

    // Globale externe Quelle (z.B. J&S)
    #[ORM\Column(name: 'is_js_material', type: 'boolean', options: ['default' => false])]
    private bool $isJsMaterial = false;

    #[ORM\Column(name: 'external_source', type: 'string', length: 50, nullable: true)]
    private ?string $externalSource = null;

    // Verpackungseinheit (Bündel, Kiste, etc.)
    #[ORM\Column(name: 'pack_size', type: 'integer', nullable: true)]
    private ?int $packSize = null;

    #[ORM\Column(name: 'pack_unit', type: 'string', length: 40, nullable: true)]
    private ?string $packUnit = null;

    /**
     * VE-Bezeichnung bei Meterware (pack_unit=m), z. B. Rolle — pack_size dann = Meter pro VE.
     * Bei Stückware bleibt die VE-Bezeichnung in pack_unit.
     */
    #[ORM\Column(name: 'packaging_unit', type: 'string', length: 40, nullable: true)]
    private ?string $packagingUnit = null;

    /** Optional: Verkaufspreis pro Verpackungseinheit (CHF/VE), z. B. für Aufteilen auf Stückpreis */
    #[ORM\Column(name: 'pack_sale_price_chf', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $packSalePriceChf = null;

    /** Packmaß: Abmessungen der Verpackungseinheit (nicht des Einzelstücks) */
    #[ORM\Column(name: 'pack_weight', type: 'string', length: 120, nullable: true)]
    private ?string $packWeight = null;

    #[ORM\Column(name: 'pack_size_length', type: 'string', length: 120, nullable: true)]
    private ?string $packSizeLength = null;

    #[ORM\Column(name: 'pack_size_width', type: 'string', length: 120, nullable: true)]
    private ?string $packSizeWidth = null;

    #[ORM\Column(name: 'pack_size_height', type: 'string', length: 120, nullable: true)]
    private ?string $packSizeHeight = null;

    // Verbrauchsmaterial
    #[ORM\Column(name: 'is_consumable', type: 'boolean', options: ['default' => false])]
    private bool $isConsumable = false;

    // Esswaren
    #[ORM\Column(name: 'is_food', type: 'boolean', options: ['default' => false])]
    private bool $isFood = false;

    #[ORM\Column(name: 'sale_price', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $salePrice = null;

    /** Optional: Zusatz pro Stück bei externen Aktivitäten (addiert auf sale_price) */
    #[ORM\Column(name: 'external_sale_price_chf', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $externalSalePriceChf = null;

    /** Referenz-Einkaufspreis pro Stück (CHF), Pflicht bei Verbrauchsmaterial/Esswaren */
    #[ORM\Column(name: 'reference_purchase_unit_chf', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $referencePurchaseUnitChf = null;

    #[ORM\Column(name: 'min_stock', type: 'integer', nullable: true)]
    private ?int $minStock = null;

    /** Produktfoto(s) — aktuell max. 1 Primary (MediaPhoto-JSON) */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $photos = null;

    // Timestamps
    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    #[ORM\Column(name: 'deleted_at', type: 'datetime', nullable: true)]
    private ?\DateTime $deletedAt = null;

    #[ORM\OneToMany(mappedBy: 'materialItem', targetEntity: MaterialBatch::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $batches;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->batches = new ArrayCollection();
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

    public function getDepartmentId(): string
    {
        return $this->departmentId;
    }

    public function setDepartmentId(string $departmentId): self
    {
        $this->departmentId = $departmentId;
        return $this;
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

    public function getNo(): ?int
    {
        return $this->no;
    }

    public function setNo(?int $no): self
    {
        $this->no = $no;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCategoryId(): ?string
    {
        return $this->categoryId;
    }

    public function setCategoryId(?string $categoryId): self
    {
        $this->categoryId = $categoryId;
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;
        $this->categoryId = $category?->getId();
        return $this;
    }

    public function getStorageAddressId(): ?string
    {
        return $this->storageAddressId;
    }

    public function setStorageAddressId(?string $storageAddressId): self
    {
        $this->storageAddressId = $storageAddressId;
        return $this;
    }

    public function getStorageAddress(): ?Address
    {
        return $this->storageAddress;
    }

    public function setStorageAddress(?Address $storageAddress): self
    {
        $this->storageAddress = $storageAddress;
        $this->storageAddressId = $storageAddress?->getId();
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getCondition(): string
    {
        return $this->condition;
    }

    public function setCondition(string $condition): self
    {
        $this->condition = $condition;
        return $this;
    }

    // Details Getters/Setters
    public function getColor(): ?string { return $this->color; }
    public function setColor(?string $color): self { $this->color = $color; return $this; }

    public function getMaterial(): ?string { return $this->material; }
    public function setMaterial(?string $material): self { $this->material = $material; return $this; }

    public function getSizeLength(): ?string { return $this->sizeLength; }
    public function setSizeLength(?string $sizeLength): self { $this->sizeLength = $sizeLength; return $this; }

    public function getSizeWidth(): ?string { return $this->sizeWidth; }
    public function setSizeWidth(?string $sizeWidth): self { $this->sizeWidth = $sizeWidth; return $this; }

    public function getSizeHeight(): ?string { return $this->sizeHeight; }
    public function setSizeHeight(?string $sizeHeight): self { $this->sizeHeight = $sizeHeight; return $this; }

    public function getWeight(): ?string { return $this->weight; }
    public function setWeight(?string $weight): self { $this->weight = $weight; return $this; }

    public function getIsContainer(): bool { return $this->isContainer; }
    public function setIsContainer(bool $isContainer): self { $this->isContainer = $isContainer; return $this; }

    // Zelt-spezifische Getters/Setters
    public function getTentType(): ?string { return $this->tentType; }
    public function setTentType(?string $tentType): self { $this->tentType = $tentType; return $this; }

    public function getTentCapacity(): ?int { return $this->tentCapacity; }
    public function setTentCapacity(?int $tentCapacity): self { $this->tentCapacity = $tentCapacity; return $this; }

    public function getRepairTemplateKey(): ?string { return $this->repairTemplateKey; }
    public function setRepairTemplateKey(?string $repairTemplateKey): self { $this->repairTemplateKey = $repairTemplateKey; return $this; }

    public function getLinkedContainerBatchId(): ?string
    {
        return $this->linkedContainerBatchId;
    }

    public function setLinkedContainerBatchId(?string $linkedContainerBatchId): self
    {
        $this->linkedContainerBatchId = $linkedContainerBatchId;
        return $this;
    }

    public function getLinkedContainerBatch(): ?MaterialBatch
    {
        return $this->linkedContainerBatch;
    }

    public function setLinkedContainerBatch(?MaterialBatch $linkedContainerBatch): self
    {
        $this->linkedContainerBatch = $linkedContainerBatch;
        $this->linkedContainerBatchId = $linkedContainerBatch?->getId();
        return $this;
    }

    // Material- und Tracking-Typ Getters/Setters
    public function getMaterialType(): string { return $this->materialType; }
    public function setMaterialType(string $materialType): self { $this->materialType = $materialType; return $this; }

    public function getTrackingType(): ?string { return $this->trackingType; }
    public function setTrackingType(?string $trackingType): self { $this->trackingType = $trackingType; return $this; }

    public function getComboStatus(): string { return $this->comboStatus; }
    public function setComboStatus(string $comboStatus): self { $this->comboStatus = $comboStatus; return $this; }

    public function isCombo(): bool { return in_array($this->materialType, ['physical_combo', 'virtual_combo'], true); }
    public function isComboDraft(): bool { return $this->isCombo() && $this->comboStatus === 'draft'; }
    public function isVirtualCombo(): bool { return $this->materialType === 'virtual_combo'; }

    // Identifikation Getters/Setters
    public function getManufacturer(): ?string { return $this->manufacturer; }
    public function setManufacturer(?string $manufacturer): self { $this->manufacturer = $manufacturer; return $this; }

    public function getModel(): ?string { return $this->model; }
    public function setModel(?string $model): self { $this->model = $model; return $this; }

    public function getWarrantyUntil(): ?\DateTime { return $this->warrantyUntil; }
    public function setWarrantyUntil(?\DateTime $warrantyUntil): self { $this->warrantyUntil = $warrantyUntil; return $this; }

    // Verleih Getters/Setters
    public function getRentalExternalAllowed(): bool { return $this->rentalExternalAllowed; }
    public function setRentalExternalAllowed(bool $rentalExternalAllowed): self { $this->rentalExternalAllowed = $rentalExternalAllowed; return $this; }

    public function getRentalScope(): ?string { return $this->rentalScope; }
    public function setRentalScope(?string $rentalScope): self { $this->rentalScope = $rentalScope; return $this; }

    public function getRentalRequiresApproval(): bool { return $this->rentalRequiresApproval; }
    public function setRentalRequiresApproval(bool $rentalRequiresApproval): self { $this->rentalRequiresApproval = $rentalRequiresApproval; return $this; }

    public function getRentalPriceDay(): ?string { return $this->rentalPriceDay; }
    public function setRentalPriceDay(?string $rentalPriceDay): self { $this->rentalPriceDay = $rentalPriceDay; return $this; }

    public function getRentalPriceWeek(): ?string { return $this->rentalPriceWeek; }
    public function setRentalPriceWeek(?string $rentalPriceWeek): self { $this->rentalPriceWeek = $rentalPriceWeek; return $this; }

    public function getRentalPriceMonth(): ?string { return $this->rentalPriceMonth; }
    public function setRentalPriceMonth(?string $rentalPriceMonth): self { $this->rentalPriceMonth = $rentalPriceMonth; return $this; }

    public function getRentalDeposit(): ?string { return $this->rentalDeposit; }
    public function setRentalDeposit(?string $rentalDeposit): self { $this->rentalDeposit = $rentalDeposit; return $this; }

    public function getRentalLeadDays(): ?int { return $this->rentalLeadDays; }
    public function setRentalLeadDays(?int $rentalLeadDays): self { $this->rentalLeadDays = $rentalLeadDays; return $this; }

    public function getRentalMaxDays(): ?int { return $this->rentalMaxDays; }
    public function setRentalMaxDays(?int $rentalMaxDays): self { $this->rentalMaxDays = $rentalMaxDays; return $this; }

    public function getRentalNotes(): ?string { return $this->rentalNotes; }
    public function setRentalNotes(?string $rentalNotes): self { $this->rentalNotes = $rentalNotes; return $this; }

    public function getRentalCalcParams(): ?array
    {
        return $this->rentalCalcParams;
    }

    public function setRentalCalcParams(?array $rentalCalcParams): self
    {
        $this->rentalCalcParams = $rentalCalcParams;
        return $this;
    }

    // Externe Quelle Getters/Setters
    public function getIsJsMaterial(): bool { return $this->isJsMaterial; }
    public function setIsJsMaterial(bool $isJsMaterial): self { $this->isJsMaterial = $isJsMaterial; return $this; }

    public function getExternalSource(): ?string { return $this->externalSource; }
    public function setExternalSource(?string $externalSource): self { $this->externalSource = $externalSource; return $this; }

    // Verpackungseinheit Getters/Setters
    public function getPackSize(): ?int { return $this->packSize; }
    public function setPackSize(?int $packSize): self { $this->packSize = $packSize; return $this; }

    public function getPackUnit(): ?string { return $this->packUnit; }
    public function setPackUnit(?string $packUnit): self { $this->packUnit = $packUnit; return $this; }

    public function getPackagingUnit(): ?string { return $this->packagingUnit; }
    public function setPackagingUnit(?string $packagingUnit): self { $this->packagingUnit = $packagingUnit; return $this; }

    public function getPackSalePriceChf(): ?string { return $this->packSalePriceChf; }
    public function setPackSalePriceChf(?string $packSalePriceChf): self
    {
        $this->packSalePriceChf = $packSalePriceChf;
        return $this;
    }

    public function getPackWeight(): ?string { return $this->packWeight; }
    public function setPackWeight(?string $packWeight): self { $this->packWeight = $packWeight; return $this; }

    public function getPackSizeLength(): ?string { return $this->packSizeLength; }
    public function setPackSizeLength(?string $packSizeLength): self { $this->packSizeLength = $packSizeLength; return $this; }

    public function getPackSizeWidth(): ?string { return $this->packSizeWidth; }
    public function setPackSizeWidth(?string $packSizeWidth): self { $this->packSizeWidth = $packSizeWidth; return $this; }

    public function getPackSizeHeight(): ?string { return $this->packSizeHeight; }
    public function setPackSizeHeight(?string $packSizeHeight): self { $this->packSizeHeight = $packSizeHeight; return $this; }

    /**
     * Berechnet die Anzahl Verpackungseinheiten aus dem Gesamtbestand
     * z.B. 80 Stk. bei packSize=10 → 8 Einheiten
     */
    public function getPackCount(): ?float
    {
        if ($this->packSize === null || $this->packSize <= 0) {
            return null;
        }
        return $this->getTotalStock() / $this->packSize;
    }

    /**
     * Formatierte Anzeige: "80 Stk. (8 Bündel à 10)"
     */
    public function getStockDisplay(): string
    {
        $total = $this->getTotalStock();
        if ($this->packSize && $this->packUnit) {
            $packs = floor($total / $this->packSize);
            $rest = $total % $this->packSize;
            if ($rest === 0) {
                return sprintf('%d Stk. (%d %s à %d)', $total, $packs, $this->packUnit, $this->packSize);
            } else {
                return sprintf('%d Stk. (%d %s à %d + %d Stk.)', $total, $packs, $this->packUnit, $this->packSize, $rest);
            }
        }
        return $total . ' Stk.';
    }

    // Verbrauchsmaterial Getters/Setters
    public function getIsConsumable(): bool { return $this->isConsumable; }
    public function setIsConsumable(bool $isConsumable): self { $this->isConsumable = $isConsumable; return $this; }

    /** Verbrauch/Verbrauchsmaterial-Tab: Verbrauchsmaterial oder Esswaren. */
    public function countsAsConsumableForActivity(): bool
    {
        return $this->isConsumable || $this->isFood;
    }

    // Esswaren Getters/Setters
    public function getIsFood(): bool { return $this->isFood; }
    public function setIsFood(bool $isFood): self { $this->isFood = $isFood; return $this; }

    public function getSalePrice(): ?string { return $this->salePrice; }
    public function setSalePrice(?string $salePrice): self { $this->salePrice = $salePrice; return $this; }

    public function getExternalSalePriceChf(): ?string { return $this->externalSalePriceChf; }
    public function setExternalSalePriceChf(?string $externalSalePriceChf): self
    {
        $this->externalSalePriceChf = $externalSalePriceChf;
        return $this;
    }

    public function getReferencePurchaseUnitChf(): ?string { return $this->referencePurchaseUnitChf; }
    public function setReferencePurchaseUnitChf(?string $referencePurchaseUnitChf): self
    {
        $this->referencePurchaseUnitChf = $referencePurchaseUnitChf;
        return $this;
    }

    public function getMinStock(): ?int { return $this->minStock; }
    public function setMinStock(?int $minStock): self { $this->minStock = $minStock; return $this; }

    /** @return list<array<string, mixed>>|null */
    public function getPhotos(): ?array { return $this->photos; }

    /** @param list<array<string, mixed>>|null $photos */
    public function setPhotos(?array $photos): self { $this->photos = $photos; return $this; }

    public function getPrimaryPhotoUrl(): ?string
    {
        $photos = $this->photos ?? [];
        if ($photos !== []) {
            $first = $photos[0];
            if (\is_array($first) && !empty($first['url'])) {
                return (string) $first['url'];
            }
            if (\is_string($first) && $first !== '') {
                return $first;
            }
        }

        return null;
    }

    // Timestamps
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTime $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * @return Collection<int, MaterialBatch>
     */
    public function getBatches(): Collection
    {
        return $this->batches;
    }

    public function addBatch(MaterialBatch $batch): self
    {
        if (!$this->batches->contains($batch)) {
            $this->batches->add($batch);
            $batch->setMaterialItem($this);
        }
        return $this;
    }

    public function removeBatch(MaterialBatch $batch): self
    {
        $this->batches->removeElement($batch);
        return $this;
    }

    /**
     * Berechnet den Gesamtbestand aus allen aktiven Batches
     */
    public function getTotalStock(): int
    {
        $total = 0;
        foreach ($this->batches as $batch) {
            if ($batch->getStatus() === 'active') {
                $total += $batch->getQty();
            }
        }
        return $total;
    }
}
