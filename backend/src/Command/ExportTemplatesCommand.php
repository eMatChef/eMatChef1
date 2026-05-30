<?php

namespace App\Command;

use App\Service\TemplateImportExportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:templates:export',
    description: 'Exportiert Zelt-Vorlagen als v5-JSON'
)]
class ExportTemplatesCommand extends Command
{
    public function __construct(
        private TemplateImportExportService $importExportService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('department_id', InputArgument::OPTIONAL, 'Department-ID (leer = zentrale/globale Vorlagen)')
            ->addOption('global', 'g', InputOption::VALUE_NONE, 'Globale Vorlagen exportieren')
            ->addOption('manufacturer', 'm', InputOption::VALUE_OPTIONAL, 'Nur Vorlagen dieses Herstellers (z.B. zelthangar)')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Ausgabedatei (z.B. data/templates/zelthangar.json)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $departmentId = $input->getArgument('department_id');
        $isGlobal = $input->getOption('global') || !$departmentId;
        $scope = $isGlobal ? 'global' : 'department';
        $manufacturer = $input->getOption('manufacturer');

        $io->title($isGlobal ? 'Zelt-Vorlagen Export (ZENTRAL)' : 'Zelt-Vorlagen Export (Department)');

        $result = $this->importExportService->exportToJson(
            $scope,
            $isGlobal ? null : (string) $departmentId,
            is_string($manufacturer) && $manufacturer !== '' ? $manufacturer : null,
        );

        if (!empty($result['error'])) {
            $io->error($result['error']);

            return Command::FAILURE;
        }

        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $io->error('JSON-Encoding fehlgeschlagen');

            return Command::FAILURE;
        }

        $outputPath = $input->getOption('output');
        if (is_string($outputPath) && $outputPath !== '') {
            if (!str_starts_with($outputPath, '/')) {
                $outputPath = dirname(__DIR__, 2) . '/' . $outputPath;
            }
            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($outputPath, $json . "\n");
            $io->success(sprintf(
                '%d Vorlagen exportiert nach %s',
                count($result['templates'] ?? []),
                $outputPath,
            ));
        } else {
            $output->write($json . "\n");
        }

        return Command::SUCCESS;
    }
}
