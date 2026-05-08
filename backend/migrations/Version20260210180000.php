<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add is_food column to material_item
 */
final class Version20260210180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_food boolean column to material_item table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item ADD COLUMN is_food BOOLEAN DEFAULT FALSE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item DROP COLUMN is_food');
    }
}
