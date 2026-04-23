<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Verkaufspreis pro Verpackungseinheit (optional), z. B. für Aufteilen auf Stückpreis.
 */
final class Version20260404100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'material_item.pack_sale_price_chf (optional, CHF pro VE)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item ADD COLUMN IF NOT EXISTS pack_sale_price_chf NUMERIC(10, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item DROP COLUMN IF EXISTS pack_sale_price_chf');
    }
}
