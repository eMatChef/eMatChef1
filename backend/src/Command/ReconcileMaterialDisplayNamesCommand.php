<?php

namespace App\Command;

use App\Entity\MaterialItem;
use App\Service\MaterialDisplayName;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:material:reconcile-display-names',
    description: 'Bereinigt Materialnamen: falsche Einheits-Suffixe (z. B. «(10 m)» bei Bündel-Verpackung) korrigieren.',
)]
class ReconcileMaterialDisplayNamesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('department-id', null, InputOption::VALUE_REQUIRED, 'Nur Materialien dieser Abteilung')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur anzeigen, nichts speichern');
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

        $changedRows = [];
        $updated = 0;

        foreach ($materials as $material) {
            $current = trim($material->getName());
            $next = MaterialDisplayName::formatDisplayName(
                $current,
                $material->getPackUnit(),
                $material->getPackSize(),
                $material->getSizeLength(),
            );
            if ($next === $current) {
                continue;
            }

            ++$updated;
            $changedRows[] = [
                $material->getId(),
                $material->getPackUnit() ?? '—',
                $material->getPackSize() !== null ? (string) $material->getPackSize() : '—',
                $current,
                $next,
            ];

            if (!$dryRun) {
                $material->setName($next);
            }
        }

        if (!$dryRun && $updated > 0) {
            $this->entityManager->flush();
        }

        $io->title('Material-Anzeigenamen bereinigen');
        if ($departmentId !== '') {
            $io->text('Abteilung: ' . $departmentId);
        }
        if ($dryRun) {
            $io->warning('Dry-Run — keine Änderungen gespeichert.');
        }

        $io->table(
            ['Kennzahl', 'Anzahl'],
            [
                ['Materialien geprüft', (string) count($materials)],
                ['Namen ' . ($dryRun ? 'würden geändert' : 'geändert'), (string) $updated],
            ],
        );

        if ($changedRows !== []) {
            $io->section('Geänderte Namen');
            $io->table(['ID', 'pack_unit', 'pack_size', 'Alt', 'Neu'], $changedRows);
        } else {
            $io->success('Keine Anpassungen nötig.');
        }

        if (!$dryRun && $updated > 0) {
            $io->success(sprintf('%d Materialnamen bereinigt.', $updated));
        }

        return Command::SUCCESS;
    }
}
