<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702120000 extends AbstractMigration
{
    use CreatesTableUnlessExistsTrait;

    public function getDescription(): string
    {
        return 'Grossanlass Beschaffung Phase 5: quotes, orders, received quantities';
    }

    public function up(Schema $schema): void
    {
        if ($this->prepareNewTable($schema, 'activity_grossanlass_procurement_quote')) {
            $this->addSql(<<<'SQL'
CREATE TABLE activity_grossanlass_procurement_quote (
    id CHARACTER(12) NOT NULL,
    procurement_line_id CHARACTER(12) NOT NULL,
    supplier VARCHAR(255) NOT NULL,
    amount_chf NUMERIC(12, 2) NOT NULL,
    notes TEXT DEFAULT NULL,
    selected BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
            $this->addSql('CREATE INDEX idx_grossanlass_procurement_quote_line ON activity_grossanlass_procurement_quote (procurement_line_id)');
        }

        if ($this->prepareNewTable($schema, 'activity_grossanlass_procurement_order')) {
            $this->addSql(<<<'SQL'
CREATE TABLE activity_grossanlass_procurement_order (
    id CHARACTER(12) NOT NULL,
    procurement_line_id CHARACTER(12) NOT NULL,
    ordered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    cost_chf NUMERIC(12, 2) NOT NULL,
    order_ref VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
            $this->addSql('CREATE UNIQUE INDEX uq_grossanlass_procurement_order_line ON activity_grossanlass_procurement_order (procurement_line_id)');
        }

        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'activity_grossanlass_procurement_line_wish' AND column_name = 'received_quantity'
    ) THEN
        ALTER TABLE activity_grossanlass_procurement_line_wish
            ADD COLUMN received_quantity INT NOT NULL DEFAULT 0;
    END IF;
END $$;
SQL);

        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_procurement_quote_line') THEN
        ALTER TABLE activity_grossanlass_procurement_quote ADD CONSTRAINT fk_grossanlass_procurement_quote_line
            FOREIGN KEY (procurement_line_id) REFERENCES activity_grossanlass_procurement_line (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_grossanlass_procurement_order_line') THEN
        ALTER TABLE activity_grossanlass_procurement_order ADD CONSTRAINT fk_grossanlass_procurement_order_line
            FOREIGN KEY (procurement_line_id) REFERENCES activity_grossanlass_procurement_line (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_order DROP CONSTRAINT IF EXISTS fk_grossanlass_procurement_order_line');
        $this->addSql('DROP TABLE IF EXISTS activity_grossanlass_procurement_order');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_quote DROP CONSTRAINT IF EXISTS fk_grossanlass_procurement_quote_line');
        $this->addSql('DROP TABLE IF EXISTS activity_grossanlass_procurement_quote');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line_wish DROP COLUMN IF EXISTS received_quantity');
    }
}
