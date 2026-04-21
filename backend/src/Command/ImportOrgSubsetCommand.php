<?php

namespace App\Command;

use App\Service\Accounting\AccountingCostCenterBootstrapService;
use App\Service\Bootstrap\SuperadminBootstrapService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:org-subset:import',
    description: 'Importiert einen Organisations-Subset aus JSON-Seed'
)]
class ImportOrgSubsetCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountingCostCenterBootstrapService $accountingCostCenterBootstrap,
        private SuperadminBootstrapService $superadminBootstrap,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Quelle (JSON)',
                'data/seeds/orgs/org_js000000_and_GLOBALORG001/subset.json'
            )
            ->addOption(
                'ensure-superadmin',
                null,
                InputOption::VALUE_NONE,
                'Legt superadmin@example.com an (oder aktualisiert Rollen/Membership)'
            )
            ->addOption(
                'superadmin-email',
                null,
                InputOption::VALUE_OPTIONAL,
                'Superadmin E-Mail',
                'superadmin@example.com'
            )
            ->addOption(
                'superadmin-password',
                null,
                InputOption::VALUE_OPTIONAL,
                'Superadmin Passwort',
                'password'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $this->resolvePath((string) $input->getOption('file'));
        $ensureSuperadmin = (bool) $input->getOption('ensure-superadmin');
        $superadminEmail = (string) $input->getOption('superadmin-email');
        $superadminPassword = (string) $input->getOption('superadmin-password');

        if (!is_file($file)) {
            $io->error('Seed-Datei nicht gefunden: ' . $file);
            return Command::FAILURE;
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            $io->error('Seed-Datei konnte nicht gelesen werden.');
            return Command::FAILURE;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload) || !isset($payload['tables']) || !is_array($payload['tables'])) {
            $io->error('Ungültiges Seed-Format (tables fehlt).');
            return Command::FAILURE;
        }

        $tables = $payload['tables'];
        $conn = $this->entityManager->getConnection();

        $io->title('Organisation-Subset Import');
        if (isset($payload['meta']['org_ids']) && is_array($payload['meta']['org_ids'])) {
            $io->text('Organisationen: ' . implode(', ', $payload['meta']['org_ids']));
        }

        $conflicts = [
            'organisation' => ['id'],
            'department' => ['id'],
            'profile' => ['id'],
            'user' => ['id'],
            'membership' => ['user_id', 'department_id'],
            'address' => ['id'],
            'category' => ['id'],
            'storage_rack' => ['id'],
            'storage_slot' => ['id'],
            'material_item' => ['id'],
            'material_batch' => ['id'],
            'batch_storage_allocation' => ['id'],
            'material_template' => ['id'],
            'material_template_component' => ['id'],
        ];

        $order = [
            'organisation',
            'department',
            'profile',
            'user',
            'membership',
            'address',
            'category',
            'storage_rack',
            'storage_slot',
            'material_item',
            'material_batch',
            'batch_storage_allocation',
            'material_template',
            'material_template_component',
        ];

        $jsonColumns = [];
        foreach ($order as $table) {
            $jsonColumns[$table] = $this->fetchJsonColumns($conn, $table);
        }

        $conn->beginTransaction();
        try {
            foreach ($order as $table) {
                $rows = $tables[$table] ?? [];
                if (!is_array($rows)) {
                    throw new \RuntimeException(sprintf('Tabelle "%s" hat ungültiges Format.', $table));
                }

                $imported = 0;
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $normalized = $this->normalizeRowForInsert($row, $jsonColumns[$table]);
                    $this->upsertRow($conn, $table, $normalized, $conflicts[$table], $table === 'user');
                    $imported++;
                }
                $io->text(sprintf(' - %s: %d', $table, $imported));
            }

            if ($ensureSuperadmin) {
                $superadmin = $this->superadminBootstrap->ensure($superadminEmail, $superadminPassword);
                $io->text(' - superadmin ensured: ' . $superadmin->getId());
            }

            $conn->commit();
            $this->entityManager->clear();
        } catch (\Throwable $e) {
            $conn->rollBack();
            $io->error('Import fehlgeschlagen: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success('Subset erfolgreich importiert.');
        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $jsonColumns
     * @return array<string, mixed>
     */
    private function normalizeRowForInsert(array $row, array $jsonColumns): array
    {
        $normalized = $row;
        foreach ($normalized as $key => $value) {
            if (is_bool($value)) {
                // PDO/PostgreSQL behandelt false ohne Typbindung sonst als ''.
                $normalized[$key] = $value ? 'true' : 'false';
            }
        }

        foreach ($jsonColumns as $column) {
            if (array_key_exists($column, $normalized) && is_array($normalized[$column])) {
                $normalized[$column] = json_encode($normalized[$column], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function fetchJsonColumns(Connection $conn, string $table): array
    {
        $rows = $conn->fetchAllAssociative(
            'SELECT column_name, data_type, udt_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ?',
            [$table]
        );

        $jsonColumns = [];
        foreach ($rows as $row) {
            $udt = (string) ($row['udt_name'] ?? '');
            $dataType = (string) ($row['data_type'] ?? '');
            if ($udt === 'json' || $udt === 'jsonb' || $dataType === 'json') {
                $jsonColumns[] = (string) $row['column_name'];
            }
        }

        return $jsonColumns;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $conflictColumns
     */
    private function upsertRow(Connection $conn, string $table, array $row, array $conflictColumns, bool $reservedTable = false): void
    {
        if ($row === []) {
            return;
        }

        $columns = array_keys($row);
        $quotedColumns = array_map(fn(string $c) => sprintf('"%s"', $c), $columns);
        $columnSql = implode(', ', $quotedColumns);
        $valueSql = implode(', ', array_fill(0, count($columns), '?'));

        $quotedTable = $reservedTable ? '"user"' : sprintf('"%s"', $table);
        $conflictSql = implode(', ', array_map(fn(string $c) => sprintf('"%s"', $c), $conflictColumns));

        $updateColumns = array_values(array_filter(
            $columns,
            fn(string $col) => !in_array($col, $conflictColumns, true)
        ));

        if ($updateColumns === []) {
            $onConflict = 'DO NOTHING';
        } else {
            $assignments = implode(', ', array_map(
                fn(string $col) => sprintf('"%s" = EXCLUDED."%s"', $col, $col),
                $updateColumns
            ));
            $onConflict = 'DO UPDATE SET ' . $assignments;
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s) %s',
            $quotedTable,
            $columnSql,
            $valueSql,
            $conflictSql,
            $onConflict
        );

        $conn->executeStatement($sql, array_values($row));
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^(\/|[A-Za-z]:[\\\\\/])/', $path) === 1) {
            return $path;
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}

