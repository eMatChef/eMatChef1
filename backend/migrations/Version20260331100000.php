<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260331100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create accounting_booking (Kostenbuchungen CHF, mw/dc).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE accounting_booking (
            id CHARACTER(13) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            cost_center_id CHARACTER(13) NOT NULL,
            group_id CHARACTER(12) DEFAULT NULL,
            amount NUMERIC(12, 2) NOT NULL,
            booked_at DATE NOT NULL,
            entry_type VARCHAR(32) NOT NULL,
            payment_method VARCHAR(32) DEFAULT NULL,
            receipt_label VARCHAR(255) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_ab_department_booked ON accounting_booking (department_id, booked_at)');
        $this->addSql('CREATE INDEX idx_ab_cost_center ON accounting_booking (cost_center_id)');
        $this->addSql('ALTER TABLE accounting_booking ADD CONSTRAINT FK_AB_DEPARTMENT FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE accounting_booking ADD CONSTRAINT FK_AB_COST_CENTER FOREIGN KEY (cost_center_id) REFERENCES accounting_cost_center (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE accounting_booking ADD CONSTRAINT FK_AB_GROUP FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE accounting_booking');
    }
}
