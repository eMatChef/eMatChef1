<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Demo-Seed-Infrastruktur: Szenario-Identität am Department und Ownership-Ledger (rein additiv). */
final class Version20261008100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add department.demo_scenario_key (unique, requires demo_mode) and demo_seed_record ledger';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department ADD demo_scenario_key VARCHAR(40) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_department_demo_scenario_key ON department (demo_scenario_key)');
        $this->addSql('ALTER TABLE department ADD CONSTRAINT chk_department_demo_scenario_requires_demo CHECK (demo_scenario_key IS NULL OR demo_mode = true)');

        $this->addSql(<<<'SQL'
CREATE TABLE demo_seed_record (
    id CHARACTER(12) NOT NULL,
    scenario_key VARCHAR(40) NOT NULL,
    seed_key VARCHAR(190) NOT NULL,
    entity_class VARCHAR(190) NOT NULL,
    entity_id VARCHAR(64) NOT NULL,
    department_id CHARACTER(12) DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_demo_seed_record_key ON demo_seed_record (scenario_key, seed_key)');
        $this->addSql('CREATE INDEX idx_demo_seed_record_department ON demo_seed_record (department_id)');
        $this->addSql('ALTER TABLE demo_seed_record ADD CONSTRAINT fk_demo_seed_record_department FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE demo_seed_record');
        $this->addSql('ALTER TABLE department DROP CONSTRAINT chk_department_demo_scenario_requires_demo');
        $this->addSql('DROP INDEX uniq_department_demo_scenario_key');
        $this->addSql('ALTER TABLE department DROP demo_scenario_key');
    }
}
