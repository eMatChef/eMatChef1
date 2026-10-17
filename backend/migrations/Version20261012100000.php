<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: separate budget limits for material and logistics (total = sum of both).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE department_grossanlass_budget_area (
    department_id CHAR(12) NOT NULL,
    area VARCHAR(16) NOT NULL,
    limit_chf NUMERIC(12, 2) NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(department_id, area)
)
SQL);
        $this->addSql('ALTER TABLE department_grossanlass_budget_area ADD CONSTRAINT fk_ga_budget_area_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE department_grossanlass_budget_area');
    }
}
