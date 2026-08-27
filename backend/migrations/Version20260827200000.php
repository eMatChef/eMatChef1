<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass User-Karten: Fahrklassen und Nachweis-Felder';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE department_grossanlass_user_card ADD COLUMN IF NOT EXISTS drive_classes JSON DEFAULT '[]' NOT NULL");
        $this->addSql("ALTER TABLE department_grossanlass_user_card ADD COLUMN IF NOT EXISTS drive_proof_kind VARCHAR(16) DEFAULT 'none' NOT NULL");
        $this->addSql('ALTER TABLE department_grossanlass_user_card ADD COLUMN IF NOT EXISTS drive_verified BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_user_card ADD COLUMN IF NOT EXISTS drive_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_user_card ADD COLUMN IF NOT EXISTS drive_verified_by_id CHARACTER(12) DEFAULT NULL');
        $this->addSql("ALTER TABLE department_grossanlass_user_card ADD COLUMN IF NOT EXISTS drive_document_filename VARCHAR(255) DEFAULT '' NOT NULL");
        $this->addSql("ALTER TABLE department_grossanlass_user_card ADD COLUMN IF NOT EXISTS drive_document_original_name VARCHAR(255) DEFAULT '' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_user_card DROP COLUMN IF EXISTS drive_document_original_name');
        $this->addSql('ALTER TABLE department_grossanlass_user_card DROP COLUMN IF EXISTS drive_document_filename');
        $this->addSql('ALTER TABLE department_grossanlass_user_card DROP COLUMN IF EXISTS drive_verified_by_id');
        $this->addSql('ALTER TABLE department_grossanlass_user_card DROP COLUMN IF EXISTS drive_verified_at');
        $this->addSql('ALTER TABLE department_grossanlass_user_card DROP COLUMN IF EXISTS drive_verified');
        $this->addSql('ALTER TABLE department_grossanlass_user_card DROP COLUMN IF EXISTS drive_proof_kind');
        $this->addSql('ALTER TABLE department_grossanlass_user_card DROP COLUMN IF EXISTS drive_classes');
    }
}
