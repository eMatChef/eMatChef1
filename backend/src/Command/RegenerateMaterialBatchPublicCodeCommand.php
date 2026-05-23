<?php

namespace App\Command;

use App\Entity\MaterialItem;
use App\Service\Public\PublicCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:public-code:regenerate-material-batch',
    description: 'Ergaenzt fehlende Material- und Batch-public_code-Eintraege fuer kanonische QR-URLs (/i/m/…/b/…)',
)]
class RegenerateMaterialBatchPublicCodeCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PublicCodeService $publicCodeService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('department-id', null, InputOption::VALUE_REQUIRED, 'Nur Materialien dieser Abteilung')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur zaehlen, nichts speichern');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $departmentId = trim((string) $input->getOption('department-id'));
        $dryRun = (bool) $input->getOption('dry-run');

        $qb = $this->entityManager->getRepository(MaterialItem::class)
            ->createQueryBuilder('m')
            ->where('m.deletedAt IS NULL')
            ->orderBy('m.departmentId', 'ASC')
            ->addOrderBy('m.name', 'ASC');

        if ($departmentId !== '') {
            $qb->andWhere('m.departmentId = :departmentId')
                ->setParameter('departmentId', $departmentId);
        }

        /** @var MaterialItem[] $materials */
        $materials = $qb->getQuery()->getResult();

        if ($materials === []) {
            $io->success('Keine Materialien gefunden.');
            return Command::SUCCESS;
        }

        $stats = [
            'materials' => count($materials),
            'material_codes_created' => 0,
            'batch_codes_created' => 0,
            'batches_skipped' => 0,
            'errors' => 0,
        ];
        $errorRows = [];

        foreach ($materials as $material) {
            $isPhysicalComboFromContainer = $material->getMaterialType() === 'physical_combo'
                && $material->getLinkedContainerBatchId();

            if (!$isPhysicalComboFromContainer) {
                $hadMaterial = $this->publicCodeService->getActiveMaterialPublicCode((string) $material->getId()) !== null;
                if ($dryRun) {
                    if (!$hadMaterial) {
                        ++$stats['material_codes_created'];
                    }
                } else {
                    try {
                        $this->publicCodeService->ensureMaterialPublicCode($material);
                        if (!$hadMaterial) {
                            ++$stats['material_codes_created'];
                        }
                    } catch (\Throwable $e) {
                        ++$stats['errors'];
                        $errorRows[] = [$material->getId(), $material->getName(), 'material', $e->getMessage()];
                        continue;
                    }
                }
            }

            foreach ($material->getBatches() as $batch) {
                if (
                    $material->getTrackingType() === 'serialized'
                    && trim((string) $batch->getSerialNumber()) === ''
                ) {
                    ++$stats['batches_skipped'];
                    continue;
                }

                $hadBatch = $this->publicCodeService->getActiveBatchPublicCode((string) $batch->getId()) !== null;
                if ($dryRun) {
                    if (!$hadBatch) {
                        ++$stats['batch_codes_created'];
                    }
                    continue;
                }

                try {
                    $this->publicCodeService->ensureBatchPublicCode($batch);
                    if (!$hadBatch && $this->publicCodeService->getActiveBatchPublicCode((string) $batch->getId()) !== null) {
                        ++$stats['batch_codes_created'];
                    }
                } catch (\Throwable $e) {
                    ++$stats['errors'];
                    $errorRows[] = [
                        $material->getId(),
                        $material->getName(),
                        'batch ' . $batch->getId(),
                        $e->getMessage(),
                    ];
                }
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->title('Public-Code Backfill (Material + Batch)');
        if ($departmentId !== '') {
            $io->text('Abteilung: ' . $departmentId);
        }
        if ($dryRun) {
            $io->warning('Dry-Run — keine Aenderungen gespeichert.');
        }

        $io->table(
            ['Kennzahl', 'Anzahl'],
            [
                ['Materialien verarbeitet', (string) $stats['materials']],
                ['Neue Material-Codes' . ($dryRun ? ' (wuerden)' : ''), (string) $stats['material_codes_created']],
                ['Neue Batch-Codes' . ($dryRun ? ' (wuerden)' : ''), (string) $stats['batch_codes_created']],
                ['Chargen übersprungen (serialisiert ohne SN)', (string) $stats['batches_skipped']],
                ['Fehler', (string) $stats['errors']],
            ],
        );

        if ($errorRows !== []) {
            $io->section('Fehler');
            $io->table(['Material-ID', 'Name', 'Kontext', 'Meldung'], $errorRows);
        }

        if ($stats['errors'] > 0) {
            return Command::FAILURE;
        }

        $io->success($dryRun ? 'Dry-Run abgeschlossen.' : 'Backfill abgeschlossen.');

        return Command::SUCCESS;
    }
}
