<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Verbrauchsmaterial & Preisverrechnung:
 * - material_item: is_consumable, sale_price, min_stock
 * - activity_item: is_consumable, unit_price, line_total, price_type
 */
final class Version20260209110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Verbrauchsmaterial (is_consumable, sale_price, min_stock) und Preisverrechnung (unit_price, line_total, price_type) auf ActivityItem';
    }

    public function up(Schema $schema): void
    {
        // MaterialItem erweitern
        $this->addSql('ALTER TABLE material_item ADD COLUMN is_consumable BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE material_item ADD COLUMN sale_price DECIMAL(10,2) NULL');
        $this->addSql('ALTER TABLE material_item ADD COLUMN min_stock INTEGER NULL');

        // ActivityItem erweitern
        $this->addSql('ALTER TABLE activity_item ADD COLUMN is_consumable BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE activity_item ADD COLUMN unit_price DECIMAL(10,2) NULL');
        $this->addSql('ALTER TABLE activity_item ADD COLUMN line_total DECIMAL(12,2) NULL');
        $this->addSql("ALTER TABLE activity_item ADD COLUMN price_type VARCHAR(20) NULL DEFAULT 'free'");
    }

    public function down(Schema $schema): void
    {
        // ActivityItem Spalten entfernen
        $this->addSql('ALTER TABLE activity_item DROP COLUMN IF EXISTS price_type');
        $this->addSql('ALTER TABLE activity_item DROP COLUMN IF EXISTS line_total');
        $this->addSql('ALTER TABLE activity_item DROP COLUMN IF EXISTS unit_price');
        $this->addSql('ALTER TABLE activity_item DROP COLUMN IF EXISTS is_consumable');

        // MaterialItem Spalten entfernen
        $this->addSql('ALTER TABLE material_item DROP COLUMN IF EXISTS min_stock');
        $this->addSql('ALTER TABLE material_item DROP COLUMN IF EXISTS sale_price');
        $this->addSql('ALTER TABLE material_item DROP COLUMN IF EXISTS is_consumable');
    }
}
