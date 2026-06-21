<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove pack group intent feature (activity_pack_group_intent, intent_id on pack_item)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_pack_item_intent');
        $this->addSql('ALTER TABLE activity_pack_item DROP COLUMN IF EXISTS intent_id');
        $this->addSql('DROP TABLE IF EXISTS activity_pack_group_intent');
    }

    public function down(Schema $schema): void
    {
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
        $this->addSql('ALTER TABLE activity_pack_item ADD COLUMN IF NOT EXISTS intent_id CHARACTER(13) DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_pack_item_intent ON activity_pack_item (intent_id)');
    }
}
