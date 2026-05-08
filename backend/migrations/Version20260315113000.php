<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Require storage_address_id on storage_rack and block deleting used storage addresses';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE storage_rack sr
            SET storage_address_id = COALESCE(
                (
                    SELECT a1.id
                    FROM address a1
                    WHERE a1.department_id = sr.department_id
                      AND a1.type = 'storage'
                      AND a1.is_primary = true
                    ORDER BY a1.id
                    LIMIT 1
                ),
                (
                    SELECT a2.id
                    FROM address a2
                    WHERE a2.department_id = sr.department_id
                      AND a2.type = 'storage'
                    ORDER BY a2.is_primary DESC, a2.id
                    LIMIT 1
                )
            )
            WHERE sr.storage_address_id IS NULL
        SQL);

        $invalidRows = (int) $this->connection->fetchOne(<<<'SQL'
            SELECT COUNT(*)
            FROM storage_rack sr
            LEFT JOIN address a ON a.id = sr.storage_address_id
            WHERE sr.storage_address_id IS NULL
               OR a.id IS NULL
               OR a.department_id <> sr.department_id
               OR a.type <> 'storage'
        SQL);
        if ($invalidRows > 0) {
            throw new \RuntimeException('storage_rack enthält ungültige/fehlende storage_address_id Einträge. Bitte Daten bereinigen und Migration erneut ausführen.');
        }

        $this->addSql('ALTER TABLE storage_rack DROP CONSTRAINT IF EXISTS fk_storage_rack_address');
        $this->addSql('ALTER TABLE storage_rack ALTER COLUMN storage_address_id SET NOT NULL');
        $this->addSql('ALTER TABLE storage_rack ADD CONSTRAINT fk_storage_rack_address FOREIGN KEY (storage_address_id) REFERENCES address (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE storage_rack DROP CONSTRAINT IF EXISTS fk_storage_rack_address');
        $this->addSql('ALTER TABLE storage_rack ALTER COLUMN storage_address_id DROP NOT NULL');
        $this->addSql('ALTER TABLE storage_rack ADD CONSTRAINT fk_storage_rack_address FOREIGN KEY (storage_address_id) REFERENCES address (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}

