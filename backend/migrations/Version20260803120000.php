<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * EAN / barcode_tag vom Material-Stamm (material_item) auf die Charge (material_batch).
 * Idempotent: sicher auch wenn Spalten schon verschoben wurden.
 */
final class Version20260803120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move ean and barcode_tag from material_item to material_batch';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_batch ADD COLUMN IF NOT EXISTS ean VARCHAR(13) DEFAULT NULL');
        $this->addSql('ALTER TABLE material_batch ADD COLUMN IF NOT EXISTS barcode_tag VARCHAR(50) DEFAULT NULL');

        // Nur kopieren, wenn material_item die Spalten noch hat.
        $this->addSql(<<<'SQL'
DO $$
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_name = 'material_item' AND column_name = 'ean'
  ) THEN
    UPDATE material_batch b
    SET ean = COALESCE(b.ean, NULLIF(TRIM(m.ean), '')),
        barcode_tag = COALESCE(b.barcode_tag, NULLIF(TRIM(m.barcode_tag), ''))
    FROM material_item m
    WHERE b.material_item_id = m.id
      AND (
        (m.ean IS NOT NULL AND TRIM(m.ean) <> '')
        OR (m.barcode_tag IS NOT NULL AND TRIM(m.barcode_tag) <> '')
      );
  END IF;
END $$;
SQL);

        $this->addSql('ALTER TABLE material_item DROP COLUMN IF EXISTS ean');
        $this->addSql('ALTER TABLE material_item DROP COLUMN IF EXISTS barcode_tag');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item ADD COLUMN IF NOT EXISTS ean VARCHAR(13) DEFAULT NULL');
        $this->addSql('ALTER TABLE material_item ADD COLUMN IF NOT EXISTS barcode_tag VARCHAR(50) DEFAULT NULL');

        $this->addSql(<<<'SQL'
UPDATE material_item m
SET ean = sub.ean,
    barcode_tag = sub.barcode_tag
FROM (
    SELECT DISTINCT ON (material_item_id)
        material_item_id,
        ean,
        barcode_tag
    FROM material_batch
    WHERE (ean IS NOT NULL AND TRIM(ean) <> '')
       OR (barcode_tag IS NOT NULL AND TRIM(barcode_tag) <> '')
    ORDER BY material_item_id, created_at ASC NULLS LAST, id ASC
) sub
WHERE m.id = sub.material_item_id
SQL);

        $this->addSql('ALTER TABLE material_batch DROP COLUMN IF EXISTS ean');
        $this->addSql('ALTER TABLE material_batch DROP COLUMN IF EXISTS barcode_tag');
    }
}
