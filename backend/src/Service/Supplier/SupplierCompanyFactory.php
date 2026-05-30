<?php

declare(strict_types=1);

namespace App\Service\Supplier;

use App\Entity\Address;
use App\Entity\SupplierCompany;
use App\Entity\SupplierMembership;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Legt SupplierCompany + Haupt-Adresse (scope=supplier) atomar an.
 */
class SupplierCompanyFactory
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<string>              $capabilities
     * @param array<string, mixed|null> $addressData Felder für Address (company, street, …)
     */
    public function createWithAddress(
        string $name,
        array $addressData = [],
        ?string $manufacturerKey = null,
        array $capabilities = [],
        string $status = SupplierCompany::STATUS_PENDING,
        ?string $linkedDepartmentId = null,
    ): SupplierCompany {
        return $this->entityManager->wrapInTransaction(function () use (
            $name,
            $addressData,
            $manufacturerKey,
            $capabilities,
            $status,
            $linkedDepartmentId,
        ): SupplierCompany {
            $companyId = IdGenerator::generateUnique($this->entityManager, SupplierCompany::class);
            $addressId = IdGenerator::generateUnique($this->entityManager, Address::class);

            $company = new SupplierCompany();
            $company->setId($companyId);
            $company->setName(trim($name));
            $company->setManufacturerKey($manufacturerKey);
            $company->setCapabilities($capabilities);
            $company->setStatus($status);
            $company->setLinkedDepartmentId($linkedDepartmentId);

            $address = new Address();
            $address->setId($addressId);
            $address->setScope(Address::SCOPE_SUPPLIER);
            $address->setType('supplier');
            $address->setSupplierCompanyId($companyId);
            $this->applyAddressData($address, $addressData, $name);

            $this->entityManager->persist($company);
            $this->entityManager->persist($address);
            $this->entityManager->flush();

            $company->setSupplierAddressId($addressId);
            $company->setSupplierAddress($address);
            $this->entityManager->flush();

            return $company;
        });
    }

    public function addMembership(
        SupplierCompany $company,
        User $user,
        string $role = SupplierMembership::ROLE_MEMBER,
        bool $isPrimary = false,
    ): SupplierMembership {
        $membership = new SupplierMembership();
        $membership->setSupplierCompany($company);
        $membership->setUser($user);
        $membership->setRole($role);
        $membership->setIsPrimary($isPrimary);

        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        return $membership;
    }

    /**
     * Legacy: scope=global Adresse → SupplierCompany (gleiche address.id, material_batch FK bleibt).
     *
     * @param list<string> $capabilities
     */
    public function promoteGlobalAddress(
        Address $globalAddress,
        ?string $name = null,
        ?string $manufacturerKey = null,
        array $capabilities = [],
        string $status = SupplierCompany::STATUS_ACTIVE,
        ?string $linkedDepartmentId = null,
    ): SupplierCompany {
        if ($globalAddress->getScope() !== Address::SCOPE_GLOBAL || $globalAddress->getType() !== 'supplier') {
            throw new \InvalidArgumentException('Nur globale Lieferanten-Adressen können aktiviert werden');
        }
        if ($globalAddress->isDeleted()) {
            throw new \InvalidArgumentException('Gelöschte Adresse kann nicht aktiviert werden');
        }
        if ($globalAddress->getSupplierCompanyId() !== null) {
            throw new \InvalidArgumentException('Adresse ist bereits einer Supplier-Firma zugeordnet');
        }

        $companyName = trim($name ?? $globalAddress->getCompany() ?? $globalAddress->getName() ?? '');
        if ($companyName === '') {
            throw new \InvalidArgumentException('Firmenname ist erforderlich');
        }

        return $this->entityManager->wrapInTransaction(function () use (
            $globalAddress,
            $companyName,
            $manufacturerKey,
            $capabilities,
            $status,
            $linkedDepartmentId,
        ): SupplierCompany {
            $companyId = IdGenerator::generateUnique($this->entityManager, SupplierCompany::class);

            $company = new SupplierCompany();
            $company->setId($companyId);
            $company->setName($companyName);
            $company->setManufacturerKey($manufacturerKey);
            $company->setCapabilities($capabilities);
            $company->setStatus($status);
            $company->setLinkedDepartmentId($linkedDepartmentId);
            $company->setSupplierAddressId($globalAddress->getId());

            $globalAddress->setScope(Address::SCOPE_SUPPLIER);
            $globalAddress->setSupplierCompanyId($companyId);

            $this->entityManager->persist($company);
            $this->entityManager->flush();

            $company->setSupplierAddress($globalAddress);

            return $company;
        });
    }

    /** @param list<string> $raw */
    public static function normalizeCapabilities(array $raw): array
    {
        $allowed = [
            SupplierCompany::CAPABILITY_CATALOG,
            SupplierCompany::CAPABILITY_DELIVERY,
            SupplierCompany::CAPABILITY_TEMPLATES,
            SupplierCompany::CAPABILITY_REPAIRS,
            SupplierCompany::CAPABILITY_OPERATOR,
        ];
        $filtered = array_values(array_unique(array_filter(
            $raw,
            static fn ($c) => \is_string($c) && \in_array($c, $allowed, true)
        )));

        return $filtered;
    }

    public static function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (!\in_array($status, [
            SupplierCompany::STATUS_PENDING,
            SupplierCompany::STATUS_ACTIVE,
            SupplierCompany::STATUS_SUSPENDED,
        ], true)) {
            throw new \InvalidArgumentException('Ungültiger status');
        }

        return $status;
    }

    /** @param array<string, mixed|null> $data */
    private function applyAddressData(Address $address, array $data, string $companyName): void
    {
        $address->setCompany($this->nullableString($data['company'] ?? $companyName));
        $address->setName($this->nullableString($data['name'] ?? null));
        $address->setStreet($this->nullableString($data['street'] ?? null));
        $address->setStreetNumber($this->nullableString($data['street_number'] ?? null));
        $address->setPostalCode($this->nullableString($data['postal_code'] ?? null));
        $address->setCity($this->nullableString($data['city'] ?? null));
        $address->setCanton($this->nullableString($data['canton'] ?? null));
        $address->setCountry((string) ($data['country'] ?? 'Schweiz'));
        $address->setContactFirstName($this->nullableString($data['contact_first_name'] ?? null));
        $address->setContactLastName($this->nullableString($data['contact_last_name'] ?? null));
        $address->setEmail($this->nullableString($data['email'] ?? null));
        $address->setPhone($this->nullableString($data['phone'] ?? null));
        $address->setMobile($this->nullableString($data['mobile'] ?? null));
        $address->setAdditionalInfo($this->nullableString($data['additional_info'] ?? null));
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
