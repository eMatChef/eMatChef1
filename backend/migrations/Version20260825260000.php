<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825260000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass Materialübersicht: Einsätze, Pack, Rückgabe Firma';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE department_grossanlass_commitment ADD COLUMN IF NOT EXISTS packed BOOLEAN DEFAULT FALSE NOT NULL");
        $this->addSql("ALTER TABLE department_grossanlass_commitment ADD COLUMN IF NOT EXISTS pack_phase VARCHAR(16) DEFAULT 'anlass' NOT NULL");
        $this->addSql("ALTER TABLE department_grossanlass_commitment ADD COLUMN IF NOT EXISTS returned_to_firm BOOLEAN DEFAULT FALSE NOT NULL");

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS department_grossanlass_einsatz (
    id CHARACTER(12) NOT NULL,
    department_id CHARACTER(12) NOT NULL,
    commitment_id CHARACTER(12) DEFAULT NULL,
    wish_line_id CHARACTER(12) DEFAULT NULL,
    group_id CHARACTER(12) DEFAULT NULL,
    kind VARCHAR(16) DEFAULT 'einsatz' NOT NULL,
    qty INT DEFAULT 1 NOT NULL,
    starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    status VARCHAR(20) DEFAULT 'planned' NOT NULL,
    place VARCHAR(16) DEFAULT 'assigned' NOT NULL,
    who VARCHAR(120) DEFAULT '' NOT NULL,
    chauffeur_user_id CHARACTER(12) DEFAULT NULL,
    issued_to_user_id CHARACTER(12) DEFAULT NULL,
    packed BOOLEAN DEFAULT FALSE NOT NULL,
    pack_phase VARCHAR(16) DEFAULT 'anlass' NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_einsatz_dept ON department_grossanlass_einsatz (department_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_einsatz_commitment ON department_grossanlass_einsatz (commitment_id)');
        $this->addSql('ALTER TABLE department_grossanlass_einsatz DROP CONSTRAINT IF EXISTS fk_ga_einsatz_dept');
        $this->addSql('ALTER TABLE department_grossanlass_einsatz ADD CONSTRAINT fk_ga_einsatz_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_einsatz DROP CONSTRAINT IF EXISTS fk_ga_einsatz_commitment');
        $this->addSql('ALTER TABLE department_grossanlass_einsatz ADD CONSTRAINT fk_ga_einsatz_commitment FOREIGN KEY (commitment_id) REFERENCES department_grossanlass_commitment (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_einsatz DROP CONSTRAINT IF EXISTS fk_ga_einsatz_group');
        $this->addSql('ALTER TABLE department_grossanlass_einsatz ADD CONSTRAINT fk_ga_einsatz_group FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_einsatz');
        $this->addSql('ALTER TABLE department_grossanlass_commitment DROP COLUMN IF EXISTS packed');
        $this->addSql('ALTER TABLE department_grossanlass_commitment DROP COLUMN IF EXISTS pack_phase');
        $this->addSql('ALTER TABLE department_grossanlass_commitment DROP COLUMN IF EXISTS returned_to_firm');
    }
}
