<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Logistik-Knoten (Anlass-Topf) auf department_grossanlass_config';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_config ADD COLUMN IF NOT EXISTS logistics_group_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_config_logistics ON department_grossanlass_config (logistics_group_id)');
        $this->addSql('ALTER TABLE department_grossanlass_config DROP CONSTRAINT IF EXISTS fk_ga_config_logistics');
        $this->addSql('ALTER TABLE department_grossanlass_config ADD CONSTRAINT fk_ga_config_logistics FOREIGN KEY (logistics_group_id) REFERENCES "group" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_config DROP CONSTRAINT IF EXISTS fk_ga_config_logistics');
        $this->addSql('DROP INDEX IF EXISTS idx_ga_config_logistics');
        $this->addSql('ALTER TABLE department_grossanlass_config DROP COLUMN IF EXISTS logistics_group_id');
    }
}
