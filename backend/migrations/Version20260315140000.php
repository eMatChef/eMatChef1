<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add container_batch_id to batch_storage_allocation for material in Kiste (MaterialBatch) or direct slot';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE batch_storage_allocation ADD container_batch_id CHAR(13) DEFAULT NULL');
        $this->addSql('ALTER TABLE batch_storage_allocation ADD CONSTRAINT fk_batch_alloc_container_batch FOREIGN KEY (container_batch_id) REFERENCES material_batch (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_batch_alloc_container ON batch_storage_allocation (container_batch_id)');
        $this->addSql('ALTER TABLE batch_storage_allocation ALTER COLUMN rack_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE batch_storage_allocation ALTER COLUMN rack_id SET NOT NULL');
        $this->addSql('ALTER TABLE batch_storage_allocation DROP CONSTRAINT fk_batch_alloc_container_batch');
        $this->addSql('DROP INDEX idx_batch_alloc_container ON batch_storage_allocation');
        $this->addSql('ALTER TABLE batch_storage_allocation DROP COLUMN container_batch_id');
    }
}
