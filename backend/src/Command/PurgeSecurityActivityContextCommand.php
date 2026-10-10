<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Auth\SecurityActivityService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:security-activity:purge-context',
    description: 'Leert IP und User-Agent von Sicherheitsereignissen nach der Aufbewahrungsfrist (Audit-Zeilen bleiben erhalten)'
)]
final class PurgeSecurityActivityContextCommand extends Command
{
    public function __construct(private readonly SecurityActivityService $activity)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur ankündigen, nichts ändern');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ((bool) $input->getOption('dry-run')) {
            $io->note(sprintf('Dry-run: bei %d Sicherheitsereignis(sen) älter als %d Tage würden IP/User-Agent geleert (Ereignisse bleiben).', $this->activity->countExpiredContext(), SecurityActivityService::CONTEXT_RETENTION_DAYS));

            return Command::SUCCESS;
        }

        $count = $this->activity->purgeExpiredContext();
        $io->success(sprintf('%d Sicherheitsereignis(se) bereinigt (älter als %d Tage).', $count, SecurityActivityService::CONTEXT_RETENTION_DAYS));

        return Command::SUCCESS;
    }
}
