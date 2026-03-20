<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Profile;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:recreate-test-users',
    description: 'Löscht alte Test-User und erstellt sie neu mit hexadezimalen IDs'
)]
class RecreateTestUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Lösche alte Test-User und erstelle sie neu...');
        $output->writeln('');

        // Test-User Emails
        $testUsers = [
            ['email' => 'admin@example.com', 'password' => 'password', 'firstName' => 'Admin', 'lastName' => 'User', 'nickname' => 'Admin'],
            ['email' => 'manager@example.com', 'password' => 'password', 'firstName' => 'Manager', 'lastName' => 'User', 'nickname' => 'Manager'],
            ['email' => 'user@example.com', 'password' => 'password', 'firstName' => 'Test', 'lastName' => 'User', 'nickname' => 'User'],
        ];

        $deleted = 0;
        $created = 0;

        foreach ($testUsers as $testUser) {
            // Finde bestehende User/Profile
            $profile = $this->em->getRepository(Profile::class)->findOneBy(['email' => $testUser['email']]);
            
            if ($profile) {
                $user = $this->em->getRepository(User::class)->findOneBy(['profileId' => $profile->getId()]);
                
                if ($user) {
                    // Lösche User und Profile
                    $this->em->remove($user);
                    $this->em->remove($profile);
                    $this->em->flush();
                    $deleted++;
                    $output->writeln("  🗑️  Gelöscht: {$testUser['email']}");
                }
            }

            // Erstelle neuen User mit automatisch generierten IDs
            $newProfile = new Profile();
            // ID wird automatisch generiert!
            $newProfile->setEmail($testUser['email']);
            $newProfile->setFirstName($testUser['firstName']);
            $newProfile->setLastName($testUser['lastName']);
            $newProfile->setNickname($testUser['nickname']);
            $newProfile->setId(IdGenerator::generateForEntity($newProfile));
            $this->em->persist($newProfile);
            $this->em->flush(); // Profile muss zuerst gespeichert werden

            $newUser = new User();
            // ID wird automatisch generiert!
            $newUser->setProfileId($newProfile->getId());
            $newUser->setProfile($newProfile);
            $newUser->setState('active');
            $hashedPassword = $this->passwordHasher->hashPassword($newUser, $testUser['password']);
            $newUser->setPassword($hashedPassword);
            $newUser->setId(IdGenerator::generateForEntity($newUser));
            $this->em->persist($newUser);
            $this->em->flush();

            $created++;
            $output->writeln("  ✅ Erstellt: {$testUser['email']}");
            $output->writeln("     User-ID: {$newUser->getId()}");
            $output->writeln("     Profile-ID: {$newProfile->getId()}");
            $output->writeln('');
        }

        $output->writeln("✅ {$deleted} alte User gelöscht, {$created} neue User erstellt!");
        $output->writeln('');
        $output->writeln('Test-Zugänge:');
        foreach ($testUsers as $testUser) {
            $output->writeln("  - {$testUser['email']} / {$testUser['password']}");
        }

        return Command::SUCCESS;
    }
}
