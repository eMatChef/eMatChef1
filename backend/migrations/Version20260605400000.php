<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605400000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Inventur-Aufgaben inventory_task (Paket 19).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_task (
                id CHARACTER(12) NOT NULL,
                department_id CHARACTER(12) NOT NULL,
                title VARCHAR(200) NOT NULL,
                status VARCHAR(20) DEFAULT 'open' NOT NULL,
                lines_json JSON NOT NULL,
                workshop_ticket_id CHARACTER(13) DEFAULT NULL,
                created_by_user_id CHARACTER(12) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_inventory_task_department ON inventory_task (department_id)');
        $this->addSql('CREATE INDEX idx_inventory_task_status ON inventory_task (status)');
        $this->addSql('CREATE INDEX idx_inventory_task_workshop_ticket ON inventory_task (workshop_ticket_id)');
        $this->addSql('ALTER TABLE inventory_task ADD CONSTRAINT FK_inventory_task_department FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inventory_task ADD CONSTRAINT FK_inventory_task_workshop_ticket FOREIGN KEY (workshop_ticket_id) REFERENCES workshop_ticket (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_task DROP CONSTRAINT IF EXISTS FK_inventory_task_workshop_ticket');
        $this->addSql('ALTER TABLE inventory_task DROP CONSTRAINT IF EXISTS FK_inventory_task_department');
        $this->addSql('DROP TABLE inventory_task');
    }
}
