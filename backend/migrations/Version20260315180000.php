<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Physische Kombination: optionale Referenz zur Kiste (MaterialBatch) für Plan-vs.-Ist-Bezug.
 */
final class Version20260315180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add linked_container_batch_id to material_item (physical combo ↔ Kiste)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item ADD linked_container_batch_id CHARACTER(13) DEFAULT NULL');
        $this->addSql('ALTER TABLE material_item ADD CONSTRAINT fk_material_item_linked_container_batch FOREIGN KEY (linked_container_batch_id) REFERENCES material_batch (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_material_item_linked_container ON material_item (linked_container_batch_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item DROP CONSTRAINT fk_material_item_linked_container_batch');
        $this->addSql('DROP INDEX idx_material_item_linked_container ON material_item');
        $this->addSql('ALTER TABLE material_item DROP COLUMN linked_container_batch_id');
    }
}
