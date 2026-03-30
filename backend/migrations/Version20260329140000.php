<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Hinweise vom öffentlichen QR-Kontaktformular (Nachrichtenzentrale in der App).
 */
final class Version20260329140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create public_found_item_message for QR contact delivery to in-app inbox.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE public_found_item_message (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            entity_type VARCHAR(8) NOT NULL,
            material_id CHARACTER(12) DEFAULT NULL,
            batch_id VARCHAR(20) DEFAULT NULL,
            public_code VARCHAR(64) NOT NULL,
            material_name VARCHAR(512) NOT NULL,
            department_name VARCHAR(512) NOT NULL,
            serial_line VARCHAR(512) DEFAULT NULL,
            message TEXT NOT NULL,
            sender_name VARCHAR(120) DEFAULT NULL,
            sender_email VARCHAR(255) DEFAULT NULL,
            public_url VARCHAR(512) NOT NULL,
            read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            read_by_user_id CHARACTER(12) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_pfim_dept_unread ON public_found_item_message (department_id, read_at)');
        $this->addSql('ALTER TABLE public_found_item_message ADD CONSTRAINT FK_PFIM_DEPARTMENT FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE public_found_item_message');
    }
}
