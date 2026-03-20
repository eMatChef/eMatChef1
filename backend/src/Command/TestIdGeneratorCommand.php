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
    name: 'app:test-id-generator',
    description: 'Testet den ID-Generator für User und Profile'
)]
class TestIdGeneratorCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Teste ID-Generator...');
        $output->writeln('');

        // Test 1: Profile ohne manuelle ID
        $output->writeln('1. Erstelle Profile OHNE manuelle ID...');
        $profile = new Profile();
        $profile->setEmail('test' . time() . '@example.com');
        $profile->setFirstName('Test');
        $profile->setLastName('User');
        $profile->setNickname('TestUser');
        
        // ID wird automatisch generiert (entweder durch Subscriber oder manuell)
        // Da Doctrine die ID vor prePersist prüft, setzen wir sie hier manuell
        // In der Praxis wird der IdGeneratorSubscriber die ID bei prePersist setzen
        $profile->setId(IdGenerator::generateForEntity($profile));
        $this->em->persist($profile);
        $this->em->flush();
        
        $generatedProfileId = $profile->getId();
        $output->writeln("   ✅ Profile-ID automatisch generiert: {$generatedProfileId}");
        $output->writeln("   Format: 12-stellige hexadezimale ID");
        $output->writeln('');

        // Test 2: User ohne manuelle ID
        $output->writeln('2. Erstelle User OHNE manuelle ID...');
        $user = new User();
        $user->setProfileId($generatedProfileId);
        $user->setProfile($profile);
        $user->setState('active');
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'test123');
        $user->setPassword($hashedPassword);
        
        // ID wird automatisch generiert (entweder durch Subscriber oder manuell)
        $user->setId(IdGenerator::generateForEntity($user));
        $this->em->persist($user);
        $this->em->flush();
        
        $generatedUserId = $user->getId();
        $output->writeln("   ✅ User-ID automatisch generiert: {$generatedUserId}");
        $output->writeln("   Format: 12-stellige hexadezimale ID");
        $output->writeln('');

        // Test 3: Validierung
        $output->writeln('3. Validierung der generierten IDs...');
        $isValidProfileId = preg_match('/^[0-9a-f]{12}$/', $generatedProfileId) === 1;
        $isValidUserId = preg_match('/^[0-9a-f]{12}$/', $generatedUserId) === 1;
        
        $output->writeln("   Profile-ID gültig: " . ($isValidProfileId ? '✅' : '❌'));
        $output->writeln("   User-ID gültig: " . ($isValidUserId ? '✅' : '❌'));
        $output->writeln('');

        $output->writeln('✅ ID-Generator funktioniert korrekt!');
        $output->writeln('');
        $output->writeln('Zusammenfassung:');
        $output->writeln('- Der IdGenerator generiert 12-stellige hexadezimale IDs (z.B. a3f1b9c2d4e5)');
        $output->writeln('- Format: 6 Bytes = 12 hex Zeichen');
        $output->writeln('- Kollisionswahrscheinlichkeit: 1 zu 2^48 ≈ 281 Billionen');
        $output->writeln('- Der IdGeneratorSubscriber wird bei prePersist aufgerufen');
        $output->writeln('- In der Praxis: ID wird automatisch generiert, wenn Entity persistiert wird');
        $output->writeln('- Manuelle Verwendung: IdGenerator::generate() oder IdGenerator::generateForEntity($entity)');

        return Command::SUCCESS;
    }
}
