<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Unterlager-Hierarchie für Teilnehmer-Abteilungen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS department_grossanlass_unterlager (
    id CHARACTER(12) NOT NULL,
    host_department_id CHARACTER(12) NOT NULL,
    parent_id CHARACTER(12) DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0 NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_unterlager_host ON department_grossanlass_unterlager (host_department_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_unterlager_parent ON department_grossanlass_unterlager (parent_id)');
        $this->addSql('ALTER TABLE department_grossanlass_unterlager DROP CONSTRAINT IF EXISTS fk_ga_unterlager_host');
        $this->addSql('ALTER TABLE department_grossanlass_unterlager ADD CONSTRAINT fk_ga_unterlager_host FOREIGN KEY (host_department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_unterlager DROP CONSTRAINT IF EXISTS fk_ga_unterlager_parent');
        $this->addSql('ALTER TABLE department_grossanlass_unterlager ADD CONSTRAINT fk_ga_unterlager_parent FOREIGN KEY (parent_id) REFERENCES department_grossanlass_unterlager (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE department_grossanlass_participant ADD COLUMN IF NOT EXISTS unterlager_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_participant_unterlager ON department_grossanlass_participant (unterlager_id)');
        $this->addSql('ALTER TABLE department_grossanlass_participant DROP CONSTRAINT IF EXISTS fk_ga_participant_unterlager');
        $this->addSql('ALTER TABLE department_grossanlass_participant ADD CONSTRAINT fk_ga_participant_unterlager FOREIGN KEY (unterlager_id) REFERENCES department_grossanlass_unterlager (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_participant DROP CONSTRAINT IF EXISTS fk_ga_participant_unterlager');
        $this->addSql('DROP INDEX IF EXISTS idx_ga_participant_unterlager');
        $this->addSql('ALTER TABLE department_grossanlass_participant DROP COLUMN IF EXISTS unterlager_id');
        $this->addSql('ALTER TABLE department_grossanlass_unterlager DROP CONSTRAINT IF EXISTS fk_ga_unterlager_parent');
        $this->addSql('ALTER TABLE department_grossanlass_unterlager DROP CONSTRAINT IF EXISTS fk_ga_unterlager_host');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_unterlager');
    }
}
