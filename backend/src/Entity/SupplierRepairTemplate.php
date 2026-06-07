<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Supplier-Override für Zeltblatt-Preise und Dienstleistungen (Struktur von repair_template).
 */
#[ORM\Entity(repositoryClass: \App\Repository\SupplierRepairTemplateRepository::class)]
#[ORM\Table(name: 'supplier_repair_template')]
#[ORM\UniqueConstraint(name: 'uniq_supplier_repair_template', columns: ['supplier_company_id', 'template_key'])]
#[ORM\Index(name: 'idx_supplier_repair_template_company', columns: ['supplier_company_id'])]
class SupplierRepairTemplate
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(name: 'supplier_company_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $supplierCompanyId;

    #[ORM\ManyToOne(targetEntity: SupplierCompany::class)]
    #[ORM\JoinColumn(name: 'supplier_company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private SupplierCompany $supplierCompany;

    #[ORM\Column(name: 'template_key', type: 'string', length: 50)]
    private string $templateKey;

    #[ORM\Column(name: 'prices_json', type: 'json')]
    private array $pricesJson = [];

    #[ORM\Column(name: 'flat_rate_chf', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $flatRateChf = null;

    /** Reinigung/Reparatur-Dienste (z. B. waschen, imprägnieren). */
    #[ORM\Column(name: 'services_json', type: 'json')]
    private array $servicesJson = ['services' => []];

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

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
        $this->supplierCompanyId = $supplierCompany->getId();

        return $this;
    }

    public function getTemplateKey(): string
    {
        return $this->templateKey;
    }

    public function setTemplateKey(string $templateKey): self
    {
        $this->templateKey = $templateKey;

        return $this;
    }

    public function getPricesJson(): array
    {
        return $this->pricesJson;
    }

    public function setPricesJson(array $pricesJson): self
    {
        $this->pricesJson = $pricesJson;

        return $this;
    }

    public function getFlatRateChf(): ?string
    {
        return $this->flatRateChf;
    }

    public function setFlatRateChf(?string $flatRateChf): self
    {
        $this->flatRateChf = $flatRateChf;

        return $this;
    }

    public function getServicesJson(): array
    {
        return $this->servicesJson;
    }

    public function setServicesJson(array $servicesJson): self
    {
        $this->servicesJson = $servicesJson;

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

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
