<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Helferbedarf je Aufgabe: department_grossanlass_task_helper_need (Gesamtzahl label = \'\', optionale Funktionen). Bestehender Gruppenbedarf bleibt unverändert.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_task_helper_need (
            task_id CHARACTER(12) NOT NULL,
            label VARCHAR(120) NOT NULL DEFAULT \'\',
            required_count INT NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (task_id, label),
            CONSTRAINT fk_ga_task_helper_need_task FOREIGN KEY (task_id) REFERENCES department_grossanlass_task (id) ON DELETE CASCADE
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_task_helper_need');
    }
}
