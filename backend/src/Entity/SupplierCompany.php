<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SupplierCompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SupplierCompanyRepository::class)]
#[ORM\Table(name: 'supplier_company')]
#[ORM\UniqueConstraint(name: 'uniq_supplier_company_address', columns: ['supplier_address_id'])]
#[ORM\UniqueConstraint(name: 'uniq_supplier_company_manufacturer_key', columns: ['manufacturer_key'])]
class SupplierCompany
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';

    public const CAPABILITY_CATALOG = 'catalog';
    public const CAPABILITY_DELIVERY = 'delivery';
    public const CAPABILITY_TEMPLATES = 'templates';
    public const CAPABILITY_REPAIRS = 'repairs';
    public const CAPABILITY_OPERATOR = 'operator';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?string $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(name: 'manufacturer_key', type: 'string', length: 120, nullable: true)]
    private ?string $manufacturerKey = null;

    #[ORM\Column(name: 'join_code', type: 'string', length: 8, nullable: true)]
    private ?string $joinCode = null;

    #[ORM\Column(name: 'supplier_address_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $supplierAddressId = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(name: 'supplier_address_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?Address $supplierAddress = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $capabilities = [];

    #[ORM\Column(name: 'linked_department_id', type: 'string', length: 12, nullable: true, columnDefinition: 'CHARACTER(12) NULL')]
    private ?string $linkedDepartmentId = null;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'linked_department_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Department $linkedDepartment = null;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice(choices: [self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_SUSPENDED])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    /** @var Collection<int, SupplierMembership> */
    #[ORM\OneToMany(mappedBy: 'supplierCompany', targetEntity: SupplierMembership::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $memberships;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->memberships = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getManufacturerKey(): ?string
    {
        return $this->manufacturerKey;
    }

    public function setManufacturerKey(?string $manufacturerKey): self
    {
        $this->manufacturerKey = $manufacturerKey !== null && $manufacturerKey !== ''
            ? mb_strtolower(trim($manufacturerKey))
            : null;
        return $this;
    }

    public function getJoinCode(): ?string
    {
        return $this->joinCode;
    }

    public function setJoinCode(?string $joinCode): self
    {
        if ($joinCode === null || $joinCode === '') {
            $this->joinCode = null;
            return $this;
        }
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', $joinCode) ?? '');
        $this->joinCode = $normalized !== '' ? $normalized : null;
        return $this;
    }

    public function getSupplierAddressId(): ?string
    {
        return $this->supplierAddressId;
    }

    public function setSupplierAddressId(?string $supplierAddressId): self
    {
        $this->supplierAddressId = $supplierAddressId;
        return $this;
    }

    public function getSupplierAddress(): ?Address
    {
        return $this->supplierAddress;
    }

    public function setSupplierAddress(?Address $supplierAddress): self
    {
        $this->supplierAddress = $supplierAddress;
        $this->supplierAddressId = $supplierAddress?->getId();
        return $this;
    }

    /** @return list<string> */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    /** @param list<string> $capabilities */
    public function setCapabilities(array $capabilities): self
    {
        $this->capabilities = array_values(array_unique($capabilities));
        return $this;
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    public function getLinkedDepartmentId(): ?string
    {
        return $this->linkedDepartmentId;
    }

    public function setLinkedDepartmentId(?string $linkedDepartmentId): self
    {
        $this->linkedDepartmentId = $linkedDepartmentId;
        return $this;
    }

    public function getLinkedDepartment(): ?Department
    {
        return $this->linkedDepartment;
    }

    public function setLinkedDepartment(?Department $linkedDepartment): self
    {
        $this->linkedDepartment = $linkedDepartment;
        $this->linkedDepartmentId = $linkedDepartment?->getId();
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

    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /** @return Collection<int, SupplierMembership> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(SupplierMembership $membership): self
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setSupplierCompany($this);
        }
        return $this;
    }

    public function removeMembership(SupplierMembership $membership): self
    {
        $this->memberships->removeElement($membership);
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'manufacturer_key' => $this->manufacturerKey,
            'supplier_address_id' => $this->supplierAddressId,
            'capabilities' => $this->capabilities,
            'linked_department_id' => $this->linkedDepartmentId,
            'status' => $this->status,
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt->format('c'),
        ];
    }
}
