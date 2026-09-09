<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass-Wunsch: Flag «genug vorhanden» (ohne weitere Anfrage)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line ADD COLUMN IF NOT EXISTS enough_on_hand BOOLEAN DEFAULT FALSE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line DROP COLUMN IF EXISTS enough_on_hand');
    }
}
