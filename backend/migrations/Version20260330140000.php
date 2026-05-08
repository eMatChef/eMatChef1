<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Kostenstellen-IDs auf 13-stellige Zentral-ID (ks + Jahr + Hex) umstellen.
 * Wenn die Tabelle fehlt (Abweichung DB-Stand), wird sie mit id CHARACTER(13) angelegt.
 */
final class Version20260330140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accounting cost center: id CHARACTER(13) for generate13 (prefix ks).';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['accounting_cost_center'])) {
            $this->addSql('CREATE TABLE accounting_cost_center (
                id CHARACTER(13) NOT NULL,
                department_id CHARACTER(12) NOT NULL,
                name VARCHAR(255) NOT NULL,
                account_code VARCHAR(32) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )');
            $this->addSql('CREATE INDEX idx_acc_cc_department ON accounting_cost_center (department_id)');
            $this->addSql('ALTER TABLE accounting_cost_center ADD CONSTRAINT FK_ACC_CC_DEPARTMENT FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

            return;
        }

        $this->addSql('DELETE FROM accounting_cost_center');
        $this->addSql('ALTER TABLE accounting_cost_center ALTER COLUMN id TYPE CHARACTER(13)');
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['accounting_cost_center'])) {
            return;
        }

        $this->addSql('DELETE FROM accounting_cost_center');
        $this->addSql('ALTER TABLE accounting_cost_center ALTER COLUMN id TYPE CHARACTER(12)');
    }
}
