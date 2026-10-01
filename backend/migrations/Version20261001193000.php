<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Fahrzeugwunsch am Bauauftrag merkt die Kategorie';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_vehicle_need ADD COLUMN IF NOT EXISTS category_label VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_vehicle_need DROP COLUMN IF EXISTS category_label');
    }
}
