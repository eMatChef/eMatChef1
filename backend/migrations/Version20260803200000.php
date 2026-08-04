<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * #7 Phase 3: Einnahme-Vermerk (Bar/Rechnung) an der Aktivität.
 */
final class Version20260803200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Activity: collection_note (+ amount/at/by) for Bar/Rechnung vermerk';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE activity ADD COLUMN IF NOT EXISTS collection_note VARCHAR(16) DEFAULT NULL");
        $this->addSql('ALTER TABLE activity ADD COLUMN IF NOT EXISTS collection_note_amount NUMERIC(12, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE activity ADD COLUMN IF NOT EXISTS collection_note_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE activity ADD COLUMN IF NOT EXISTS collection_note_by_user_id CHARACTER(12) DEFAULT NULL');
        $this->addSql(<<<'SQL'
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'fk_activity_collection_note_by_user'
  ) THEN
    ALTER TABLE activity
      ADD CONSTRAINT fk_activity_collection_note_by_user
      FOREIGN KEY (collection_note_by_user_id) REFERENCES "user"(id) ON DELETE SET NULL;
  END IF;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT IF EXISTS fk_activity_collection_note_by_user');
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS collection_note_by_user_id');
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS collection_note_at');
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS collection_note_amount');
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS collection_note');
    }
}
