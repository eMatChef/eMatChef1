<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Transport-Touren: Status (planned / in_transit / arrived)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE activity_transport_tour
    ADD COLUMN IF NOT EXISTS status VARCHAR(16) NOT NULL DEFAULT 'planned'
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_transport_tour_status ON activity_transport_tour (activity_id, direction, status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_transport_tour_status');
        $this->addSql('ALTER TABLE activity_transport_tour DROP COLUMN IF EXISTS status');
    }
}
