<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass-Anfragen: Webseite, Was, Hinweise, Kontakt, Telefon für Mail-Platzhalter';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE department_grossanlass_inquiry ADD COLUMN IF NOT EXISTS website VARCHAR(500) DEFAULT '' NOT NULL");
        $this->addSql("ALTER TABLE department_grossanlass_inquiry ADD COLUMN IF NOT EXISTS offering TEXT DEFAULT '' NOT NULL");
        $this->addSql("ALTER TABLE department_grossanlass_inquiry ADD COLUMN IF NOT EXISTS notes TEXT DEFAULT '' NOT NULL");
        $this->addSql("ALTER TABLE department_grossanlass_inquiry ADD COLUMN IF NOT EXISTS contact_name VARCHAR(255) DEFAULT '' NOT NULL");
        $this->addSql("ALTER TABLE department_grossanlass_inquiry ADD COLUMN IF NOT EXISTS phone VARCHAR(64) DEFAULT '' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_inquiry DROP COLUMN IF EXISTS phone');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry DROP COLUMN IF EXISTS contact_name');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry DROP COLUMN IF EXISTS notes');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry DROP COLUMN IF EXISTS offering');
        $this->addSql('ALTER TABLE department_grossanlass_inquiry DROP COLUMN IF EXISTS website');
    }
}
