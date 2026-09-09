<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass: Material-Stufe Grob/Fein an Runden, last_stage am Wunsch, quantity_asked an Beschaffung';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_round ADD COLUMN IF NOT EXISTS material_stage VARCHAR(8) DEFAULT NULL');
        $this->addSql("UPDATE activity_grossanlass_round SET material_stage = 'grob' WHERE form_purpose = 'material_wish' AND material_stage IS NULL");
        $this->addSql("ALTER TABLE activity_grossanlass_wish_line ADD COLUMN IF NOT EXISTS last_stage VARCHAR(8) DEFAULT 'grob' NOT NULL");
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line ADD COLUMN IF NOT EXISTS quantity_asked INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line DROP COLUMN IF EXISTS quantity_asked');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line DROP COLUMN IF EXISTS last_stage');
        $this->addSql('ALTER TABLE activity_grossanlass_round DROP COLUMN IF EXISTS material_stage');
    }
}
