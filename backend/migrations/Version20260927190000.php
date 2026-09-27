<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Postfach merkt den Anbieter (gmail, outlook), nicht nur Gmail';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE department_grossanlass_gmail_account ADD COLUMN IF NOT EXISTS provider VARCHAR(16) NOT NULL DEFAULT 'gmail'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_gmail_account DROP COLUMN IF EXISTS provider');
    }
}
