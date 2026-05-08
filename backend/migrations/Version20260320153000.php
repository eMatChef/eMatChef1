<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create print_task_item table for persistent print cart';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE print_task_item (
            id CHARACTER(13) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            created_by_user_id CHARACTER(12) DEFAULT NULL,
            entity_type VARCHAR(32) NOT NULL,
            entity_id VARCHAR(20) NOT NULL,
            label VARCHAR(255) NOT NULL,
            public_code VARCHAR(64) DEFAULT NULL,
            public_url VARCHAR(512) NOT NULL,
            status VARCHAR(16) DEFAULT \'pending\' NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            printed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_print_task_dept_status_created ON print_task_item (department_id, status, created_at)');
        $this->addSql('CREATE UNIQUE INDEX uq_print_task_pending_entity ON print_task_item (department_id, entity_type, entity_id) WHERE status = \'pending\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE print_task_item');
    }
}

