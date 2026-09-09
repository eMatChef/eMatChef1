<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass Stammdaten: Eventstandort als Kontakt (address)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_config ADD COLUMN IF NOT EXISTS venue_address_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_config_venue ON department_grossanlass_config (venue_address_id)');
        $this->addSql('ALTER TABLE department_grossanlass_config DROP CONSTRAINT IF EXISTS fk_ga_config_venue');
        $this->addSql('ALTER TABLE department_grossanlass_config ADD CONSTRAINT fk_ga_config_venue FOREIGN KEY (venue_address_id) REFERENCES address (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_config DROP CONSTRAINT IF EXISTS fk_ga_config_venue');
        $this->addSql('DROP INDEX IF EXISTS idx_ga_config_venue');
        $this->addSql('ALTER TABLE department_grossanlass_config DROP COLUMN IF EXISTS venue_address_id');
    }
}
