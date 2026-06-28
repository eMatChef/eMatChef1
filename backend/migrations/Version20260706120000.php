<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Transport-Tour-Positionen: gemessenes Gewicht (measured_weight_kg)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE activity_transport_tour_item
    ADD COLUMN IF NOT EXISTS measured_weight_kg NUMERIC(10, 2) DEFAULT NULL
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_transport_tour_item DROP COLUMN IF EXISTS measured_weight_kg');
    }
}
