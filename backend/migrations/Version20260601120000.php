<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Department calendar periods (Fixe Daten) for manual vacation/break ranges.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE department_calendar_period (
    id CHAR(12) NOT NULL,
    department_id CHAR(12) NOT NULL,
    label VARCHAR(32) NOT NULL,
    name VARCHAR(120) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_by_user_id CHAR(12) DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX idx_department_calendar_period_dept_dates ON department_calendar_period (department_id, start_date, end_date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE department_calendar_period');
    }
}
