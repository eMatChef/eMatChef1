<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617120000 extends AbstractMigration
{
    use CreatesTableUnlessExistsTrait;

    public function getDescription(): string
    {
        return 'activity_js_order + activity_js_order_item (J+S Phase 3)';
    }

    public function up(Schema $schema): void
    {
        if ($this->prepareNewTable($schema, 'activity_js_order')) {
            $this->addSql(<<<'SQL'
            CREATE TABLE activity_js_order (
                id CHARACTER(13) NOT NULL,
                activity_id CHARACTER(12) NOT NULL,
                status VARCHAR(20) DEFAULT 'draft' NOT NULL,
                form_data JSON DEFAULT NULL,
                participant_count INT DEFAULT NULL,
                delivery_type VARCHAR(20) DEFAULT 'franko' NOT NULL,
                ordered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                ordered_by_user_id CHARACTER(12) DEFAULT NULL,
                generated_pdf_media_id CHARACTER(12) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
            $this->addSql('CREATE UNIQUE INDEX uniq_js_order_activity ON activity_js_order (activity_id)');
            $this->addSql('CREATE INDEX idx_js_order_status ON activity_js_order (status)');
            $this->addSql('ALTER TABLE activity_js_order ADD CONSTRAINT fk_js_order_activity FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE activity_js_order ADD CONSTRAINT fk_js_order_ordered_by FOREIGN KEY (ordered_by_user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        }

        if ($this->prepareNewTable($schema, 'activity_js_order_item')) {
            $this->addSql(<<<'SQL'
            CREATE TABLE activity_js_order_item (
                id CHARACTER(13) NOT NULL,
                js_order_id CHARACTER(13) NOT NULL,
                material_item_id CHARACTER(12) NOT NULL,
                quantity_ordered INT DEFAULT 0 NOT NULL,
                dotation_suggested INT DEFAULT NULL,
                order_confirmed BOOLEAN DEFAULT false NOT NULL,
                quantity_received INT DEFAULT 0 NOT NULL,
                quantity_returned INT DEFAULT 0 NOT NULL,
                notes TEXT DEFAULT NULL,
                sort_order INT DEFAULT 0 NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
            $this->addSql('CREATE INDEX idx_js_order_item_order ON activity_js_order_item (js_order_id)');
            $this->addSql('CREATE INDEX idx_js_order_item_material ON activity_js_order_item (material_item_id)');
            $this->addSql('ALTER TABLE activity_js_order_item ADD CONSTRAINT fk_js_order_item_order FOREIGN KEY (js_order_id) REFERENCES activity_js_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE activity_js_order_item ADD CONSTRAINT fk_js_order_item_material FOREIGN KEY (material_item_id) REFERENCES material_item (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_js_order_item DROP CONSTRAINT IF EXISTS fk_js_order_item_material');
        $this->addSql('ALTER TABLE activity_js_order_item DROP CONSTRAINT IF EXISTS fk_js_order_item_order');
        $this->addSql('DROP TABLE IF EXISTS activity_js_order_item');

        $this->addSql('ALTER TABLE activity_js_order DROP CONSTRAINT IF EXISTS fk_js_order_ordered_by');
        $this->addSql('ALTER TABLE activity_js_order DROP CONSTRAINT IF EXISTS fk_js_order_activity');
        $this->addSql('DROP TABLE IF EXISTS activity_js_order');
    }
}
