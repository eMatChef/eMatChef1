<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass Kosten-Ledger und Rahmen pro Zahler';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE department_grossanlass_budget (
    id CHARACTER(12) NOT NULL,
    department_id CHARACTER(12) NOT NULL,
    payer_group_id CHARACTER(12) DEFAULT NULL,
    rahmen_chf NUMERIC(12, 2) NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        $this->addSql('CREATE INDEX idx_ga_budget_dept ON department_grossanlass_budget (department_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_ga_budget_dept_payer ON department_grossanlass_budget (department_id, payer_group_id) WHERE payer_group_id IS NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uq_ga_budget_dept_central ON department_grossanlass_budget (department_id) WHERE payer_group_id IS NULL');
        $this->addSql('ALTER TABLE department_grossanlass_budget ADD CONSTRAINT fk_ga_budget_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_budget ADD CONSTRAINT fk_ga_budget_payer FOREIGN KEY (payer_group_id) REFERENCES "group" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
CREATE TABLE department_grossanlass_cost (
    id CHARACTER(12) NOT NULL,
    department_id CHARACTER(12) NOT NULL,
    procurement_line_id CHARACTER(12) DEFAULT NULL,
    commitment_id CHARACTER(12) DEFAULT NULL,
    cost_kind VARCHAR(16) NOT NULL,
    asset_treatment VARCHAR(16) DEFAULT NULL,
    requesting_group_id CHARACTER(12) DEFAULT NULL,
    payer_group_id CHARACTER(12) DEFAULT NULL,
    category_id CHARACTER(12) DEFAULT NULL,
    label VARCHAR(255) NOT NULL,
    partner_address_id CHARACTER(12) DEFAULT NULL,
    soll_chf NUMERIC(12, 2) DEFAULT NULL,
    cash_out_chf NUMERIC(12, 2) DEFAULT NULL,
    deposit_chf NUMERIC(12, 2) DEFAULT NULL,
    deposit_returned_chf NUMERIC(12, 2) DEFAULT NULL,
    proceeds_expected_chf NUMERIC(12, 2) DEFAULT NULL,
    proceeds_actual_chf NUMERIC(12, 2) DEFAULT NULL,
    status VARCHAR(16) NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        $this->addSql('CREATE INDEX idx_ga_cost_dept ON department_grossanlass_cost (department_id)');
        $this->addSql('CREATE INDEX idx_ga_cost_dept_payer ON department_grossanlass_cost (department_id, payer_group_id)');
        $this->addSql('CREATE INDEX idx_ga_cost_dept_requesting ON department_grossanlass_cost (department_id, requesting_group_id)');
        $this->addSql('CREATE INDEX idx_ga_cost_dept_kind ON department_grossanlass_cost (department_id, cost_kind)');
        $this->addSql('CREATE INDEX idx_ga_cost_line ON department_grossanlass_cost (procurement_line_id)');
        $this->addSql('CREATE INDEX idx_ga_cost_commitment ON department_grossanlass_cost (commitment_id)');
        $this->addSql('ALTER TABLE department_grossanlass_cost ADD CONSTRAINT fk_ga_cost_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_cost ADD CONSTRAINT fk_ga_cost_line FOREIGN KEY (procurement_line_id) REFERENCES activity_grossanlass_procurement_line (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_cost ADD CONSTRAINT fk_ga_cost_commitment FOREIGN KEY (commitment_id) REFERENCES department_grossanlass_commitment (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_cost ADD CONSTRAINT fk_ga_cost_requesting FOREIGN KEY (requesting_group_id) REFERENCES "group" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_cost ADD CONSTRAINT fk_ga_cost_payer FOREIGN KEY (payer_group_id) REFERENCES "group" (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_cost ADD CONSTRAINT fk_ga_cost_category FOREIGN KEY (category_id) REFERENCES activity_grossanlass_procurement_category (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE department_grossanlass_cost ADD CONSTRAINT fk_ga_cost_partner FOREIGN KEY (partner_address_id) REFERENCES address (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
INSERT INTO department_grossanlass_budget (id, department_id, payer_group_id, rahmen_chf, updated_at)
SELECT 'kb' || substr(md5(department_id || 'central'), 1, 10), department_id, NULL, rahmen_chf, updated_at
FROM activity_grossanlass_procurement_finance
WHERE rahmen_chf IS NOT NULL
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_cost DROP CONSTRAINT fk_ga_cost_partner');
        $this->addSql('ALTER TABLE department_grossanlass_cost DROP CONSTRAINT fk_ga_cost_category');
        $this->addSql('ALTER TABLE department_grossanlass_cost DROP CONSTRAINT fk_ga_cost_payer');
        $this->addSql('ALTER TABLE department_grossanlass_cost DROP CONSTRAINT fk_ga_cost_requesting');
        $this->addSql('ALTER TABLE department_grossanlass_cost DROP CONSTRAINT fk_ga_cost_commitment');
        $this->addSql('ALTER TABLE department_grossanlass_cost DROP CONSTRAINT fk_ga_cost_line');
        $this->addSql('ALTER TABLE department_grossanlass_cost DROP CONSTRAINT fk_ga_cost_dept');
        $this->addSql('DROP TABLE department_grossanlass_cost');
        $this->addSql('ALTER TABLE department_grossanlass_budget DROP CONSTRAINT fk_ga_budget_payer');
        $this->addSql('ALTER TABLE department_grossanlass_budget DROP CONSTRAINT fk_ga_budget_dept');
        $this->addSql('DROP TABLE department_grossanlass_budget');
    }
}
