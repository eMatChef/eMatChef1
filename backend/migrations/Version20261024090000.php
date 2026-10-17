<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Aufgabe: Nachbesserung (department_grossanlass_task.rework_event_id, Verknüpfung mit dem Rückweisungsereignis). Bestehende Aufgaben bleiben normale Aufgaben (NULL).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_task ADD COLUMN IF NOT EXISTS rework_event_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_task_rework_event ON department_grossanlass_task (rework_event_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_ga_task_rework_event');
        $this->addSql('ALTER TABLE department_grossanlass_task DROP COLUMN IF EXISTS rework_event_id');
    }
}
