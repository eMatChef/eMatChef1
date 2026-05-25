<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend inbox_message with workflow_status and source_ref_id for all inbox kinds.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inbox_message ADD workflow_status VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE inbox_message ADD source_ref_id VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE inbox_message ADD read_by_user_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_inbox_category_workflow ON inbox_message (department_id, category, workflow_status)');
        $this->addSql('CREATE INDEX idx_inbox_source_ref ON inbox_message (source_ref_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_inbox_source_ref');
        $this->addSql('DROP INDEX idx_inbox_category_workflow');
        $this->addSql('ALTER TABLE inbox_message DROP COLUMN read_by_user_id');
        $this->addSql('ALTER TABLE inbox_message DROP COLUMN source_ref_id');
        $this->addSql('ALTER TABLE inbox_message DROP COLUMN workflow_status');
    }
}
