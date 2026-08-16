<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Material: is_rentable (statische Artikel wie Kühlschrank nicht vermietbar).
 */
final class Version20260816140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add material_item.is_rentable (default true)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item ADD COLUMN IF NOT EXISTS is_rentable BOOLEAN DEFAULT TRUE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item DROP COLUMN IF EXISTS is_rentable');
    }
}
