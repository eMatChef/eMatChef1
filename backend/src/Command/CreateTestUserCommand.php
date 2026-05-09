<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Profile;
use App\Entity\Department;
use App\Entity\Membership;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-test-user',
    description: 'Erstellt Test-User für Entwicklung'
)]
class CreateTestUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Hole Standard-Department (muss existieren!)
        $department = $this->em->getRepository(Department::class)->findOneBy([]);
        if (!$department) {
            $output->writeln('<error>Kein Department gefunden! Bitte zuerst app:create-initial-data ausführen.</error>');
            return Command::FAILURE;
        }

        // Test-User: admin@ematchef.ch
        $adminUser = $this->createUser(
            IdGenerator::generateUnique($this->em, User::class),
            IdGenerator::generateUnique($this->em, Profile::class),
            'admin@ematchef.ch',
            'test',
            'Admin',
            'User',
            'Admin',
            'admin'
        );
        $this->assignToDepartment($adminUser, $department, 'admin', true);

        // Test-User: manager@ematchef.ch
        $managerUser = $this->createUser(
            IdGenerator::generateUnique($this->em, User::class),
            IdGenerator::generateUnique($this->em, Profile::class),
            'manager@ematchef.ch',
            'test',
            'Manager',
            'User',
            'Manager',
            'manager'
        );
        $this->assignToDepartment($managerUser, $department, 'manager', false);

        // Test-User: user@ematchef.ch
        $normalUser = $this->createUser(
            IdGenerator::generateUnique($this->em, User::class),
            IdGenerator::generateUnique($this->em, Profile::class),
            'user@ematchef.ch',
            'test',
            'Test',
            'User',
            'User',
            'user'
        );
        $this->assignToDepartment($normalUser, $department, 'user', false);

        $this->em->flush();

        $output->writeln('Test-User erstellt:');
        $output->writeln('  - admin@ematchef.ch / test (Admin, Primary)');
        $output->writeln('  - manager@ematchef.ch / test (Manager)');
        $output->writeln('  - user@ematchef.ch / test (User)');
        $output->writeln('Alle User sind dem Department "' . $department->getName() . '" zugeordnet.');

        return Command::SUCCESS;
    }

    private function createUser(
        string $userId,
        string $profileId,
        string $email,
        string $password,
        string $firstName,
        string $lastName,
        string $nickname,
        string $role
    ): User {
        // Prüfe ob User bereits existiert (über Email)
        $existingProfile = $this->em->getRepository(Profile::class)->findOneBy(['email' => $email]);
        if ($existingProfile) {
            $existingUser = $this->em->getRepository(User::class)->findOneBy(['profileId' => $existingProfile->getId()]);
            if ($existingUser) {
                return $existingUser;
            }
        }

        // Profile erstellen
        $profile = new Profile();
        $profile->setId($profileId);
        $profile->setEmail($email);
        $profile->setFirstName($firstName);
        $profile->setLastName($lastName);
        $profile->setNickname($nickname);
        $profile->setRoles([$role === 'admin' ? 'ROLE_ADMIN' : ($role === 'manager' ? 'ROLE_MANAGER' : 'ROLE_USER')]);
        $this->em->persist($profile);

        // User erstellen
        $user = new User();
        $user->setId($userId);
        $user->setProfileId($profileId);
        $user->setProfile($profile);
        $user->setState('active');
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        $user->setEmailVerified(true);
        // Erster User hat kein createdBy (kann null sein)
        $this->em->persist($user);

        return $user;
    }

    private function assignToDepartment(User $user, Department $department, string $role, bool $isPrimary): void
    {
        // Prüfe ob bereits zugeordnet
        $existing = $this->em->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $department->getId()
        ]);
        if ($existing) {
            return;
        }

        $membership = new Membership();
        $membership->setUser($user);
        $membership->setDepartment($department);
        $membership->setRole($role);
        $membership->setIsPrimary($isPrimary);
        $this->em->persist($membership);
    }
}
