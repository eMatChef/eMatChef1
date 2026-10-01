<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925201000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Einheit an Wunsch- und Bedarfszeile, Standard Stk';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE activity_grossanlass_wish_line ADD COLUMN IF NOT EXISTS quantity_unit VARCHAR(8) NOT NULL DEFAULT 'Stk'");
        $this->addSql("ALTER TABLE activity_grossanlass_procurement_line ADD COLUMN IF NOT EXISTS quantity_unit VARCHAR(8) NOT NULL DEFAULT 'Stk'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line DROP COLUMN IF EXISTS quantity_unit');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line DROP COLUMN IF EXISTS quantity_unit');
    }
}
