<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260708120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aktivitaet: submitted_by_user_id fuer Einreicher';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE activity
    ADD COLUMN IF NOT EXISTS submitted_by_user_id CHARACTER(12) DEFAULT NULL
SQL);
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_activity_submitted_by_user'
    ) THEN
        ALTER TABLE activity
            ADD CONSTRAINT fk_activity_submitted_by_user
            FOREIGN KEY (submitted_by_user_id) REFERENCES "user" (id) ON DELETE SET NULL;
    END IF;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT IF EXISTS fk_activity_submitted_by_user');
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS submitted_by_user_id');
    }
}
