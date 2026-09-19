<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918225300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Stern am GA-Ort (wichtige Punkte auf Stammdaten, Rest intern)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_place ADD COLUMN IF NOT EXISTS starred BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_place DROP COLUMN IF EXISTS starred');
    }
}
