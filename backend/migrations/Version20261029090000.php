<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Beschaffung: Teilmenge an der Bestellung, entschiedene Wunschsumme an der Position. Rein additiv.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_order ADD COLUMN IF NOT EXISTS quantity INT DEFAULT NULL');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line ADD COLUMN IF NOT EXISTS source_quantity_ack INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_order DROP COLUMN IF EXISTS quantity');
        $this->addSql('ALTER TABLE activity_grossanlass_procurement_line DROP COLUMN IF EXISTS source_quantity_ack');
    }
}
