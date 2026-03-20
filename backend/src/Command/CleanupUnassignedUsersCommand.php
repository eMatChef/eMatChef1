<?php

namespace App\Command;

use App\Service\UnassignedUserCleanupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-unassigned-users',
    description: 'Loescht unzugeordnete Benutzer ohne Department nach einer Frist'
)]
class CleanupUnassignedUsersCommand extends Command
{
    public function __construct(private UnassignedUserCleanupService $cleanupService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                null,
                InputOption::VALUE_REQUIRED,
                'Frist in Tagen, ab wann unzugeordnete User geloescht werden',
                21
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Nur anzeigen, welche User geloescht wuerden'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($days < 1) {
            $io->error('Die Option --days muss mindestens 1 sein.');
            return Command::INVALID;
        }

        $preview = $this->cleanupService->preview($days);
        $users = $preview['items'];

        if (empty($users)) {
            $io->success("Keine unzugeordneten User gefunden (aelter als {$days} Tage).");
            return Command::SUCCESS;
        }

        $io->section(sprintf(
            '%d unzugeordnete User gefunden (aelter als %d Tage)',
            $preview['count'],
            $preview['days']
        ));

        $rows = [];
        foreach ($users as $user) {
            $rows[] = [
                $user['user_id'],
                $user['email'] ?? '(kein Profil)',
                (new \DateTimeImmutable($user['created_at']))->format('Y-m-d H:i:s'),
            ];
        }
        $io->table(['User ID', 'E-Mail', 'Erstellt am'], $rows);

        if ($dryRun) {
            $io->warning('Dry-Run aktiv: Es wurden keine Daten geloescht.');
            return Command::SUCCESS;
        }

        $result = $this->cleanupService->cleanup($days);

        $io->success(sprintf(
            'Cleanup abgeschlossen: %d User und %d Profile geloescht.',
            $result['deleted_users'],
            $result['deleted_profiles']
        ));

        return Command::SUCCESS;
    }
}
