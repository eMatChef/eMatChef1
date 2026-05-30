<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paket 9 — supplier_catalog_item (Phase 2a).
 */
final class Version20260530180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supplier portal Paket 9: supplier_catalog_item table for B2B catalog.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_catalog_item (
                id CHARACTER(12) NOT NULL,
                supplier_company_id CHARACTER(12) NOT NULL,
                name VARCHAR(255) NOT NULL,
                sku VARCHAR(120) DEFAULT NULL,
                manufacturer VARCHAR(120) DEFAULT NULL,
                tracking_type VARCHAR(20) NOT NULL DEFAULT 'bulk',
                unit_price NUMERIC(12, 2) DEFAULT NULL,
                currency VARCHAR(3) NOT NULL DEFAULT 'CHF',
                min_qty INT DEFAULT NULL,
                pack_size INT DEFAULT NULL,
                category_hint VARCHAR(255) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                external_ref VARCHAR(120) DEFAULT NULL,
                is_active BOOLEAN NOT NULL DEFAULT true,
                visibility VARCHAR(20) NOT NULL DEFAULT 'private',
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_supplier_catalog_company ON supplier_catalog_item (supplier_company_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_supplier_catalog_sku ON supplier_catalog_item (supplier_company_id, sku) WHERE sku IS NOT NULL');
        $this->addSql('ALTER TABLE supplier_catalog_item ADD CONSTRAINT fk_supplier_catalog_company FOREIGN KEY (supplier_company_id) REFERENCES supplier_company (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE supplier_catalog_item DROP CONSTRAINT IF EXISTS fk_supplier_catalog_company');
        $this->addSql('DROP TABLE IF EXISTS supplier_catalog_item');
    }
}
