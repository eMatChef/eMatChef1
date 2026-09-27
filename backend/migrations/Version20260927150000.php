<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Offerte speichert Logistikweg (abholen, zurückbringen)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_quote ADD COLUMN IF NOT EXISTS inbound_mode VARCHAR(16) NULL');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_quote ADD COLUMN IF NOT EXISTS return_needed BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_quote ADD COLUMN IF NOT EXISTS return_at TIMESTAMP(0) WITHOUT TIME ZONE NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_quote DROP COLUMN IF EXISTS return_at');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_quote DROP COLUMN IF EXISTS return_needed');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_quote DROP COLUMN IF EXISTS inbound_mode');
    }
}
