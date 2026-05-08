<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Department Settings - Key/Value Einstellungen pro Department
 * Für konfigurierbare Defaults wie Aktivitäts-Zeiten, Material-Vorlaufzeiten etc.
 */
final class Version20260208170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create department_setting table for configurable defaults per department';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE department_setting (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id),
            CONSTRAINT fk_department_setting_department FOREIGN KEY (department_id) REFERENCES department(id) ON DELETE CASCADE
        )');

        $this->addSql('CREATE UNIQUE INDEX uq_department_setting_key ON department_setting (department_id, setting_key)');
        $this->addSql('CREATE INDEX idx_department_setting_department ON department_setting (department_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_setting');
    }
}
