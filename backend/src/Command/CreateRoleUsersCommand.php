<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Profile;
use App\Entity\Department;
use App\Entity\Membership;
use App\Enum\DepartmentRole;
use App\Service\Bootstrap\DevBootstrapContextService;
use App\Service\Bootstrap\DemoGrossanlassSeedService;
use App\Service\Bootstrap\DemoSupplierSeedService;
use App\Util\E2eSmokeUser;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-role-users',
    description: 'Erstellt für jede Rolle einen Benutzer (löscht alte Test-User)'
)]
class CreateRoleUsersCommand extends Command
{
    /** Seed-/Banner-Passwort für alle Rollen-User (@ematchef.ch) */
    public const DEMO_PASSWORD = 'test!ematchef';

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private DevBootstrapContextService $bootstrapContext,
        private DemoSupplierSeedService $demoSupplierSeed,
        private DemoGrossanlassSeedService $demoGrossanlassSeed,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'skip-delete',
            null,
            InputOption::VALUE_NONE,
            'Bestehende @ematchef.ch-User nicht löschen (nur anlegen/aktualisieren)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Erstelle Benutzer für alle Rollen');

        // Hole oder erstelle sichtbare Organisation und Department (kein GLOBALORG001 mehr)
        [$organisation, $department] = $this->bootstrapContext->findOrCreateOrganisationAndDepartment();

        if ($input->getOption('skip-delete')) {
            $io->note('Überspringe Löschen bestehender Test-User (--skip-delete).');
        } else {
        // Lösche alle bestehenden Test-User (außer dem ersten Superadmin, falls er existiert)
        $io->section('Lösche alte Test-User...');
        $allUsers = $this->em->getRepository(User::class)->findAll();
        $deletedCount = 0;
        foreach ($allUsers as $user) {
            $profile = $user->getProfile();
            if (!$profile || !str_ends_with($profile->getEmail(), '@ematchef.ch')) {
                continue;
            }
            // E2E-Smoke bleibt erhalten (wird von app:ensure-e2e-user / app:dev-demo:reset gepflegt)
            if (E2eSmokeUser::isExcluded($profile->getEmail())) {
                continue;
            }
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
        $this->em->flush();
        $io->success("$deletedCount alte Test-User gelöscht");
        }

        // Erstelle einen Superadmin als Erstes (wird als createdBy verwendet)
        $io->section('Erstelle Superadmin...');
        $superadminUser = $this->createUser(
            'superadmin@ematchef.ch',
            'Superadmin',
            'User',
            'Superadmin',
            DepartmentRole::SUPERADMIN,
            $department,
            true
        );
        $this->em->flush();
        $io->success('Superadmin erstellt: superadmin@ematchef.ch / ' . self::DEMO_PASSWORD);

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
            $email = $fullName . '@ematchef.ch';
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
            $io->text("✓ {$role->getLabel()}: $email / " . self::DEMO_PASSWORD);
        }

        $this->em->flush();
        $io->success('Alle Rollen-Benutzer erfolgreich erstellt!');

        $io->section('Grossanlass-Rollen (Demo Grossanlass)…');
        $gaDepartment = $this->demoGrossanlassSeed->ensureDepartment($organisation, $superadminUser);
        $gaSpecs = [
            ['email' => 'ga-mw@ematchef.ch', 'first' => 'GA', 'last' => 'Materialchef', 'nick' => 'GA-MW', 'role' => DepartmentRole::MATWART],
            ['email' => 'ga-cmw@ematchef.ch', 'first' => 'GA', 'last' => 'Co-Materialchef', 'nick' => 'GA-CMW', 'role' => DepartmentRole::CO_MATWART],
            ['email' => 'ga-ok@ematchef.ch', 'first' => 'GA', 'last' => 'OK-Leitung', 'nick' => 'GA-OK', 'role' => DepartmentRole::DEPCHEF],
            ['email' => 'ga-komm@ematchef.ch', 'first' => 'GA', 'last' => 'Kommunikation', 'nick' => 'GA-Komm', 'role' => DepartmentRole::KOMMUNIKATION],
            ['email' => 'ga-spon@ematchef.ch', 'first' => 'GA', 'last' => 'Sponsoring', 'nick' => 'GA-Spon', 'role' => DepartmentRole::SPONSORING],
            ['email' => 'ga-bereich@ematchef.ch', 'first' => 'GA', 'last' => 'Bereichsleitung', 'nick' => 'GA-BL', 'role' => DepartmentRole::USER],
            ['email' => 'ga-helfer@ematchef.ch', 'first' => 'GA', 'last' => 'Helfer', 'nick' => 'GA-Helfer', 'role' => DepartmentRole::USER],
        ];
        $gaUsers = [];
        foreach ($gaSpecs as $spec) {
            $gaUsers[$spec['email']] = $this->createUser(
                $spec['email'],
                $spec['first'],
                $spec['last'],
                $spec['nick'],
                $spec['role'],
                $gaDepartment,
                true,
                $superadminUser
            );
            $io->text("✓ {$spec['nick']}: {$spec['email']} / " . self::DEMO_PASSWORD);
        }
        $this->em->flush();
        $this->demoGrossanlassSeed->ensureDemoRessort(
            $gaDepartment,
            $gaUsers['ga-bereich@ematchef.ch'],
            $gaUsers['ga-helfer@ematchef.ch'],
        );
        $io->success('Grossanlass-Demo: ' . DemoGrossanlassSeedService::DEPARTMENT_NAME . ' / ' . DemoGrossanlassSeedService::RESSORT_NAME);

        $io->section('Erstelle Demo-Lieferant...');
        $this->demoSupplierSeed->ensure($superadminUser);
        $io->success('Demo-Lieferant: ' . DemoSupplierSeedService::EMAIL . ' / ' . self::DEMO_PASSWORD);

        $io->note([
            'Alle Benutzer haben das Passwort: ' . self::DEMO_PASSWORD,
            'Login-Emails:',
            '  - superadmin@ematchef.ch (Superadmin)',
            '  - organisationschef@ematchef.ch (Organisationschef)',
            '  - suborgchef@ematchef.ch (Suborgchef)',
            '  - matwart@ematchef.ch (Materialchef)',
            '  - depchef@ematchef.ch (Departmentchef)',
            '  - leader1@ematchef.ch (Leader 1)',
            '  - leader2@ematchef.ch (Leader 2)',
            '  - leader3@ematchef.ch (Leader 3)',
            '  - user@ematchef.ch (User)',
            '  - ga-mw@ematchef.ch (GA Materialchef)',
            '  - ga-cmw@ematchef.ch (GA Co-Materialchef)',
            '  - ga-ok@ematchef.ch (GA OK-Leitung)',
            '  - ga-komm@ematchef.ch (GA Kommunikation)',
            '  - ga-spon@ematchef.ch (GA Sponsoring)',
            '  - ga-bereich@ematchef.ch (GA Bereichsleitung, Leader am Demo-Ressort)',
            '  - ga-helfer@ematchef.ch (GA Helfer, Mitglied am Demo-Ressort)',
            '  - supplier@ematchef.ch (Lieferant / Testfirma, ohne Department)',
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
                $existingUser->setPassword($this->passwordHasher->hashPassword($existingUser, self::DEMO_PASSWORD));
                $existingUser->setState('active');
                $existingUser->setEmailVerified(true);
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
        $hashedPassword = $this->passwordHasher->hashPassword($user, self::DEMO_PASSWORD);
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
