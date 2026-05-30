<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Media\MediaCompressionLegacyService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:media:compress-legacy',
    description: 'Komprimiert Werkstatt-Fotos ohne bytes-Metadaten in der DB',
)]
class MediaCompressLegacyCommand extends Command
{
    public function __construct(
        private MediaCompressionLegacyService $compressionLegacyService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Nur zählen, keine Dateien ändern',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Legacy-Foto-Kompression (Werkstatt-Tickets)' . ($dryRun ? ' — Dry-run' : ''));

        $stats = $this->compressionLegacyService->run($dryRun);

        if ($stats['photos'] === 0) {
            $io->success('Keine Legacy-Fotos ohne bytes-Metadaten gefunden.');

            return Command::SUCCESS;
        }

        $savedMb = max(0, $stats['bytes_before'] - $stats['bytes_after']) / 1024 / 1024;

        if ($dryRun) {
            $io->warning(sprintf(
                'Dry-run: %d Foto(s) ohne bytes-Metadaten in %d Ticket(s) würden komprimiert.',
                $stats['photos'],
                $stats['compressed'],
            ));

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Kompression abgeschlossen: %d Ticket(s), %d Foto(s) komprimiert, %d übersprungen, %.2f MB eingespart.',
            $stats['entities'],
            $stats['compressed'],
            $stats['skipped'],
            $savedMb,
        ));

        return Command::SUCCESS;
    }
}
