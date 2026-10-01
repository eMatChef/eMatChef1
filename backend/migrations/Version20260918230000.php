<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918230000 extends AbstractMigration
{
    use CreatesTableUnlessExistsTrait;

    public function getDescription(): string
    {
        return 'Grossanlass: grobes Zeitfenster am Bauprojekt + Aufgaben (Crew-Arbeit, nicht Bedarf)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "group" ADD COLUMN IF NOT EXISTS window_start DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE "group" ADD COLUMN IF NOT EXISTS window_end DATE DEFAULT NULL');

        if ($this->prepareNewTable($schema, 'department_grossanlass_task')) {
            $this->addSql(<<<'SQL'
CREATE TABLE department_grossanlass_task (
    id CHARACTER(12) NOT NULL,
    department_id CHARACTER(12) NOT NULL,
    group_id CHARACTER(12) NOT NULL,
    title VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
            $this->addSql('CREATE INDEX idx_ga_task_dept ON department_grossanlass_task (department_id)');
            $this->addSql('CREATE INDEX idx_ga_task_group ON department_grossanlass_task (group_id)');
        }

        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_ga_task_dept') THEN
        ALTER TABLE department_grossanlass_task ADD CONSTRAINT fk_ga_task_dept
            FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_ga_task_group') THEN
        ALTER TABLE department_grossanlass_task ADD CONSTRAINT fk_ga_task_group
            FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_task DROP CONSTRAINT IF EXISTS fk_ga_task_group');
        $this->addSql('ALTER TABLE department_grossanlass_task DROP CONSTRAINT IF EXISTS fk_ga_task_dept');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_task');
        $this->addSql('ALTER TABLE "group" DROP COLUMN IF EXISTS window_end');
        $this->addSql('ALTER TABLE "group" DROP COLUMN IF EXISTS window_start');
    }
}
