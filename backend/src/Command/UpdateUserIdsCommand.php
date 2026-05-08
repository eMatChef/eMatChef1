<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Profile;
use App\Util\IdGenerator;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:update-user-ids',
    description: 'Aktualisiert bestehende User-IDs auf neue hexadezimale IDs'
)]
class UpdateUserIdsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private Connection $connection
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Aktualisiere User-IDs auf hexadezimale IDs...');
        $output->writeln('');

        // Finde alle User mit alten IDs (nicht hex-Format)
        $users = $this->em->getRepository(User::class)->findAll();
        
        $updated = 0;
        foreach ($users as $user) {
            $oldUserId = $user->getId();
            $oldProfileId = $user->getProfileId();
            
            // Prüfe ob ID bereits hex-Format hat (12-stellig mit a-f Zeichen)
            $hasHexChars = preg_match('/[a-f]/', $oldUserId) || preg_match('/[a-f]/', $oldProfileId);
            $isValidHex = preg_match('/^[0-9a-f]{12}$/', $oldUserId) && preg_match('/^[0-9a-f]{12}$/', $oldProfileId);
            
            if ($isValidHex && $hasHexChars) {
                $output->writeln("  ⏭️  User {$oldUserId} hat bereits hex-ID, überspringe");
                continue;
            }
            
            // Generiere neue IDs
            $newProfileId = IdGenerator::generate();
            $newUserId = IdGenerator::generate();
            
            // Stelle sicher, dass die IDs eindeutig sind
            while ($this->em->getRepository(Profile::class)->find($newProfileId)) {
                $newProfileId = IdGenerator::generate();
            }
            while ($this->em->getRepository(User::class)->find($newUserId)) {
                $newUserId = IdGenerator::generate();
            }
            
            $profile = $user->getProfile();
            $email = $profile ? $profile->getEmail() : 'N/A';
            
            // Verwende SQL direkt, um Foreign Key Constraints zu umgehen
            $this->connection->beginTransaction();
            try {
                // 1. Temporär Foreign Key Constraint deaktivieren
                $this->connection->executeStatement('SET CONSTRAINTS ALL DEFERRED');
                
                // 2. Profile ID aktualisieren
                $this->connection->executeStatement(
                    'UPDATE profile SET id = ? WHERE id = ?',
                    [$newProfileId, $oldProfileId]
                );
                
                // 3. User ID und Profile ID Referenz aktualisieren
                $this->connection->executeStatement(
                    'UPDATE "user" SET id = ?, profile_id = ? WHERE id = ?',
                    [$newUserId, $newProfileId, $oldUserId]
                );
                
                $this->connection->commit();
                
                // Entity Manager Cache leeren
                $this->em->clear();
                
                $output->writeln("  ✅ User aktualisiert:");
                $output->writeln("     Alte User-ID: {$oldUserId} → Neue User-ID: {$newUserId}");
                $output->writeln("     Alte Profile-ID: {$oldProfileId} → Neue Profile-ID: {$newProfileId}");
                $output->writeln("     Email: {$email}");
                $output->writeln('');
                
                $updated++;
            } catch (\Exception $e) {
                $this->connection->rollBack();
                $output->writeln("  ❌ Fehler beim Aktualisieren von User {$oldUserId}: " . $e->getMessage());
            }
        }
        
        if ($updated > 0) {
            $output->writeln("✅ {$updated} User erfolgreich aktualisiert!");
        } else {
            $output->writeln("ℹ️  Keine User zum Aktualisieren gefunden (alle haben bereits hex-IDs)");
        }

        return Command::SUCCESS;
    }
}
