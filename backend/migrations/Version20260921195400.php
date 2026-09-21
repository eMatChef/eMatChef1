<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921195400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Status des Bauvorhabens an Bereich/Bauprojekt';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "group" ADD COLUMN IF NOT EXISTS build_status VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "group" DROP COLUMN IF EXISTS build_status');
    }
}
