<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Direkt-Beschaffung (source/self_organized) und group_membership.can_procure';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE activity_grossanlass_procurement_line ADD COLUMN IF NOT EXISTS source VARCHAR(20) NOT NULL DEFAULT 'from_wish'");
        $this->addSql("ALTER TABLE activity_grossanlass_procurement_line ADD COLUMN IF NOT EXISTS self_organized BOOLEAN NOT NULL DEFAULT FALSE");
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_gpl_source ON activity_grossanlass_procurement_line (source)');

        $this->addSql('ALTER TABLE group_membership ADD COLUMN IF NOT EXISTS can_procure BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_gpl_source');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line DROP COLUMN IF EXISTS self_organized');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line DROP COLUMN IF EXISTS source');
        $this->addSql('ALTER TABLE group_membership DROP COLUMN IF EXISTS can_procure');
    }
}
