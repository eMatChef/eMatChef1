<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Helferpool: externe Datenquellen (API-Daten), Original-Antworten, externe Datensätze, Synchronisationshistorie, Konfliktstatus der Zuteilung.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_ext_source (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            name VARCHAR(120) NOT NULL,
            base_url VARCHAR(500) NOT NULL DEFAULT \'\',
            endpoint_path VARCHAR(300) NOT NULL DEFAULT \'\',
            auth_type VARCHAR(12) NOT NULL DEFAULT \'none\',
            auth_header VARCHAR(80) DEFAULT NULL,
            auth_user VARCHAR(180) DEFAULT NULL,
            secret_enc TEXT DEFAULT NULL,
            mapping JSON NOT NULL DEFAULT \'{}\',
            snapshot_complete BOOLEAN NOT NULL DEFAULT FALSE,
            connection_status VARCHAR(12) NOT NULL DEFAULT \'never\',
            last_tested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            last_error VARCHAR(500) DEFAULT NULL,
            last_success_sync_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_ext_source_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE,
            CONSTRAINT chk_ga_ext_source_auth CHECK (auth_type IN (\'none\', \'bearer\', \'header\', \'basic\')),
            CONSTRAINT chk_ga_ext_source_status CHECK (connection_status IN (\'never\', \'ok\', \'error\'))
        )');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_ext_source_id_dept ON department_grossanlass_ext_source (id, department_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_ext_source_name ON department_grossanlass_ext_source (department_id, LOWER(name))');

        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_ext_payload (
            id CHARACTER(12) NOT NULL,
            source_id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            origin VARCHAR(8) NOT NULL,
            http_status INT DEFAULT NULL,
            body_hash CHAR(64) NOT NULL,
            body TEXT NOT NULL,
            byte_size INT NOT NULL,
            fetched_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            seen_count INT NOT NULL DEFAULT 1,
            fetched_by_user_id CHARACTER(12) DEFAULT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_ext_payload_source FOREIGN KEY (source_id, department_id) REFERENCES department_grossanlass_ext_source (id, department_id) ON DELETE CASCADE,
            CONSTRAINT chk_ga_ext_payload_origin CHECK (origin IN (\'fetch\', \'import\'))
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_ext_payload_source ON department_grossanlass_ext_payload (source_id, fetched_at)');

        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_ext_record (
            id CHARACTER(12) NOT NULL,
            source_id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            external_key VARCHAR(190) NOT NULL,
            state VARCHAR(12) NOT NULL DEFAULT \'active\',
            data JSON NOT NULL DEFAULT \'{}\',
            content_hash CHAR(64) NOT NULL,
            payload_id CHARACTER(12) DEFAULT NULL,
            helper_id CHARACTER(12) DEFAULT NULL,
            first_imported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            last_synced_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_ext_record_source FOREIGN KEY (source_id, department_id) REFERENCES department_grossanlass_ext_source (id, department_id) ON DELETE CASCADE,
            CONSTRAINT fk_ga_ext_record_payload FOREIGN KEY (payload_id) REFERENCES department_grossanlass_ext_payload (id) ON DELETE SET NULL,
            CONSTRAINT fk_ga_ext_record_helper FOREIGN KEY (helper_id) REFERENCES department_grossanlass_helper (id) ON DELETE SET NULL,
            CONSTRAINT chk_ga_ext_record_state CHECK (state IN (\'active\', \'cancelled\', \'removed\'))
        )');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_ext_record_key ON department_grossanlass_ext_record (source_id, external_key)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_ext_record_helper ON department_grossanlass_ext_record (helper_id)');

        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_ext_run (
            id CHARACTER(12) NOT NULL,
            source_id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            payload_id CHARACTER(12) DEFAULT NULL,
            started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            outcome VARCHAR(10) NOT NULL,
            created_count INT NOT NULL DEFAULT 0,
            updated_count INT NOT NULL DEFAULT 0,
            unchanged_count INT NOT NULL DEFAULT 0,
            removed_count INT NOT NULL DEFAULT 0,
            conflict_count INT NOT NULL DEFAULT 0,
            errors JSON NOT NULL DEFAULT \'[]\',
            user_id CHARACTER(12) DEFAULT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_ext_run_source FOREIGN KEY (source_id, department_id) REFERENCES department_grossanlass_ext_source (id, department_id) ON DELETE CASCADE,
            CONSTRAINT fk_ga_ext_run_payload FOREIGN KEY (payload_id) REFERENCES department_grossanlass_ext_payload (id) ON DELETE SET NULL,
            CONSTRAINT chk_ga_ext_run_outcome CHECK (outcome IN (\'ok\', \'partial\', \'failed\'))
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_ext_run_source ON department_grossanlass_ext_run (source_id, started_at)');

        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_ext_change (
            id CHARACTER(12) NOT NULL,
            record_id CHARACTER(12) NOT NULL,
            run_id CHARACTER(12) DEFAULT NULL,
            change_type VARCHAR(12) NOT NULL,
            old_data JSON DEFAULT NULL,
            new_data JSON DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_ext_change_record FOREIGN KEY (record_id) REFERENCES department_grossanlass_ext_record (id) ON DELETE CASCADE,
            CONSTRAINT fk_ga_ext_change_run FOREIGN KEY (run_id) REFERENCES department_grossanlass_ext_run (id) ON DELETE SET NULL
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_ext_change_record ON department_grossanlass_ext_change (record_id, created_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_ext_change_run ON department_grossanlass_ext_change (run_id)');

        $this->addSql('ALTER TABLE department_grossanlass_helper ADD COLUMN IF NOT EXISTS ext_source_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_helper ADD COLUMN IF NOT EXISTS ext_key VARCHAR(190) DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_helper ADD CONSTRAINT fk_ga_helper_ext_source FOREIGN KEY (ext_source_id) REFERENCES department_grossanlass_ext_source (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_helper_ext ON department_grossanlass_helper (ext_source_id, ext_key) WHERE ext_source_id IS NOT NULL');

        $this->addSql('ALTER TABLE department_grossanlass_helper_availability ADD COLUMN IF NOT EXISTS ext_record_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_helper_availability ADD CONSTRAINT fk_ga_helper_avail_ext FOREIGN KEY (ext_record_id) REFERENCES department_grossanlass_ext_record (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_helper_avail_ext ON department_grossanlass_helper_availability (ext_record_id) WHERE ext_record_id IS NOT NULL');

        $this->addSql('ALTER TABLE department_grossanlass_staffing_assignment ADD COLUMN IF NOT EXISTS status_before_conflict VARCHAR(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_staffing_assignment ADD COLUMN IF NOT EXISTS conflict_info JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_staffing_assignment ADD COLUMN IF NOT EXISTS conflict_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_staffing_assignment DROP CONSTRAINT IF EXISTS chk_ga_staffing_assign_status');
        $this->addSql('ALTER TABLE department_grossanlass_staffing_assignment ADD CONSTRAINT chk_ga_staffing_assign_status CHECK (status IN (\'planned\', \'confirmed\', \'cancelled\', \'conflict\'))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE department_grossanlass_staffing_assignment SET status = COALESCE(status_before_conflict, \'planned\') WHERE status = \'conflict\'');
        $this->addSql('ALTER TABLE department_grossanlass_staffing_assignment DROP CONSTRAINT IF EXISTS chk_ga_staffing_assign_status');
        $this->addSql('ALTER TABLE department_grossanlass_staffing_assignment ADD CONSTRAINT chk_ga_staffing_assign_status CHECK (status IN (\'planned\', \'confirmed\', \'cancelled\'))');
        $this->addSql('ALTER TABLE department_grossanlass_staffing_assignment DROP COLUMN IF EXISTS conflict_at, DROP COLUMN IF EXISTS conflict_info, DROP COLUMN IF EXISTS status_before_conflict');
        $this->addSql('DROP INDEX IF EXISTS uniq_ga_helper_avail_ext');
        $this->addSql('ALTER TABLE department_grossanlass_helper_availability DROP COLUMN IF EXISTS ext_record_id');
        $this->addSql('DROP INDEX IF EXISTS uniq_ga_helper_ext');
        $this->addSql('ALTER TABLE department_grossanlass_helper DROP COLUMN IF EXISTS ext_key, DROP COLUMN IF EXISTS ext_source_id');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_ext_change');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_ext_run');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_ext_record');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_ext_payload');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_ext_source');
    }
}
