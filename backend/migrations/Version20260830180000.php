<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass R4–R7: Fahrausweise Profil, Orte, Packs, Einsatz-Ziel';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_drive_license (
    user_id CHARACTER(12) NOT NULL,
    drive_classes JSON NOT NULL,
    valid_until DATE DEFAULT NULL,
    document_filename VARCHAR(255) DEFAULT '' NOT NULL,
    document_original_name VARCHAR(255) DEFAULT '' NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (user_id)
)
SQL);
        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_place (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            name VARCHAR(255) NOT NULL,
            group_id CHARACTER(12) DEFAULT NULL,
            unterlager_id CHARACTER(12) DEFAULT NULL,
            public_code VARCHAR(32) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_place_code ON department_grossanlass_place (public_code)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_place_dept ON department_grossanlass_place (department_id)');

        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_pack (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            einsatz_id CHARACTER(12) NOT NULL,
            public_code VARCHAR(32) NOT NULL,
            status VARCHAR(16) DEFAULT \'staging\' NOT NULL,
            current_place_id CHARACTER(12) DEFAULT NULL,
            trip_released_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            sort_order INT DEFAULT 0 NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_pack_code ON department_grossanlass_pack (public_code)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_pack_einsatz ON department_grossanlass_pack (einsatz_id)');

        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_pack_line (
            id CHARACTER(12) NOT NULL,
            pack_id CHARACTER(12) NOT NULL,
            commitment_id CHARACTER(12) DEFAULT NULL,
            wish_line_id CHARACTER(12) DEFAULT NULL,
            label VARCHAR(255) DEFAULT \'\' NOT NULL,
            qty_needed INT DEFAULT 1 NOT NULL,
            qty_packed INT DEFAULT 0 NOT NULL,
            valid_from TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            valid_to TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_pack_line_pack ON department_grossanlass_pack_line (pack_id)');

        $this->addSql('ALTER TABLE department_grossanlass_einsatz ADD COLUMN IF NOT EXISTS destination_place_id CHARACTER(12) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_einsatz DROP COLUMN IF EXISTS destination_place_id');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_pack_line');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_pack');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_place');
        $this->addSql('DROP TABLE IF EXISTS user_drive_license');
    }
}
