<?php

namespace App\Command;

use App\Entity\Membership;
use App\Entity\User;
use App\Service\Demo\DemoEnvironmentGuard;
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
    description: 'Veraltet: Legacy-Testkonten neu anlegen. Gesperrt ohne Freigabe; Ersatz: app:create-role-users'
)]
class RecreateTestUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private DemoEnvironmentGuard $environmentGuard,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $denial = $this->environmentGuard->destructiveDenial();
        if ($denial !== null) {
            $output->writeln('<error>' . $denial . '</error>');

            return Command::FAILURE;
        }

        // Vorabprüfung: Konten mit Mitgliedschaften sind nicht eindeutig Seed-eigen → nichts ändern.
        foreach (['admin@ematchef.ch', 'manager@ematchef.ch', 'user@ematchef.ch'] as $email) {
            $existing = $this->em->getRepository(Profile::class)->findOneBy(['email' => $email]);
            $existingUser = $existing ? $this->em->getRepository(User::class)->findOneBy(['profileId' => $existing->getId()]) : null;
            if ($existingUser && $this->em->getRepository(Membership::class)->findOneBy(['userId' => $existingUser->getId()])) {
                $output->writeln(sprintf('<error>%s hat Mitgliedschaften (echtes Konto?). Abbruch ohne Änderung.</error>', $email));

                return Command::FAILURE;
            }
        }

        $output->writeln('Lösche alte Test-User und erstelle sie neu...');
        $output->writeln('');

        // Test-User Emails (Domain wie create-role-users / Banner-Demo)
        $testUsers = [
            ['email' => 'admin@ematchef.ch', 'password' => 'test!ematchef', 'firstName' => 'Admin', 'lastName' => 'User', 'nickname' => 'Admin'],
            ['email' => 'manager@ematchef.ch', 'password' => 'test!ematchef', 'firstName' => 'Manager', 'lastName' => 'User', 'nickname' => 'Manager'],
            ['email' => 'user@ematchef.ch', 'password' => 'test!ematchef', 'firstName' => 'Test', 'lastName' => 'User', 'nickname' => 'User'],
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
            $newUser->setEmailVerified(true);
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
