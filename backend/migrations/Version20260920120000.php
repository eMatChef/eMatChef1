<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Beschrieb (description) am Ressort/Bauprojekt';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "group" ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "group" DROP COLUMN IF EXISTS description');
    }
}
