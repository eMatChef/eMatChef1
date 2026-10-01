<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Bedarfsposition merkt Partner oder Kauf, Standard ist noch offen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE activity_grossanlass_procurement_line ADD COLUMN IF NOT EXISTS supply_mode VARCHAR(16) NOT NULL DEFAULT 'open'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line DROP COLUMN IF EXISTS supply_mode');
    }
}
