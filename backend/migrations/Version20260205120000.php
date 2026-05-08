<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration für Material-System (Category, MaterialItem, MaterialBatch)
 * Lagerorte werden über Address mit type='storage' abgebildet
 */
final class Version20260205120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Erstellt Tabellen für Material-System: category, material_item, material_batch';
    }

    public function up(Schema $schema): void
    {
        // Kategorie-Tabelle
        $this->addSql('
            CREATE TABLE category (
                id CHARACTER(12) NOT NULL,
                department_id CHARACTER(12) NOT NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT DEFAULT NULL,
                parent_id CHARACTER(12) DEFAULT NULL,
                sort_order INT DEFAULT 0 NOT NULL,
                created_at TIMESTAMP NOT NULL,
                updated_at TIMESTAMP NOT NULL,
                PRIMARY KEY(id)
            )
        ');
        $this->addSql('CREATE INDEX IDX_category_department ON category (department_id)');
        $this->addSql('CREATE INDEX IDX_category_parent ON category (parent_id)');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_category_department FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_category_parent FOREIGN KEY (parent_id) REFERENCES category (id) ON DELETE SET NULL');

        // Material-Stammdaten-Tabelle
        $this->addSql('
            CREATE TABLE material_item (
                id CHARACTER(12) NOT NULL,
                department_id CHARACTER(12) NOT NULL,
                no INT DEFAULT NULL,
                name VARCHAR(160) NOT NULL,
                description TEXT DEFAULT NULL,
                category_id CHARACTER(12) DEFAULT NULL,
                storage_address_id CHARACTER(12) DEFAULT NULL,
                location VARCHAR(160) DEFAULT NULL,
                condition VARCHAR(20) DEFAULT \'ok\' NOT NULL,
                color VARCHAR(120) DEFAULT NULL,
                material VARCHAR(120) DEFAULT NULL,
                size_length VARCHAR(120) DEFAULT NULL,
                size_width VARCHAR(120) DEFAULT NULL,
                size_height VARCHAR(120) DEFAULT NULL,
                weight VARCHAR(120) DEFAULT NULL,
                is_tent BOOLEAN DEFAULT FALSE NOT NULL,
                ean VARCHAR(13) DEFAULT NULL,
                barcode_tag VARCHAR(50) DEFAULT NULL,
                manufacturer VARCHAR(255) DEFAULT NULL,
                model VARCHAR(100) DEFAULT NULL,
                serial_number VARCHAR(100) DEFAULT NULL,
                warranty_until DATE DEFAULT NULL,
                rental_external_allowed BOOLEAN DEFAULT FALSE NOT NULL,
                rental_scope VARCHAR(32) DEFAULT NULL,
                rental_requires_approval BOOLEAN DEFAULT FALSE NOT NULL,
                rental_price_day DECIMAL(10,2) DEFAULT NULL,
                rental_price_week DECIMAL(12,2) DEFAULT NULL,
                rental_price_month DECIMAL(12,2) DEFAULT NULL,
                rental_deposit DECIMAL(12,2) DEFAULT NULL,
                rental_lead_days INT DEFAULT NULL,
                rental_max_days INT DEFAULT NULL,
                rental_notes TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL,
                updated_at TIMESTAMP NOT NULL,
                deleted_at TIMESTAMP DEFAULT NULL,
                PRIMARY KEY(id)
            )
        ');
        $this->addSql('CREATE INDEX IDX_material_item_department ON material_item (department_id)');
        $this->addSql('CREATE INDEX IDX_material_item_category ON material_item (category_id)');
        $this->addSql('CREATE INDEX IDX_material_item_storage ON material_item (storage_address_id)');
        $this->addSql('CREATE INDEX IDX_material_item_name ON material_item (name)');
        $this->addSql('CREATE INDEX IDX_material_item_deleted ON material_item (deleted_at)');
        $this->addSql('ALTER TABLE material_item ADD CONSTRAINT FK_material_item_department FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE material_item ADD CONSTRAINT FK_material_item_category FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE material_item ADD CONSTRAINT FK_material_item_storage FOREIGN KEY (storage_address_id) REFERENCES address (id) ON DELETE SET NULL');

        // Material-Batch-Tabelle (Einkäufe/Bewegungen)
        $this->addSql('
            CREATE TABLE material_batch (
                id CHARACTER(13) NOT NULL,
                material_item_id CHARACTER(12) NOT NULL,
                supplier_id CHARACTER(12) DEFAULT NULL,
                acquired_on DATE NOT NULL,
                label VARCHAR(80) DEFAULT NULL,
                qty INT NOT NULL,
                unit_price DECIMAL(12,2) DEFAULT NULL,
                external_ref VARCHAR(120) DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                is_initial BOOLEAN DEFAULT FALSE NOT NULL,
                batch_type VARCHAR(20) DEFAULT \'purchase\' NOT NULL,
                status VARCHAR(20) DEFAULT \'active\' NOT NULL,
                invoice_number VARCHAR(100) DEFAULT NULL,
                expiry_date DATE DEFAULT NULL,
                storage_address_id CHARACTER(12) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL,
                PRIMARY KEY(id)
            )
        ');
        $this->addSql('CREATE INDEX IDX_material_batch_item ON material_batch (material_item_id)');
        $this->addSql('CREATE INDEX IDX_material_batch_supplier ON material_batch (supplier_id)');
        $this->addSql('CREATE INDEX IDX_material_batch_storage ON material_batch (storage_address_id)');
        $this->addSql('CREATE INDEX IDX_material_batch_acquired ON material_batch (acquired_on)');
        $this->addSql('CREATE INDEX IDX_material_batch_status ON material_batch (status)');
        $this->addSql('ALTER TABLE material_batch ADD CONSTRAINT FK_material_batch_item FOREIGN KEY (material_item_id) REFERENCES material_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE material_batch ADD CONSTRAINT FK_material_batch_supplier FOREIGN KEY (supplier_id) REFERENCES address (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE material_batch ADD CONSTRAINT FK_material_batch_storage FOREIGN KEY (storage_address_id) REFERENCES address (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS material_batch');
        $this->addSql('DROP TABLE IF EXISTS material_item');
        $this->addSql('DROP TABLE IF EXISTS category');
    }
}
