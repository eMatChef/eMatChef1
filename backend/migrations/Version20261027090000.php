<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Abhängigkeiten: Art (sequential|simultaneous) und Protokoll der Änderung. Bestehende Zeilen bleiben «sequential».';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE department_grossanlass_dependency ADD COLUMN IF NOT EXISTS mode VARCHAR(16) NOT NULL DEFAULT 'sequential'");
        $this->addSql('ALTER TABLE department_grossanlass_dependency ADD COLUMN IF NOT EXISTS mode_changed_by_user_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_dependency ADD COLUMN IF NOT EXISTS mode_changed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_dependency DROP COLUMN IF EXISTS mode_changed_at');
        $this->addSql('ALTER TABLE department_grossanlass_dependency DROP COLUMN IF EXISTS mode_changed_by_user_id');
        $this->addSql('ALTER TABLE department_grossanlass_dependency DROP COLUMN IF EXISTS mode');
    }
}
