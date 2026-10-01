<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Polygon für GA-area (Bereich/Unterressort auf der Karte)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_place ADD COLUMN IF NOT EXISTS polygon JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_place DROP COLUMN IF EXISTS polygon');
    }
}
