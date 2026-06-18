<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Beleg-Anhänge (JSON) für ausstehende Anschaffungs-Aufträge';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accounting_acquisition_follow_up ADD COLUMN IF NOT EXISTS receipts JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accounting_acquisition_follow_up DROP COLUMN IF EXISTS receipts');
    }
}
