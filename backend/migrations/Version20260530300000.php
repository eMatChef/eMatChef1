<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paket 14 — workshop_ticket.assigned_to_supplier_company_id (Phase 3).
 */
final class Version20260530300000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supplier portal Paket 14: assign workshop tickets to supplier companies.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE workshop_ticket ADD assigned_to_supplier_company_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_workshop_supplier_company ON workshop_ticket (assigned_to_supplier_company_id)');
        $this->addSql('ALTER TABLE workshop_ticket ADD CONSTRAINT fk_workshop_supplier_company FOREIGN KEY (assigned_to_supplier_company_id) REFERENCES supplier_company (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE workshop_ticket DROP CONSTRAINT IF EXISTS fk_workshop_supplier_company');
        $this->addSql('DROP INDEX IF EXISTS idx_workshop_supplier_company');
        $this->addSql('ALTER TABLE workshop_ticket DROP COLUMN IF EXISTS assigned_to_supplier_company_id');
    }
}
