<?php

declare(strict_types=1);

namespace App\Service\Bootstrap;

use App\Command\CreateRoleUsersCommand;
use App\Entity\Profile;
use App\Entity\SupplierCompany;
use App\Entity\SupplierMembership;
use App\Entity\User;
use App\Service\Supplier\SupplierCompanyFactory;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Dev-Demo: Testfirma + supplier@ematchef.ch (kein Department, Zugang zum Supplier-Bereich).
 */
final class DemoSupplierSeedService
{
    public const EMAIL = 'supplier@ematchef.ch';
    public const MANUFACTURER_KEY = 'ematchef-demo';
    public const COMPANY_NAME = 'Demo Lieferant';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private SupplierCompanyFactory $supplierCompanyFactory,
    ) {
    }

    public function ensure(?User $createdBy = null): User
    {
        $user = $this->ensureUser($createdBy);
        $company = $this->ensureCompany();
        $this->ensureMembership($company, $user);
        $user->setLastUsedSupplierCompany($company);
        $this->entityManager->flush();

        return $user;
    }

    private function ensureUser(?User $createdBy): User
    {
        $profile = $this->entityManager->getRepository(Profile::class)->findOneBy(['email' => self::EMAIL]);
        if (!$profile) {
            $profile = new Profile();
            $profile->setId(IdGenerator::generateUnique($this->entityManager, Profile::class));
            $profile->setEmail(self::EMAIL);
            $this->entityManager->persist($profile);
        }
        $profile->setFirstName('Supplier');
        $profile->setLastName('User');
        $profile->setNickname('Supplier');
        $profile->setRoles(['ROLE_USER']);

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['profileId' => $profile->getId()]);
        if (!$user) {
            $user = new User();
            $user->setId(IdGenerator::generateUnique($this->entityManager, User::class));
            $user->setProfileId($profile->getId());
            $user->setProfile($profile);
            $this->entityManager->persist($user);
        }

        $user->setState('active');
        $user->setEmailVerified(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, CreateRoleUsersCommand::DEMO_PASSWORD));
        if ($createdBy !== null && $user->getCreatedBy() === null) {
            $user->setCreatedBy($createdBy);
        }

        return $user;
    }

    private function ensureCompany(): SupplierCompany
    {
        $company = $this->entityManager->getRepository(SupplierCompany::class)->findOneBy([
            'manufacturerKey' => self::MANUFACTURER_KEY,
        ]);

        $capabilities = [
            SupplierCompany::CAPABILITY_CATALOG,
            SupplierCompany::CAPABILITY_DELIVERY,
            SupplierCompany::CAPABILITY_TEMPLATES,
            SupplierCompany::CAPABILITY_REPAIRS,
        ];

        if ($company instanceof SupplierCompany) {
            $company->setName(self::COMPANY_NAME);
            $company->setStatus(SupplierCompany::STATUS_ACTIVE);
            $company->setCapabilities($capabilities);

            return $company;
        }

        return $this->supplierCompanyFactory->createWithAddress(
            name: self::COMPANY_NAME,
            addressData: [
                'street' => 'Teststrasse',
                'street_number' => '1',
                'postal_code' => '8000',
                'city' => 'Zürich',
                'email' => self::EMAIL,
                'contact_first_name' => 'Supplier',
                'contact_last_name' => 'User',
            ],
            manufacturerKey: self::MANUFACTURER_KEY,
            capabilities: $capabilities,
            status: SupplierCompany::STATUS_ACTIVE,
        );
    }

    private function ensureMembership(SupplierCompany $company, User $user): void
    {
        $existing = $this->entityManager->getRepository(SupplierMembership::class)->findOneBy([
            'userId' => $user->getId(),
            'supplierCompanyId' => $company->getId(),
        ]);
        if ($existing instanceof SupplierMembership) {
            $existing->setRole(SupplierMembership::ROLE_ADMIN);
            $existing->setIsPrimary(true);

            return;
        }

        $this->supplierCompanyFactory->addMembership(
            $company,
            $user,
            SupplierMembership::ROLE_ADMIN,
            true,
        );
    }
}
