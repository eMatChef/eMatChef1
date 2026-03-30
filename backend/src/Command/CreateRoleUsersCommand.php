<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Profile;
use App\Entity\Department;
use App\Entity\Organisation;
use App\Entity\Membership;
use App\Enum\DepartmentRole;
use App\Service\Accounting\AccountingCostCenterBootstrapService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-role-users',
    description: 'Erstellt für jede Rolle einen Benutzer (löscht alte Test-User)'
)]
class CreateRoleUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private AccountingCostCenterBootstrapService $accountingCostCenterBootstrap
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Erstelle Benutzer für alle Rollen');

        // Hole oder erstelle Organisation und Department
        $organisation = $this->em->getRepository(Organisation::class)->findOneBy([]);
        if (!$organisation) {
            $io->warning('Keine Organisation gefunden. Erstelle Standard-Organisation...');
            $organisation = new Organisation();
            $organisation->setId(IdGenerator::generateUnique($this->em, Organisation::class));
            $organisation->setName('Standard Organisation');
            $this->em->persist($organisation);
            $this->em->flush();
        }

        $department = $this->em->getRepository(Department::class)->findOneBy([]);
        if (!$department) {
            $io->warning('Kein Department gefunden. Erstelle Standard-Department...');
            $department = new Department();
            $department->setId(IdGenerator::generateUnique($this->em, Department::class));
            $department->setName('Standard Department');
            $department->setOrganisation($organisation);
            $this->em->persist($department);
            $this->em->flush();
            $this->accountingCostCenterBootstrap->ensureDefaultCostCenters($this->em, $department);
        }

        // Lösche alle bestehenden Test-User (außer dem ersten Superadmin, falls er existiert)
        $io->section('Lösche alte Test-User...');
        $allUsers = $this->em->getRepository(User::class)->findAll();
        $deletedCount = 0;
        foreach ($allUsers as $user) {
            $profile = $user->getProfile();
            if ($profile && str_ends_with($profile->getEmail(), '@example.com')) {
                // Lösche Membership-Zuordnungen
                $memberships = $this->em->getRepository(Membership::class)
                    ->findBy(['userId' => $user->getId()]);
                foreach ($memberships as $m) {
                    $this->em->remove($m);
                }
                // Lösche User und Profile
                $this->em->remove($user);
                $this->em->remove($profile);
                $deletedCount++;
            }
        }
        $this->em->flush();
        $io->success("$deletedCount alte Test-User gelöscht");

        // Erstelle einen Superadmin als Erstes (wird als createdBy verwendet)
        $io->section('Erstelle Superadmin...');
        $superadminUser = $this->createUser(
            'superadmin@example.com',
            'Superadmin',
            'User',
            'Superadmin',
            DepartmentRole::SUPERADMIN,
            $department,
            true
        );
        $this->em->flush();
        $io->success('Superadmin erstellt: superadmin@example.com / password');

        // Erstelle für jede andere Rolle einen Benutzer
        $io->section('Erstelle Benutzer für alle Rollen...');
        $roles = [
            DepartmentRole::ORGANISATIONSCHEF,
            DepartmentRole::SUBORGCHEF,
            DepartmentRole::MATWART,
            DepartmentRole::DEPCHEF,
            DepartmentRole::LEADER1,
            DepartmentRole::LEADER2,
            DepartmentRole::LEADER3,
            DepartmentRole::USER,
        ];

        foreach ($roles as $role) {
            // Verwende den vollständigen Namen für Email und Anzeige
            $fullName = $role->getFullName();
            $email = $fullName . '@example.com';
            $firstName = ucfirst($fullName);
            $lastName = 'User';
            $nickname = ucfirst($fullName);

            $this->createUser(
                $email,
                $firstName,
                $lastName,
                $nickname,
                $role,
                $department,
                false,
                $superadminUser
            );
            $io->text("✓ {$role->getLabel()}: $email / password");
        }

        $this->em->flush();
        $io->success('Alle Benutzer erfolgreich erstellt!');

        $io->note([
            'Alle Benutzer haben das Passwort: password',
            'Login-Emails:',
            '  - superadmin@example.com (Superadmin)',
            '  - organisationschef@example.com (Organisationschef)',
            '  - suborgchef@example.com (Suborgchef)',
            '  - matwart@example.com (Materialchef)',
            '  - depchef@example.com (Departmentchef)',
            '  - leader1@example.com (Leader 1)',
            '  - leader2@example.com (Leader 2)',
            '  - leader3@example.com (Leader 3)',
            '  - user@example.com (User)',
        ]);

        return Command::SUCCESS;
    }

    private function createUser(
        string $email,
        string $firstName,
        string $lastName,
        string $nickname,
        DepartmentRole $role,
        Department $department,
        bool $isPrimary,
        ?User $createdBy = null
    ): User {
        // Prüfe ob User bereits existiert
        $existingProfile = $this->em->getRepository(Profile::class)->findOneBy(['email' => $email]);
        if ($existingProfile) {
            $existingUser = $this->em->getRepository(User::class)->findOneBy(['profileId' => $existingProfile->getId()]);
            if ($existingUser) {
                // Profile-Rollen aktualisieren (für sa/org/sub)
                $existingProfile->setRoles($this->getProfileRolesForRole($role));
                $this->updateMembership($existingUser, $department, $role, $isPrimary);
                return $existingUser;
            }
        }

        // Profile erstellen
        $profile = new Profile();
        $profile->setId(IdGenerator::generateUnique($this->em, Profile::class));
        $profile->setEmail($email);
        $profile->setFirstName($firstName);
        $profile->setLastName($lastName);
        $profile->setNickname($nickname);
        // Globale Admin-Rollen (sa/org/sub) kommen aus profile.roles
        $profile->setRoles($this->getProfileRolesForRole($role));
        $this->em->persist($profile);

        // User erstellen
        $user = new User();
        $user->setId(IdGenerator::generateUnique($this->em, User::class));
        $user->setProfileId($profile->getId());
        $user->setProfile($profile);
        $user->setState('active');
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'password');
        $user->setPassword($hashedPassword);
        $user->setEmailVerified(true);

        if ($createdBy) {
            $user->setCreatedBy($createdBy);
        }
        
        $this->em->persist($user);

        // Membership-Zuordnung erstellen (sa/org/sub werden als mw gespeichert)
        $this->createMembership($user, $department, $role, $isPrimary);

        return $user;
    }

    /**
     * Globale Admin-Rollen (sa/org/sub) in profile.roles, sonst ROLE_USER
     */
    private function getProfileRolesForRole(DepartmentRole $role): array
    {
        return match ($role) {
            DepartmentRole::SUPERADMIN => ['ROLE_USER', 'ROLE_SUPERADMIN', 'ROLE_WEBADMIN'],
            DepartmentRole::ORGANISATIONSCHEF => ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'],
            DepartmentRole::SUBORGCHEF => ['ROLE_USER', 'ROLE_SUBORGCHEF'],
            default => ['ROLE_USER'],
        };
    }

    /**
     * Membership-Rolle: nur mw, dc, l1, l2, l3, u. sa/org/sub werden als mw gespeichert.
     */
    private function getMembershipRole(DepartmentRole $role): string
    {
        return match ($role) {
            DepartmentRole::SUPERADMIN, DepartmentRole::ORGANISATIONSCHEF, DepartmentRole::SUBORGCHEF => 'mw',
            default => $role->value,
        };
    }

    private function createMembership(
        User $user,
        Department $department,
        DepartmentRole $role,
        bool $isPrimary
    ): void {
        // Prüfe ob bereits zugeordnet
        $existing = $this->em->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $department->getId()
        ]);
        if ($existing) {
            $existing->setRole($this->getMembershipRole($role));
            $existing->setIsPrimary($isPrimary);
            return;
        }

        $membership = new Membership();
        $membership->setUser($user);
        $membership->setDepartment($department);
        $membership->setRole($this->getMembershipRole($role));
        $membership->setIsPrimary($isPrimary);
        $this->em->persist($membership);
    }

    private function updateMembership(
        User $user,
        Department $department,
        DepartmentRole $role,
        bool $isPrimary
    ): void {
        $existing = $this->em->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $department->getId()
        ]);
        if ($existing) {
            $existing->setRole($this->getMembershipRole($role));
            $existing->setIsPrimary($isPrimary);
        } else {
            $this->createMembership($user, $department, $role, $isPrimary);
        }
    }
}
