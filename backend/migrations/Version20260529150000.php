<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paket 5 – Virtuelle Kombo + vereinheitlichtes Options-/Delta-Fundament (Weg B).
 *
 * - Options-/Delta-Schema für Kombo (material_combo_option_group / _option / _option_delta)
 *   und gespiegelt für Vorlagen (material_template_option*).
 * - component_source ('stock' | 'self_provided') auf Basis-Stücklisten
 *   (material_combo_component, material_template_component).
 * - Zeilenmodell B: activity_item.parent_activity_item_id + config_snapshot.
 *
 * Schema deckt Gruppen/Auswahl-Modus bereits mit ab; die Gruppen-UI folgt erst in Paket 6.
 */
final class Version20260529150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Combo options/deltas + component_source + activity_item parent line & config snapshot (Paket 5).';
    }

    public function up(Schema $schema): void
    {
        // === component_source auf Basis-Stücklisten ===
        $this->addSql("ALTER TABLE material_combo_component ADD COLUMN component_source VARCHAR(20) NOT NULL DEFAULT 'stock'");
        $this->addSql("ALTER TABLE material_template_component ADD COLUMN component_source VARCHAR(20) NOT NULL DEFAULT 'stock'");

        // === Zeilenmodell B auf activity_item ===
        $this->addSql('ALTER TABLE activity_item ADD COLUMN parent_activity_item_id CHARACTER(13) DEFAULT NULL');
        $this->addSql('ALTER TABLE activity_item ADD COLUMN config_snapshot JSON DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_activity_item_parent ON activity_item (parent_activity_item_id)');
        $this->addSql('
            ALTER TABLE activity_item
                ADD CONSTRAINT fk_activity_item_parent FOREIGN KEY (parent_activity_item_id)
                REFERENCES activity_item (id) ON DELETE CASCADE
        ');

        // === Options-/Delta-Schema (Kombo) ===
        $this->addSql("
            CREATE TABLE material_combo_option_group (
                id CHARACTER(13) NOT NULL,
                material_item_id CHARACTER(12) NOT NULL,
                name VARCHAR(120) NOT NULL,
                selection_type VARCHAR(20) NOT NULL DEFAULT 'exclusive',
                min_select INT NOT NULL DEFAULT 0,
                max_select INT DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id)
            )
        ");
        $this->addSql('CREATE INDEX idx_combo_optgroup_material ON material_combo_option_group (material_item_id)');
        $this->addSql('
            ALTER TABLE material_combo_option_group
                ADD CONSTRAINT fk_combo_optgroup_material FOREIGN KEY (material_item_id)
                REFERENCES material_item (id) ON DELETE CASCADE
        ');

        $this->addSql("
            CREATE TABLE material_combo_option (
                id CHARACTER(13) NOT NULL,
                material_item_id CHARACTER(12) NOT NULL,
                option_group_id CHARACTER(13) DEFAULT NULL,
                name VARCHAR(120) NOT NULL,
                display_mode VARCHAR(20) NOT NULL DEFAULT 'toggle',
                default_selected BOOLEAN NOT NULL DEFAULT false,
                sort_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id)
            )
        ");
        $this->addSql('CREATE INDEX idx_combo_option_material ON material_combo_option (material_item_id)');
        $this->addSql('CREATE INDEX idx_combo_option_group ON material_combo_option (option_group_id)');
        $this->addSql('
            ALTER TABLE material_combo_option
                ADD CONSTRAINT fk_combo_option_material FOREIGN KEY (material_item_id)
                REFERENCES material_item (id) ON DELETE CASCADE
        ');
        $this->addSql('
            ALTER TABLE material_combo_option
                ADD CONSTRAINT fk_combo_option_group FOREIGN KEY (option_group_id)
                REFERENCES material_combo_option_group (id) ON DELETE CASCADE
        ');

        $this->addSql("
            CREATE TABLE material_combo_option_delta (
                id CHARACTER(13) NOT NULL,
                option_id CHARACTER(13) NOT NULL,
                component_material_id CHARACTER(12) NOT NULL,
                qty_delta INT NOT NULL DEFAULT 0,
                assignment_mode VARCHAR(20) NOT NULL DEFAULT 'bulk',
                tracking VARCHAR(20) DEFAULT NULL,
                component_source VARCHAR(20) NOT NULL DEFAULT 'stock',
                sort_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id)
            )
        ");
        $this->addSql('CREATE INDEX idx_combo_optdelta_option ON material_combo_option_delta (option_id)');
        $this->addSql('CREATE INDEX idx_combo_optdelta_component ON material_combo_option_delta (component_material_id)');
        $this->addSql('
            ALTER TABLE material_combo_option_delta
                ADD CONSTRAINT fk_combo_optdelta_option FOREIGN KEY (option_id)
                REFERENCES material_combo_option (id) ON DELETE CASCADE
        ');
        $this->addSql('
            ALTER TABLE material_combo_option_delta
                ADD CONSTRAINT fk_combo_optdelta_component FOREIGN KEY (component_material_id)
                REFERENCES material_item (id) ON DELETE CASCADE
        ');

        // === Options-/Delta-Schema (Vorlage, gespiegelt) ===
        $this->addSql("
            CREATE TABLE material_template_option_group (
                id CHARACTER(12) NOT NULL,
                template_id CHARACTER(12) NOT NULL,
                name VARCHAR(120) NOT NULL,
                selection_type VARCHAR(20) NOT NULL DEFAULT 'exclusive',
                min_select INT NOT NULL DEFAULT 0,
                max_select INT DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id)
            )
        ");
        $this->addSql('CREATE INDEX idx_tpl_optgroup_template ON material_template_option_group (template_id)');
        $this->addSql('
            ALTER TABLE material_template_option_group
                ADD CONSTRAINT fk_tpl_optgroup_template FOREIGN KEY (template_id)
                REFERENCES material_template (id) ON DELETE CASCADE
        ');

        $this->addSql("
            CREATE TABLE material_template_option (
                id CHARACTER(12) NOT NULL,
                template_id CHARACTER(12) NOT NULL,
                option_group_id CHARACTER(12) DEFAULT NULL,
                name VARCHAR(120) NOT NULL,
                display_mode VARCHAR(20) NOT NULL DEFAULT 'toggle',
                default_selected BOOLEAN NOT NULL DEFAULT false,
                sort_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id)
            )
        ");
        $this->addSql('CREATE INDEX idx_tpl_option_template ON material_template_option (template_id)');
        $this->addSql('CREATE INDEX idx_tpl_option_group ON material_template_option (option_group_id)');
        $this->addSql('
            ALTER TABLE material_template_option
                ADD CONSTRAINT fk_tpl_option_template FOREIGN KEY (template_id)
                REFERENCES material_template (id) ON DELETE CASCADE
        ');
        $this->addSql('
            ALTER TABLE material_template_option
                ADD CONSTRAINT fk_tpl_option_group FOREIGN KEY (option_group_id)
                REFERENCES material_template_option_group (id) ON DELETE CASCADE
        ');

        $this->addSql("
            CREATE TABLE material_template_option_delta (
                id CHARACTER(12) NOT NULL,
                option_id CHARACTER(12) NOT NULL,
                component_type VARCHAR(60) NOT NULL,
                name VARCHAR(160) NOT NULL,
                qty_delta INT NOT NULL DEFAULT 0,
                tracking VARCHAR(20) NOT NULL DEFAULT 'bulk',
                component_source VARCHAR(20) NOT NULL DEFAULT 'stock',
                is_generic BOOLEAN NOT NULL DEFAULT false,
                sort_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id)
            )
        ");
        $this->addSql('CREATE INDEX idx_tpl_optdelta_option ON material_template_option_delta (option_id)');
        $this->addSql('
            ALTER TABLE material_template_option_delta
                ADD CONSTRAINT fk_tpl_optdelta_option FOREIGN KEY (option_id)
                REFERENCES material_template_option (id) ON DELETE CASCADE
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS material_template_option_delta');
        $this->addSql('DROP TABLE IF EXISTS material_template_option');
        $this->addSql('DROP TABLE IF EXISTS material_template_option_group');
        $this->addSql('DROP TABLE IF EXISTS material_combo_option_delta');
        $this->addSql('DROP TABLE IF EXISTS material_combo_option');
        $this->addSql('DROP TABLE IF EXISTS material_combo_option_group');

        $this->addSql('ALTER TABLE activity_item DROP CONSTRAINT IF EXISTS fk_activity_item_parent');
        $this->addSql('DROP INDEX IF EXISTS idx_activity_item_parent');
        $this->addSql('ALTER TABLE activity_item DROP COLUMN IF EXISTS config_snapshot');
        $this->addSql('ALTER TABLE activity_item DROP COLUMN IF EXISTS parent_activity_item_id');

        $this->addSql('ALTER TABLE material_template_component DROP COLUMN IF EXISTS component_source');
        $this->addSql('ALTER TABLE material_combo_component DROP COLUMN IF EXISTS component_source');
    }
}
