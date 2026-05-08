<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Eventstandort (venue_address_id) für Aktivitäten — getrennt von Mieter-/Kundenadresse (address_id).
 * Idempotent: gleiche Logik wie spätere Repair-Migration, damit fehlende Zwischenstände / Wiederholung nicht brechen.
 */
final class Version20260331120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'activity.venue_address_id (FK address, optional Eventstandort)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD COLUMN IF NOT EXISTS venue_address_id CHARACTER(12) DEFAULT NULL');
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_activity_venue_address'
    ) THEN
        ALTER TABLE activity ADD CONSTRAINT fk_activity_venue_address
            FOREIGN KEY (venue_address_id) REFERENCES address (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT IF EXISTS fk_activity_venue_address');
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS venue_address_id');
    }
}
