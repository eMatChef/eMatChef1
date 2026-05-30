<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paket 11 — supplier_material_template* (Phase 2c).
 */
final class Version20260530220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supplier portal Paket 11: supplier_material_template tables (components, options, deltas).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_material_template (
                id CHARACTER(12) NOT NULL,
                supplier_company_id CHARACTER(12) NOT NULL,
                name VARCHAR(160) NOT NULL,
                description TEXT DEFAULT NULL,
                manufacturer VARCHAR(255) DEFAULT NULL,
                model VARCHAR(100) DEFAULT NULL,
                material_type VARCHAR(20) NOT NULL DEFAULT 'physical_combo',
                tent_type VARCHAR(40) DEFAULT NULL,
                capacity INT DEFAULT NULL,
                category_hint VARCHAR(255) DEFAULT NULL,
                unit_price NUMERIC(12, 2) DEFAULT NULL,
                currency VARCHAR(3) NOT NULL DEFAULT 'CHF',
                is_active BOOLEAN NOT NULL DEFAULT true,
                visibility VARCHAR(20) NOT NULL DEFAULT 'private',
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                source VARCHAR(40) DEFAULT NULL,
                legacy_material_template_id CHARACTER(12) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_supplier_material_template_company ON supplier_material_template (supplier_company_id)');
        $this->addSql('ALTER TABLE supplier_material_template ADD CONSTRAINT fk_supplier_material_template_company FOREIGN KEY (supplier_company_id) REFERENCES supplier_company (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_material_template_component (
                id CHARACTER(12) NOT NULL,
                template_id CHARACTER(12) NOT NULL,
                component_type VARCHAR(60) NOT NULL,
                name VARCHAR(160) NOT NULL,
                required_qty INT NOT NULL DEFAULT 1,
                is_optional BOOLEAN NOT NULL DEFAULT false,
                tracking VARCHAR(20) NOT NULL DEFAULT 'bulk',
                component_source VARCHAR(20) NOT NULL DEFAULT 'stock',
                is_generic BOOLEAN NOT NULL DEFAULT false,
                sort_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_sup_tpl_comp_template ON supplier_material_template_component (template_id)');
        $this->addSql('ALTER TABLE supplier_material_template_component ADD CONSTRAINT fk_sup_tpl_comp_template FOREIGN KEY (template_id) REFERENCES supplier_material_template (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_material_template_option_group (
                id CHARACTER(12) NOT NULL,
                template_id CHARACTER(12) NOT NULL,
                name VARCHAR(120) NOT NULL,
                selection_type VARCHAR(20) NOT NULL DEFAULT 'exclusive',
                min_select INT NOT NULL DEFAULT 0,
                max_select INT DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_sup_tpl_optgroup_template ON supplier_material_template_option_group (template_id)');
        $this->addSql('ALTER TABLE supplier_material_template_option_group ADD CONSTRAINT fk_sup_tpl_optgroup_template FOREIGN KEY (template_id) REFERENCES supplier_material_template (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_material_template_option (
                id CHARACTER(12) NOT NULL,
                template_id CHARACTER(12) NOT NULL,
                option_group_id CHARACTER(12) DEFAULT NULL,
                name VARCHAR(120) NOT NULL,
                display_mode VARCHAR(20) NOT NULL DEFAULT 'toggle',
                default_selected BOOLEAN NOT NULL DEFAULT false,
                sort_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_sup_tpl_option_template ON supplier_material_template_option (template_id)');
        $this->addSql('CREATE INDEX idx_sup_tpl_option_group ON supplier_material_template_option (option_group_id)');
        $this->addSql('ALTER TABLE supplier_material_template_option ADD CONSTRAINT fk_sup_tpl_option_template FOREIGN KEY (template_id) REFERENCES supplier_material_template (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE supplier_material_template_option ADD CONSTRAINT fk_sup_tpl_option_group FOREIGN KEY (option_group_id) REFERENCES supplier_material_template_option_group (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_material_template_option_delta (
                id CHARACTER(12) NOT NULL,
                option_id CHARACTER(12) NOT NULL,
                component_type VARCHAR(60) NOT NULL,
                name VARCHAR(160) NOT NULL,
                qty_delta INT NOT NULL DEFAULT 0,
                tracking VARCHAR(20) NOT NULL DEFAULT 'bulk',
                component_source VARCHAR(20) NOT NULL DEFAULT 'stock',
                is_generic BOOLEAN NOT NULL DEFAULT false,
                sort_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_sup_tpl_optdelta_option ON supplier_material_template_option_delta (option_id)');
        $this->addSql('ALTER TABLE supplier_material_template_option_delta ADD CONSTRAINT fk_sup_tpl_optdelta_option FOREIGN KEY (option_id) REFERENCES supplier_material_template_option (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE supplier_material_template_option_delta DROP CONSTRAINT IF EXISTS fk_sup_tpl_optdelta_option');
        $this->addSql('DROP TABLE IF EXISTS supplier_material_template_option_delta');
        $this->addSql('ALTER TABLE supplier_material_template_option DROP CONSTRAINT IF EXISTS fk_sup_tpl_option_group');
        $this->addSql('ALTER TABLE supplier_material_template_option DROP CONSTRAINT IF EXISTS fk_sup_tpl_option_template');
        $this->addSql('DROP TABLE IF EXISTS supplier_material_template_option');
        $this->addSql('ALTER TABLE supplier_material_template_option_group DROP CONSTRAINT IF EXISTS fk_sup_tpl_optgroup_template');
        $this->addSql('DROP TABLE IF EXISTS supplier_material_template_option_group');
        $this->addSql('ALTER TABLE supplier_material_template_component DROP CONSTRAINT IF EXISTS fk_sup_tpl_comp_template');
        $this->addSql('DROP TABLE IF EXISTS supplier_material_template_component');
        $this->addSql('ALTER TABLE supplier_material_template DROP CONSTRAINT IF EXISTS fk_supplier_material_template_company');
        $this->addSql('DROP TABLE IF EXISTS supplier_material_template');
    }
}
