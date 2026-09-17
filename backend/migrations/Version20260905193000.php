<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grossanlass-Wunsch: Herkunft für «genug vorhanden» (Eigenbestand / Zusage)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line ADD COLUMN IF NOT EXISTS enough_on_hand_source VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line ADD COLUMN IF NOT EXISTS enough_on_hand_detail VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line ADD COLUMN IF NOT EXISTS enough_on_hand_ref_id VARCHAR(12) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line DROP COLUMN IF EXISTS enough_on_hand_source');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line DROP COLUMN IF EXISTS enough_on_hand_detail');
        $this->addSql('ALTER TABLE activity_grossanlass_wish_line DROP COLUMN IF EXISTS enough_on_hand_ref_id');
    }
}
