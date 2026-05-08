<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Preismodus: Setpreis (Pauschale) vs. Einzelpreis (pro Artikel)
 */
final class Version20260209130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pricing_mode column to activity (set_price or item_price)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE activity ADD COLUMN pricing_mode VARCHAR(20) DEFAULT 'item_price'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP COLUMN IF EXISTS pricing_mode');
    }
}
