<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GA-Aufgabe: Status (open|progress|done). Bestehende Aufgaben bleiben open.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE department_grossanlass_task ADD COLUMN IF NOT EXISTS status VARCHAR(16) DEFAULT 'open' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_task DROP COLUMN IF EXISTS status');
    }
}
