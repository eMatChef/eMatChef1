<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * MW-Kostenfreigabe vor Aktivitäts-Abschluss (Materialabschluss).
 */
final class Version20260803140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Activity: costs_released_at + costs_released_by_user_id for MW cost release';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD COLUMN IF NOT EXISTS costs_released_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE activity ADD COLUMN IF NOT EXISTS costs_released_by_user_id CHARACTER(12) DEFAULT NULL');
        $this->addSql(<<<'SQL'
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'fk_activity_costs_released_by_user'
  ) THEN
    ALTER TABLE activity
      ADD CONSTRAINT fk_activity_costs_released_by_user
      FOREIGN KEY (costs_released_by_user_id) REFERENCES "user"(id) ON DELETE SET NULL;
  END IF;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT IF EXISTS fk_activity_costs_released_by_user');
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS costs_released_by_user_id');
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS costs_released_at');
    }
}
