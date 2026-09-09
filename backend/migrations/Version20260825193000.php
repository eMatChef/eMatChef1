<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Gast sieht Lager oder Event';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE department_grossanlass_config ADD COLUMN IF NOT EXISTS guest_activity_type VARCHAR(20) DEFAULT 'camp' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_config DROP COLUMN IF EXISTS guest_activity_type');
    }
}
