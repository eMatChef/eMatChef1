<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create batch_storage_allocation table for splitting batch quantities across multiple storage locations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE batch_storage_allocation (
                id CHARACTER(13) NOT NULL,
                batch_id CHARACTER(13) NOT NULL,
                rack_id CHARACTER(12) NOT NULL,
                slot_id CHARACTER(12) DEFAULT NULL,
                qty INT NOT NULL,
                department_id CHARACTER(12) NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_batch_alloc_batch ON batch_storage_allocation (batch_id)');
        $this->addSql('CREATE INDEX idx_batch_alloc_department ON batch_storage_allocation (department_id)');
        $this->addSql('CREATE INDEX idx_batch_alloc_rack ON batch_storage_allocation (rack_id)');
        $this->addSql('ALTER TABLE batch_storage_allocation ADD CONSTRAINT fk_batch_alloc_batch FOREIGN KEY (batch_id) REFERENCES material_batch (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE batch_storage_allocation ADD CONSTRAINT fk_batch_alloc_rack FOREIGN KEY (rack_id) REFERENCES storage_rack (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE batch_storage_allocation ADD CONSTRAINT fk_batch_alloc_slot FOREIGN KEY (slot_id) REFERENCES storage_slot (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE batch_storage_allocation ADD CONSTRAINT fk_batch_alloc_department FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS batch_storage_allocation');
    }
}
