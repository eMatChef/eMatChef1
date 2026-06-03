<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Buchhaltung: Zahlungsstatus, Zuordnungsregeln (source_kind → Kostenstelle).
 */
final class Version20260603140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accounting: payment_status on bookings, cost center mapping rules.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE accounting_booking ADD COLUMN IF NOT EXISTS payment_status VARCHAR(16) NOT NULL DEFAULT 'paid'");
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ab_payment_status ON accounting_booking (department_id, payment_status)');

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS accounting_cost_center_rule (
    id CHAR(13) NOT NULL,
    department_id CHAR(12) NOT NULL,
    source_kind VARCHAR(32) NOT NULL,
    cost_center_id CHAR(13) NOT NULL,
    default_entry_type VARCHAR(32) DEFAULT NULL,
    default_payment_method VARCHAR(32) DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uk_accr_dept_source ON accounting_cost_center_rule (department_id, source_kind)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_accr_dept ON accounting_cost_center_rule (department_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS accounting_cost_center_rule');
        $this->addSql('DROP INDEX IF EXISTS idx_ab_payment_status');
        $this->addSql('ALTER TABLE accounting_booking DROP COLUMN IF EXISTS payment_status');
    }
}
