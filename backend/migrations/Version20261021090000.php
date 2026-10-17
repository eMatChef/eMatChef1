<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Aufgabe: Verlauf Fertigmeldung/Abnahme/Rückgabe (department_grossanlass_task_event). Status «review» braucht keine Schemaänderung (VARCHAR(16)); bestehende Aufgaben bleiben unverändert.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_task_event (
            id CHARACTER(12) NOT NULL,
            task_id CHARACTER(12) NOT NULL,
            type VARCHAR(16) NOT NULL,
            user_id CHARACTER(12) NOT NULL,
            note TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_task_event_task FOREIGN KEY (task_id) REFERENCES department_grossanlass_task (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_task_event_task ON department_grossanlass_task_event (task_id, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_task_event');
    }
}
