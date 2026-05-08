<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ausstehende Buchhaltungs-Zuordnung nach Anschaffung (pending / recorded).
 */
final class Version20260401130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create accounting_acquisition_follow_up for pending cost-center assignment.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE accounting_acquisition_follow_up (id CHARACTER(12) NOT NULL, department_id CHARACTER(12) NOT NULL, material_batch_id CHARACTER(13) DEFAULT NULL, amount NUMERIC(12, 2) NOT NULL, suggested_date DATE NOT NULL, receipt_label VARCHAR(255) DEFAULT NULL, status VARCHAR(16) NOT NULL, accounting_booking_id CHARACTER(13) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_aafu_department_status ON accounting_acquisition_follow_up (department_id, status)');
        $this->addSql('ALTER TABLE accounting_acquisition_follow_up ADD CONSTRAINT FK_AAFU_DEPARTMENT FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE accounting_acquisition_follow_up ADD CONSTRAINT FK_AAFU_MATERIAL_BATCH FOREIGN KEY (material_batch_id) REFERENCES material_batch (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE accounting_acquisition_follow_up ADD CONSTRAINT FK_AAFU_BOOKING FOREIGN KEY (accounting_booking_id) REFERENCES accounting_booking (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE accounting_acquisition_follow_up');
    }
}
