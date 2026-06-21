<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260630130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fahrzeug-Besitzer (Kontakt) + Aktivitäts-Fuhrpark (activity_vehicle)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_vehicle ADD COLUMN IF NOT EXISTS owner_address_id CHARACTER(12) DEFAULT NULL');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_department_vehicle_owner_address') THEN
        ALTER TABLE department_vehicle ADD CONSTRAINT fk_department_vehicle_owner_address
            FOREIGN KEY (owner_address_id) REFERENCES address (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS activity_vehicle (
    id CHARACTER(13) NOT NULL,
    activity_id CHARACTER(12) NOT NULL,
    vehicle_id CHARACTER(12) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_activity_vehicle ON activity_vehicle (activity_id, vehicle_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_activity_vehicle_activity ON activity_vehicle (activity_id)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_activity_vehicle_activity') THEN
        ALTER TABLE activity_vehicle ADD CONSTRAINT fk_activity_vehicle_activity
            FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_activity_vehicle_vehicle') THEN
        ALTER TABLE activity_vehicle ADD CONSTRAINT fk_activity_vehicle_vehicle
            FOREIGN KEY (vehicle_id) REFERENCES department_vehicle (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS activity_vehicle');
        $this->addSql('ALTER TABLE department_vehicle DROP CONSTRAINT IF EXISTS fk_department_vehicle_owner_address');
        $this->addSql('ALTER TABLE department_vehicle DROP COLUMN IF EXISTS owner_address_id');
    }
}
