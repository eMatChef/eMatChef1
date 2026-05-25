<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Kontaktperson (Vor-/Nachname) auf Adressen. */
final class Version20260524120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add contact_first_name and contact_last_name to address table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address ADD contact_first_name VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE address ADD contact_last_name VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address DROP contact_last_name');
        $this->addSql('ALTER TABLE address DROP contact_first_name');
    }
}
