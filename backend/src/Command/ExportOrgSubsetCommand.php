<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:org-subset:export',
    description: 'Exportiert einen Organisations-Subset als JSON-Seed'
)]
class ExportOrgSubsetCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'org',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Organisation-ID(s), z.B. --org=org_js000000 --org=GLOBALORG001'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Ziel-Datei (JSON)',
                'data/seeds/orgs/org_js000000_and_GLOBALORG001/subset.json'
            )
            ->addOption(
                'with-global-templates',
                null,
                InputOption::VALUE_NONE,
                'Globale Templates (department_id IS NULL) mit exportieren'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $orgIds = array_values(array_filter((array) $input->getOption('org')));
        $outputFile = (string) $input->getOption('output');
        $withGlobalTemplates = (bool) $input->getOption('with-global-templates');

        if ($orgIds === []) {
            $io->error('Bitte mindestens eine Organisation-ID mit --org angeben.');
            return Command::FAILURE;
        }

        $conn = $this->entityManager->getConnection();
        $io->title('Organisation-Subset Export');
        $io->text('Organisationen: ' . implode(', ', $orgIds));

        $orgRows = $this->fetchByValues($conn, 'organisation', 'id', $orgIds);
        if ($orgRows === []) {
            $io->error('Keine der angegebenen Organisationen wurde gefunden.');
            return Command::FAILURE;
        }

        $departmentRows = $this->fetchByValues($conn, 'department', 'organisation_id', $orgIds);
        $departmentIds = $this->columnValues($departmentRows, 'id');
        if ($departmentRows === []) {
            $io->warning('Keine Departments für die angegebenen Organisationen gefunden.');
        }

        $membershipRows = $departmentIds === []
            ? []
            : $this->fetchByValues($conn, 'membership', 'department_id', $departmentIds);
        $userIds = $this->columnValues($membershipRows, 'user_id');

        $userRows = $userIds === [] ? [] : $this->fetchByValues($conn, 'user', 'id', $userIds, true);
        $creatorUserIds = $this->columnValues($userRows, 'created_by');
        if ($creatorUserIds !== []) {
            $creatorRows = $this->fetchByValues($conn, 'user', 'id', $creatorUserIds, true);
            $userRows = $this->mergeRowsById($userRows, $creatorRows);
        }

        $profileIds = $this->columnValues($userRows, 'profile_id');
        $profileRows = $profileIds === [] ? [] : $this->fetchByValues($conn, 'profile', 'id', $profileIds);

        $addressRows = $departmentIds === [] ? [] : $this->fetchByValues($conn, 'address', 'department_id', $departmentIds);
        $categoryRows = $departmentIds === [] ? [] : $this->fetchByValues($conn, 'category', 'department_id', $departmentIds);

        $rackRows = $departmentIds === [] ? [] : $this->fetchByValues($conn, 'storage_rack', 'department_id', $departmentIds);
        $rackIds = $this->columnValues($rackRows, 'id');
        $slotRows = $rackIds === [] ? [] : $this->fetchByValues($conn, 'storage_slot', 'rack_id', $rackIds);

        $materialRows = $departmentIds === [] ? [] : $this->fetchByValues($conn, 'material_item', 'department_id', $departmentIds);
        $materialIds = $this->columnValues($materialRows, 'id');
        $batchRows = $materialIds === [] ? [] : $this->fetchByValues($conn, 'material_batch', 'material_item_id', $materialIds);
        $batchIds = $this->columnValues($batchRows, 'id');

        // Zusätzliche referenzierte Batches aufnehmen (source_batch_id / container_batch_id)
        $extraBatchIds = array_values(array_filter(array_merge(
            $this->columnValues($batchRows, 'source_batch_id'),
            $this->columnValues($batchRows, 'container_batch_id')
        )));
        if ($extraBatchIds !== []) {
            $extraBatchRows = $this->fetchByValues($conn, 'material_batch', 'id', $extraBatchIds);
            $batchRows = $this->mergeRowsById($batchRows, $extraBatchRows);
            $batchIds = $this->columnValues($batchRows, 'id');
        }

        $allocationRows = $batchIds === [] ? [] : $this->fetchByValues($conn, 'batch_storage_allocation', 'batch_id', $batchIds);
        $containerBatchIds = $this->columnValues($allocationRows, 'container_batch_id');
        if ($containerBatchIds !== []) {
            $containerBatchRows = $this->fetchByValues($conn, 'material_batch', 'id', $containerBatchIds);
            $batchRows = $this->mergeRowsById($batchRows, $containerBatchRows);
            $batchIds = $this->columnValues($batchRows, 'id');
        }

        $templateRows = [];
        if ($departmentIds !== []) {
            $templateRows = $this->fetchByValues($conn, 'material_template', 'department_id', $departmentIds);
        }
        if ($withGlobalTemplates) {
            $globalTemplateRows = $conn->fetchAllAssociative('SELECT * FROM "material_template" WHERE "department_id" IS NULL');
            $templateRows = $this->mergeRowsById($templateRows, $globalTemplateRows);
        }
        $templateIds = $this->columnValues($templateRows, 'id');
        $templateComponentRows = $templateIds === []
            ? []
            : $this->fetchByValues($conn, 'material_template_component', 'template_id', $templateIds);

        $payload = [
            'meta' => [
                'format' => 'org-subset-seed',
                'version' => 1,
                'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'org_ids' => $orgIds,
                'with_global_templates' => $withGlobalTemplates,
            ],
            'tables' => [
                'organisation' => $orgRows,
                'department' => $departmentRows,
                'profile' => $profileRows,
                'user' => $userRows,
                'membership' => $membershipRows,
                'address' => $addressRows,
                'category' => $categoryRows,
                'storage_rack' => $rackRows,
                'storage_slot' => $slotRows,
                'material_item' => $materialRows,
                'material_batch' => $batchRows,
                'batch_storage_allocation' => $allocationRows,
                'material_template' => $templateRows,
                'material_template_component' => $templateComponentRows,
            ],
        ];

        $absolutePath = $this->resolvePath($outputFile);
        $dir = dirname($absolutePath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $io->error('Konnte Zielverzeichnis nicht erstellen: ' . $dir);
            return Command::FAILURE;
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $io->error('JSON-Kodierung fehlgeschlagen.');
            return Command::FAILURE;
        }

        file_put_contents($absolutePath, $json . PHP_EOL);

        $io->success('Subset exportiert: ' . $absolutePath);
        foreach ($payload['tables'] as $table => $rows) {
            $io->text(sprintf(' - %s: %d', $table, count($rows)));
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchByValues(Connection $conn, string $table, string $column, array $values, bool $isReservedTable = false): array
    {
        if ($values === []) {
            return [];
        }

        $quotedTable = $isReservedTable ? '"user"' : sprintf('"%s"', $table);
        $quotedColumn = sprintf('"%s"', $column);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $sql = sprintf('SELECT * FROM %s WHERE %s IN (%s)', $quotedTable, $quotedColumn, $placeholders);

        return $conn->fetchAllAssociative($sql, array_values($values));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function columnValues(array $rows, string $column): array
    {
        $map = [];
        foreach ($rows as $row) {
            if (!array_key_exists($column, $row) || $row[$column] === null) {
                continue;
            }
            $map[(string) $row[$column]] = (string) $row[$column];
        }

        return array_values($map);
    }

    /**
     * @param array<int, array<string, mixed>> $base
     * @param array<int, array<string, mixed>> $add
     * @return array<int, array<string, mixed>>
     */
    private function mergeRowsById(array $base, array $add): array
    {
        $byId = [];
        foreach ($base as $row) {
            if (isset($row['id'])) {
                $byId[(string) $row['id']] = $row;
            }
        }

        foreach ($add as $row) {
            if (isset($row['id'])) {
                $byId[(string) $row['id']] = $row;
            }
        }

        return array_values($byId);
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^(\/|[A-Za-z]:[\\\\\/])/', $path) === 1) {
            return $path;
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}

