<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\MaterialDisplayName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Bereinigt Materialnamen mit fälschlich gesetztem Meter-Suffix bei Verpackungseinheiten (z. B. Bündel).
 */
final class Version20260627120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reconcile material_item.name display suffixes (fix false «(X m)» on packaging units).';
    }

    public function up(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT id, name, pack_unit, pack_size, size_length
            FROM material_item
            WHERE deleted_at IS NULL
            ORDER BY id
            SQL
        );

        foreach ($rows as $row) {
            $current = trim((string) ($row['name'] ?? ''));
            if ($current === '') {
                continue;
            }

            $packSize = isset($row['pack_size']) && $row['pack_size'] !== null
                ? (int) $row['pack_size']
                : null;

            $next = MaterialDisplayName::formatDisplayName(
                $current,
                isset($row['pack_unit']) ? (string) $row['pack_unit'] : null,
                $packSize,
                isset($row['size_length']) ? (string) $row['size_length'] : null,
            );

            if ($next === $current) {
                continue;
            }

            $this->connection->executeStatement(
                'UPDATE material_item SET name = ?, updated_at = NOW() WHERE id = ?',
                [$next, (string) $row['id']],
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Datenbereinigung — nicht rückgängig machbar ohne Snapshot.
    }
}
