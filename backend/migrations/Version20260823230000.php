<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: eigene Werkstatt-Fälle (nicht workshop_ticket)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS department_grossanlass_workshop_case (
    id CHAR(12) NOT NULL,
    department_id CHAR(12) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    origin VARCHAR(16) NOT NULL DEFAULT 'loan',
    owner_firm_name VARCHAR(255) NOT NULL DEFAULT '',
    material_label VARCHAR(255) NOT NULL DEFAULT '',
    path VARCHAR(24) NOT NULL DEFAULT 'repair',
    status VARCHAR(24) NOT NULL DEFAULT 'open',
    created_by_id CHAR(12) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_workshop_case_dept ON department_grossanlass_workshop_case (department_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_workshop_case_status ON department_grossanlass_workshop_case (status)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    ALTER TABLE department_grossanlass_workshop_case
        ADD CONSTRAINT fk_ga_workshop_case_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_workshop_case');
    }
}
