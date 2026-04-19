<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Eventstandort (venue_address_id) für Aktivitäten — getrennt von Mieter-/Kundenadresse (address_id).
 */
final class Version20260331120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'activity.venue_address_id (FK address, optional Eventstandort)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD venue_address_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT fk_activity_venue_address FOREIGN KEY (venue_address_id) REFERENCES address (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT fk_activity_venue_address');
        $this->addSql('ALTER TABLE activity DROP COLUMN venue_address_id');
    }
}
