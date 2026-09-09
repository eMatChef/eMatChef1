<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass-Anfragen: Vorname und Nachname des Kontakts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE department_grossanlass_inquiry ADD COLUMN IF NOT EXISTS contact_first_name VARCHAR(128) DEFAULT '' NOT NULL");
        $this->addSql("ALTER TABLE department_grossanlass_inquiry ADD COLUMN IF NOT EXISTS contact_last_name VARCHAR(128) DEFAULT '' NOT NULL");
        $this->addSql(<<<'SQL'
UPDATE department_grossanlass_inquiry
SET
  contact_first_name = CASE
    WHEN btrim(contact_name) = '' THEN ''
    WHEN position(' ' in btrim(contact_name)) = 0 THEN left(btrim(contact_name), 128)
    ELSE left(left(btrim(contact_name), position(' ' in btrim(contact_name)) - 1), 128)
  END,
  contact_last_name = CASE
    WHEN btrim(contact_name) = '' THEN ''
    WHEN position(' ' in btrim(contact_name)) = 0 THEN ''
    ELSE left(btrim(substring(btrim(contact_name) from position(' ' in btrim(contact_name)) + 1)), 128)
  END
WHERE btrim(contact_first_name) = '' AND btrim(contact_last_name) = '' AND btrim(contact_name) <> ''
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_inquiry DROP COLUMN IF EXISTS contact_first_name');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry DROP COLUMN IF EXISTS contact_last_name');
    }
}
