<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Abhängigkeiten (department_grossanlass_dependency): Aufgabe→Aufgabe und Bauauftrag→Bauauftrag mit Freigabe- und Entfernungsprotokoll. Bestehende Daten bleiben unverändert.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_dependency (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            kind VARCHAR(8) NOT NULL,
            successor_id CHARACTER(12) NOT NULL,
            predecessor_id CHARACTER(12) NOT NULL,
            created_by_user_id CHARACTER(12) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            released_by_user_id CHARACTER(12) DEFAULT NULL,
            released_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            release_reason TEXT DEFAULT NULL,
            removed_by_user_id CHARACTER(12) DEFAULT NULL,
            removed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_dependency_department FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_dependency_successor ON department_grossanlass_dependency (department_id, kind, successor_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_dependency_predecessor ON department_grossanlass_dependency (department_id, kind, predecessor_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_dependency');
    }
}
