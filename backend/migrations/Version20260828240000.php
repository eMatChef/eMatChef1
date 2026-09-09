<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adressen: Anrede Herr/Frau der Kontaktperson';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address ADD COLUMN IF NOT EXISTS contact_salutation VARCHAR(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address DROP COLUMN IF EXISTS contact_salutation');
    }
}
