<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Zentrale Inbox-Tabelle für User-Nachrichten und Aktivitäts-Meldungen.
 */
final class Version20260517140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create inbox_message table for unified notification storage.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE inbox_message (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            category VARCHAR(32) NOT NULL,
            type VARCHAR(64) NOT NULL,
            recipient_scope VARCHAR(20) NOT NULL,
            recipient_user_id CHARACTER(12) DEFAULT NULL,
            sender_user_id CHARACTER(12) DEFAULT NULL,
            activity_id CHARACTER(12) DEFAULT NULL,
            subject VARCHAR(512) DEFAULT NULL,
            body TEXT DEFAULT NULL,
            payload JSON NOT NULL,
            read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_inbox_dept_recipient_unread ON inbox_message (department_id, recipient_user_id, read_at)');
        $this->addSql('CREATE INDEX idx_inbox_dept_sender ON inbox_message (department_id, sender_user_id, category)');
        $this->addSql('CREATE INDEX idx_inbox_dept_mw_unread ON inbox_message (department_id, recipient_scope, read_at)');
        $this->addSql('CREATE INDEX idx_inbox_activity ON inbox_message (activity_id)');
        $this->addSql('ALTER TABLE inbox_message ADD CONSTRAINT FK_INBOX_MESSAGE_DEPARTMENT FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE inbox_message');
    }
}
