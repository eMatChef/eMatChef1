<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ActivityItem: mark Verbrauchsmaterial-Nachbuchung (separate Zeile für Lager-/Nachbuch-Hinweis in Packliste)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_item ADD COLUMN is_replenishment BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_item DROP COLUMN IF EXISTS is_replenishment');
    }
}
