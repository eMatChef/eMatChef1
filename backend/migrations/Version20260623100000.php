<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phases 9b–12: replenishment wishes, pack group intents, pack session presence, intent_id on pack_item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS activity_replenishment_wish (
    id CHARACTER(13) NOT NULL,
    activity_id CHARACTER(12) NOT NULL,
    material_item_id CHARACTER(12) NOT NULL,
    quantity_requested INT NOT NULL,
    notes TEXT DEFAULT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    requested_by_user_id CHARACTER(12) NOT NULL,
    requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    decided_by_user_id CHARACTER(12) DEFAULT NULL,
    decided_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    rejection_reason TEXT DEFAULT NULL,
    fulfilled_activity_item_id CHARACTER(13) DEFAULT NULL,
    availability_snapshot JSON DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_replenishment_wish_activity ON activity_replenishment_wish (activity_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_replenishment_wish_status ON activity_replenishment_wish (activity_id, status)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_replenishment_wish_activity') THEN
        ALTER TABLE activity_replenishment_wish ADD CONSTRAINT fk_replenishment_wish_activity
            FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS activity_pack_group_intent (
    id CHARACTER(13) NOT NULL,
    activity_id CHARACTER(12) NOT NULL,
    label VARCHAR(80) DEFAULT NULL,
    created_by_user_id CHARACTER(12) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    resolved_container_id CHARACTER(13) DEFAULT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_pack_group_intent_activity ON activity_pack_group_intent (activity_id)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_pack_group_intent_activity') THEN
        ALTER TABLE activity_pack_group_intent ADD CONSTRAINT fk_pack_group_intent_activity
            FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);

        $this->addSql('ALTER TABLE activity_pack_item ADD COLUMN IF NOT EXISTS intent_id CHARACTER(13) DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_pack_item_intent ON activity_pack_item (intent_id)');

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS activity_pack_session_presence (
    id CHARACTER(13) NOT NULL,
    activity_id CHARACTER(12) NOT NULL,
    user_id CHARACTER(12) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    shelf VARCHAR(80) DEFAULT NULL,
    container_id CHARACTER(13) DEFAULT NULL,
    journey_step VARCHAR(32) DEFAULT NULL,
    last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_pack_presence_activity_user ON activity_pack_session_presence (activity_id, user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_pack_presence_activity ON activity_pack_session_presence (activity_id, last_seen_at)');
        $this->addSql(<<<'SQL'
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_pack_presence_activity') THEN
        ALTER TABLE activity_pack_session_presence ADD CONSTRAINT fk_pack_presence_activity
            FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
    END IF;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS activity_pack_session_presence');
        $this->addSql('ALTER TABLE activity_pack_item DROP COLUMN IF EXISTS intent_id');
        $this->addSql('DROP TABLE IF EXISTS activity_pack_group_intent');
        $this->addSql('DROP TABLE IF EXISTS activity_replenishment_wish');
    }
}
