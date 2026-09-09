<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drucklayouts + Bogen-Geometrie (Ränder/Raster) + Avery-PDF-Speicher';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE print_media ADD COLUMN IF NOT EXISTS shape VARCHAR(16) DEFAULT 'rect' NOT NULL");
        $this->addSql('ALTER TABLE print_media ADD COLUMN IF NOT EXISTS sheet_width_mm NUMERIC(8, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE print_media ADD COLUMN IF NOT EXISTS sheet_height_mm NUMERIC(8, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE print_media ADD COLUMN IF NOT EXISTS margin_top_mm NUMERIC(8, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE print_media ADD COLUMN IF NOT EXISTS margin_left_mm NUMERIC(8, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE print_media ADD COLUMN IF NOT EXISTS gap_x_mm NUMERIC(8, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE print_media ADD COLUMN IF NOT EXISTS gap_y_mm NUMERIC(8, 2) DEFAULT NULL');

        $this->addSql("UPDATE print_media SET shape = 'round' WHERE catalog_key IN ('brother_dk_11219', 'avery_l4716')");
        $this->addSql("UPDATE print_media SET sheet_width_mm = 210, sheet_height_mm = 297 WHERE family = 'office_a4'");
        $this->addSql("UPDATE print_media SET margin_top_mm = 6, margin_left_mm = 0, gap_x_mm = 0, gap_y_mm = 0 WHERE catalog_key = 'avery_3425'");
        $this->addSql("UPDATE print_media SET margin_top_mm = 4.5, margin_left_mm = 0, gap_x_mm = 0, gap_y_mm = 0 WHERE catalog_key = 'avery_6122'");
        $this->addSql("UPDATE print_media SET margin_top_mm = 21.5, margin_left_mm = 13.6, gap_x_mm = 0, gap_y_mm = 0 WHERE catalog_key = 'avery_l6140'");
        $this->addSql("UPDATE print_media SET margin_top_mm = 28.5, margin_left_mm = 15, gap_x_mm = 0, gap_y_mm = 0 WHERE catalog_key = 'avery_l4716'");
        $this->addSql("UPDATE print_media SET margin_top_mm = 21.6, margin_left_mm = 5.9, gap_x_mm = 0, gap_y_mm = 0 WHERE catalog_key = 'avery_l6107'");
        $this->addSql("UPDATE print_media SET margin_top_mm = 15.15, margin_left_mm = 9.75, gap_x_mm = 0, gap_y_mm = 0 WHERE catalog_key = 'avery_l7160'");
        $this->addSql("UPDATE print_media SET margin_top_mm = 0.45, margin_left_mm = 0, gap_x_mm = 0, gap_y_mm = 0 WHERE catalog_key = 'avery_3652'");
        $this->addSql("UPDATE print_media SET sheet_width_mm = width_mm, sheet_height_mm = COALESCE(height_mm, width_mm), margin_top_mm = 0, margin_left_mm = 0, gap_x_mm = 0, gap_y_mm = 0 WHERE family = 'brother_ql'");
        $this->addSql("UPDATE print_media SET sheet_width_mm = width_mm, sheet_height_mm = COALESCE(height_mm, width_mm), margin_top_mm = 0, margin_left_mm = 0 WHERE catalog_key LIKE 'iso_a%'");

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS print_layout (
    id CHARACTER(12) NOT NULL,
    name VARCHAR(120) NOT NULL,
    media_id CHARACTER(12) NOT NULL,
    department_id CHARACTER(12) DEFAULT NULL,
    organisation_id CHARACTER(12) DEFAULT NULL,
    fields JSON NOT NULL,
    template_filename VARCHAR(180) DEFAULT NULL,
    include_template_on_print BOOLEAN DEFAULT FALSE NOT NULL,
    status VARCHAR(20) NOT NULL,
    scope VARCHAR(20) NOT NULL,
    global_requested BOOLEAN DEFAULT FALSE NOT NULL,
    created_by_user_id CHARACTER(12) DEFAULT NULL,
    reviewed_by_user_id CHARACTER(12) DEFAULT NULL,
    reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_print_layout_org ON print_layout (organisation_id)');
        $this->addSql('ALTER TABLE print_layout DROP CONSTRAINT IF EXISTS fk_print_layout_media');
        $this->addSql('ALTER TABLE print_layout ADD CONSTRAINT fk_print_layout_media FOREIGN KEY (media_id) REFERENCES print_media (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE print_layout DROP CONSTRAINT IF EXISTS fk_print_layout_media');
        $this->addSql('DROP TABLE IF EXISTS print_layout');
        $this->addSql('ALTER TABLE print_media DROP COLUMN IF EXISTS shape');
        $this->addSql('ALTER TABLE print_media DROP COLUMN IF EXISTS sheet_width_mm');
        $this->addSql('ALTER TABLE print_media DROP COLUMN IF EXISTS sheet_height_mm');
        $this->addSql('ALTER TABLE print_media DROP COLUMN IF EXISTS margin_top_mm');
        $this->addSql('ALTER TABLE print_media DROP COLUMN IF EXISTS margin_left_mm');
        $this->addSql('ALTER TABLE print_media DROP COLUMN IF EXISTS gap_x_mm');
        $this->addSql('ALTER TABLE print_media DROP COLUMN IF EXISTS gap_y_mm');
    }
}
