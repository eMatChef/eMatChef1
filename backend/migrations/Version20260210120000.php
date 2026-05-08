<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Erweitert activity_pack_item um quantity_issued und quantity_returned
 * für das 4-stufige Workflow-Board: Bestätigt → Gepackt → Am Event → Retour
 */
final class Version20260210120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add quantity_issued and quantity_returned columns to activity_pack_item for 4-stage workflow board.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_pack_item ADD COLUMN quantity_issued INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE activity_pack_item ADD COLUMN quantity_returned INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_pack_item DROP COLUMN IF EXISTS quantity_issued');
        $this->addSql('ALTER TABLE activity_pack_item DROP COLUMN IF EXISTS quantity_returned');
    }
}
