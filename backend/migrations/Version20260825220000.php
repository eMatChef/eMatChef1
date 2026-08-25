<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Druckkatalog: QL-720NW, DK-11208/11219/11221, Avery 3425/6122/L6140/L4716/L6107, ISO A4–A8';
    }

    public function up(Schema $schema): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->addSql("INSERT INTO print_media (id, catalog_key, family, brand, sku, name, width_mm, height_mm, cols, rows, is_continuous, default_cut_length_mm, status, scope, created_at, updated_at) VALUES
            ('pm1120800001', 'brother_dk_11208', 'brother_ql', 'Brother', 'DK-11208', 'Brother DK-11208 38×90 mm', 38.00, 90.00, 1, 1, FALSE, NULL, 'published', 'global', '{$now}', '{$now}'),
            ('pm1121900001', 'brother_dk_11219', 'brother_ql', 'Brother', 'DK-11219', 'Brother DK-11219 Ø12 mm rund', 12.00, 12.00, 1, 1, FALSE, NULL, 'published', 'global', '{$now}', '{$now}'),
            ('pm1122100001', 'brother_dk_11221', 'brother_ql', 'Brother', 'DK-11221', 'Brother DK-11221 23×23 mm', 23.00, 23.00, 1, 1, FALSE, NULL, 'published', 'global', '{$now}', '{$now}'),
            ('pm3425000001', 'avery_3425', 'office_a4', 'Avery Zweckform', '3425', 'Avery 3425 105×57 mm (10/A4)', 105.00, 57.00, 2, 5, FALSE, NULL, 'published', 'global', '{$now}', '{$now}'),
            ('pm6122000001', 'avery_6122', 'office_a4', 'Avery Zweckform', '6122', 'Avery 6122 70×36 mm (24/A4)', 70.00, 36.00, 3, 8, FALSE, NULL, 'published', 'global', '{$now}', '{$now}'),
            ('pml614000001', 'avery_l6140', 'office_a4', 'Avery Zweckform', 'L6140-20', 'Avery L6140-20 45,7×25,4 mm (40/A4)', 45.70, 25.40, 4, 10, FALSE, NULL, 'published', 'global', '{$now}', '{$now}'),
            ('pml471600001', 'avery_l4716', 'office_a4', 'Avery Zweckform', 'L4716-20', 'Avery L4716-20 Ø30 mm rund (48/A4)', 30.00, 30.00, 6, 8, FALSE, NULL, 'published', 'global', '{$now}', '{$now}'),
            ('pml610700001', 'avery_l6107', 'office_a4', 'Avery Zweckform', 'L6107-20', 'Avery L6107-20 99,1×42,3 mm (12/A4)', 99.10, 42.30, 2, 6, FALSE, NULL, 'published', 'global', '{$now}', '{$now}'),
            ('pmisoa400001', 'iso_a4', 'office_a4', 'ISO', 'A4', 'ISO A4 210×297 mm', 210.00, 297.00, 1, 1, FALSE, NULL, 'published', 'global', '{$now}', '{$now}'),
            ('pmisoa500001', 'iso_a5', 'office_a4', 'ISO', 'A5', 'ISO A5 148×210 mm', 148.00, 210.00, 1, 1, FALSE, NULL, 'published', 'global', '{$now}', '{$now}'),
            ('pmisoa600001', 'iso_a6', 'office_a4', 'ISO', 'A6', 'ISO A6 105×148 mm', 105.00, 148.00, 1, 1, FALSE, NULL, 'published', 'global', '{$now}', '{$now}'),
            ('pmisoa700001', 'iso_a7', 'office_a4', 'ISO', 'A7', 'ISO A7 74×105 mm', 74.00, 105.00, 1, 1, FALSE, NULL, 'published', 'global', '{$now}', '{$now}'),
            ('pmisoa800001', 'iso_a8', 'office_a4', 'ISO', 'A8', 'ISO A8 52×74 mm', 52.00, 74.00, 1, 1, FALSE, NULL, 'published', 'global', '{$now}', '{$now}')
            ON CONFLICT (id) DO NOTHING");

        $this->addSql("INSERT INTO print_device_model (id, catalog_key, family, brand, name, compatible_media_keys, status, scope, created_at, updated_at) VALUES
            ('dmql720nw001', 'brother_ql_720nw', 'brother_ql', 'Brother', 'QL-720NW', '[\"brother_dk_11208\",\"brother_dk_11209\",\"brother_dk_11219\",\"brother_dk_11221\",\"brother_dk_22205\",\"brother_dk_22225\"]'::json, 'published', 'global', '{$now}', '{$now}')
            ON CONFLICT (id) DO NOTHING");

        $qlKeys = '["brother_dk_11208","brother_dk_11209","brother_dk_11219","brother_dk_11221","brother_dk_22205","brother_dk_22225"]';
        $this->addSql("UPDATE print_device_model SET compatible_media_keys = '{$qlKeys}'::json, updated_at = '{$now}' WHERE catalog_key IN ('brother_ql_700', 'brother_ql_820nwb', 'brother_ql_720nw')");

        $officeKeys = '["avery_l7160","avery_3652","avery_3655","avery_3425","avery_6122","avery_l6140","avery_l4716","avery_l6107","iso_a4","iso_a5","iso_a6","iso_a7","iso_a8"]';
        $this->addSql("UPDATE print_device_model SET compatible_media_keys = '{$officeKeys}'::json, updated_at = '{$now}' WHERE catalog_key = 'office_a4_laser'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM print_device_model WHERE id = 'dmql720nw001'");
        $this->addSql("DELETE FROM print_media WHERE id IN (
            'pm1120800001', 'pm1121900001', 'pm1122100001',
            'pm3425000001', 'pm6122000001', 'pml614000001', 'pml471600001', 'pml610700001',
            'pmisoa400001', 'pmisoa500001', 'pmisoa600001', 'pmisoa700001', 'pmisoa800001'
        )");
    }
}
