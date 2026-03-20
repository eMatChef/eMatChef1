<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add is_generic to material_template_component
 */
final class Version20260210190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_generic boolean to material_template_component for shared/generic components';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_template_component ADD COLUMN is_generic BOOLEAN DEFAULT FALSE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_template_component DROP COLUMN is_generic');
    }
}
