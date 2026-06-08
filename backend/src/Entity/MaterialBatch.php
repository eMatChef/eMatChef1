<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MaterialBatch - Einkäufe/Bewegungen
 * 
 * Jedes Material hat mindestens 1 Batch (initial)
 */
#[ORM\Entity]
#[ORM\Table(name: 'material_batch')]
class MaterialBatch
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'material_item_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $materialItemId;

    #[ORM\ManyToOne(targetEntity: MaterialItem::class, inversedBy: 'batches')]
    #[ORM\JoinColumn(name: 'material_item_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialItem $materialItem;

    #[ORM\Column(name: 'supplier_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $supplierId = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(name: 'supplier_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Address $supplier = null;

    #[ORM\Column(name: 'acquired_on', type: 'date')]
    private \DateTime $acquiredOn;

    #[ORM\Column(type: 'string', length: 80, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: 'integer')]
    private int $qty;

    #[ORM\Column(name: 'unit_price', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $unitPrice = null;

    #[ORM\Column(name: 'external_ref', type: 'string', length: 120, nullable: true)]
    private ?string $externalRef = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'is_initial', type: 'boolean', options: ['default' => false])]
    private bool $isInitial = false;

    #[ORM\Column(name: 'batch_type', type: 'string', length: 20, options: ['default' => 'purchase'])]
    private string $batchType = 'purchase'; // initial, purchase, writeoff, correction, return, adjustment, donation, found, loan

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'active'])]
    private string $status = 'active'; // active, inactive, defect, lost

    #[ORM\Column(name: 'invoice_number', type: 'string', length: 100, nullable: true)]
    private ?string $invoiceNumber = null;

    #[ORM\Column(name: 'serial_number', type: 'string', length: 100, nullable: true)]
    private ?string $serialNumber = null;

    #[ORM\Column(name: 'expiry_date', type: 'date', nullable: true)]
    private ?\DateTime $expiryDate = null;

    #[ORM\Column(name: 'storage_address_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $storageAddressId = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(name: 'storage_address_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Address $storageAddress = null;

    #[ORM\Column(name: 'rack_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $rackId = null;

    #[ORM\ManyToOne(targetEntity: StorageRack::class)]
    #[ORM\JoinColumn(name: 'rack_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?StorageRack $rack = null;

    #[ORM\Column(name: 'slot_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $slotId = null;

    #[ORM\ManyToOne(targetEntity: StorageSlot::class)]
    #[ORM\JoinColumn(name: 'slot_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?StorageSlot $slot = null;

    /** Pro Instanz (serialisiert): Behälter, der anderen Inhalt aufnehmen kann; ergänzt material_item.is_container. */
    #[ORM\Column(name: 'is_container', type: 'boolean', options: ['default' => false])]
    private bool $isContainer = false;

    #[ORM\Column(name: 'source_batch_id', type: 'string', length: 13, nullable: true, columnDefinition: 'CHARACTER(13) NULL')]
    private ?string $sourceBatchId = null;

    #[ORM\ManyToOne(targetEntity: MaterialBatch::class)]
    #[ORM\JoinColumn(name: 'source_batch_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MaterialBatch $sourceBatch = null;

    #[ORM\Column(name: 'conversion_group_id', type: 'string', length: 40, nullable: true)]
    private ?string $conversionGroupId = null;

    /** @var \Doctrine\Common\Collections\Collection<int, BatchStorageAllocation> */
    #[ORM\OneToMany(targetEntity: BatchStorageAllocation::class, mappedBy: 'batch', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private \Doctrine\Common\Collections\Collection $allocations;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->acquiredOn = new \DateTime();
        $this->allocations = new \Doctrine\Common\Collections\ArrayCollection();
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

    public function getMaterialItemId(): string
    {
        return $this->materialItemId;
    }

    public function setMaterialItemId(string $materialItemId): self
    {
        $this->materialItemId = $materialItemId;
        return $this;
    }

    public function getMaterialItem(): MaterialItem
    {
        return $this->materialItem;
    }

    public function setMaterialItem(MaterialItem $materialItem): self
    {
        $this->materialItem = $materialItem;
        $this->materialItemId = $materialItem->getId();
        return $this;
    }

    public function getSupplierId(): ?string
    {
        return $this->supplierId;
    }

    public function setSupplierId(?string $supplierId): self
    {
        $this->supplierId = $supplierId;
        return $this;
    }

    public function getSupplier(): ?Address
    {
        return $this->supplier;
    }

    public function setSupplier(?Address $supplier): self
    {
        $this->supplier = $supplier;
        $this->supplierId = $supplier?->getId();
        return $this;
    }

    public function getAcquiredOn(): \DateTime
    {
        return $this->acquiredOn;
    }

    public function setAcquiredOn(\DateTime $acquiredOn): self
    {
        $this->acquiredOn = $acquiredOn;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getQty(): int
    {
        return $this->qty;
    }

    public function setQty(int $qty): self
    {
        $this->qty = $qty;
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

    public function getExternalRef(): ?string
    {
        return $this->externalRef;
    }

    public function setExternalRef(?string $externalRef): self
    {
        $this->externalRef = $externalRef;
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

    public function getIsInitial(): bool
    {
        return $this->isInitial;
    }

    public function setIsInitial(bool $isInitial): self
    {
        $this->isInitial = $isInitial;
        return $this;
    }

    public function getBatchType(): string
    {
        return $this->batchType;
    }

    public function setBatchType(string $batchType): self
    {
        $this->batchType = $batchType;
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

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(?string $invoiceNumber): self
    {
        $this->invoiceNumber = $invoiceNumber;
        return $this;
    }

    public function getExpiryDate(): ?\DateTime
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(?\DateTime $expiryDate): self
    {
        $this->expiryDate = $expiryDate;
        return $this;
    }

    public function getSerialNumber(): ?string
    {
        return $this->serialNumber;
    }

    public function setSerialNumber(?string $serialNumber): self
    {
        $this->serialNumber = $serialNumber;
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

    public function getRackId(): ?string
    {
        return $this->rackId;
    }

    public function setRackId(?string $rackId): self
    {
        $this->rackId = $rackId;
        return $this;
    }

    public function getRack(): ?StorageRack
    {
        return $this->rack;
    }

    public function setRack(?StorageRack $rack): self
    {
        $this->rack = $rack;
        $this->rackId = $rack?->getId();
        return $this;
    }

    public function getSlotId(): ?string
    {
        return $this->slotId;
    }

    public function setSlotId(?string $slotId): self
    {
        $this->slotId = $slotId;
        return $this;
    }

    public function getSlot(): ?StorageSlot
    {
        return $this->slot;
    }

    public function setSlot(?StorageSlot $slot): self
    {
        $this->slot = $slot;
        $this->slotId = $slot?->getId();
        return $this;
    }

    /**
     * Effektiver Gestell-Standort: direktes rack_id oder erster freistehender Allokationsplatz.
     */
    public function getEffectiveRackId(): ?string
    {
        if ($this->rackId !== null && $this->rackId !== '') {
            return $this->rackId;
        }
        foreach ($this->allocations as $allocation) {
            if ($allocation->getContainerBatchId() !== null) {
                continue;
            }
            $rackId = $allocation->getRackId();
            if ($rackId !== null && $rackId !== '') {
                return $rackId;
            }
        }

        return null;
    }

    /**
     * Effektiver Platz-Standort: direktes slot_id oder erster freistehender Allokationsplatz.
     */
    public function getEffectiveSlotId(): ?string
    {
        if ($this->slotId !== null && $this->slotId !== '') {
            return $this->slotId;
        }
        foreach ($this->allocations as $allocation) {
            if ($allocation->getContainerBatchId() !== null) {
                continue;
            }
            $slotId = $allocation->getSlotId();
            if ($slotId !== null && $slotId !== '') {
                return $slotId;
            }
        }

        return null;
    }

    public function getIsContainer(): bool
    {
        return $this->isContainer;
    }

    public function setIsContainer(bool $isContainer): self
    {
        $this->isContainer = $isContainer;
        return $this;
    }

    public function getSourceBatchId(): ?string
    {
        return $this->sourceBatchId;
    }

    public function setSourceBatchId(?string $sourceBatchId): self
    {
        $this->sourceBatchId = $sourceBatchId;
        return $this;
    }

    public function getSourceBatch(): ?MaterialBatch
    {
        return $this->sourceBatch;
    }

    public function setSourceBatch(?MaterialBatch $sourceBatch): self
    {
        $this->sourceBatch = $sourceBatch;
        $this->sourceBatchId = $sourceBatch?->getId();
        return $this;
    }

    public function getConversionGroupId(): ?string
    {
        return $this->conversionGroupId;
    }

    public function setConversionGroupId(?string $conversionGroupId): self
    {
        $this->conversionGroupId = $conversionGroupId;
        return $this;
    }

    /**
     * @return \Doctrine\Common\Collections\Collection<int, BatchStorageAllocation>
     */
    public function getAllocations(): \Doctrine\Common\Collections\Collection
    {
        return $this->allocations;
    }

    public function addAllocation(BatchStorageAllocation $allocation): self
    {
        if (!$this->allocations->contains($allocation)) {
            $this->allocations->add($allocation);
            $allocation->setBatch($this);
        }
        return $this;
    }

    public function removeAllocation(BatchStorageAllocation $allocation): self
    {
        $this->allocations->removeElement($allocation);
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * Berechnet den Gesamtpreis (qty * unitPrice)
     */
    public function getTotalPrice(): ?float
    {
        if ($this->unitPrice === null) {
            return null;
        }
        return $this->qty * (float) $this->unitPrice;
    }
}
