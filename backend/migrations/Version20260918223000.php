<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: GA-Karte (Hintergrund) und Ort-Positionen darauf';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS department_grossanlass_map (
            id CHARACTER(12) NOT NULL,
            department_id CHARACTER(12) NOT NULL,
            name VARCHAR(255) NOT NULL,
            image_filename VARCHAR(255) DEFAULT NULL,
            image_width INT NOT NULL DEFAULT 0,
            image_height INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_map_dept ON department_grossanlass_map (department_id)');
        $this->addSql('ALTER TABLE department_grossanlass_place ADD COLUMN IF NOT EXISTS map_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_place ADD COLUMN IF NOT EXISTS map_x DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_place ADD COLUMN IF NOT EXISTS map_y DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ga_place_map ON department_grossanlass_place (map_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_ga_place_map');
        $this->addSql('ALTER TABLE department_grossanlass_place DROP COLUMN IF EXISTS map_y');
        $this->addSql('ALTER TABLE department_grossanlass_place DROP COLUMN IF EXISTS map_x');
        $this->addSql('ALTER TABLE department_grossanlass_place DROP COLUMN IF EXISTS map_id');
        $this->addSql('DROP TABLE IF EXISTS department_grossanlass_map');
    }
}
