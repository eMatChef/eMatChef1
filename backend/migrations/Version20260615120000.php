<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Virtuelle Kombo Pack-Flow: source_activity_item_id auf activity_pack_container';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_pack_container ADD COLUMN IF NOT EXISTS source_activity_item_id CHARACTER(13) DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_pack_container_source_item ON activity_pack_container (source_activity_item_id)');
        $this->addSql('ALTER TABLE activity_pack_container ADD CONSTRAINT fk_pack_container_source_item FOREIGN KEY (source_activity_item_id) REFERENCES activity_item (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_pack_container DROP CONSTRAINT IF EXISTS fk_pack_container_source_item');
        $this->addSql('DROP INDEX IF EXISTS idx_pack_container_source_item');
        $this->addSql('ALTER TABLE activity_pack_container DROP COLUMN IF EXISTS source_activity_item_id');
    }
}
