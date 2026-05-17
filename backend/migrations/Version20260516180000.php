<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Address soft delete (deleted_at, deleted_by_user_id).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE address ADD deleted_by_user_id CHARACTER(12) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_address_deleted_at ON address (deleted_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_address_deleted_at');
        $this->addSql('ALTER TABLE address DROP deleted_by_user_id');
        $this->addSql('ALTER TABLE address DROP deleted_at');
    }
}
