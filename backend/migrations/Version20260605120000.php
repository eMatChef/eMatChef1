<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Workshop-Ticket: optionale material_batch_id für serialisierte Instanzen.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE workshop_ticket ADD material_batch_id CHARACTER(13) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_workshop_material_batch ON workshop_ticket (material_batch_id)');
        $this->addSql('ALTER TABLE workshop_ticket ADD CONSTRAINT fk_workshop_material_batch FOREIGN KEY (material_batch_id) REFERENCES material_batch (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE workshop_ticket DROP CONSTRAINT IF EXISTS fk_workshop_material_batch');
        $this->addSql('DROP INDEX IF EXISTS idx_workshop_material_batch');
        $this->addSql('ALTER TABLE workshop_ticket DROP COLUMN IF EXISTS material_batch_id');
    }
}
