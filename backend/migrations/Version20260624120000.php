<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624120000 extends AbstractMigration
{
    use CreatesTableUnlessExistsTrait;

    public function getDescription(): string
    {
        return 'Grossanlass: department.is_grossanlass, department_grossanlass_config, activity_grossanlass_config';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department ADD COLUMN IF NOT EXISTS is_grossanlass BOOLEAN NOT NULL DEFAULT FALSE');

        if ($this->prepareNewTable($schema, 'department_grossanlass_config')) {
            $this->addSql(<<<'SQL'
CREATE TABLE department_grossanlass_config (
    department_id CHARACTER(12) NOT NULL,
    main_activity_id CHARACTER(12) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    published_by_user_id CHARACTER(12) DEFAULT NULL,
    struktur_modus VARCHAR(20) NOT NULL DEFAULT 'offen',
    planned_event_start TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    planned_event_end TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    PRIMARY KEY(department_id)
)
SQL);
            $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_config_department') THEN
        ALTER TABLE department_grossanlass_config ADD CONSTRAINT fk_grossanlass_config_department
            FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
            $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_config_main_activity') THEN
        ALTER TABLE department_grossanlass_config ADD CONSTRAINT fk_grossanlass_config_main_activity
            FOREIGN KEY (main_activity_id) REFERENCES activity (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        }

        if ($this->prepareNewTable($schema, 'activity_grossanlass_config')) {
            $this->addSql(<<<'SQL'
CREATE TABLE activity_grossanlass_config (
    activity_id CHARACTER(12) NOT NULL,
    grossanlass_role VARCHAR(20) NOT NULL,
    PRIMARY KEY(activity_id)
)
SQL);
            $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_activity_grossanlass_config_activity') THEN
        ALTER TABLE activity_grossanlass_config ADD CONSTRAINT fk_activity_grossanlass_config_activity
            FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_config DROP CONSTRAINT IF EXISTS fk_activity_grossanlass_config_activity');
        $this->addSql('DROP TABLE IF EXISTS activity_grossanlass_config');
        $this->addSql('ALTER TABLE department_grossanlass_config DROP CONSTRAINT IF EXISTS fk_grossanlass_config_main_activity');
        $this->addSql('ALTER TABLE department_grossanlass_config DROP CONSTRAINT IF EXISTS fk_grossanlass_config_department');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_config');
        $this->addSql('ALTER TABLE department DROP COLUMN IF EXISTS is_grossanlass');
    }
}
