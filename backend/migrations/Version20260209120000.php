<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Verpackungseinheit (Bündel) für MaterialItem
 * Neue Felder: pack_size, pack_unit
 */
final class Version20260209120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pack_size and pack_unit columns to material_item for bundle tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item ADD COLUMN pack_size INTEGER NULL');
        $this->addSql('ALTER TABLE material_item ADD COLUMN pack_unit VARCHAR(40) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item DROP COLUMN pack_unit');
        $this->addSql('ALTER TABLE material_item DROP COLUMN pack_size');
    }
}
