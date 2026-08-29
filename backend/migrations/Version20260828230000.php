<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass-Anfragen: Anrede Herr/Frau am Kontakt';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE department_grossanlass_inquiry ADD COLUMN IF NOT EXISTS contact_salutation VARCHAR(16) DEFAULT '' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_inquiry DROP COLUMN IF EXISTS contact_salutation');
    }
}
