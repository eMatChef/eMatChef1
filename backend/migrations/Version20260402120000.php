<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Budget-Soll pro Kostenstelle und Kalenderjahr (Vergleich mit Ist-Buchungen in der API).
 */
final class Version20260402120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create accounting_budget_line for planned amounts per cost center and year.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE accounting_budget_line (id CHARACTER(13) NOT NULL, department_id CHARACTER(12) NOT NULL, cost_center_id CHARACTER(13) NOT NULL, calendar_year INT NOT NULL, amount_chf NUMERIC(12, 2) NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uk_abl_dept_cc_year ON accounting_budget_line (department_id, cost_center_id, calendar_year)');
        $this->addSql('CREATE INDEX idx_abl_dept_year ON accounting_budget_line (department_id, calendar_year)');
        $this->addSql('ALTER TABLE accounting_budget_line ADD CONSTRAINT FK_ABL_DEPARTMENT FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE accounting_budget_line ADD CONSTRAINT FK_ABL_COST_CENTER FOREIGN KEY (cost_center_id) REFERENCES accounting_cost_center (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE accounting_budget_line');
    }
}
