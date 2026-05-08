<?php

namespace App\Command;

use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:test-login',
    description: 'Testet Login-Funktionalität'
)]
class TestLoginCommand extends Command
{
    public function __construct(
        private ProfileRepository $profileRepository,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Teste Login-Funktionalität...');
        $output->writeln('');

        // Test 1: Profile finden
        $email = 'admin@example.com';
        $output->writeln("1. Suche Profile mit Email: {$email}");
        $profile = $this->profileRepository->findOneBy(['email' => $email]);
        
        if (!$profile) {
            $output->writeln("   ❌ Profile nicht gefunden!");
            return Command::FAILURE;
        }
        
        $output->writeln("   ✅ Profile gefunden: ID = {$profile->getId()}");
        $output->writeln('');

        // Test 2: User finden
        $output->writeln("2. Suche User mit Profile-ID: {$profile->getId()}");
        $user = $this->userRepository->findOneBy(['profileId' => $profile->getId()]);
        
        if (!$user) {
            $output->writeln("   ❌ User nicht gefunden!");
            return Command::FAILURE;
        }
        
        $output->writeln("   ✅ User gefunden: ID = {$user->getId()}");
        $output->writeln('');

        // Test 3: Passwort prüfen
        $output->writeln("3. Prüfe Passwort 'password'");
        $isValid = $this->passwordHasher->isPasswordValid($user, 'password');
        
        if ($isValid) {
            $output->writeln("   ✅ Passwort ist gültig!");
        } else {
            $output->writeln("   ❌ Passwort ist ungültig!");
            return Command::FAILURE;
        }
        $output->writeln('');

        $output->writeln('✅ Alle Tests erfolgreich!');
        $output->writeln('');
        $output->writeln('Login sollte funktionieren mit:');
        $output->writeln("  Email: {$email}");
        $output->writeln("  Password: password");

        return Command::SUCCESS;
    }
}
