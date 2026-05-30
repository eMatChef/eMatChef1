<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SupplierDeliveryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupplierDeliveryRepository::class)]
#[ORM\Table(name: 'supplier_delivery')]
class SupplierDelivery
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_IMPORTED = 'imported';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'supplier_company_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $supplierCompanyId;

    #[ORM\ManyToOne(targetEntity: SupplierCompany::class)]
    #[ORM\JoinColumn(name: 'supplier_company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private SupplierCompany $supplierCompany;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Department $department;

    #[ORM\Column(name: 'delivery_ref', type: 'string', length: 120, nullable: true)]
    private ?string $deliveryRef = null;

    #[ORM\Column(name: 'invoice_ref', type: 'string', length: 120, nullable: true)]
    private ?string $invoiceRef = null;

    #[ORM\Column(name: 'delivered_at', type: 'datetime', nullable: true)]
    private ?\DateTime $deliveredAt = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    /** @var Collection<int, SupplierDeliveryLine> */
    #[ORM\OneToMany(mappedBy: 'delivery', targetEntity: SupplierDeliveryLine::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $lines;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->lines = new ArrayCollection();
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

    public function getDeliveryRef(): ?string
    {
        return $this->deliveryRef;
    }

    public function setDeliveryRef(?string $deliveryRef): self
    {
        $this->deliveryRef = $deliveryRef !== null && trim($deliveryRef) !== '' ? trim($deliveryRef) : null;

        return $this;
    }

    public function getInvoiceRef(): ?string
    {
        return $this->invoiceRef;
    }

    public function setInvoiceRef(?string $invoiceRef): self
    {
        $this->invoiceRef = $invoiceRef !== null && trim($invoiceRef) !== '' ? trim($invoiceRef) : null;

        return $this;
    }

    public function getDeliveredAt(): ?\DateTime
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTime $deliveredAt): self
    {
        $this->deliveredAt = $deliveredAt;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

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

    /** @return Collection<int, SupplierDeliveryLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(SupplierDeliveryLine $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setDelivery($this);
        }

        return $this;
    }

    public function clearLines(): self
    {
        foreach ($this->lines as $line) {
            $this->lines->removeElement($line);
        }

        return $this;
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /** @return array<string, mixed> */
    public function toArray(bool $includeLines = true): array
    {
        $payload = [
            'id' => $this->id,
            'supplier_company_id' => $this->supplierCompanyId,
            'supplier_company_name' => $this->supplierCompany->getName(),
            'department_id' => $this->departmentId,
            'department_name' => $this->department->getName(),
            'delivery_ref' => $this->deliveryRef,
            'invoice_ref' => $this->invoiceRef,
            'delivered_at' => $this->deliveredAt?->format(\DateTimeInterface::ATOM),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];

        if ($includeLines) {
            $payload['lines'] = array_map(
                static fn (SupplierDeliveryLine $line) => $line->toArray(),
                $this->lines->toArray()
            );
        }

        return $payload;
    }
}
