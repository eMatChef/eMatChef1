<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Media\MediaRetentionService;
use App\Service\Media\MediaSettingsStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:media:retention',
    description: 'Löscht Werkstatt-Fotos abgeschlossener Tickets nach Retention-Frist',
)]
class MediaRetentionCommand extends Command
{
    public function __construct(
        private MediaRetentionService $retentionService,
        private MediaSettingsStore $settingsStore,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'years',
                null,
                InputOption::VALUE_REQUIRED,
                'Retention in Jahren (Default aus media_settings.json oder ' . MediaSettingsStore::RETENTION_YEARS_DEFAULT . ')',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Nur betroffene Tickets anzeigen, nichts löschen',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $yearsOpt = $input->getOption('years');
        $years = $yearsOpt !== null ? (int) $yearsOpt : null;

        if ($years !== null && ($years < MediaSettingsStore::RETENTION_YEARS_MIN || $years > MediaSettingsStore::RETENTION_YEARS_MAX)) {
            $io->error(sprintf(
                'Option --years muss zwischen %d und %d liegen.',
                MediaSettingsStore::RETENTION_YEARS_MIN,
                MediaSettingsStore::RETENTION_YEARS_MAX,
            ));

            return Command::INVALID;
        }

        $effectiveYears = $years ?? $this->settingsStore->getRetentionYears();
        $io->title(sprintf('Medien-Retention (Werkstatt-Fotos, %d Jahre%s)', $effectiveYears, $dryRun ? ', Dry-run' : ''));

        $result = $this->retentionService->run($years, $dryRun);

        if ($result->ticketsMatched === 0) {
            $io->success('Keine abgeschlossenen Tickets mit Fotos ausserhalb der Retention-Frist gefunden.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($result->items as $item) {
            $rows[] = [
                $item['ticket_id'],
                $item['department_id'],
                mb_substr((string) $item['title'], 0, 40),
                $item['completed_at'] ?? '—',
                $item['photo_count'],
                $item['files'],
                round(((int) $item['bytes']) / 1024 / 1024, 2),
            ];
        }
        $io->table(
            ['Ticket', 'Department', 'Titel', 'Abgeschlossen', 'Fotos (DB)', 'Dateien', 'MB'],
            $rows,
        );

        if ($dryRun) {
            $io->warning(sprintf(
                'Dry-run: %d Ticket(s), %d Datei(en), %.2f MB würden gelöscht.',
                $result->ticketsMatched,
                array_sum(array_column($result->items, 'files')),
                array_sum(array_column($result->items, 'bytes')) / 1024 / 1024,
            ));

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Retention abgeschlossen: %d Ticket(s), %d Issue-Report(s), %d Datei(en), %.2f MB freigegeben.',
            $result->ticketsProcessed,
            $result->issueReportsProcessed,
            $result->filesDeleted,
            $result->megabytesFreed(),
        ));

        return Command::SUCCESS;
    }
}
