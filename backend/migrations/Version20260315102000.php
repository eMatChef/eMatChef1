<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315102000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill storage_rack/storage_slot from legacy material_batch location fields and map rack_id/slot_id';
    }

    public function up(Schema $schema): void
    {
        // 1) Distinct racks aus legacy location_rack erstellen (nur wenn noch nicht vorhanden).
        $this->addSql(<<<'SQL'
            INSERT INTO storage_rack (id, department_id, storage_address_id, name, sort_order, is_active, created_at, updated_at)
            SELECT
                SUBSTRING(md5(random()::text), 1, 12)::char(12) AS id,
                mi.department_id,
                mb.storage_address_id,
                mb.location_rack,
                0,
                true,
                NOW(),
                NOW()
            FROM material_batch mb
            INNER JOIN material_item mi ON mi.id = mb.material_item_id
            LEFT JOIN storage_rack sr
              ON sr.department_id = mi.department_id
             AND COALESCE(sr.storage_address_id, '') = COALESCE(mb.storage_address_id, '')
             AND lower(sr.name) = lower(mb.location_rack)
            WHERE mb.location_rack IS NOT NULL
              AND trim(mb.location_rack) <> ''
              AND sr.id IS NULL
            GROUP BY mi.department_id, mb.storage_address_id, mb.location_rack
        SQL);

        // 2) Distinct slots pro rack erstellen.
        $this->addSql(<<<'SQL'
            INSERT INTO storage_slot (id, rack_id, name, sort_order, is_active, created_at, updated_at)
            SELECT
                SUBSTRING(md5(random()::text), 1, 12)::char(12) AS id,
                sr.id AS rack_id,
                mb.location_slot AS name,
                0,
                true,
                NOW(),
                NOW()
            FROM material_batch mb
            INNER JOIN material_item mi ON mi.id = mb.material_item_id
            INNER JOIN storage_rack sr
              ON sr.department_id = mi.department_id
             AND COALESCE(sr.storage_address_id, '') = COALESCE(mb.storage_address_id, '')
             AND lower(sr.name) = lower(mb.location_rack)
            LEFT JOIN storage_slot ss
              ON ss.rack_id = sr.id
             AND lower(ss.name) = lower(mb.location_slot)
            WHERE mb.location_slot IS NOT NULL
              AND trim(mb.location_slot) <> ''
              AND ss.id IS NULL
            GROUP BY sr.id, mb.location_slot
        SQL);

        // 3) rack_id/slot_id auf bestehenden Batches setzen, falls noch NULL.
        $this->addSql(<<<'SQL'
            UPDATE material_batch mb
            SET rack_id = sr.id
            FROM material_item mi, storage_rack sr
            WHERE mb.material_item_id = mi.id
              AND mb.rack_id IS NULL
              AND mb.location_rack IS NOT NULL
              AND trim(mb.location_rack) <> ''
              AND sr.department_id = mi.department_id
              AND COALESCE(sr.storage_address_id, '') = COALESCE(mb.storage_address_id, '')
              AND lower(sr.name) = lower(mb.location_rack)
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE material_batch mb
            SET slot_id = ss.id
            FROM storage_slot ss, storage_rack sr, material_item mi
            WHERE mb.slot_id IS NULL
              AND mb.location_slot IS NOT NULL
              AND trim(mb.location_slot) <> ''
              AND sr.id = ss.rack_id
              AND mi.id = mb.material_item_id
              AND mb.rack_id = sr.id
              AND lower(ss.name) = lower(mb.location_slot)
              AND sr.department_id = mi.department_id
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Reiner Backfill ohne sichere Rückabwicklung.
    }
}

