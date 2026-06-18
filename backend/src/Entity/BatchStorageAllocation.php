<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * BatchStorageAllocation - Zuordnung von Batch-Menge zu Lagerplatz
 *
 * Ermöglicht ein Batch (Einkauf) auf mehrere Lagerplätze aufzuteilen.
 * z.B. 30 Stk. gekauft → 10 in Kiste A, 20 in Kiste B
 *
 * Material liegt entweder direkt im Slot (rack_id, slot_id) oder in einer Kiste (container_batch_id).
 * Kiste = MaterialBatch eines „Lagerkiste“-Materials. Bei container_batch_id: Standort aus Kisten-Batch.
 */
#[ORM\Entity]
#[ORM\Table(name: 'batch_storage_allocation')]
#[ORM\Index(name: 'idx_batch_alloc_batch', columns: ['batch_id'])]
#[ORM\Index(name: 'idx_batch_alloc_department', columns: ['department_id'])]
#[ORM\Index(name: 'idx_batch_alloc_rack', columns: ['rack_id'])]
#[ORM\Index(name: 'idx_batch_alloc_container', columns: ['container_batch_id'])]
class BatchStorageAllocation
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'batch_id', type: 'string', length: 13, columnDefinition: 'CHARACTER(13) NOT NULL')]
    private string $batchId;

    #[ORM\ManyToOne(targetEntity: MaterialBatch::class)]
    #[ORM\JoinColumn(name: 'batch_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MaterialBatch $batch;

    #[ORM\Column(name: 'container_batch_id', type: 'string', length: 13, nullable: true, columnDefinition: 'CHARACTER(13) NULL')]
    private ?string $containerBatchId = null;

    #[ORM\ManyToOne(targetEntity: MaterialBatch::class)]
    #[ORM\JoinColumn(name: 'container_batch_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MaterialBatch $containerBatch = null;

    #[ORM\Column(name: 'rack_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $rackId = null;

    #[ORM\ManyToOne(targetEntity: StorageRack::class)]
    #[ORM\JoinColumn(name: 'rack_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?StorageRack $rack = null;

    #[ORM\Column(name: 'slot_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $slotId = null;

    #[ORM\ManyToOne(targetEntity: StorageSlot::class)]
    #[ORM\JoinColumn(name: 'slot_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?StorageSlot $slot = null;

    #[ORM\Column(type: 'integer')]
    private int $qty;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getBatchId(): string
    {
        return $this->batchId;
    }

    public function setBatchId(string $batchId): self
    {
        $this->batchId = $batchId;
        return $this;
    }

    public function getBatch(): MaterialBatch
    {
        return $this->batch;
    }

    public function setBatch(MaterialBatch $batch): self
    {
        $this->batch = $batch;
        $this->batchId = $batch->getId();
        return $this;
    }

    public function getContainerBatchId(): ?string
    {
        return $this->containerBatchId;
    }

    public function setContainerBatchId(?string $containerBatchId): self
    {
        $this->containerBatchId = $containerBatchId;
        return $this;
    }

    public function getContainerBatch(): ?MaterialBatch
    {
        return $this->containerBatch;
    }

    public function setContainerBatch(?MaterialBatch $containerBatch): self
    {
        $this->containerBatch = $containerBatch;
        $this->containerBatchId = $containerBatch?->getId();
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

    /**
     * Effektiver Gestell-Standort: aus Kisten-Batch, falls container_batch_id gesetzt, sonst rack_id.
     */
    public function getEffectiveRackId(): ?string
    {
        if ($this->containerBatch !== null) {
            return $this->containerBatch->getEffectiveRackId();
        }
        return $this->rackId;
    }

    /**
     * Effektiver Platz-Standort: aus Kisten-Batch, falls container_batch_id gesetzt, sonst slot_id.
     */
    public function getEffectiveSlotId(): ?string
    {
        if ($this->containerBatch !== null) {
            return $this->containerBatch->getEffectiveSlotId();
        }
        return $this->slotId;
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

    public function getQty(): int
    {
        return $this->qty;
    }

    public function setQty(int $qty): self
    {
        $this->qty = $qty;
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
}
