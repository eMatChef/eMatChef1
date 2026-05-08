<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260307120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add global J&S marker fields to material_item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_item ADD COLUMN is_js_material BOOLEAN DEFAULT FALSE');
        $this->addSql('UPDATE material_item SET is_js_material = FALSE WHERE is_js_material IS NULL');
        $this->addSql('ALTER TABLE material_item ALTER COLUMN is_js_material SET NOT NULL');
        $this->addSql('ALTER TABLE material_item ADD COLUMN external_source VARCHAR(50) DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN material_item.is_js_material IS 'true = globales J&S/externes Material'");
        $this->addSql("COMMENT ON COLUMN material_item.external_source IS 'Quelle z.B. js_ch'");
        $this->addSql('CREATE INDEX idx_material_is_js ON material_item (is_js_material)');
        $this->addSql('CREATE INDEX idx_material_external_source ON material_item (external_source)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_material_external_source');
        $this->addSql('DROP INDEX IF EXISTS idx_material_is_js');
        $this->addSql('ALTER TABLE material_item DROP COLUMN external_source');
        $this->addSql('ALTER TABLE material_item DROP COLUMN is_js_material');
    }
}
