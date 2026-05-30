<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Vorlagen-Editor Paket 1: Hersteller-Picker (FK address), template_kind, template_domain.
 */
final class Version20260530240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Material template: manufacturer_address_id, template_kind, template_domain.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_template ADD manufacturer_address_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE material_template ADD template_kind VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE material_template ADD template_domain VARCHAR(40) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_template_manufacturer_address ON material_template (manufacturer_address_id)');
        $this->addSql('ALTER TABLE material_template ADD CONSTRAINT fk_material_template_manufacturer_address FOREIGN KEY (manufacturer_address_id) REFERENCES address (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE material_template DROP CONSTRAINT IF EXISTS fk_material_template_manufacturer_address');
        $this->addSql('DROP INDEX IF EXISTS idx_template_manufacturer_address');
        $this->addSql('ALTER TABLE material_template DROP COLUMN IF EXISTS template_domain');
        $this->addSql('ALTER TABLE material_template DROP COLUMN IF EXISTS template_kind');
        $this->addSql('ALTER TABLE material_template DROP COLUMN IF EXISTS manufacturer_address_id');
    }
}
