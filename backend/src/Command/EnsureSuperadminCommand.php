<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Bootstrap\SuperadminBootstrapService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ensure-superadmin',
    description: 'Legt Standard-Organisation/Department an (falls nötig) und stellt einen Superadmin sicher'
)]
final class EnsureSuperadminCommand extends Command
{
    public function __construct(
        private SuperadminBootstrapService $superadminBootstrap,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-Mail des Superadmins')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Passwort (nicht-interaktiv); sonst wird verdeckt gefragt');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = trim((string) $input->getArgument('email'));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Ungültige E-Mail-Adresse.');

            return Command::FAILURE;
        }

        $password = $input->getOption('password');
        if ($password !== null && $password !== '') {
            $password = (string) $password;
        } elseif ($input->isInteractive()) {
            $question = new Question('Superadmin-Passwort');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = (string) $this->getHelper('question')->ask($input, $output, $question);
        } else {
            $io->error('Nicht-interaktiv: bitte --password=... angeben (Passwort erscheint in der Prozessliste – nur einmalig verwenden).');

            return Command::FAILURE;
        }

        if ($password === '') {
            $io->error('Passwort darf nicht leer sein.');

            return Command::FAILURE;
        }

        $user = $this->superadminBootstrap->ensure($email, $password);
        $io->success(sprintf('Superadmin gesichert (User-ID: %s, E-Mail: %s).', $user->getId(), $email));
        $io->note('Passwort nach dem ersten Login in der App ändern; CLI-Option --password in Shell-Historie vermeiden.');

        return Command::SUCCESS;
    }
}
