<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Workshop-Ticket: affected_quantity für teilweise betroffene Bulk-Mengen.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE workshop_ticket ADD affected_quantity INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE workshop_ticket DROP COLUMN IF EXISTS affected_quantity');
    }
}
