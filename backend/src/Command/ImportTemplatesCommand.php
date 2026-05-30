<?php

namespace App\Command;

use App\Entity\Department;
use App\Service\TemplateImportExportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:templates:import',
    description: 'Importiert Zelt-Vorlagen aus JSON-Dateien (v4/v5-Format)'
)]
class ImportTemplatesCommand extends Command
{
    public function __construct(
        private TemplateImportExportService $importExportService,
        private \Doctrine\ORM\EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('department_id', InputArgument::OPTIONAL, 'Department-ID (leer = zentrale/globale Vorlagen)')
            ->addOption('file', 'f', InputOption::VALUE_OPTIONAL, 'Einzelne JSON-Datei importieren (z.B. data/templates/hajk.json)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Alle JSON-Dateien aus data/templates/ importieren')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Bestehende Vorlagen überschreiben (duplicate_action=update)')
            ->addOption('global', 'g', InputOption::VALUE_NONE, 'Als zentrale Vorlagen importieren (department_id=NULL)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur Vorschau, keine DB-Änderungen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $departmentId = $input->getArgument('department_id');
        $isGlobal = $input->getOption('global') || !$departmentId;
        $scope = $isGlobal ? 'global' : 'department';

        if ($isGlobal) {
            $io->title('Zelt-Vorlagen Import (ZENTRAL)');
            $io->text('Scope: Globale Vorlagen (sichtbar für alle Departments)');
        } else {
            $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
            if (!$department) {
                $io->error('Department nicht gefunden: ' . $departmentId);

                return Command::FAILURE;
            }
            $io->title('Zelt-Vorlagen Import (Department)');
            $io->text('Department: ' . $department->getName() . ' (' . $department->getId() . ')');
        }

        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');
        $files = [];

        if ($input->getOption('all')) {
            $dir = dirname(__DIR__, 2) . '/data/templates';
            if (!is_dir($dir)) {
                $io->error('Template-Verzeichnis nicht gefunden: ' . $dir);

                return Command::FAILURE;
            }
            foreach (glob($dir . '/*.json') as $file) {
                $files[] = $file;
            }
        } elseif ($input->getOption('file')) {
            $file = $input->getOption('file');
            if (!file_exists($file)) {
                $file = dirname(__DIR__, 2) . '/' . $file;
            }
            if (!file_exists($file)) {
                $io->error('Datei nicht gefunden: ' . $input->getOption('file'));

                return Command::FAILURE;
            }
            $files[] = $file;
        } else {
            $io->error('Bitte --file oder --all angeben.');

            return Command::FAILURE;
        }

        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($files as $filePath) {
            $io->section('Datei: ' . basename($filePath));

            $content = file_get_contents($filePath);
            $json = json_decode($content, true);

            if (!$json || !isset($json['manufacturer']) || !isset($json['templates'])) {
                $io->warning('Ungültiges Format, übersprungen.');
                continue;
            }

            $io->text('Hersteller: ' . $json['manufacturer']);

            $result = $this->importExportService->importFromJson($json, [
                'scope' => $scope,
                'department_id' => $isGlobal ? null : $departmentId,
                'duplicate_action' => $force ? 'update' : 'skip',
                'dry_run' => $dryRun,
            ]);

            if (!empty($result['error'])) {
                $io->error($result['error']);

                return Command::FAILURE;
            }

            foreach ($result['rows'] ?? [] as $row) {
                $name = $row['name'] ?? '?';
                $action = $row['action'] ?? '';
                $icon = match ($action) {
                    'create' => '✅',
                    'update' => '🔄',
                    'skip' => '⏭',
                    default => '❌',
                };
                if (($row['status'] ?? '') === 'error') {
                    $io->text("  ❌  $name: " . implode(', ', $row['errors'] ?? []));
                } else {
                    $io->text("  $icon  $name ($action)");
                }
            }

            $stats = $result['stats'] ?? [];
            $totalCreated += (int) ($stats['created'] ?? 0);
            $totalUpdated += (int) ($stats['updated'] ?? 0);
            $totalSkipped += (int) ($stats['skipped'] ?? 0);
            $totalErrors += (int) ($stats['errors'] ?? 0);
        }

        $io->newLine();
        if ($dryRun) {
            $io->success(sprintf(
                'Dry-run abgeschlossen: %d würden erstellt, %d aktualisiert, %d übersprungen, %d Fehler',
                $totalCreated,
                $totalUpdated,
                $totalSkipped,
                $totalErrors,
            ));
        } else {
            $io->success(sprintf(
                'Import abgeschlossen: %d erstellt, %d aktualisiert, %d übersprungen, %d Fehler',
                $totalCreated,
                $totalUpdated,
                $totalSkipped,
                $totalErrors,
            ));
        }

        return $totalErrors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
