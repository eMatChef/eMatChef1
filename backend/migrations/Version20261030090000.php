<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Beschaffung: mehrere Bestellungen je Position (Offerte/Absprache, Lieferant, Storno), Kauf-Charge je Bestellung, Gegenbuchung im Wareneingang, Offerte↔Anfrage. Rein additiv mit Backfill.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_order ADD COLUMN IF NOT EXISTS quote_id CHAR(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_order ADD COLUMN IF NOT EXISTS supplier VARCHAR(255) DEFAULT NULL');
        $this->addSql("ALTER TABLE activity_grossanlass_procurement_order ADD COLUMN IF NOT EXISTS agreement_kind VARCHAR(16) NOT NULL DEFAULT 'quote'");
        $this->addSql("ALTER TABLE activity_grossanlass_procurement_order ADD COLUMN IF NOT EXISTS status VARCHAR(16) NOT NULL DEFAULT 'confirmed'");
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_order ADD COLUMN IF NOT EXISTS return_needed BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_order ADD COLUMN IF NOT EXISTS inbound_mode VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_order ADD COLUMN IF NOT EXISTS cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_order ADD COLUMN IF NOT EXISTS cancel_reason TEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_gpo_line ON activity_grossanlass_procurement_order (procurement_line_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_gpo_quote ON activity_grossanlass_procurement_order (quote_id)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_gpo_quote') THEN
        ALTER TABLE activity_grossanlass_procurement_order
            ADD CONSTRAINT fk_gpo_quote FOREIGN KEY (quote_id) REFERENCES activity_grossanlass_procurement_quote (id) ON DELETE SET NULL;
    END IF;
END $$
SQL);
        // Bisherige Bestellungen: Grundlage = die damals gewählte Offerte; ohne Offerte gilt sie als Absprache.
        $this->addSql(<<<'SQL'
UPDATE activity_grossanlass_procurement_order o
SET quote_id = (
    SELECT q.id FROM activity_grossanlass_procurement_quote q
    WHERE q.procurement_line_id = o.procurement_line_id AND q.selected = TRUE
    ORDER BY q.created_at LIMIT 1
)
WHERE o.quote_id IS NULL
SQL);
        $this->addSql("UPDATE activity_grossanlass_procurement_order SET agreement_kind = 'phone' WHERE quote_id IS NULL AND agreement_kind = 'quote'");

        $this->addSql('ALTER TABLE department_grossanlass_commitment ADD COLUMN IF NOT EXISTS order_id CHAR(12) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ga_commitment_order ON department_grossanlass_commitment (order_id)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_ga_commitment_order') THEN
        ALTER TABLE department_grossanlass_commitment
            ADD CONSTRAINT fk_ga_commitment_order FOREIGN KEY (order_id) REFERENCES activity_grossanlass_procurement_order (id) ON DELETE SET NULL;
    END IF;
END $$
SQL);
        // Bestehende Kauf-Chargen der Bestellung zuordnen (bisher nur als JSON in item_details).
        $this->addSql(<<<'SQL'
UPDATE department_grossanlass_commitment c
SET order_id = o.id
FROM activity_grossanlass_procurement_order o
WHERE c.order_id IS NULL
  AND c.origin = 'buy'
  AND c.procurement_line_id = o.procurement_line_id
  AND NOT EXISTS (SELECT 1 FROM department_grossanlass_commitment c2 WHERE c2.order_id = o.id)
  AND c.id = (
      SELECT c3.id FROM department_grossanlass_commitment c3
      WHERE c3.procurement_line_id = o.procurement_line_id AND c3.origin = 'buy'
      ORDER BY (c3.item_details::jsonb ->> 'order_id' = o.id) DESC, c3.id LIMIT 1
  )
SQL);

        $this->addSql('ALTER TABLE department_grossanlass_charge_movement ADD COLUMN IF NOT EXISTS reverses_id CHAR(12) DEFAULT NULL');

        $this->addSql('ALTER TABLE activity_grossanlass_procurement_quote ADD COLUMN IF NOT EXISTS inquiry_id CHAR(12) DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_gpq_inquiry ON activity_grossanlass_procurement_quote (inquiry_id)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_gpq_inquiry') THEN
        ALTER TABLE activity_grossanlass_procurement_quote
            ADD CONSTRAINT fk_gpq_inquiry FOREIGN KEY (inquiry_id) REFERENCES department_grossanlass_inquiry (id) ON DELETE SET NULL;
    END IF;
END $$
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_quote DROP CONSTRAINT IF EXISTS fk_gpq_inquiry');
        $this->addSql('DROP INDEX IF EXISTS idx_gpq_inquiry');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_quote DROP COLUMN IF EXISTS inquiry_id');
        $this->addSql('ALTER TABLE department_grossanlass_charge_movement DROP COLUMN IF EXISTS reverses_id');
        $this->addSql('ALTER TABLE department_grossanlass_commitment DROP CONSTRAINT IF EXISTS fk_ga_commitment_order');
        $this->addSql('DROP INDEX IF EXISTS uniq_ga_commitment_order');
        $this->addSql('ALTER TABLE department_grossanlass_commitment DROP COLUMN IF EXISTS order_id');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_order DROP CONSTRAINT IF EXISTS fk_gpo_quote');
        $this->addSql('DROP INDEX IF EXISTS idx_gpo_quote');
        $this->addSql('DROP INDEX IF EXISTS idx_gpo_line');
        foreach (['quote_id', 'supplier', 'agreement_kind', 'status', 'return_needed', 'inbound_mode', 'cancelled_at', 'cancel_reason'] as $column) {
            $this->addSql(sprintf('ALTER TABLE activity_grossanlass_procurement_order DROP COLUMN IF EXISTS %s', $column));
        }
    }
}
