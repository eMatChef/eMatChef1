<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\MaterialTemplate;
use App\Service\TemplateImportExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:templates:purge',
    description: 'Löscht Material-Vorlagen außer explizit behaltene (Name-Muster); optional Export der Behaltenden',
)]
class PurgeTemplatesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TemplateImportExportService $importExportService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('keep-name', null, InputOption::VALUE_REQUIRED, 'Name-Teilstring (case-insensitive), z. B. sarasani')
            ->addOption('scope', null, InputOption::VALUE_REQUIRED, 'global|department|all', 'global')
            ->addOption('department-id', null, InputOption::VALUE_OPTIONAL, 'Nur bei scope=department')
            ->addOption('export-kept', null, InputOption::VALUE_OPTIONAL, 'Behaltene Vorlagen vor Löschen nach JSON exportieren')
            ->addOption('prune-json-dir', null, InputOption::VALUE_OPTIONAL, 'Alte JSON-Dateien in diesem Ordner löschen (Behaltene bleiben)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur anzeigen, nichts löschen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $keepName = mb_strtolower(trim((string) $input->getOption('keep-name')));
        if ($keepName === '') {
            $io->error('--keep-name ist erforderlich (z. B. sarasani)');

            return Command::FAILURE;
        }

        $scope = (string) $input->getOption('scope');
        $departmentId = $input->getOption('department-id');
        $dryRun = (bool) $input->getOption('dry-run');

        $qb = $this->entityManager->getRepository(MaterialTemplate::class)->createQueryBuilder('t');
        if ($scope === 'global') {
            $qb->where('t.departmentId IS NULL');
        } elseif ($scope === 'department') {
            if (!is_string($departmentId) || $departmentId === '') {
                $io->error('--department-id ist bei scope=department erforderlich');

                return Command::FAILURE;
            }
            $qb->where('t.departmentId = :departmentId')->setParameter('departmentId', $departmentId);
        }

        /** @var MaterialTemplate[] $all */
        $all = $qb->orderBy('t.name', 'ASC')->getQuery()->getResult();

        $keep = [];
        $remove = [];
        foreach ($all as $template) {
            if (str_contains(mb_strtolower($template->getName()), $keepName)) {
                $keep[] = $template;
            } else {
                $remove[] = $template;
            }
        }

        $io->title('Material-Vorlagen bereinigen');
        $io->text(sprintf('Behalten (%d):', count($keep)));
        foreach ($keep as $t) {
            $io->writeln('  ✓ ' . $t->getName() . ' (' . $t->getId() . ')');
        }
        $io->text(sprintf('Löschen (%d):', count($remove)));
        foreach ($remove as $t) {
            $io->writeln('  ✗ ' . $t->getName() . ' (' . $t->getId() . ')');
        }

        if ($keep === []) {
            $io->warning('Keine Vorlage zum Behalten gefunden — Abbruch.');

            return Command::FAILURE;
        }

        $exportPath = $input->getOption('export-kept');
        if (is_string($exportPath) && $exportPath !== '' && !$dryRun) {
            $keepIds = array_map(static fn (MaterialTemplate $t) => $t->getId(), $keep);
            $exportScope = $scope === 'department' ? 'department' : 'global';
            $result = $this->importExportService->exportToJson(
                $exportScope,
                $scope === 'department' ? (string) $departmentId : null,
                null,
                $keepIds,
            );
            if (!empty($result['error'])) {
                $io->error($result['error']);

                return Command::FAILURE;
            }
            $result['manufacturer'] = $keepName;
            $path = str_starts_with($exportPath, '/') ? $exportPath : dirname(__DIR__, 2) . '/' . $exportPath;
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents(
                $path,
                json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
            );
            $io->success('Behaltene Vorlagen exportiert nach ' . $path);
        }

        if (!$dryRun) {
            foreach ($remove as $template) {
                $this->entityManager->remove($template);
            }
            $this->entityManager->flush();
            $io->success(sprintf('%d Vorlage(n) gelöscht.', count($remove)));
        } else {
            $io->note('Dry-run — keine DB-Änderungen.');
        }

        $pruneDir = $input->getOption('prune-json-dir');
        if (is_string($pruneDir) && $pruneDir !== '') {
            $dir = str_starts_with($pruneDir, '/') ? $pruneDir : dirname(__DIR__, 2) . '/' . $pruneDir;
            if (!is_dir($dir)) {
                $io->warning('JSON-Ordner nicht gefunden: ' . $dir);
            } else {
                $keepFiles = [$keepName . '.json'];
                foreach (glob($dir . '/*.json') ?: [] as $file) {
                    $base = basename($file);
                    if (in_array($base, $keepFiles, true)) {
                        continue;
                    }
                    if ($dryRun) {
                        $io->writeln('[dry-run] JSON löschen: ' . $base);
                    } else {
                        unlink($file);
                        $io->writeln('JSON gelöscht: ' . $base);
                    }
                }
            }
        }

        return Command::SUCCESS;
    }
}
