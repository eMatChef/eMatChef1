<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Aufgabe: optionale Checkliste (department_grossanlass_task_step) und Blockierung (blocked_*). Bestehende Aufgaben bleiben unverändert.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_task_step (
            id CHARACTER(12) NOT NULL,
            task_id CHARACTER(12) NOT NULL,
            label VARCHAR(255) NOT NULL,
            sort_order INT DEFAULT 0 NOT NULL,
            is_done BOOLEAN DEFAULT false NOT NULL,
            done_by_user_id CHARACTER(12) DEFAULT NULL,
            done_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_task_step_task FOREIGN KEY (task_id) REFERENCES department_grossanlass_task (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_task_step_task ON department_grossanlass_task_step (task_id, sort_order)');
        $this->addSql('ALTER TABLE department_grossanlass_task ADD COLUMN IF NOT EXISTS blocked_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_task ADD COLUMN IF NOT EXISTS blocked_by_user_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_task ADD COLUMN IF NOT EXISTS blocked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_task_step');
        $this->addSql('ALTER TABLE department_grossanlass_task DROP COLUMN IF EXISTS blocked_reason');
        $this->addSql('ALTER TABLE department_grossanlass_task DROP COLUMN IF EXISTS blocked_by_user_id');
        $this->addSql('ALTER TABLE department_grossanlass_task DROP COLUMN IF EXISTS blocked_at');
    }
}
