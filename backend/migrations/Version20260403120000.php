<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Optionale Verknüpfung Buchung → Material (Kosten pro Artikel, Reparatur, manuelle Zuordnung).
 */
final class Version20260403120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional material_item_id to accounting_booking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accounting_booking ADD material_item_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_ab_material_item ON accounting_booking (department_id, material_item_id)');
        $this->addSql('ALTER TABLE accounting_booking ADD CONSTRAINT FK_AB_MATERIAL_ITEM FOREIGN KEY (material_item_id) REFERENCES material_item (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accounting_booking DROP CONSTRAINT FK_AB_MATERIAL_ITEM');
        $this->addSql('DROP INDEX idx_ab_material_item');
        $this->addSql('ALTER TABLE accounting_booking DROP material_item_id');
    }
}
