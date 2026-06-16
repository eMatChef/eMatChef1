<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\JsPdfCatalogSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:js-catalog-sync-pdf',
    description: 'J+S-Katalog dept_js00000 an PDF-Formularzeilen anpassen (Namen, Duplikate entfernen)',
)]
final class SyncJsPdfCatalogCommand extends Command
{
    public function __construct(
        private JsPdfCatalogSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur anzeigen, nichts speichern');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Dry-run — keine Änderungen in der Datenbank');
        }

        $stats = $this->syncService->sync($dryRun);

        $io->success(sprintf(
            'J+S-Katalog sync: %d umbenannt, %d Bestellpositionen umgebucht, %d Varianten archiviert, %d übersprungen',
            $stats['renamed'],
            $stats['remapped'],
            $stats['retired'],
            $stats['skipped'],
        ));

        $io->text('Manifest: backend/data/js-order/pdf_catalog_manifest.json');

        return Command::SUCCESS;
    }
}
