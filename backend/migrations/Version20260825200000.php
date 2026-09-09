<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Druckkatalog: Geräte, Medien, Department-Favoriten + Seed Avery/Brother';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS print_device_model (
    id CHARACTER(12) NOT NULL,
    catalog_key VARCHAR(64) NOT NULL,
    family VARCHAR(32) NOT NULL,
    brand VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    compatible_media_keys JSON NOT NULL,
    status VARCHAR(20) NOT NULL,
    scope VARCHAR(20) NOT NULL,
    organisation_id CHARACTER(12) DEFAULT NULL,
    created_by_user_id CHARACTER(12) DEFAULT NULL,
    reviewed_by_user_id CHARACTER(12) DEFAULT NULL,
    reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_print_device_model_key ON print_device_model (catalog_key)');

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS print_media (
    id CHARACTER(12) NOT NULL,
    catalog_key VARCHAR(64) NOT NULL,
    family VARCHAR(32) NOT NULL,
    brand VARCHAR(80) NOT NULL,
    sku VARCHAR(64) NOT NULL,
    name VARCHAR(160) NOT NULL,
    width_mm NUMERIC(8, 2) NOT NULL,
    height_mm NUMERIC(8, 2) DEFAULT NULL,
    cols INT DEFAULT 1 NOT NULL,
    rows INT DEFAULT 1 NOT NULL,
    is_continuous BOOLEAN DEFAULT FALSE NOT NULL,
    default_cut_length_mm INT DEFAULT NULL,
    status VARCHAR(20) NOT NULL,
    scope VARCHAR(20) NOT NULL,
    organisation_id CHARACTER(12) DEFAULT NULL,
    created_by_user_id CHARACTER(12) DEFAULT NULL,
    reviewed_by_user_id CHARACTER(12) DEFAULT NULL,
    reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_print_media_key ON print_media (catalog_key)');

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS department_print_preset (
    id CHARACTER(12) NOT NULL,
    department_id CHARACTER(12) NOT NULL,
    name VARCHAR(120) NOT NULL,
    device_model_id CHARACTER(12) NOT NULL,
    media_id CHARACTER(12) NOT NULL,
    cut_length_mm INT DEFAULT NULL,
    is_default BOOLEAN DEFAULT FALSE NOT NULL,
    created_by_user_id CHARACTER(12) DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_dept_print_preset_dept ON department_print_preset (department_id)');
        $this->addSql('ALTER TABLE department_print_preset DROP CONSTRAINT IF EXISTS fk_dept_print_preset_dept');
        $this->addSql('ALTER TABLE department_print_preset ADD CONSTRAINT fk_dept_print_preset_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_print_preset DROP CONSTRAINT IF EXISTS fk_dept_print_preset_model');
        $this->addSql('ALTER TABLE department_print_preset ADD CONSTRAINT fk_dept_print_preset_model FOREIGN KEY (device_model_id) REFERENCES print_device_model (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_print_preset DROP CONSTRAINT IF EXISTS fk_dept_print_preset_media');
        $this->addSql('ALTER TABLE department_print_preset ADD CONSTRAINT fk_dept_print_preset_media FOREIGN KEY (media_id) REFERENCES print_media (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->addSql("INSERT INTO print_media (id, catalog_key, family, brand, sku, name, width_mm, height_mm, cols, rows, is_continuous, default_cut_length_mm, status, scope, created_at, updated_at) VALUES
            ('pm1120900001', 'brother_dk_11209', 'brother_ql', 'Brother', 'DK-11209', 'Brother DK-11209 62×29 mm', 62.00, 29.00, 1, 1, FALSE, NULL, 'published', 'global', '{$now}', '{$now}'),
            ('pm2222500001', 'brother_dk_22225', 'brother_ql', 'Brother', 'DK-22225', 'Brother DK-22225 38 mm Endlos', 38.00, NULL, 1, 1, TRUE, 55, 'published', 'global', '{$now}', '{$now}'),
            ('pm2220500001', 'brother_dk_22205', 'brother_ql', 'Brother', 'DK-22205', 'Brother DK-22205 62 mm Endlos', 62.00, NULL, 1, 1, TRUE, 40, 'published', 'global', '{$now}', '{$now}'),
            ('pml716000001', 'avery_l7160', 'office_a4', 'Avery Zweckform', 'L7160', 'Avery L7160 63,5×38,1 mm (21/A4)', 63.50, 38.10, 3, 7, FALSE, NULL, 'published', 'global', '{$now}', '{$now}'),
            ('pm3652000001', 'avery_3652', 'office_a4', 'Avery Zweckform', '3652', 'Avery 3652 70×42,3 mm (21/A4)', 70.00, 42.30, 3, 7, FALSE, NULL, 'published', 'global', '{$now}', '{$now}'),
            ('pm3655000001', 'avery_3655', 'office_a4', 'Avery Zweckform', '3655', 'Avery 3655 105×48 mm (12/A4)', 105.00, 48.00, 2, 6, FALSE, NULL, 'published', 'global', '{$now}', '{$now}')
            ON CONFLICT (id) DO NOTHING");

        $this->addSql("INSERT INTO print_device_model (id, catalog_key, family, brand, name, compatible_media_keys, status, scope, created_at, updated_at) VALUES
            ('dmql820nwb01', 'brother_ql_820nwb', 'brother_ql', 'Brother', 'QL-820NWB', '[\"brother_dk_11209\",\"brother_dk_22225\",\"brother_dk_22205\"]'::json, 'published', 'global', '{$now}', '{$now}'),
            ('dmql70000001', 'brother_ql_700', 'brother_ql', 'Brother', 'QL-700', '[\"brother_dk_11209\",\"brother_dk_22225\",\"brother_dk_22205\"]'::json, 'published', 'global', '{$now}', '{$now}'),
            ('dma4laser001', 'office_a4_laser', 'office_a4', 'Büro', 'Laser / Inkjet A4', '[\"avery_l7160\",\"avery_3652\",\"avery_3655\"]'::json, 'published', 'global', '{$now}', '{$now}')
            ON CONFLICT (id) DO NOTHING");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_print_preset DROP CONSTRAINT IF EXISTS fk_dept_print_preset_dept');
        $this->addSql('ALTER TABLE department_print_preset DROP CONSTRAINT IF EXISTS fk_dept_print_preset_model');
        $this->addSql('ALTER TABLE department_print_preset DROP CONSTRAINT IF EXISTS fk_dept_print_preset_media');
        $this->addSql('DROP TABLE IF EXISTS department_print_preset');
        $this->addSql('DROP TABLE IF EXISTS print_media');
        $this->addSql('DROP TABLE IF EXISTS print_device_model');
    }
}
