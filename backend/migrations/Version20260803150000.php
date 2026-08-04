<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Event-POI Kinderadressen: pin_color für freie Markerfarbe.
 */
final class Version20260803150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'address.pin_color — Markerfarbe für Event-POIs (event_poi)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address ADD COLUMN IF NOT EXISTS pin_color VARCHAR(7) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address DROP COLUMN IF EXISTS pin_color');
    }
}
