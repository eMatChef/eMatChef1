<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Pack-Pipeline: Transport-Stufen + Activity-Status issued → at_event.
 */
final class Version20260516120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add quantity_transport_to/back on pack items; migrate activity status issued to at_event';
    }

    public function up(Schema $schema): void
    {
        foreach (['activity_pack_item', 'activity_pack_container_item'] as $table) {
            $this->addSql("ALTER TABLE {$table} ADD quantity_transport_to INT NOT NULL DEFAULT 0");
            $this->addSql("ALTER TABLE {$table} ADD quantity_transport_back INT NOT NULL DEFAULT 0");
        }

        // Bestehende Ausgaben: Transport-Hin = Ausgabe, Retour-Rücktransport = Retour (kein sichtbarer Zwischenschritt)
        $this->addSql('UPDATE activity_pack_item SET quantity_transport_to = quantity_issued');
        $this->addSql('UPDATE activity_pack_item SET quantity_transport_back = quantity_returned');
        $this->addSql('UPDATE activity_pack_container_item SET quantity_transport_to = quantity_issued');
        $this->addSql('UPDATE activity_pack_container_item SET quantity_transport_back = quantity_returned');

        $this->addSql("UPDATE activity SET status = 'at_event' WHERE status = 'issued'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE activity SET status = 'issued' WHERE status = 'at_event'");

        foreach (['activity_pack_item', 'activity_pack_container_item'] as $table) {
            $this->addSql("ALTER TABLE {$table} DROP COLUMN quantity_transport_back");
            $this->addSql("ALTER TABLE {$table} DROP COLUMN quantity_transport_to");
        }
    }
}
