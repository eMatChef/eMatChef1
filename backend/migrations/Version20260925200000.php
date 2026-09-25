<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Zurückbringen an Wunsch- und Bedarfszeile';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line ADD COLUMN IF NOT EXISTS return_needed BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line ADD COLUMN IF NOT EXISTS return_needed BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line DROP COLUMN IF EXISTS return_needed');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line DROP COLUMN IF EXISTS return_needed');
    }
}
