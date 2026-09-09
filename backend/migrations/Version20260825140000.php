<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: nicht zugeordnete Gmail-Nachrichten';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS department_grossanlass_gmail_unmatched (
    id CHAR(12) NOT NULL,
    department_id CHAR(12) NOT NULL,
    gmail_message_id VARCHAR(128) NOT NULL,
    gmail_thread_id VARCHAR(128) NOT NULL DEFAULT '',
    from_email VARCHAR(180) NOT NULL DEFAULT '',
    from_name VARCHAR(255) NOT NULL DEFAULT '',
    subject VARCHAR(255) NOT NULL DEFAULT '',
    body TEXT NOT NULL,
    received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    discarded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_gmail_unmatched_dept ON department_grossanlass_gmail_unmatched (department_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_gmail_unmatched_msg ON department_grossanlass_gmail_unmatched (department_id, gmail_message_id)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    ALTER TABLE department_grossanlass_gmail_unmatched
        ADD CONSTRAINT fk_ga_gmail_unmatched_dept FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_gmail_unmatched DROP CONSTRAINT IF EXISTS fk_ga_gmail_unmatched_dept');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_gmail_unmatched');
    }
}
