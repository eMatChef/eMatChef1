<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the activity_history table for tracking all changes to activities.
 */
final class Version20260208160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates the activity_history table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE activity_history (
            id CHARACTER(13) NOT NULL,
            activity_id CHARACTER(12) NOT NULL,
            user_id CHARACTER(12) DEFAULT NULL,
            action VARCHAR(20) NOT NULL,
            snapshot JSON NOT NULL,
            changes JSON NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_activity_history_activity ON activity_history (activity_id)');
        $this->addSql('CREATE INDEX idx_activity_history_created ON activity_history (created_at)');
        $this->addSql('ALTER TABLE activity_history ADD CONSTRAINT FK_activity_history_activity FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity_history ADD CONSTRAINT FK_activity_history_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_history DROP CONSTRAINT FK_activity_history_activity');
        $this->addSql('ALTER TABLE activity_history DROP CONSTRAINT FK_activity_history_user');
        $this->addSql('DROP TABLE activity_history');
    }
}
