<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Meterware: Länge/VE pro Charge; am Material optional packaging_unit (VE-Bezeichnung bei pack_unit=m).
 */
final class Version20260815210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add batch size_length/pack_* and material packaging_unit for meter VE per receipt';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_batch ADD COLUMN IF NOT EXISTS size_length VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE material_batch ADD COLUMN IF NOT EXISTS pack_size INT DEFAULT NULL');
        $this->addSql('ALTER TABLE material_batch ADD COLUMN IF NOT EXISTS pack_unit VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE material_item ADD COLUMN IF NOT EXISTS packaging_unit VARCHAR(40) DEFAULT NULL');

        // Bestehende Meterware: Stammlänge auf Chargen übernehmen (Fallback).
        $this->addSql(<<<'SQL'
UPDATE material_batch b
SET size_length = m.size_length
FROM material_item m
WHERE b.material_item_id = m.id
  AND b.size_length IS NULL
  AND m.size_length IS NOT NULL
  AND TRIM(m.size_length) <> ''
  AND LOWER(TRIM(COALESCE(m.pack_unit, ''))) IN ('m', 'meter', 'metre')
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_batch DROP COLUMN IF EXISTS size_length');
        $this->addSql('ALTER TABLE material_batch DROP COLUMN IF EXISTS pack_size');
        $this->addSql('ALTER TABLE material_batch DROP COLUMN IF EXISTS pack_unit');
        $this->addSql('ALTER TABLE material_item DROP COLUMN IF EXISTS packaging_unit');
    }
}
