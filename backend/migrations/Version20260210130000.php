<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Erstellt die Tabellen für das Template- und Combo-System:
 * - material_template: Zelt-/Kombinations-Vorlagen
 * - material_template_component: Bauteile einer Vorlage
 * - material_combo_component: Verknüpfung Combo-Artikel ↔ Komponenten
 * - material_item: +3 Felder (tent_type, tent_capacity, reservation_mode)
 */
final class Version20260210130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create material_template, material_template_component, material_combo_component tables and add tent fields to material_item.';
    }

    public function up(Schema $schema): void
    {
        // ============================================================
        // 1. material_template – Zelt-/Kombinations-Vorlagen
        // ============================================================
        $this->addSql('
            CREATE TABLE material_template (
                id CHARACTER(12) NOT NULL,
                department_id CHARACTER(12) NOT NULL,
                name VARCHAR(160) NOT NULL,
                description TEXT DEFAULT NULL,
                manufacturer VARCHAR(255) DEFAULT NULL,
                model VARCHAR(100) DEFAULT NULL,
                category_id CHARACTER(12) DEFAULT NULL,
                material_type VARCHAR(20) NOT NULL DEFAULT \'physical_combo\',
                tent_type VARCHAR(40) DEFAULT NULL,
                capacity INT DEFAULT NULL,
                reservation_mode VARCHAR(20) DEFAULT NULL,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                source VARCHAR(40) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                CONSTRAINT fk_template_department FOREIGN KEY (department_id) REFERENCES department(id) ON DELETE CASCADE,
                CONSTRAINT fk_template_category FOREIGN KEY (category_id) REFERENCES category(id) ON DELETE SET NULL
            )
        ');

        $this->addSql('CREATE INDEX idx_template_department ON material_template (department_id)');
        $this->addSql('CREATE INDEX idx_template_manufacturer ON material_template (manufacturer)');

        // ============================================================
        // 2. material_template_component – Bauteile einer Vorlage
        // ============================================================
        $this->addSql('
            CREATE TABLE material_template_component (
                id CHARACTER(12) NOT NULL,
                template_id CHARACTER(12) NOT NULL,
                component_type VARCHAR(60) NOT NULL,
                name VARCHAR(160) NOT NULL,
                required_qty INT NOT NULL DEFAULT 1,
                is_optional BOOLEAN NOT NULL DEFAULT FALSE,
                tracking VARCHAR(20) NOT NULL DEFAULT \'bulk\',
                repair_types JSON DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                CONSTRAINT fk_tpl_comp_template FOREIGN KEY (template_id) REFERENCES material_template(id) ON DELETE CASCADE
            )
        ');

        $this->addSql('CREATE INDEX idx_tpl_comp_template ON material_template_component (template_id)');

        // ============================================================
        // 3. material_combo_component – Combo-Artikel ↔ Komponenten
        // ============================================================
        $this->addSql('
            CREATE TABLE material_combo_component (
                id CHARACTER(13) NOT NULL,
                parent_material_id CHARACTER(12) NOT NULL,
                component_material_id CHARACTER(12) NOT NULL,
                component_batch_id CHARACTER(13) DEFAULT NULL,
                qty INT NOT NULL DEFAULT 1,
                component_role VARCHAR(60) DEFAULT NULL,
                assignment_mode VARCHAR(20) NOT NULL DEFAULT \'bulk\',
                is_optional BOOLEAN NOT NULL DEFAULT FALSE,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                CONSTRAINT fk_combo_parent FOREIGN KEY (parent_material_id) REFERENCES material_item(id) ON DELETE CASCADE,
                CONSTRAINT fk_combo_component FOREIGN KEY (component_material_id) REFERENCES material_item(id) ON DELETE CASCADE,
                CONSTRAINT fk_combo_batch FOREIGN KEY (component_batch_id) REFERENCES material_batch(id) ON DELETE SET NULL
            )
        ');

        $this->addSql('CREATE INDEX idx_combo_parent ON material_combo_component (parent_material_id)');
        $this->addSql('CREATE INDEX idx_combo_component ON material_combo_component (component_material_id)');
        $this->addSql('CREATE INDEX idx_combo_batch ON material_combo_component (component_batch_id)');

        // ============================================================
        // 4. material_item – Zelt-spezifische Felder hinzufügen
        // ============================================================
        $this->addSql('ALTER TABLE material_item ADD COLUMN tent_type VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE material_item ADD COLUMN tent_capacity INT DEFAULT NULL');
        $this->addSql('ALTER TABLE material_item ADD COLUMN reservation_mode VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Neue Spalten entfernen
        $this->addSql('ALTER TABLE material_item DROP COLUMN IF EXISTS tent_type');
        $this->addSql('ALTER TABLE material_item DROP COLUMN IF EXISTS tent_capacity');
        $this->addSql('ALTER TABLE material_item DROP COLUMN IF EXISTS reservation_mode');

        // Tabellen löschen (umgekehrte Reihenfolge wegen FKs)
        $this->addSql('DROP TABLE IF EXISTS material_combo_component');
        $this->addSql('DROP TABLE IF EXISTS material_template_component');
        $this->addSql('DROP TABLE IF EXISTS material_template');
    }
}
