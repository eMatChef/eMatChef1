<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918224500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: WGS84 am GA-Ort und Overlay-Bounds an der GA-Karte (eine Karte mit Stammdaten)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_place ADD COLUMN IF NOT EXISTS latitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_place ADD COLUMN IF NOT EXISTS longitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_map ADD COLUMN IF NOT EXISTS bounds_north DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_map ADD COLUMN IF NOT EXISTS bounds_south DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_map ADD COLUMN IF NOT EXISTS bounds_east DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE department_grossanlass_map ADD COLUMN IF NOT EXISTS bounds_west DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_place DROP COLUMN IF EXISTS latitude');
        $this->addSql('ALTER TABLE department_grossanlass_place DROP COLUMN IF EXISTS longitude');
        $this->addSql('ALTER TABLE department_grossanlass_map DROP COLUMN IF EXISTS bounds_north');
        $this->addSql('ALTER TABLE department_grossanlass_map DROP COLUMN IF EXISTS bounds_south');
        $this->addSql('ALTER TABLE department_grossanlass_map DROP COLUMN IF EXISTS bounds_east');
        $this->addSql('ALTER TABLE department_grossanlass_map DROP COLUMN IF EXISTS bounds_west');
    }
}
