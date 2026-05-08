<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create storage rack/slot and activity pack container tables; extend material_batch with relational storage and split lineage fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE storage_rack (
                id CHARACTER(12) NOT NULL,
                department_id CHARACTER(12) NOT NULL,
                storage_address_id CHARACTER(12) DEFAULT NULL,
                name VARCHAR(80) NOT NULL,
                sort_order INT DEFAULT 0 NOT NULL,
                is_active BOOLEAN DEFAULT true NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_storage_rack_department ON storage_rack (department_id)');
        $this->addSql('CREATE INDEX idx_storage_rack_address ON storage_rack (storage_address_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_storage_rack_name ON storage_rack (department_id, storage_address_id, name)');
        $this->addSql('ALTER TABLE storage_rack ADD CONSTRAINT fk_storage_rack_department FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE storage_rack ADD CONSTRAINT fk_storage_rack_address FOREIGN KEY (storage_address_id) REFERENCES address (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE storage_slot (
                id CHARACTER(12) NOT NULL,
                rack_id CHARACTER(12) NOT NULL,
                name VARCHAR(80) NOT NULL,
                sort_order INT DEFAULT 0 NOT NULL,
                is_active BOOLEAN DEFAULT true NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_storage_slot_rack ON storage_slot (rack_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_storage_slot_name ON storage_slot (rack_id, name)');
        $this->addSql('ALTER TABLE storage_slot ADD CONSTRAINT fk_storage_slot_rack FOREIGN KEY (rack_id) REFERENCES storage_rack (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE material_batch ADD COLUMN rack_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE material_batch ADD COLUMN slot_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE material_batch ADD COLUMN source_batch_id CHARACTER(13) DEFAULT NULL');
        $this->addSql('ALTER TABLE material_batch ADD COLUMN conversion_group_id VARCHAR(40) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_material_batch_rack ON material_batch (rack_id)');
        $this->addSql('CREATE INDEX idx_material_batch_slot ON material_batch (slot_id)');
        $this->addSql('CREATE INDEX idx_material_batch_source ON material_batch (source_batch_id)');
        $this->addSql('CREATE INDEX idx_material_batch_conversion_group ON material_batch (conversion_group_id)');
        $this->addSql('ALTER TABLE material_batch ADD CONSTRAINT fk_material_batch_rack FOREIGN KEY (rack_id) REFERENCES storage_rack (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE material_batch ADD CONSTRAINT fk_material_batch_slot FOREIGN KEY (slot_id) REFERENCES storage_slot (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE material_batch ADD CONSTRAINT fk_material_batch_source FOREIGN KEY (source_batch_id) REFERENCES material_batch (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE activity_pack_container (
                id CHARACTER(13) NOT NULL,
                activity_id CHARACTER(12) NOT NULL,
                container_batch_id CHARACTER(13) DEFAULT NULL,
                label VARCHAR(120) NOT NULL,
                status VARCHAR(20) DEFAULT 'draft' NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_pack_container_activity ON activity_pack_container (activity_id)');
        $this->addSql('CREATE INDEX idx_pack_container_batch ON activity_pack_container (container_batch_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_pack_container_activity_batch ON activity_pack_container (activity_id, container_batch_id)');
        $this->addSql('ALTER TABLE activity_pack_container ADD CONSTRAINT fk_pack_container_activity FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity_pack_container ADD CONSTRAINT fk_pack_container_batch FOREIGN KEY (container_batch_id) REFERENCES material_batch (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE activity_pack_container_item (
                id CHARACTER(13) NOT NULL,
                pack_container_id CHARACTER(13) NOT NULL,
                material_item_id CHARACTER(12) NOT NULL,
                material_batch_id CHARACTER(13) DEFAULT NULL,
                quantity_packed INT DEFAULT 0 NOT NULL,
                quantity_issued INT DEFAULT 0 NOT NULL,
                quantity_returned INT DEFAULT 0 NOT NULL,
                condition_out VARCHAR(50) DEFAULT 'ok' NOT NULL,
                notes TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_pack_container_item_container ON activity_pack_container_item (pack_container_id)');
        $this->addSql('CREATE INDEX idx_pack_container_item_material ON activity_pack_container_item (material_item_id)');
        $this->addSql('CREATE INDEX idx_pack_container_item_batch ON activity_pack_container_item (material_batch_id)');
        $this->addSql('ALTER TABLE activity_pack_container_item ADD CONSTRAINT fk_pack_container_item_container FOREIGN KEY (pack_container_id) REFERENCES activity_pack_container (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity_pack_container_item ADD CONSTRAINT fk_pack_container_item_material FOREIGN KEY (material_item_id) REFERENCES material_item (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity_pack_container_item ADD CONSTRAINT fk_pack_container_item_batch FOREIGN KEY (material_batch_id) REFERENCES material_batch (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_pack_container_item DROP CONSTRAINT IF EXISTS fk_pack_container_item_batch');
        $this->addSql('ALTER TABLE activity_pack_container_item DROP CONSTRAINT IF EXISTS fk_pack_container_item_material');
        $this->addSql('ALTER TABLE activity_pack_container_item DROP CONSTRAINT IF EXISTS fk_pack_container_item_container');
        $this->addSql('DROP TABLE IF EXISTS activity_pack_container_item');

        $this->addSql('ALTER TABLE activity_pack_container DROP CONSTRAINT IF EXISTS fk_pack_container_batch');
        $this->addSql('ALTER TABLE activity_pack_container DROP CONSTRAINT IF EXISTS fk_pack_container_activity');
        $this->addSql('DROP TABLE IF EXISTS activity_pack_container');

        $this->addSql('ALTER TABLE material_batch DROP CONSTRAINT IF EXISTS fk_material_batch_source');
        $this->addSql('ALTER TABLE material_batch DROP CONSTRAINT IF EXISTS fk_material_batch_slot');
        $this->addSql('ALTER TABLE material_batch DROP CONSTRAINT IF EXISTS fk_material_batch_rack');
        $this->addSql('DROP INDEX IF EXISTS idx_material_batch_conversion_group');
        $this->addSql('DROP INDEX IF EXISTS idx_material_batch_source');
        $this->addSql('DROP INDEX IF EXISTS idx_material_batch_slot');
        $this->addSql('DROP INDEX IF EXISTS idx_material_batch_rack');
        $this->addSql('ALTER TABLE material_batch DROP COLUMN IF EXISTS conversion_group_id');
        $this->addSql('ALTER TABLE material_batch DROP COLUMN IF EXISTS source_batch_id');
        $this->addSql('ALTER TABLE material_batch DROP COLUMN IF EXISTS slot_id');
        $this->addSql('ALTER TABLE material_batch DROP COLUMN IF EXISTS rack_id');

        $this->addSql('ALTER TABLE storage_slot DROP CONSTRAINT IF EXISTS fk_storage_slot_rack');
        $this->addSql('DROP TABLE IF EXISTS storage_slot');

        $this->addSql('ALTER TABLE storage_rack DROP CONSTRAINT IF EXISTS fk_storage_rack_address');
        $this->addSql('ALTER TABLE storage_rack DROP CONSTRAINT IF EXISTS fk_storage_rack_department');
        $this->addSql('DROP TABLE IF EXISTS storage_rack');
    }
}

