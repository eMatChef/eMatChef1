<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paket 1 — SupplierCompany + SupplierMembership (M2).
 */
final class Version20260530140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supplier portal Paket 1: supplier_company, supplier_membership, address FK, user.last_used_supplier_company_id.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE supplier_company (
                id CHARACTER(12) NOT NULL,
                name VARCHAR(255) NOT NULL,
                manufacturer_key VARCHAR(120) DEFAULT NULL,
                supplier_address_id CHARACTER(12) DEFAULT NULL,
                capabilities JSON NOT NULL DEFAULT '[]',
                linked_department_id CHARACTER(12) DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        ");
        $this->addSql('CREATE UNIQUE INDEX uniq_supplier_company_address ON supplier_company (supplier_address_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_supplier_company_manufacturer_key ON supplier_company (manufacturer_key)');
        $this->addSql('CREATE INDEX idx_supplier_company_status ON supplier_company (status)');
        $this->addSql('CREATE INDEX idx_supplier_company_linked_dept ON supplier_company (linked_department_id)');

        $this->addSql('
            ALTER TABLE supplier_company
                ADD CONSTRAINT fk_supplier_company_address FOREIGN KEY (supplier_address_id)
                REFERENCES address (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE
        ');
        $this->addSql('
            ALTER TABLE supplier_company
                ADD CONSTRAINT fk_supplier_company_linked_department FOREIGN KEY (linked_department_id)
                REFERENCES department (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        ');

        $this->addSql("
            CREATE TABLE supplier_membership (
                supplier_company_id CHARACTER(12) NOT NULL,
                user_id CHARACTER(12) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'member',
                is_primary BOOLEAN NOT NULL DEFAULT false,
                PRIMARY KEY (supplier_company_id, user_id)
            )
        ");
        $this->addSql('CREATE INDEX idx_supplier_membership_user ON supplier_membership (user_id)');

        $this->addSql('
            ALTER TABLE supplier_membership
                ADD CONSTRAINT fk_supplier_membership_company FOREIGN KEY (supplier_company_id)
                REFERENCES supplier_company (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        ');
        $this->addSql('
            ALTER TABLE supplier_membership
                ADD CONSTRAINT fk_supplier_membership_user FOREIGN KEY (user_id)
                REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        ');

        $this->addSql('
            ALTER TABLE address
                ADD CONSTRAINT fk_address_supplier_company FOREIGN KEY (supplier_company_id)
                REFERENCES supplier_company (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        ');
        $this->addSql('CREATE INDEX idx_address_supplier_company ON address (supplier_company_id)');

        $this->addSql('ALTER TABLE "user" ADD last_used_supplier_company_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('
            ALTER TABLE "user"
                ADD CONSTRAINT fk_user_last_used_supplier_company FOREIGN KEY (last_used_supplier_company_id)
                REFERENCES supplier_company (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT IF EXISTS fk_user_last_used_supplier_company');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS last_used_supplier_company_id');

        $this->addSql('ALTER TABLE address DROP CONSTRAINT IF EXISTS fk_address_supplier_company');
        $this->addSql('DROP INDEX IF EXISTS idx_address_supplier_company');

        $this->addSql('DROP TABLE IF EXISTS supplier_membership');
        $this->addSql('DROP TABLE IF EXISTS supplier_company');
    }
}
