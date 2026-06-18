<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Department-Fuhrpark (department_vehicle) + Transport-Touren (activity_transport_tour/item)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS department_vehicle (
    id CHARACTER(12) NOT NULL,
    department_id CHARACTER(12) NOT NULL,
    name VARCHAR(120) NOT NULL,
    plate VARCHAR(32) DEFAULT NULL,
    length_m NUMERIC(6, 2) DEFAULT NULL,
    width_m NUMERIC(6, 2) DEFAULT NULL,
    height_m NUMERIC(6, 2) DEFAULT NULL,
    max_payload_kg NUMERIC(10, 2) DEFAULT NULL,
    max_volume_m3 NUMERIC(10, 2) DEFAULT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_department_vehicle_dept ON department_vehicle (department_id)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_department_vehicle_department') THEN
        ALTER TABLE department_vehicle ADD CONSTRAINT fk_department_vehicle_department
            FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS activity_transport_tour (
    id CHARACTER(13) NOT NULL,
    activity_id CHARACTER(12) NOT NULL,
    label VARCHAR(80) NOT NULL,
    vehicle_id CHARACTER(12) NOT NULL,
    lending_department_id CHARACTER(12) DEFAULT NULL,
    direction VARCHAR(16) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_by_user_id CHARACTER(12) DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_transport_tour_activity ON activity_transport_tour (activity_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_transport_tour_direction ON activity_transport_tour (activity_id, direction)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_transport_tour_activity') THEN
        ALTER TABLE activity_transport_tour ADD CONSTRAINT fk_transport_tour_activity
            FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_transport_tour_vehicle') THEN
        ALTER TABLE activity_transport_tour ADD CONSTRAINT fk_transport_tour_vehicle
            FOREIGN KEY (vehicle_id) REFERENCES department_vehicle (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS activity_transport_tour_item (
    id CHARACTER(13) NOT NULL,
    tour_id CHARACTER(13) NOT NULL,
    pack_container_id CHARACTER(13) DEFAULT NULL,
    pack_item_id CHARACTER(13) DEFAULT NULL,
    quantity INT DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_transport_tour_item_tour ON activity_transport_tour_item (tour_id)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_transport_tour_item_tour') THEN
        ALTER TABLE activity_transport_tour_item ADD CONSTRAINT fk_transport_tour_item_tour
            FOREIGN KEY (tour_id) REFERENCES activity_transport_tour (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS activity_transport_tour_item');
        $this->addSql('DROP TABLE IF EXISTS activity_transport_tour');
        $this->addSql('DROP TABLE IF EXISTS department_vehicle');
    }
}
