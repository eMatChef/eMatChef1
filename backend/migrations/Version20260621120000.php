<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * J+S-Lieferadresse auf Aktivität (kann vom Eventstandort abweichen).
 */
final class Version20260621120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'activity.js_delivery_address_id (FK address, optional J+S-Lieferadresse)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD COLUMN IF NOT EXISTS js_delivery_address_id CHARACTER(12) DEFAULT NULL');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_activity_js_delivery_address'
    ) THEN
        ALTER TABLE activity ADD CONSTRAINT fk_activity_js_delivery_address
            FOREIGN KEY (js_delivery_address_id) REFERENCES address (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT IF EXISTS fk_activity_js_delivery_address');
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS js_delivery_address_id');
    }
}
