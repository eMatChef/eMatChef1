<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Helferpool: Fähigkeitenkatalog pro GA-Department sowie Beruf, Fähigkeiten, Führerausweis, Qualifikationen und Ressort am Helfer.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_skill (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            name VARCHAR(80) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_ga_skill_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_skill_dept_name ON department_grossanlass_skill (department_id, LOWER(name))');

        $this->addSql('ALTER TABLE department_grossanlass_helper ADD COLUMN IF NOT EXISTS occupation VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_helper ADD COLUMN IF NOT EXISTS area VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_helper ADD COLUMN IF NOT EXISTS skills JSON NOT NULL DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE department_grossanlass_helper ADD COLUMN IF NOT EXISTS drive_classes JSON NOT NULL DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE department_grossanlass_helper ADD COLUMN IF NOT EXISTS drive_valid_until DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_helper ADD COLUMN IF NOT EXISTS qualifications JSON NOT NULL DEFAULT \'[]\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_helper DROP COLUMN IF EXISTS qualifications, DROP COLUMN IF EXISTS drive_valid_until, DROP COLUMN IF EXISTS drive_classes, DROP COLUMN IF EXISTS skills, DROP COLUMN IF EXISTS area, DROP COLUMN IF EXISTS occupation');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_skill');
    }
}
