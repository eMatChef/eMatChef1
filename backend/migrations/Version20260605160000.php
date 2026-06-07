<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Department-Zeltblatt-Overrides department_repair_template.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE department_repair_template (
                id CHARACTER(12) NOT NULL,
                department_id CHARACTER(12) NOT NULL,
                template_key VARCHAR(50) NOT NULL,
                prices_json JSON NOT NULL,
                flat_rate_chf NUMERIC(10, 2) DEFAULT NULL,
                is_active BOOLEAN DEFAULT true NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_dept_repair_template ON department_repair_template (department_id, template_key)');
        $this->addSql('CREATE INDEX idx_dept_repair_template_dept ON department_repair_template (department_id)');
        $this->addSql('ALTER TABLE department_repair_template ADD CONSTRAINT FK_dept_repair_template_department FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_repair_template DROP CONSTRAINT IF EXISTS FK_dept_repair_template_department');
        $this->addSql('DROP TABLE IF EXISTS department_repair_template');
    }
}
