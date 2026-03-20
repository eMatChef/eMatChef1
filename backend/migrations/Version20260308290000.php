<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308290000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shared audit_event table for membership and profile changes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_event (id CHARACTER(13) NOT NULL, entity_type VARCHAR(40) NOT NULL, entity_id VARCHAR(64) NOT NULL, action VARCHAR(64) NOT NULL, actor_user_id CHARACTER(12) DEFAULT NULL, target_user_id CHARACTER(12) DEFAULT NULL, department_id CHARACTER(12) DEFAULT NULL, changes JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_audit_entity ON audit_event (entity_type, entity_id, created_at)');
        $this->addSql('CREATE INDEX idx_audit_actor ON audit_event (actor_user_id, created_at)');
        $this->addSql('CREATE INDEX idx_audit_target ON audit_event (target_user_id, created_at)');
        $this->addSql('CREATE INDEX idx_audit_department ON audit_event (department_id, created_at)');
        $this->addSql('CREATE INDEX idx_audit_created ON audit_event (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_event');
    }
}
