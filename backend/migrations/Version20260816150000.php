<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Überschuss-Meldungen bei Aktivitäts-Retour (Esswaren/Verbrauch) — Basis für MW-Abarbeitung.
 * Spec: docs/activities/surplus-return-food.md
 */
final class Version20260816150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add activity_surplus_report for leftover food/consumables on activity return';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS activity_surplus_report (
    id CHAR(13) NOT NULL,
    department_id CHAR(12) NOT NULL,
    activity_id CHAR(12) NOT NULL,
    reported_by_user_id CHAR(12) DEFAULT NULL,
    name_free_text VARCHAR(255) NOT NULL,
    qty INT NOT NULL DEFAULT 1,
    kind VARCHAR(20) NOT NULL DEFAULT 'food',
    expiry_date DATE DEFAULT NULL,
    material_item_id CHAR(12) DEFAULT NULL,
    resolved_batch_id CHAR(13) DEFAULT NULL,
    inventory_task_id CHAR(12) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
)
SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_surplus_report_activity ON activity_surplus_report (activity_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_surplus_report_department ON activity_surplus_report (department_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_surplus_report_status ON activity_surplus_report (status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_surplus_report_material ON activity_surplus_report (material_item_id)');

        $this->addSql(<<<'SQL'
DO $$ BEGIN
  ALTER TABLE activity_surplus_report
    ADD CONSTRAINT fk_surplus_report_department
    FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
  ALTER TABLE activity_surplus_report
    ADD CONSTRAINT fk_surplus_report_activity
    FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
  ALTER TABLE activity_surplus_report
    ADD CONSTRAINT fk_surplus_report_reporter
    FOREIGN KEY (reported_by_user_id) REFERENCES "user" (id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
  ALTER TABLE activity_surplus_report
    ADD CONSTRAINT fk_surplus_report_material
    FOREIGN KEY (material_item_id) REFERENCES material_item (id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
  ALTER TABLE activity_surplus_report
    ADD CONSTRAINT fk_surplus_report_batch
    FOREIGN KEY (resolved_batch_id) REFERENCES material_batch (id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
  ALTER TABLE activity_surplus_report
    ADD CONSTRAINT fk_surplus_report_inventory_task
    FOREIGN KEY (inventory_task_id) REFERENCES inventory_task (id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS activity_surplus_report');
    }
}
