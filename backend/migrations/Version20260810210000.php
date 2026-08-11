<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pack-Pipeline: quantity_wet + Trocknungs-Metadaten auf pack_item und pack_container_item.';
    }

    public function up(Schema $schema): void
    {
        foreach (['activity_pack_item', 'activity_pack_container_item'] as $table) {
            $this->addSql("ALTER TABLE {$table} ADD quantity_wet INT NOT NULL DEFAULT 0");
            $this->addSql("ALTER TABLE {$table} ADD wet_hung BOOLEAN DEFAULT NULL");
            $this->addSql("ALTER TABLE {$table} ADD wet_drying_storage_address_id CHARACTER(12) DEFAULT NULL");
            $this->addSql("ALTER TABLE {$table} ADD wet_drying_rack_id VARCHAR(64) DEFAULT NULL");
            $this->addSql("ALTER TABLE {$table} ADD wet_drying_slot_id VARCHAR(64) DEFAULT NULL");
            $this->addSql("ALTER TABLE {$table} ADD wet_drying_location_label VARCHAR(255) DEFAULT NULL");
            $this->addSql("ALTER TABLE {$table} ADD wet_workshop_ticket_id CHARACTER(13) DEFAULT NULL");
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['activity_pack_item', 'activity_pack_container_item'] as $table) {
            $this->addSql("ALTER TABLE {$table} DROP quantity_wet");
            $this->addSql("ALTER TABLE {$table} DROP wet_hung");
            $this->addSql("ALTER TABLE {$table} DROP wet_drying_storage_address_id");
            $this->addSql("ALTER TABLE {$table} DROP wet_drying_rack_id");
            $this->addSql("ALTER TABLE {$table} DROP wet_drying_slot_id");
            $this->addSql("ALTER TABLE {$table} DROP wet_drying_location_label");
            $this->addSql("ALTER TABLE {$table} DROP wet_workshop_ticket_id");
        }
    }
}
