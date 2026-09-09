<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825250000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass-Zusagen: Menge und Artikeldetails (VE, Gewicht, Teile)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE department_grossanlass_commitment ADD COLUMN IF NOT EXISTS quantity INT DEFAULT 1 NOT NULL");
        $this->addSql("ALTER TABLE department_grossanlass_commitment ADD COLUMN IF NOT EXISTS item_details JSON DEFAULT '[]' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department_grossanlass_commitment DROP COLUMN IF EXISTS quantity');
        $this->addSql('ALTER TABLE department_grossanlass_commitment DROP COLUMN IF EXISTS item_details');
    }
}
