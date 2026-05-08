<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Kostenstellen für die unterstützende Buchhaltung (pro Department).
 */
final class Version20260330120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create accounting_cost_center for department cost centers (budget lines).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE accounting_cost_center (
            id CHARACTER(13) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            name VARCHAR(255) NOT NULL,
            account_code VARCHAR(32) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_acc_cc_department ON accounting_cost_center (department_id)');
        $this->addSql('ALTER TABLE accounting_cost_center ADD CONSTRAINT FK_ACC_CC_DEPARTMENT FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE accounting_cost_center');
    }
}
