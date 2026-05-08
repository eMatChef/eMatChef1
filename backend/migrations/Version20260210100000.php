<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Kontaktfelder zur Adress-Tabelle hinzufügen (E-Mail, Telefon, Mobil)
 */
final class Version20260210100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email, phone, mobile columns to address table for contact book functionality';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address ADD COLUMN email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE address ADD COLUMN phone VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE address ADD COLUMN mobile VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address DROP COLUMN IF EXISTS email');
        $this->addSql('ALTER TABLE address DROP COLUMN IF EXISTS phone');
        $this->addSql('ALTER TABLE address DROP COLUMN IF EXISTS mobile');
    }
}
