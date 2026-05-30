<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SupplierMembershipRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SupplierMembershipRepository::class)]
#[ORM\Table(name: 'supplier_membership')]
class SupplierMembership
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MEMBER = 'member';

    #[ORM\Id]
    #[ORM\Column(name: 'supplier_company_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $supplierCompanyId;

    #[ORM\Id]
    #[ORM\Column(name: 'user_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $userId;

    #[ORM\ManyToOne(targetEntity: SupplierCompany::class, inversedBy: 'memberships')]
    #[ORM\JoinColumn(name: 'supplier_company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private SupplierCompany $supplierCompany;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'supplierMemberships')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice(choices: [self::ROLE_ADMIN, self::ROLE_MEMBER])]
    private string $role = self::ROLE_MEMBER;

    #[ORM\Column(name: 'is_primary', type: 'boolean', options: ['default' => false])]
    private bool $isPrimary = false;

    public function getSupplierCompanyId(): string
    {
        return $this->supplierCompanyId;
    }

    public function setSupplierCompanyId(string $supplierCompanyId): self
    {
        $this->supplierCompanyId = $supplierCompanyId;
        return $this;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): self
    {
        $this->userId = $userId;
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        $this->userId = $user->getId();
        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getIsPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): self
    {
        $this->isPrimary = $isPrimary;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'supplier_company_id' => $this->supplierCompanyId,
            'user_id' => $this->userId,
            'role' => $this->role,
            'is_primary' => $this->isPrimary,
        ];
    }
}
