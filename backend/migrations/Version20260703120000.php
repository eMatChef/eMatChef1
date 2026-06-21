<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass Beschaffung: quote PDF + supplier contact link';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'activity_grossanlass_procurement_quote' AND column_name = 'supplier_address_id'
    ) THEN
        ALTER TABLE activity_grossanlass_procurement_quote
            ADD COLUMN supplier_address_id CHARACTER(12) DEFAULT NULL;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'activity_grossanlass_procurement_quote' AND column_name = 'pdf_filename'
    ) THEN
        ALTER TABLE activity_grossanlass_procurement_quote
            ADD COLUMN pdf_filename VARCHAR(255) DEFAULT NULL;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_procurement_quote_supplier') THEN
        ALTER TABLE activity_grossanlass_procurement_quote ADD CONSTRAINT fk_grossanlass_procurement_quote_supplier
            FOREIGN KEY (supplier_address_id) REFERENCES address (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_indexes WHERE indexname = 'idx_grossanlass_procurement_quote_supplier'
    ) THEN
        CREATE INDEX idx_grossanlass_procurement_quote_supplier ON activity_grossanlass_procurement_quote (supplier_address_id);
    END IF;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_quote DROP CONSTRAINT IF EXISTS fk_grossanlass_procurement_quote_supplier');
        $this->addSql('DROP INDEX IF EXISTS idx_grossanlass_procurement_quote_supplier');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_quote DROP COLUMN IF EXISTS pdf_filename');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_quote DROP COLUMN IF EXISTS supplier_address_id');
    }
}
