<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * is_tent → is_container (Behälter); material_batch.is_container für serialisierte Instanzen.
 */
final class Version20260406120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename material_item.is_tent to is_container; add material_batch.is_container';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item RENAME COLUMN is_tent TO is_container');
        $this->addSql('ALTER TABLE material_batch ADD COLUMN IF NOT EXISTS is_container BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_batch DROP COLUMN IF EXISTS is_container');
        $this->addSql('ALTER TABLE material_item RENAME COLUMN is_container TO is_tent');
    }
}
