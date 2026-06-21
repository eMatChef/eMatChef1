<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_grossanlass_procurement_quote')]
#[ORM\Index(name: 'idx_grossanlass_procurement_quote_line', columns: ['procurement_line_id'])]
class ActivityGrossanlassProcurementQuote
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'procurement_line_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $procurementLineId;

    #[ORM\ManyToOne(targetEntity: ActivityGrossanlassProcurementLine::class)]
    #[ORM\JoinColumn(name: 'procurement_line_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ActivityGrossanlassProcurementLine $procurementLine;

    #[ORM\Column(type: 'string', length: 255)]
    private string $supplier;

    #[ORM\Column(name: 'supplier_address_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $supplierAddressId = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(name: 'supplier_address_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Address $supplierAddress = null;

    #[ORM\Column(name: 'pdf_filename', type: 'string', length: 255, nullable: true)]
    private ?string $pdfFilename = null;

    #[ORM\Column(name: 'amount_chf', type: 'decimal', precision: 12, scale: 2)]
    private string $amountChf;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'boolean')]
    private bool $selected = false;

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

    public function getProcurementLineId(): string
    {
        return $this->procurementLineId;
    }

    public function getProcurementLine(): ActivityGrossanlassProcurementLine
    {
        return $this->procurementLine;
    }

    public function setProcurementLine(ActivityGrossanlassProcurementLine $line): self
    {
        $this->procurementLine = $line;
        $this->procurementLineId = $line->getId();

        return $this;
    }

    public function getSupplier(): string
    {
        return $this->supplier;
    }

    public function setSupplier(string $supplier): self
    {
        $this->supplier = $supplier;

        return $this;
    }

    public function getSupplierAddressId(): ?string
    {
        return $this->supplierAddressId;
    }

    public function getSupplierAddress(): ?Address
    {
        return $this->supplierAddress;
    }

    public function setSupplierAddress(?Address $address): self
    {
        $this->supplierAddress = $address;
        $this->supplierAddressId = $address?->getId();

        return $this;
    }

    public function getPdfFilename(): ?string
    {
        return $this->pdfFilename;
    }

    public function setPdfFilename(?string $pdfFilename): self
    {
        $this->pdfFilename = $pdfFilename;

        return $this;
    }

    public function getAmountChf(): string
    {
        return $this->amountChf;
    }

    public function setAmountChf(string $amountChf): self
    {
        $this->amountChf = $amountChf;

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

    public function isSelected(): bool
    {
        return $this->selected;
    }

    public function setSelected(bool $selected): self
    {
        $this->selected = $selected;

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
