<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add material_type, tracking_type to material_item
 * Add serial_number to material_batch
 */
final class Version20260205150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add material_type, tracking_type to material_item and serial_number to material_batch';
    }

    public function up(Schema $schema): void
    {
        // MaterialItem: material_type und tracking_type
        $this->addSql('ALTER TABLE material_item ADD COLUMN material_type VARCHAR(20) NOT NULL DEFAULT \'physical\'');
        $this->addSql('ALTER TABLE material_item ADD COLUMN tracking_type VARCHAR(20) NULL');

        // MaterialBatch: serial_number
        $this->addSql('ALTER TABLE material_batch ADD COLUMN serial_number VARCHAR(100) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item DROP COLUMN material_type');
        $this->addSql('ALTER TABLE material_item DROP COLUMN tracking_type');
        $this->addSql('ALTER TABLE material_batch DROP COLUMN serial_number');
    }
}
