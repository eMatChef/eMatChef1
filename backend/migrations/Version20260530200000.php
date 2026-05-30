<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paket 10 — supplier_delivery + supplier_delivery_line (Phase 2b).
 */
final class Version20260530200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supplier portal Paket 10: supplier_delivery and supplier_delivery_line tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_delivery (
                id CHARACTER(12) NOT NULL,
                supplier_company_id CHARACTER(12) NOT NULL,
                department_id CHARACTER(12) NOT NULL,
                delivery_ref VARCHAR(120) DEFAULT NULL,
                invoice_ref VARCHAR(120) DEFAULT NULL,
                delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                notes TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_supplier_delivery_company ON supplier_delivery (supplier_company_id)');
        $this->addSql('CREATE INDEX idx_supplier_delivery_department ON supplier_delivery (department_id)');
        $this->addSql('CREATE INDEX idx_supplier_delivery_status ON supplier_delivery (status)');
        $this->addSql('ALTER TABLE supplier_delivery ADD CONSTRAINT fk_supplier_delivery_company FOREIGN KEY (supplier_company_id) REFERENCES supplier_company (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE supplier_delivery ADD CONSTRAINT fk_supplier_delivery_department FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_delivery_line (
                id CHARACTER(12) NOT NULL,
                delivery_id CHARACTER(12) NOT NULL,
                catalog_item_id CHARACTER(12) NOT NULL,
                qty INT NOT NULL DEFAULT 1,
                unit_price NUMERIC(12, 2) DEFAULT NULL,
                serial_numbers JSON NOT NULL DEFAULT '[]',
                component_serials JSON DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_supplier_delivery_line_delivery ON supplier_delivery_line (delivery_id)');
        $this->addSql('ALTER TABLE supplier_delivery_line ADD CONSTRAINT fk_supplier_delivery_line_delivery FOREIGN KEY (delivery_id) REFERENCES supplier_delivery (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE supplier_delivery_line ADD CONSTRAINT fk_supplier_delivery_line_catalog FOREIGN KEY (catalog_item_id) REFERENCES supplier_catalog_item (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE supplier_delivery_line DROP CONSTRAINT IF EXISTS fk_supplier_delivery_line_catalog');
        $this->addSql('ALTER TABLE supplier_delivery_line DROP CONSTRAINT IF EXISTS fk_supplier_delivery_line_delivery');
        $this->addSql('DROP TABLE IF EXISTS supplier_delivery_line');
        $this->addSql('ALTER TABLE supplier_delivery DROP CONSTRAINT IF EXISTS fk_supplier_delivery_department');
        $this->addSql('ALTER TABLE supplier_delivery DROP CONSTRAINT IF EXISTS fk_supplier_delivery_company');
        $this->addSql('DROP TABLE IF EXISTS supplier_delivery');
    }
}
