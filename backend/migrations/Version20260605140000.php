<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Workshop-Ticket: strategy, phase und repair_checklist für Workflow 2026.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE workshop_ticket ADD strategy VARCHAR(30) DEFAULT 'triage' NOT NULL");
        $this->addSql('ALTER TABLE workshop_ticket ADD phase VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE workshop_ticket ADD repair_checklist JSON DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_workshop_strategy ON workshop_ticket (strategy)');
        $this->addSql('CREATE INDEX idx_workshop_phase ON workshop_ticket (phase)');

        // Bestehende Tickets: Alt-Status → strategy/phase (§9.3 materialwart-workflow2026)
        $this->addSql("UPDATE workshop_ticket SET strategy = 'triage', phase = NULL WHERE status = 'open'");

        $this->addSql("
            UPDATE workshop_ticket SET
                strategy = CASE
                    WHEN type = 'writeoff' THEN 'writeoff'
                    WHEN type = 'inspection' THEN 'inspection'
                    WHEN type = 'cleaning' AND assigned_to_supplier_company_id IS NOT NULL THEN 'external_cleaning'
                    WHEN assigned_to_supplier_company_id IS NOT NULL THEN 'external_repair'
                    ELSE 'internal_repair'
                END,
                phase = 'in_progress'
            WHERE status = 'in_progress'
        ");

        $this->addSql("
            UPDATE workshop_ticket SET
                strategy = CASE
                    WHEN assigned_to_supplier_company_id IS NOT NULL THEN 'external_repair'
                    ELSE 'internal_repair'
                END,
                phase = CASE
                    WHEN assigned_to_supplier_company_id IS NOT NULL THEN 'awaiting_quote'
                    ELSE 'ordered'
                END
            WHERE status = 'waiting_parts'
        ");

        $this->addSql("
            UPDATE workshop_ticket SET
                strategy = CASE
                    WHEN type = 'writeoff' THEN 'writeoff'
                    WHEN type = 'inspection' THEN 'inspection'
                    WHEN type = 'cleaning' AND assigned_to_supplier_company_id IS NOT NULL THEN 'external_cleaning'
                    WHEN assigned_to_supplier_company_id IS NOT NULL THEN 'external_repair'
                    ELSE 'internal_repair'
                END,
                phase = 'completed'
            WHERE status = 'completed'
        ");

        $this->addSql("UPDATE workshop_ticket SET phase = 'cancelled' WHERE status = 'cancelled'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_workshop_phase');
        $this->addSql('DROP INDEX IF EXISTS idx_workshop_strategy');
        $this->addSql('ALTER TABLE workshop_ticket DROP COLUMN IF EXISTS repair_checklist');
        $this->addSql('ALTER TABLE workshop_ticket DROP COLUMN IF EXISTS phase');
        $this->addSql('ALTER TABLE workshop_ticket DROP COLUMN IF EXISTS strategy');
    }
}
