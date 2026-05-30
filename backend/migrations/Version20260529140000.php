<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paket 4 – „Verwandtes Zubehör" (separate Empfehlungs-Verknüpfung).
 *
 * Neue Relation Material → Materialien, GETRENNT von der Stückliste
 * (`material_combo_component`). Spiegel-Tabelle für Vorlagen, die beim
 * „Vorlage → Material" zu konkreten Verknüpfungen aufgelöst wird.
 */
final class Version20260529140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add material_related_accessory and material_template_related_accessory (related accessories).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE material_related_accessory (
                id CHARACTER(13) NOT NULL,
                material_id CHARACTER(12) NOT NULL,
                accessory_material_id CHARACTER(12) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        ");
        $this->addSql('CREATE INDEX idx_related_accessory_material ON material_related_accessory (material_id)');
        $this->addSql('CREATE INDEX idx_related_accessory_accessory ON material_related_accessory (accessory_material_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_related_accessory_pair ON material_related_accessory (material_id, accessory_material_id)');
        $this->addSql('
            ALTER TABLE material_related_accessory
                ADD CONSTRAINT fk_related_accessory_material FOREIGN KEY (material_id)
                REFERENCES material_item (id) ON DELETE CASCADE
        ');
        $this->addSql('
            ALTER TABLE material_related_accessory
                ADD CONSTRAINT fk_related_accessory_accessory FOREIGN KEY (accessory_material_id)
                REFERENCES material_item (id) ON DELETE CASCADE
        ');

        $this->addSql("
            CREATE TABLE material_template_related_accessory (
                id CHARACTER(12) NOT NULL,
                template_id CHARACTER(12) NOT NULL,
                name VARCHAR(160) NOT NULL,
                component_type VARCHAR(60) DEFAULT NULL,
                is_generic BOOLEAN NOT NULL DEFAULT false,
                sort_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id)
            )
        ");
        $this->addSql('CREATE INDEX idx_tpl_related_accessory_template ON material_template_related_accessory (template_id)');
        $this->addSql('
            ALTER TABLE material_template_related_accessory
                ADD CONSTRAINT fk_tpl_related_accessory_template FOREIGN KEY (template_id)
                REFERENCES material_template (id) ON DELETE CASCADE
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS material_template_related_accessory');
        $this->addSql('DROP TABLE IF EXISTS material_related_accessory');
    }
}
