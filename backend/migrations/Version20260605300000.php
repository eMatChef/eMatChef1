<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605300000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supplier-Zeltblatt-Overrides supplier_repair_template (Paket 14).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_repair_template (
                id CHARACTER(12) NOT NULL,
                supplier_company_id CHARACTER(12) NOT NULL,
                template_key VARCHAR(50) NOT NULL,
                prices_json JSON NOT NULL,
                flat_rate_chf NUMERIC(10, 2) DEFAULT NULL,
                services_json JSON NOT NULL,
                is_active BOOLEAN DEFAULT true NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_supplier_repair_template ON supplier_repair_template (supplier_company_id, template_key)');
        $this->addSql('CREATE INDEX idx_supplier_repair_template_company ON supplier_repair_template (supplier_company_id)');
        $this->addSql('ALTER TABLE supplier_repair_template ADD CONSTRAINT FK_supplier_repair_template_company FOREIGN KEY (supplier_company_id) REFERENCES supplier_company (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE supplier_repair_template DROP CONSTRAINT IF EXISTS FK_supplier_repair_template_company');
        $this->addSql('DROP TABLE IF EXISTS supplier_repair_template');
    }
}
